<?php

namespace App\TravelClick\Jobs\OutboundJobs;

use App\TravelClick\DTOs\ReservationDataDto;
use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Events\ReservationSyncCompleted;
use App\TravelClick\Events\ReservationSyncFailed;
use App\TravelClick\Exceptions\SoapException;
use App\TravelClick\Exceptions\ValidationException;
use App\TravelClick\Builders\ReservationXmlBuilder;
use App\TravelClick\Services\SoapService;
use App\TravelClick\Support\RetryHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to send reservation data to TravelClick via SOAP.
 *
 * This job handles all types of reservations (transient, travel agency,
 * corporate, package, group, alternate payment) and automatically updates
 * inventory when needed.
 */
class SendReservationJob implements ShouldQueue, ShouldBeUnique
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
     * The reservation data to be sent.
     *
     * @var ReservationDataDto
     */
    protected ReservationDataDto $reservationData;

    /**
     * Whether to update inventory after successful reservation.
     *
     * @var bool
     */
    protected bool $updateInventory;

    /**
     * Create a new job instance.
     *
     * @param ReservationDataDto $reservationData The reservation data to send
     * @param bool $updateInventory Whether to update inventory after successful submission
     * @return void
     */
    public function __construct(ReservationDataDto $reservationData, bool $updateInventory = true)
    {
        $this->reservationData = $reservationData;
        $this->updateInventory = $updateInventory;

        // Set the queue for this job from config
        $this->queue = config('travelclick.queues.outbound', 'travelclick-outbound');
    }

    /**
     * Execute the job.
     *
     * @param SoapService $soapService
     * @param ReservationXmlBuilder $xmlBuilder
     * @param RetryHelper $retryHelper
     * @return void
     */
    public function handle(
        SoapService $soapService,
        ReservationXmlBuilder $xmlBuilder,
        RetryHelper $retryHelper
    ): void {
        $hotelCode = $this->reservationData->hotelCode;
        $logContext = [
            'job_id' => $this->job?->getJobId(),
            'hotel_code' => $hotelCode,
            'reservation_id' => $this->reservationData->reservationId,
            'reservation_type' => $this->reservationData->reservationType->value,
        ];

        Log::info('Starting reservation sync to TravelClick', $logContext);

        try {
            // Use RetryHelper for advanced retry capabilities with circuit breaker
            $response = $retryHelper->executeWithRetry(
                operation: function () use ($soapService, $xmlBuilder, $hotelCode) {
                    // Build the XML payload
                    $xml = $xmlBuilder->buildReservationXml($this->reservationData);

                    // Send the XML to TravelClick
                    $response = $soapService->sendReservation($xml, $hotelCode);

                    // Check for failure response
                    if (!$response->isSuccess) {
                        throw new SoapException(
                            "Failed to send reservation: {$response->errorMessage}",
                            $response->messageId,
                            soapFaultString: $response->rawResponse ?? null
                        );
                    }

                    return $response;
                },
                operationType: MessageType::RESERVATION->value,
                serviceIdentifier: "travelclick-reservation-{$hotelCode}"
            );

            // Process successful response
            $this->processSuccessfulResponse($response);
        } catch (ValidationException $e) {
            // Handle validation exceptions (data formatting issues)
            Log::error('Reservation validation failed: ' . $e->getMessage(), array_merge($logContext, [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'validation_errors' => $e->getSummary(),
            ]));

            $this->handleFailure($e, true); // Don't retry validation errors

        } catch (Throwable $e) {
            // Handle all other exceptions
            Log::error('Reservation sync failed: ' . $e->getMessage(), array_merge($logContext, [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]));

            $this->handleFailure($e);
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware(): array
    {
        // Use WithoutOverlapping to prevent processing the same reservation multiple times
        // Using reservation ID to ensure uniqueness
        return [
            (new WithoutOverlapping($this->getUniqueLockId()))
                ->dontRelease() // Prevent releasing back to queue if locked
                ->expireAfter(300) // Lock expires after 5 minutes
        ];
    }

    /**
     * The unique ID used to prevent job overlapping.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        return $this->getUniqueLockId();
    }

    /**
     * Generate a unique lock ID for this job.
     *
     * @return string
     */
    protected function getUniqueLockId(): string
    {
        return 'reservation:' . $this->reservationData->hotelCode . ':' . $this->reservationData->reservationId;
    }

    /**
     * Handle a job failure.
     *
     * @param Throwable $exception
     * @param bool $shouldNotRetry Whether the job should not be retried
     * @return void
     */
    protected function handleFailure(Throwable $exception, bool $shouldNotRetry = false): void
    {
        // If set to not retry or exceeded max attempts, handle final failure
        if ($shouldNotRetry || $this->attempts() >= $this->tries) {
            // Fire event to notify of failure
            event(new ReservationSyncFailed(
                $this->reservationData,
                $exception,
                $this->job?->getJobId()
            ));

            // Mark job as failed
            $this->fail($exception);
        } else {
            // Release job back to queue for retry with backoff delay
            $backoffTime = $this->backoff[$this->attempts() - 1] ?? 60;
            $this->release($backoffTime);
        }
    }

    /**
     * Process successful reservation response.
     *
     * @param SoapResponseDto $response
     * @return void
     */
    protected function processSuccessfulResponse(SoapResponseDto $response): void
    {
        // Extract confirmation number if available in response
        $confirmationNumber = $this->extractConfirmationNumber($response);

        Log::info('Reservation successfully sent to TravelClick', [
            'hotel_code' => $this->reservationData->hotelCode,
            'reservation_id' => $this->reservationData->reservationId,
            'confirmation_number' => $confirmationNumber,
            'message_id' => $response->messageId,
        ]);

        // Fire event with the response data
        event(new ReservationSyncCompleted(
            $this->reservationData,
            $response,
            $confirmationNumber
        ));

        // If needed and configured, update inventory after successful reservation
        if ($this->updateInventory && $this->reservationData->reservationType->affectsInventory()) {
            $this->dispatchInventoryUpdate();
        }
    }

    /**
     * Extract the confirmation number from the SOAP response.
     *
     * @param SoapResponseDto $response
     * @return string|null
     */
    protected function extractConfirmationNumber(SoapResponseDto $response): ?string
    {
        // This is a simplified version - in a real implementation you would
        // use an XML parser to extract the confirmation number from the response
        if (preg_match('/ResID_Value="([^"]+)".*ResID_Type="10"/', $response->rawResponse ?? '', $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Dispatch a job to update inventory.
     *
     * @return void
     */
    protected function dispatchInventoryUpdate(): void
    {
        // Get the rooms and dates affected by this reservation
        $inventoryData = $this->buildInventoryDataFromReservation();

        // Dispatch the inventory update job
        UpdateInventoryJob::dispatch($inventoryData, $this->reservationData->hotelCode)
            ->onQueue(config('travelclick.queues.outbound', 'travelclick-outbound'))
            ->delay(now()->addSeconds(5)); // Small delay to ensure reservation is processed first
    }

    /**
     * Build inventory data from reservation for update.
     *
     * @return array
     */
    protected function buildInventoryDataFromReservation(): array
    {
        // Generate inventory update data based on the reservation
        // This is a simplified version
        $inventoryData = [];

        // For each room stay in the reservation
        foreach ($this->reservationData->roomStays as $roomStay) {
            $inventoryData[] = [
                'room_type' => $roomStay->roomTypeCode,
                'start_date' => $roomStay->checkInDate->toDateString(),
                'end_date' => $roomStay->checkOutDate->toDateString(),
                // Count reduction depends on action (new, modification, cancellation)
                'count_change' => $this->getInventoryChangeAmount()
            ];
        }

        return $inventoryData;
    }

    /**
     * Get the inventory change amount based on the operation type.
     *
     * @return int
     */
    protected function getInventoryChangeAmount(): int
    {
        if ($this->reservationData->isCancellation()) {
            // For cancellations, we add back to inventory
            return 1;
        } elseif ($this->reservationData->isModification()) {
            // For modifications, depends on the specific case
            // For simplicity, assuming no change in this example
            return 0;
        } else {
            // For new reservations, we reduce inventory
            return -1;
        }
    }
}
