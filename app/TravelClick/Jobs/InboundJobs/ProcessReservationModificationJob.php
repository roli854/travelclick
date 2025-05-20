<?php

namespace App\TravelClick\Jobs\InboundJobs;

use App\TravelClick\DTOs\ReservationDataDto;
use App\TravelClick\DTOs\ReservationResponseDto;
use App\TravelClick\Enums\MessageDirection;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\ProcessingStatus;
use App\TravelClick\Enums\ReservationType;
use App\TravelClick\Models\TravelClickMessageHistory;
use App\TravelClick\Parsers\ReservationParser;
use App\TravelClick\Services\ReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to process an incoming reservation modification from TravelClick
 */
class ProcessReservationModificationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [30, 60, 120];

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * The message ID for this reservation modification.
     *
     * @var string
     */
    protected string $messageId;

    /**
     * The raw XML containing the reservation modification data.
     *
     * @var string
     */
    protected string $messageXml;

    /**
     * The hotel code for this reservation.
     *
     * @var string
     */
    protected string $hotelCode;

    /**
     * Optional batch ID for processing multiple reservations together.
     *
     * @var string|null
     */
    protected ?string $batchId;

    /**
     * The message history entry ID.
     *
     * @var int|null
     */
    protected ?int $messageHistoryId;

    /**
     * Create a new job instance.
     *
     * @param string $messageId The unique message identifier
     * @param string $messageXml The raw XML containing reservation data
     * @param string $hotelCode The hotel code
     * @param string|null $batchId Optional batch ID for processing multiple reservations
     * @param int|null $messageHistoryId Optional message history entry ID
     */
    public function __construct(
        string $messageId,
        string $messageXml,
        string $hotelCode,
        ?string $batchId = null,
        ?int $messageHistoryId = null
    ) {
        $this->messageId = $messageId;
        $this->messageXml = $messageXml;
        $this->hotelCode = $hotelCode;
        $this->batchId = $batchId;
        $this->messageHistoryId = $messageHistoryId;

        // Set the queue for this job
        $this->onQueue('travelclick-reservations');
    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return "reservation-modification-{$this->messageId}";
    }

    /**
     * Execute the job.
     *
     * @param ReservationParser $parser
     * @param ReservationService $reservationService
     * @return void
     */
    public function handle(ReservationParser $parser, ReservationService $reservationService)
    {
        // Find or create a message history entry
        $historyEntry = $this->getHistoryEntry();
        $historyEntry->markAsSent();

        try {
            Log::info('Processing reservation modification', [
                'message_id' => $this->messageId,
                'hotel_code' => $this->hotelCode,
                'batch_id' => $this->batchId
            ]);

            // Parse the XML to extract reservation data
            $parsedResponse = $parser->parse($this->messageId, $this->messageXml);

            if (!$parsedResponse->isSuccess) {
                throw new \Exception("Failed to parse reservation XML: {$parsedResponse->errorMessage}");
            }

            // Create a DTO from the parsed data
            $reservationData = $this->createReservationDto($parsedResponse);

            // Update the history entry with extracted data
            $historyEntry->markAsReceived();
            $historyEntry->update([
                'ExtractedData' => [
                    'reservation_id' => $reservationData->reservationId,
                    'confirmation_number' => $reservationData->confirmationNumber,
                    'transaction_type' => 'modify',
                    'check_in' => $reservationData->getArrivalDate()->format('Y-m-d'),
                    'check_out' => $reservationData->getDepartureDate()->format('Y-m-d'),
                    'room_type' => $reservationData->roomStays->first()->roomTypeCode,
                    'reservation_type' => $reservationData->reservationType->value,
                ]
            ]);

            // Process the modification
            $result = $reservationService->processModification($reservationData);

            if ($result->isSuccess) {
                $historyEntry->markAsProcessed("Reservation modification processed successfully: {$reservationData->confirmationNumber}");

                Log::info('Reservation modification completed successfully', [
                    'message_id' => $this->messageId,
                    'confirmation_number' => $reservationData->confirmationNumber,
                    'hotel_code' => $this->hotelCode
                ]);
            } else {
                $historyEntry->markAsFailed("Reservation modification processing failed: {$result->errorMessage}");

                Log::error('Reservation modification failed', [
                    'message_id' => $this->messageId,
                    'confirmation_number' => $reservationData->confirmationNumber,
                    'error' => $result->errorMessage
                ]);

                throw new \Exception("Failed to process reservation modification: {$result->errorMessage}");
            }
        } catch (Throwable $e) {
            $errorMessage = "Error processing reservation modification: {$e->getMessage()}";

            Log::error($errorMessage, [
                'message_id' => $this->messageId,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);

            $historyEntry->markAsFailed($errorMessage);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception)
    {
        Log::error('Reservation modification job failed', [
            'message_id' => $this->messageId,
            'error' => $exception->getMessage(),
            'exception' => get_class($exception)
        ]);

        // If we have a message history entry, mark it as failed
        if ($this->messageHistoryId) {
            $historyEntry = TravelClickMessageHistory::find($this->messageHistoryId);
            if ($historyEntry) {
                $historyEntry->markAsFailed("Job failed: {$exception->getMessage()}");
            }
        }
    }

    /**
     * Get or create a message history entry.
     *
     * @return TravelClickMessageHistory
     */
    protected function getHistoryEntry(): TravelClickMessageHistory
    {
        if ($this->messageHistoryId) {
            $entry = TravelClickMessageHistory::find($this->messageHistoryId);
            if ($entry) {
                return $entry;
            }
        }

        // Create a new entry if none exists
        return TravelClickMessageHistory::createEntry([
            'MessageID' => $this->messageId,
            'MessageType' => MessageType::RESERVATION,
            'Direction' => MessageDirection::INBOUND,
            'HotelCode' => $this->hotelCode,
            'BatchID' => $this->batchId,
            'MessageXML' => $this->messageXml,
            'ProcessingStatus' => ProcessingStatus::PENDING,
            'SystemUserID' => auth()->id() ?? 1,
        ]);
    }

    /**
     * Create a ReservationDataDto from the parsed response.
     *
     * @param ReservationResponseDto $parsedResponse
     * @return ReservationDataDto
     * @throws \Exception
     */
    protected function createReservationDto(ReservationResponseDto $parsedResponse): ReservationDataDto
    {
        $payload = $parsedResponse->getPayload();

        if (!$payload) {
            throw new \Exception('No reservation data found in parsed response');
        }

        // Ensure this is a modification
        if (!isset($payload['transaction_type']) || $payload['transaction_type'] !== 'modify') {
            throw new \Exception('Expected a reservation modification but received: ' .
                ($payload['transaction_type'] ?? 'unknown'));
        }

        // Ensure we have a reservation ID and confirmation number
        if (!isset($payload['reservation_id']) || !isset($payload['confirmation_number'])) {
            throw new \Exception('Reservation ID and confirmation number are required for modifications');
        }

        // Convert to a DTO
        try {
            return new ReservationDataDto([
                'reservationType' => $payload['type'] ?? ReservationType::TRANSIENT,
                'reservationId' => $payload['reservation_id'],
                'confirmationNumber' => $payload['confirmation_number'],
                'transactionType' => 'modify',
                'hotelCode' => $this->hotelCode,
                'primaryGuest' => $payload['guest'] ?? [],
                'roomStays' => [$payload['room'] ?? []],
                // Add other fields as needed
            ]);
        } catch (Throwable $e) {
            throw new \Exception("Failed to create reservation DTO: {$e->getMessage()}");
        }
    }
}
