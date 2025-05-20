<?php

declare(strict_types=1);

namespace App\TravelClick\Jobs\OutboundJobs;

use App\TravelClick\DTOs\ReservationDataDto;
use App\TravelClick\DTOs\SoapHeaderDto;
use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\ReservationType;
use App\TravelClick\Builders\ReservationXmlBuilder;
use App\TravelClick\Services\SoapService;
use App\TravelClick\Support\RetryHelper;
use App\TravelClick\Models\TravelClickLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to cancel a reservation in the TravelClick system.
 *
 * This job handles the cancellation process by:
 * 1. Building the cancellation XML message
 * 2. Sending it to TravelClick via SOAP
 * 3. Processing the response
 * 4. Updating inventory after successful cancellation
 */
class CancelReservationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries;

    /**
     * Number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public int $backoff = 60;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout;

    /**
     * Indicates if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public bool $failOnTimeout = true;

    /**
     * Job start time for tracking duration.
     *
     * @var float
     */
    private float $jobStartTime = 0;

    /**
     * Create a new job instance.
     *
     * @param ReservationDataDto $reservationData The reservation data with cancellation information
     * @param string $hotelCode The hotel code
     * @param bool $highPriority Whether this cancellation should have high priority in queue
     * @param int|null $propertyId Optional property ID for logging
     */
    public function __construct(
        private readonly ReservationDataDto $reservationData,
        private readonly string $hotelCode,
        private readonly bool $highPriority = false,
        private readonly ?int $propertyId = null
    ) {
        $this->tries = config('travelclick.message_types.reservation.max_retry_attempts', 3);
        $this->timeout = config('travelclick.message_types.reservation.timeout_seconds', 60);
        $this->onQueue($this->determineQueue());
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware(): array
    {
        // Ensure we're not sending multiple cancellation requests for the same reservation simultaneously
        $reservationId = $this->reservationData->reservationId;
        return [
            new WithoutOverlapping("travelclick_cancel_{$this->hotelCode}_{$reservationId}"),
        ];
    }

    /**
     * Execute the job.
     *
     * @param SoapService $soapService Service for SOAP communication
     * @param RetryHelper $retryHelper Helper for managing retries on failure
     * @return void
     * @throws Exception
     */
    public function handle(SoapService $soapService, RetryHelper $retryHelper): void
    {
        $this->jobStartTime = microtime(true);
        $operationType = 'reservation_cancel';
        $serviceIdentifier = "travelclick_cancellation_{$this->hotelCode}";

        Log::info('Starting TravelClick reservation cancellation job', [
            'hotel_code' => $this->hotelCode,
            'reservation_id' => $this->reservationData->reservationId,
            'confirmation_number' => $this->reservationData->confirmationNumber ?? 'N/A',
            'job_id' => $this->job->getJobId(),
        ]);

        try {
            $responseDto = $retryHelper->executeWithRetry(
                function () use ($soapService): SoapResponseDto {
                    return $this->processSoapRequest($soapService);
                },
                $operationType,
                $serviceIdentifier
            );

            $this->handleSuccessfulResponse($responseDto, $this->jobStartTime);

            // Dispatch an inventory update after successful cancellation
            if ($this->shouldUpdateInventory()) {
                $this->dispatchInventoryUpdate();
            }
        } catch (Throwable $exception) {
            $this->handleFailedResponse($exception, $this->jobStartTime);

            if ($this->shouldRetryAfterException($exception)) {
                $this->release($this->calculateNextRetryDelay());
                return;
            }

            throw $exception;
        }
    }

    /**
     * Process the SOAP request for reservation cancellation.
     *
     * @param SoapService $soapService
     * @return SoapResponseDto
     */
    private function processSoapRequest(SoapService $soapService): SoapResponseDto
    {
        // Create SOAP headers for the request
        $soapHeaders = SoapHeaderDto::forReservation(
            hotelCode: $this->hotelCode,
            username: config('travelclick.credentials.username'),
            password: config('travelclick.credentials.password')
        );

        // Create XML builder for reservation cancellation
        $xmlBuilder = new ReservationXmlBuilder($soapHeaders);

        // Build XML for cancellation
        $xml = $this->buildCancellationXml($xmlBuilder);

        // Send cancellation request
        return $soapService->sendReservation($xml, $this->hotelCode);
    }

    /**
     * Build the cancellation XML message.
     *
     * @param ReservationXmlBuilder $xmlBuilder
     * @return string
     */
    private function buildCancellationXml(ReservationXmlBuilder $xmlBuilder): string
    {
        // Set the cancellation information in the DTO
        // ReservationDataDto object should already have been configured with the necessary
        // cancellation details in the constructor

        try {
            // Use the builder to create XML for cancellation
            return $xmlBuilder->buildReservationXml($this->reservationData);
        } catch (Exception $e) {
            Log::error('Failed to build cancellation XML', [
                'error' => $e->getMessage(),
                'hotel_code' => $this->hotelCode,
                'reservation_id' => $this->reservationData->reservationId,
            ]);
            throw $e;
        }
    }

    /**
     * Handle successful SOAP response.
     *
     * @param SoapResponseDto $response
     * @param float $startTime
     * @return void
     */
    private function handleSuccessfulResponse(SoapResponseDto $response, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000);

        Log::info('TravelClick reservation cancellation completed successfully', [
            'hotel_code' => $this->hotelCode,
            'reservation_id' => $this->reservationData->reservationId,
            'message_id' => $response->messageId,
            'duration_ms' => $duration,
        ]);

        if ($this->isVerboseLoggingEnabled()) {
            $this->logDetailedResponse($response);
        }
    }

    /**
     * Handle failed SOAP response.
     *
     * @param Throwable $exception
     * @param float $startTime
     * @return void
     */
    private function handleFailedResponse(Throwable $exception, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000);
        $attempt = $this->attempts();

        Log::error('TravelClick reservation cancellation failed', [
            'hotel_code' => $this->hotelCode,
            'reservation_id' => $this->reservationData->reservationId,
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'duration_ms' => $duration,
            'attempt' => $attempt,
            'max_attempts' => $this->tries,
        ]);

        $this->createErrorLog($exception);
    }

    /**
     * Create an error log entry.
     *
     * @param Throwable $exception
     * @return void
     */
    private function createErrorLog(Throwable $exception): void
    {
        try {
            $elapsedSeconds = 0;
            if ($this->jobStartTime > 0) {
                $elapsedSeconds = microtime(true) - $this->jobStartTime;
            }

            TravelClickLog::create([
                'MessageID' => 'ERR_CANCEL_' . uniqid(),
                'Direction' => 'outbound',
                'MessageType' => MessageType::RESERVATION->value,
                'PropertyID' => $this->propertyId,
                'HotelCode' => $this->hotelCode,
                'Status' => 'Failed',
                'StartTime' => $this->jobStartTime > 0
                    ? Carbon::now()->subSeconds($elapsedSeconds)
                    : Carbon::now()->subMinute(),
                'EndTime' => Carbon::now(),
                'ErrorMessage' => $exception->getMessage(),
                'SystemUserID' => 1,
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to create TravelClick error log for cancellation', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determine if we should retry after an exception.
     *
     * @param Throwable $exception
     * @return bool
     */
    private function shouldRetryAfterException(Throwable $exception): bool
    {
        if ($this->attempts() >= $this->tries) {
            return false;
        }

        if ($exception instanceof \InvalidArgumentException) {
            return false;
        }

        $nonRetryableExceptions = [
            'App\TravelClick\Exceptions\ValidationException',
            'App\TravelClick\Exceptions\TravelClickAuthenticationException',
        ];

        foreach ($nonRetryableExceptions as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate delay for the next retry attempt.
     *
     * @return int
     */
    private function calculateNextRetryDelay(): int
    {
        $attempt = $this->attempts();
        $baseDelay = config('travelclick.retry_policy.initial_delay_seconds', 10);
        $maxDelay = config('travelclick.retry_policy.max_delay_seconds', 300);
        $multiplier = config('travelclick.retry_policy.multiplier', 2);

        $delay = min($maxDelay, $baseDelay * pow($multiplier, $attempt - 1));
        $jitter = mt_rand(-10, 10) / 100; // Add -10% to +10% jitter
        $delay = (int)($delay * (1 + $jitter));

        return max(5, $delay); // Minimum 5 seconds delay
    }

    /**
     * Determine which queue to use.
     *
     * @return string
     */
    private function determineQueue(): string
    {
        if ($this->highPriority) {
            return config('travelclick.queues.high_priority', 'travelclick-priority');
        }

        return config('travelclick.queues.outbound', 'travelclick-outbound');
    }

    /**
     * Check if verbose logging is enabled.
     *
     * @return bool
     */
    private function isVerboseLoggingEnabled(): bool
    {
        return config('travelclick.message_types.reservation.verbose_logging', false);
    }

    /**
     * Log detailed response information.
     *
     * @param SoapResponseDto $response
     * @return void
     */
    private function logDetailedResponse(SoapResponseDto $response): void
    {
        Log::debug('TravelClick reservation cancellation detailed response', [
            'message_id' => $response->messageId,
            'echo_token' => $response->echoToken,
            'duration_ms' => $response->durationMs,
            'warnings' => $response->warnings,
        ]);
    }

    /**
     * Determine if inventory should be updated after cancellation.
     *
     * @return bool
     */
    private function shouldUpdateInventory(): bool
    {
        // Group reservations don't affect inventory directly
        if ($this->reservationData->reservationType === ReservationType::GROUP) {
            return false;
        }

        return config('travelclick.message_types.reservation.update_inventory_after_cancel', true);
    }

    /**
     * Dispatch an inventory update job after successful cancellation.
     *
     * @return void
     */
    private function dispatchInventoryUpdate(): void
    {
        try {
            // Extract room information from the reservation
            $roomTypeInfo = $this->extractRoomTypeInfo();

            if (empty($roomTypeInfo)) {
                Log::warning('Could not extract room information for inventory update after cancellation', [
                    'reservation_id' => $this->reservationData->reservationId,
                ]);
                return;
            }

            // Dispatch inventory update job
            Log::info('Dispatching inventory update after cancellation', [
                'hotel_code' => $this->hotelCode,
                'reservation_id' => $this->reservationData->reservationId,
            ]);

            // Use the UpdateInventoryJob class to update inventory
            // The exact implementation depends on your system design
            // This is just a placeholder - adjust according to your actual implementation
            UpdateInventoryJob::urgent(
                $this->createInventoryDataFromReservation(),
                $this->hotelCode,
                $this->propertyId
            )->dispatch();
        } catch (Exception $e) {
            Log::error('Failed to dispatch inventory update after cancellation', [
                'error' => $e->getMessage(),
                'reservation_id' => $this->reservationData->reservationId,
            ]);
        }
    }

    /**
     * Extract room type information from the reservation.
     *
     * @return array
     */
    private function extractRoomTypeInfo(): array
    {
        $roomInfo = [];

        if ($this->reservationData->roomStays && $this->reservationData->roomStays->isNotEmpty()) {
            foreach ($this->reservationData->roomStays as $roomStay) {
                $roomInfo[] = [
                    'roomTypeCode' => $roomStay->roomTypeCode,
                    'checkIn' => $roomStay->checkInDate,
                    'checkOut' => $roomStay->checkOutDate,
                    'count' => 1, // Each room stay typically represents 1 room
                ];
            }
        }

        return $roomInfo;
    }

    /**
     * Create inventory data from the reservation for updating inventory.
     *
     * This method should be implemented according to your inventory data structure.
     * This is just a placeholder - replace with your actual implementation.
     *
     * @return mixed
     */
    private function createInventoryDataFromReservation()
    {
        // This is a placeholder - implement according to your actual DTO structure
        // The implementation depends on your InventoryData structure
        // For example:
        /*
        return InventoryData::createCalculated(
            $this->hotelCode,
            $this->reservationData->getCheckInDate(),
            $this->reservationData->getCheckOutDate(),
            $this->reservationData->getRoomTypeCode(),
            0, // definiteSold
            0, // tentativeSold
            0, // outOfOrder
            0  // oversell
        );
        */

        // This is a stub that needs to be implemented according to your actual system
        return null;
    }

    /**
     * Static factory method to create a regular cancellation job.
     *
     * @param ReservationDataDto $reservationData
     * @param string $hotelCode
     * @param int|null $propertyId
     * @return self
     */
    public static function cancel(
        ReservationDataDto $reservationData,
        string $hotelCode,
        ?int $propertyId = null
    ): self {
        return new self(
            reservationData: $reservationData,
            hotelCode: $hotelCode,
            highPriority: false,
            propertyId: $propertyId
        );
    }

    /**
     * Static factory method to create an urgent cancellation job.
     *
     * @param ReservationDataDto $reservationData
     * @param string $hotelCode
     * @param int|null $propertyId
     * @return self
     */
    public static function urgent(
        ReservationDataDto $reservationData,
        string $hotelCode,
        ?int $propertyId = null
    ): self {
        return new self(
            reservationData: $reservationData,
            hotelCode: $hotelCode,
            highPriority: true,
            propertyId: $propertyId
        );
    }
}
