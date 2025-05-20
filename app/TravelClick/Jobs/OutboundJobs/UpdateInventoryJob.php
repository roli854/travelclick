<?php

declare(strict_types=1);

namespace App\TravelClick\Jobs\OutboundJobs;

use App\TravelClick\DTOs\InventoryData;
use App\TravelClick\DTOs\SoapHeaderDto;
use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Enums\CountType;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Builders\InventoryXmlBuilder;
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
use Spatie\LaravelData\DataCollection;
use Throwable;

/**
 * Job for sending inventory updates to TravelClick via HTNG 2011B
 *
 * This job handles sending inventory data to TravelClick using their SOAP API.
 * It supports both "delta" (incremental changes) and "overlay" (full sync) modes,
 * as well as property-level and room-level inventory messages.
 *
 * Features:
 *  - Automatic retry on transient failures with exponential backoff
 *  - Circuit breaker pattern to prevent overwhelming failing services
 *  - Comprehensive logging of all operations
 *  - Support for both calculated and direct inventory methods
 *  - Batch processing to prevent timeouts with large volumes
 */
class UpdateInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries;

    /**
     * The number of seconds to wait before retrying the job.
     * This is overridden by our RetryHelper, but is required for Laravel.
     */
    public int $backoff = 60;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * The timeout in seconds for the job.
     */
    public int $timeout;

    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = true;

    /**
     * Store the job start time for tracking duration
     */
    private float $jobStartTime = 0;

    /**
     * Create a new job instance.
     *
     * @param DataCollection<int, InventoryData>|InventoryData $inventoryData The inventory data to send
     * @param string $hotelCode The hotel code in TravelClick
     * @param bool $isOverlay Whether this is a full overlay (vs. delta update)
     * @param bool $highPriority Whether this job has high priority
     * @param int|null $propertyId Optional property ID for logging
     */
    public function __construct(
        private readonly DataCollection|InventoryData $inventoryData,
        private readonly string $hotelCode,
        private readonly bool $isOverlay = false,
        private readonly bool $highPriority = false,
        private readonly ?int $propertyId = null
    ) {
        // Convert single InventoryData to DataCollection if needed
        if ($this->inventoryData instanceof InventoryData) {
            $this->inventoryData = new DataCollection(InventoryData::class, [$this->inventoryData]);
        }

        // Configure job retry settings from config
        $this->tries = config('travelclick.message_types.inventory.max_retry_attempts', 3);
        $this->timeout = config('travelclick.message_types.inventory.timeout_seconds', 60);

        // Set the queue based on priority
        $this->onQueue($this->determineQueue());
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware(): array
    {
        return [
            // Prevent multiple concurrent inventory updates for the same hotel
            new WithoutOverlapping("travelclick_inventory_{$this->hotelCode}"),
        ];
    }

    /**
     * Execute the job.
     *
     * @param SoapService $soapService
     * @param RetryHelper $retryHelper
     * @return void
     * @throws Exception
     */
    public function handle(SoapService $soapService, RetryHelper $retryHelper): void
    {
        $this->jobStartTime = microtime(true);
        $operationType = $this->isOverlay ? 'inventory_overlay' : 'inventory_delta';
        $serviceIdentifier = "travelclick_inventory_{$this->hotelCode}";

        Log::info('Starting TravelClick inventory update job', [
            'hotel_code' => $this->hotelCode,
            'inventory_count' => $this->inventoryData->count(),
            'mode' => $this->isOverlay ? 'overlay' : 'delta',
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
        } catch (Throwable $exception) {
            $this->handleFailedResponse($exception, $this->jobStartTime);

            // Release the job for retry if appropriate
            if ($this->shouldRetryAfterException($exception)) {
                $this->release($this->calculateNextRetryDelay());
                return;
            }

            // Rethrow the exception to mark the job as failed
            throw $exception;
        }
    }

    /**
     * Process the SOAP request to update inventory
     *
     * @param SoapService $soapService
     * @return SoapResponseDto
     */
    private function processSoapRequest(SoapService $soapService): SoapResponseDto
    {
        // Create SOAP headers
        $soapHeaders = SoapHeaderDto::forInventory(
            hotelCode: $this->hotelCode,
            username: config('travelclick.credentials.username'),
            password: config('travelclick.credentials.password')
        );

        // Create appropriate builder based on inventory type
        $xmlBuilder = $this->createXmlBuilder($soapHeaders);

        // Build XML message
        $xml = $this->buildXmlMessage($xmlBuilder);

        // Send the SOAP request
        return $soapService->updateInventory($xml, $this->hotelCode);
    }

    /**
     * Create the appropriate XML builder
     *
     * @param SoapHeaderDto $soapHeaders
     * @return InventoryXmlBuilder
     */
    private function createXmlBuilder(SoapHeaderDto $soapHeaders): InventoryXmlBuilder
    {
        // Check if we're using property level inventory
        $isPropertyLevel = $this->inventoryData->first()->isPropertyLevel ?? false;

        // Check if we're using calculated method (CountType 4,5,6,99)
        $isCalculatedMethod = $this->determineCalculationMethod();

        // Create the appropriate builder
        if ($isPropertyLevel) {
            return InventoryXmlBuilder::forPropertyLevel($soapHeaders);
        } elseif ($isCalculatedMethod) {
            return InventoryXmlBuilder::forCalculated($soapHeaders);
        } else {
            return InventoryXmlBuilder::forDirect($soapHeaders);
        }
    }

    /**
     * Build the XML message
     *
     * @param InventoryXmlBuilder $xmlBuilder
     * @return string
     */
    private function buildXmlMessage(InventoryXmlBuilder $xmlBuilder): string
    {
        try {
            return $xmlBuilder->buildBatch($this->inventoryData);
        } catch (Exception $e) {
            Log::error('Failed to build inventory XML', [
                'error' => $e->getMessage(),
                'hotel_code' => $this->hotelCode,
            ]);
            throw $e;
        }
    }

    /**
     * Handle a successful response
     *
     * @param SoapResponseDto $response
     * @param float $startTime
     * @return void
     */
    private function handleSuccessfulResponse(SoapResponseDto $response, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000);

        Log::info('TravelClick inventory update completed successfully', [
            'hotel_code' => $this->hotelCode,
            'message_id' => $response->messageId,
            'duration_ms' => $duration,
            'inventory_count' => $this->inventoryData->count(),
            'mode' => $this->isOverlay ? 'overlay' : 'delta',
        ]);

        // Additional logging if needed
        if ($this->isVerboseLoggingEnabled()) {
            $this->logDetailedResponse($response);
        }
    }

    /**
     * Handle a failed response
     *
     * @param Throwable $exception
     * @param float $startTime
     * @return void
     */
    private function handleFailedResponse(Throwable $exception, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000);
        $attempt = $this->attempts();

        Log::error('TravelClick inventory update failed', [
            'hotel_code' => $this->hotelCode,
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'duration_ms' => $duration,
            'attempt' => $attempt,
            'max_attempts' => $this->tries,
        ]);

        // Create an error log entry
        $this->createErrorLog($exception);
    }

    /**
     * Create an error log entry
     *
     * @param Throwable $exception
     * @return void
     */
    private function createErrorLog(Throwable $exception): void
    {
        try {
            $elapsedSeconds = 0;

            // Only calculate elapsed time if jobStartTime was set
            if ($this->jobStartTime > 0) {
                $elapsedSeconds = microtime(true) - $this->jobStartTime;
            }
            TravelClickLog::create([
                'MessageID' => 'ERR_' . uniqid(),
                'Direction' => 'outbound',
                'MessageType' => MessageType::INVENTORY->value,
                'PropertyID' => $this->propertyId,
                'HotelCode' => $this->hotelCode,
                'Status' => 'Failed',
                'StartTime' => $this->jobStartTime > 0
                    ? Carbon::now()->subSeconds($elapsedSeconds)
                    : Carbon::now()->subMinute(), // Fallback if no start time
                'EndTime' => Carbon::now(),
                'ErrorMessage' => $exception->getMessage(),
                'SystemUserID' => 1, // System user
            ]);
        } catch (Exception $e) {
            // Just log the error if we can't create the log entry
            Log::warning('Failed to create TravelClick error log', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determine if we should retry after this exception
     *
     * @param Throwable $exception
     * @return bool
     */
    private function shouldRetryAfterException(Throwable $exception): bool
    {
        // Don't retry if we've exceeded the maximum attempts
        if ($this->attempts() >= $this->tries) {
            return false;
        }

        // Don't retry validation errors (they won't succeed on retry)
        if ($exception instanceof \InvalidArgumentException) {
            return false;
        }

        // List of exception classes that should not be retried
        $nonRetryableExceptions = [
            'App\TravelClick\Exceptions\ValidationException',
            'App\TravelClick\Exceptions\TravelClickAuthenticationException',
        ];

        // Don't retry if the exception is in the non-retryable list
        foreach ($nonRetryableExceptions as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                return false;
            }
        }

        // Retry all other exceptions
        return true;
    }

    /**
     * Calculate the next retry delay
     *
     * @return int
     */
    private function calculateNextRetryDelay(): int
    {
        $attempt = $this->attempts();
        $baseDelay = config('travelclick.retry_policy.initial_delay_seconds', 10);
        $maxDelay = config('travelclick.retry_policy.max_delay_seconds', 300);
        $multiplier = config('travelclick.retry_policy.multiplier', 2);

        // Exponential backoff with jitter
        $delay = min($maxDelay, $baseDelay * pow($multiplier, $attempt - 1));

        // Add some randomness to prevent thundering herd problem
        $jitter = mt_rand(-10, 10) / 100; // ±10% jitter
        $delay = (int)($delay * (1 + $jitter));

        return max(5, $delay); // Minimum 5 seconds delay
    }

    /**
     * Determine the calculation method based on count types
     *
     * @return bool
     */
    private function determineCalculationMethod(): bool
    {
        // Check the first inventory item to determine the method
        foreach ($this->inventoryData as $inventory) {
            foreach ($inventory->counts as $count) {
                if ($count->countType->requiresCalculation()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine the queue to use
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
     * Check if verbose logging is enabled
     *
     * @return bool
     */
    private function isVerboseLoggingEnabled(): bool
    {
        return config('travelclick.message_types.inventory.verbose_logging', false);
    }

    /**
     * Log detailed response information
     *
     * @param SoapResponseDto $response
     * @return void
     */
    private function logDetailedResponse(SoapResponseDto $response): void
    {
        Log::debug('TravelClick inventory update detailed response', [
            'message_id' => $response->messageId,
            'echo_token' => $response->echoToken,
            'duration_ms' => $response->durationMs,
            'warnings' => $response->warnings,
        ]);
    }

    /**
     * Create a job for delta inventory update
     *
     * @param DataCollection<int, InventoryData>|InventoryData $inventoryData
     * @param string $hotelCode
     * @param int|null $propertyId
     * @return self
     */
    public static function delta(
        DataCollection|InventoryData $inventoryData,
        string $hotelCode,
        ?int $propertyId = null
    ): self {
        return new self(
            inventoryData: $inventoryData,
            hotelCode: $hotelCode,
            isOverlay: false,
            highPriority: false,
            propertyId: $propertyId
        );
    }

    /**
     * Create a job for overlay (full sync) inventory update
     *
     * @param DataCollection<int, InventoryData>|InventoryData $inventoryData
     * @param string $hotelCode
     * @param int|null $propertyId
     * @param bool $highPriority
     * @return self
     */
    public static function overlay(
        DataCollection|InventoryData $inventoryData,
        string $hotelCode,
        ?int $propertyId = null,
        bool $highPriority = true
    ): self {
        return new self(
            inventoryData: $inventoryData,
            hotelCode: $hotelCode,
            isOverlay: true,
            highPriority: $highPriority,
            propertyId: $propertyId
        );
    }

    /**
     * Create an urgent job for critical inventory updates
     *
     * @param DataCollection<int, InventoryData>|InventoryData $inventoryData
     * @param string $hotelCode
     * @param int|null $propertyId
     * @return self
     */
    public static function urgent(
        DataCollection|InventoryData $inventoryData,
        string $hotelCode,
        ?int $propertyId = null
    ): self {
        return new self(
            inventoryData: $inventoryData,
            hotelCode: $hotelCode,
            isOverlay: false,
            highPriority: true,
            propertyId: $propertyId
        );
    }
}
