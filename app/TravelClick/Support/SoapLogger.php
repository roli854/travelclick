<?php

namespace App\TravelClick\Support;

use App\TravelClick\Models\TravelClickLog;
use App\TravelClick\Models\TravelClickErrorLog;
use App\TravelClick\DTOs\SoapRequestDto;
use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\MessageDirection;
use App\TravelClick\Enums\SyncStatus;
use App\TravelClick\Enums\ErrorType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Context;
use Exception;
use Throwable;

/**
 * SoapLogger - Comprehensive logging for TravelClick SOAP operations
 *
 * This class provides detailed logging for all SOAP operations with TravelClick,
 * including performance metrics, error handling, and audit trails.
 *
 * Features:
 * - Detailed request/response logging
 * - Performance timing measurement
 * - Error classification and handling
 * - Integration with Laravel logging
 * - Configurable log levels for debugging
 * - XML storage with size optimization
 *
 * @package App\TravelClick\Support
 */
class SoapLogger
{
    /** @var array Configuration options */
    private array $config;

    /** @var bool Whether debug logging is enabled */
    private bool $debugEnabled;

    /** @var bool Whether to store XML in database */
    private bool $storeXml;

    /** @var int Maximum XML size to store (in bytes) */
    private int $maxXmlSize;

    /** @var string Current log context identifier */
    private string $contextId;

    // Log levels
    public const LEVEL_MINIMAL = 'minimal';
    public const LEVEL_STANDARD = 'standard';
    public const LEVEL_DETAILED = 'detailed';
    public const LEVEL_DEBUG = 'debug';

    /**
     * Constructor - Initialize SOAP logger with configuration
     *
     * @param array $config Optional configuration override
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->debugEnabled = $this->config['debug_enabled'] ?? false;
        $this->storeXml = $this->config['store_xml'] ?? true;
        $this->maxXmlSize = $this->config['max_xml_size'] ?? 1048576; // 1MB default
        $this->contextId = uniqid('soap_', true);
    }

    /**
     * Create logger instance from Laravel configuration
     *
     * @return self
     */
    public static function create(): self
    {
        $config = config('travelclick.logging', []);
        return new self($config);
    }

    /**
     * Log the start of a SOAP operation
     *
     * @param SoapRequestDto $request SOAP request DTO
     * @param int|null $propertyId Property ID for the operation
     * @param string|null $jobId Associated job ID
     * @return TravelClickLog Created log entry
     */
    public function logRequestStart(
        SoapRequestDto $request,
        ?int $propertyId = null,
        ?string $jobId = null
    ): TravelClickLog {
        $startTime = microtime(true);

        // Store timing information in Context for later use
        Context::add('soap_operation', [
            'message_id' => $request->messageId,
            'start_time' => $startTime,
            'context_id' => $this->contextId
        ]);

        // Determine message type from XML content
        $messageType = $this->detectMessageType($request->xmlBody);

        // Create the log entry
        $log = TravelClickLog::create([
            'MessageID' => $request->messageId,
            'Direction' => MessageDirection::OUTBOUND,
            'MessageType' => $messageType,
            'PropertyID' => $propertyId,
            'HotelCode' => $request->hotelCode,
            'RequestXML' => $this->storeXml ? $this->optimizeXmlForStorage($request->xmlBody) : null,
            'Status' => SyncStatus::PROCESSING,
            'StartedAt' => Carbon::now(),
            'SystemUserID' => auth()->id() ?? 0,
            'JobID' => $jobId,
            'Metadata' => $this->buildRequestMetadata($request)
        ]);

        // Log to Laravel log system
        $this->writeToLaravelLog('info', 'SOAP request initiated', [
            'message_id' => $request->messageId,
            'action' => $request->action,
            'hotel_code' => $request->hotelCode,
            'message_type' => $messageType->value,
            'xml_size' => strlen($request->xmlBody),
            'context_id' => $this->contextId
        ]);

        return $log;
    }

    /**
     * Log successful SOAP response
     *
     * @param TravelClickLog $log Existing log entry
     * @param SoapResponseDto $response SOAP response DTO
     * @return TravelClickLog Updated log entry
     */
    public function logResponseSuccess(TravelClickLog $log, SoapResponseDto $response): TravelClickLog
    {
        // Calculate precise timing
        $duration = $this->calculateDuration($response->durationMs);

        // Update the log entry
        $log->update([
            'Status' => SyncStatus::COMPLETED,
            'ResponseXML' => $this->storeXml ? $this->optimizeXmlForStorage($response->rawResponse) : null,
            'CompletedAt' => Carbon::now(),
            'DurationMs' => $duration,
            'Metadata' => array_merge($log->Metadata ?? [], $this->buildResponseMetadata($response))
        ]);

        // Log warnings if present
        if ($response->hasWarnings()) {
            $this->logWarnings($log, $response->warnings);
        }

        // Log to Laravel log system
        $this->writeToLaravelLog('info', 'SOAP request completed successfully', [
            'message_id' => $response->messageId,
            'duration_ms' => $duration,
            'echo_token' => $response->echoToken,
            'response_size' => strlen($response->rawResponse),
            'has_warnings' => $response->hasWarnings(),
            'context_id' => $this->contextId
        ]);

        return $log;
    }

    /**
     * Log failed SOAP response or error
     *
     * @param TravelClickLog $log Existing log entry
     * @param SoapResponseDto|null $response SOAP response DTO (if available)
     * @param Throwable|null $exception Exception that occurred
     * @return TravelClickLog Updated log entry
     */
    public function logResponseFailure(
        TravelClickLog $log,
        ?SoapResponseDto $response = null,
        ?Throwable $exception = null
    ): TravelClickLog {
        // Determine error type and message
        $errorType = $this->classifyError($exception, $response);
        $errorMessage = $this->extractErrorMessage($exception, $response);

        // Calculate duration if available
        $duration = null;
        if ($response?->durationMs) {
            $duration = $this->calculateDuration($response->durationMs);
        } elseif ($log->StartedAt) {
            $duration = $log->StartedAt->diffInMilliseconds(Carbon::now());
        }

        // Update log entry
        $log->update([
            'Status' => SyncStatus::FAILED,
            'ErrorType' => $errorType,
            'ErrorMessage' => $errorMessage,
            'ResponseXML' => $response && $this->storeXml
                ? $this->optimizeXmlForStorage($response->rawResponse)
                : null,
            'CompletedAt' => Carbon::now(),
            'DurationMs' => $duration,
            'Metadata' => array_merge(
                $log->Metadata ?? [],
                $this->buildErrorMetadata($exception, $response)
            )
        ]);

        // Create detailed error log entry
        $this->createErrorLog($log, $exception, $response);

        // Log to Laravel log system
        $this->writeToLaravelLog('error', 'SOAP request failed', [
            'message_id' => $log->MessageID,
            'error_type' => $errorType->value,
            'error_message' => $errorMessage,
            'duration_ms' => $duration,
            'exception_class' => $exception ? get_class($exception) : null,
            'has_response' => $response !== null,
            'context_id' => $this->contextId
        ]);

        return $log;
    }

    /**
     * Log warnings from TravelClick response
     *
     * @param TravelClickLog $log Log entry
     * @param array $warnings Array of warning messages
     */
    public function logWarnings(TravelClickLog $log, array $warnings): void
    {
        foreach ($warnings as $warning) {
            TravelClickErrorLog::create([
                'MessageID' => $log->MessageID,
                'ErrorType' => ErrorType::WARNING,
                'ErrorMessage' => $warning,
                'Severity' => 'Warning',
                'Context' => json_encode(['context_id' => $this->contextId]),
                'SystemUserID' => $log->SystemUserID
            ]);
        }

        $this->writeToLaravelLog('warning', 'SOAP response contained warnings', [
            'message_id' => $log->MessageID,
            'warning_count' => count($warnings),
            'warnings' => $warnings,
            'context_id' => $this->contextId
        ]);
    }

    /**
     * Log operation performance metrics
     *
     * @param TravelClickLog $log Log entry
     * @param array $metrics Additional performance metrics
     */
    public function logPerformanceMetrics(TravelClickLog $log, array $metrics = []): void
    {
        $performanceData = [
            'duration_ms' => $log->DurationMs,
            'request_size' => strlen($log->RequestXML ?? ''),
            'response_size' => strlen($log->ResponseXML ?? ''),
            'message_type' => $log->MessageType->value,
            'success' => $log->Status === SyncStatus::COMPLETED,
            ...$metrics
        ];

        $log->addMetadata(['performance' => $performanceData]);

        // Log performance warning if operation was slow
        if ($log->DurationMs && $log->DurationMs > $this->config['slow_operation_threshold']) {
            $this->writeToLaravelLog('warning', 'Slow SOAP operation detected', [
                'message_id' => $log->MessageID,
                'duration_ms' => $log->DurationMs,
                'threshold_ms' => $this->config['slow_operation_threshold'],
                'context_id' => $this->contextId
            ]);
        }
    }

    /**
     * Log debug information (only in debug mode)
     *
     * @param string $message Debug message
     * @param array $context Additional context
     * @param string|null $messageId Associated message ID
     */
    public function logDebug(string $message, array $context = [], ?string $messageId = null): void
    {
        if (!$this->debugEnabled) {
            return;
        }

        $debugContext = [
            'message_id' => $messageId,
            'context_id' => $this->contextId,
            'debug_timestamp' => Carbon::now()->toISOString(),
            ...$context
        ];

        $this->writeToLaravelLog('debug', $message, $debugContext);
    }

    /**
     * Create a detailed error log entry
     *
     * @param TravelClickLog $log Main log entry
     * @param Throwable|null $exception Exception
     * @param SoapResponseDto|null $response Response DTO
     */
    private function createErrorLog(
        TravelClickLog $log,
        ?Throwable $exception = null,
        ?SoapResponseDto $response = null
    ): void {
        $errorType = $this->classifyError($exception, $response);
        $severity = $this->determineSeverity($exception, $response);

        $context = [
            'context_id' => $this->contextId,
            'soap_action' => $log->Metadata['action'] ?? null,
            'hotel_code' => $log->HotelCode,
        ];

        if ($exception) {
            $context['exception'] = [
                'class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->debugEnabled ? $exception->getTraceAsString() : null
            ];
        }

        if ($response) {
            $context['response'] = [
                'echo_token' => $response->echoToken,
                'error_code' => $response->errorCode,
                'has_warnings' => $response->hasWarnings()
            ];
        }

        TravelClickErrorLog::create([
            'MessageID' => $log->MessageID,
            'ErrorType' => $errorType,
            'ErrorMessage' => $this->extractErrorMessage($exception, $response),
            'Severity' => $severity,
            'Context' => json_encode($context),
            'SystemUserID' => $log->SystemUserID
        ]);
    }

    /**
     * Detect message type from XML content
     *
     * @param string $xml XML content
     * @return MessageType Detected message type
     */
    private function detectMessageType(string $xml): MessageType
    {
        if (str_contains($xml, 'OTA_HotelInvCountNotifRQ')) {
            return MessageType::INVENTORY;
        }
        if (str_contains($xml, 'OTA_HotelRateNotifRQ')) {
            return MessageType::RATES;
        }
        if (str_contains($xml, 'OTA_HotelResNotifRQ')) {
            return MessageType::RESERVATION;
        }
        if (str_contains($xml, 'OTA_HotelInvBlockNotifRQ')) {
            return MessageType::GROUP_BLOCK;
        }
        if (str_contains($xml, 'OTA_HotelAvailNotifRQ')) {
            return MessageType::INVENTORY;
        }

        return MessageType::UNKNOWN;
    }

    /**
     * Classify error type based on exception and response
     *
     * @param Throwable|null $exception
     * @param SoapResponseDto|null $response
     * @return ErrorType
     */
    private function classifyError(?Throwable $exception, ?SoapResponseDto $response): ErrorType
    {
        if ($exception) {
            $exceptionClass = get_class($exception);

            if (str_contains($exceptionClass, 'AuthenticationException')) {
                return ErrorType::AUTHENTICATION;
            }
            if (str_contains($exceptionClass, 'ConnectionException')) {
                return ErrorType::CONNECTION;
            }
            if (str_contains($exceptionClass, 'ValidationException')) {
                return ErrorType::VALIDATION;
            }
            if ($exception instanceof \SoapFault) {
                return ErrorType::SOAP_XML;
            }
        }

        if ($response && !$response->isSuccess) {
            if ($response->errorCode) {
                return ErrorType::BUSINESS_LOGIC;
            }
        }

        return ErrorType::UNKNOWN;
    }

    /**
     * Extract meaningful error message
     *
     * @param Throwable|null $exception
     * @param SoapResponseDto|null $response
     * @return string
     */
    private function extractErrorMessage(?Throwable $exception, ?SoapResponseDto $response): string
    {
        if ($exception) {
            return $exception->getMessage();
        }

        if ($response && $response->errorMessage) {
            return $response->errorMessage;
        }

        return 'Unknown error occurred';
    }

    /**
     * Determine error severity
     *
     * @param Throwable|null $exception
     * @param SoapResponseDto|null $response
     * @return string
     */
    private function determineSeverity(?Throwable $exception, ?SoapResponseDto $response): string
    {
        if ($exception) {
            if (str_contains(get_class($exception), 'AuthenticationException')) {
                return 'Critical';
            }
            if (str_contains(get_class($exception), 'ConnectionException')) {
                return 'High';
            }
        }

        if ($response && $response->hasWarnings()) {
            return 'Medium';
        }

        return 'Low';
    }

    /**
     * Build request metadata
     *
     * @param SoapRequestDto $request
     * @return array
     */
    private function buildRequestMetadata(SoapRequestDto $request): array
    {
        return [
            'action' => $request->action,
            'echo_token' => $request->echoToken,
            'version' => $request->version,
            'target' => $request->target,
            'xml_size_bytes' => strlen($request->xmlBody),
            'context_id' => $this->contextId,
            'log_level' => $this->config['log_level'] ?? self::LEVEL_STANDARD,
            'request_timestamp' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Build response metadata
     *
     * @param SoapResponseDto $response
     * @return array
     */
    private function buildResponseMetadata(SoapResponseDto $response): array
    {
        return [
            'echo_token' => $response->echoToken,
            'response_size_bytes' => strlen($response->rawResponse),
            'has_warnings' => $response->hasWarnings(),
            'warnings_count' => $response->warnings ? count($response->warnings) : 0,
            'response_timestamp' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Build error metadata
     *
     * @param Throwable|null $exception
     * @param SoapResponseDto|null $response
     * @return array
     */
    private function buildErrorMetadata(?Throwable $exception, ?SoapResponseDto $response): array
    {
        $metadata = [
            'error_timestamp' => Carbon::now()->toISOString(),
            'has_exception' => $exception !== null,
            'has_response' => $response !== null
        ];

        if ($exception) {
            $metadata['exception_type'] = get_class($exception);
            $metadata['exception_code'] = $exception->getCode();
        }

        if ($response) {
            $metadata['error_code'] = $response->errorCode;
            $metadata['response_success'] = $response->isSuccess;
        }

        return $metadata;
    }

    /**
     * Calculate operation duration
     *
     * @param float|null $providedDuration Provided duration in milliseconds
     * @return float|null Calculated duration in milliseconds
     */
    private function calculateDuration(?float $providedDuration): ?float
    {
        if ($providedDuration !== null) {
            return $providedDuration;
        }

        // Try to get from Context
        $context = Context::get('soap_operation');
        if ($context && isset($context['start_time'])) {
            return (microtime(true) - $context['start_time']) * 1000;
        }

        return null;
    }

    /**
     * Optimize XML for storage (remove formatting, truncate if too large)
     *
     * @param string $xml XML content
     * @return string Optimized XML
     */
    private function optimizeXmlForStorage(string $xml): string
    {
        // Remove extra whitespace and formatting
        $xml = preg_replace('/>\s+</', '><', $xml);
        $xml = trim($xml);

        // Truncate if too large
        if (strlen($xml) > $this->maxXmlSize) {
            $xml = substr($xml, 0, $this->maxXmlSize - 100) . '... [TRUNCATED]';
        }

        return $xml;
    }

    /**
     * Write to Laravel log system with context
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Log context
     */
    private function writeToLaravelLog(string $level, string $message, array $context = []): void
    {
        // Add common context
        $context['component'] = 'TravelClick.SoapLogger';
        $context['timestamp'] = Carbon::now()->toISOString();

        // Log based on configured level
        switch ($this->config['log_level']) {
            case self::LEVEL_MINIMAL:
                if (in_array($level, ['error', 'critical'])) {
                    Log::channel('travelclick')->{$level}($message, $context);
                }
                break;

            case self::LEVEL_STANDARD:
                if (in_array($level, ['error', 'warning', 'info'])) {
                    Log::channel('travelclick')->{$level}($message, $context);
                }
                break;

            case self::LEVEL_DETAILED:
            case self::LEVEL_DEBUG:
                Log::channel('travelclick')->{$level}($message, $context);
                break;
        }

        // Always log errors to main Laravel log
        if (in_array($level, ['error', 'critical'])) {
            Log::{$level}($message, $context);
        }
    }

    /**
     * Get default configuration
     *
     * @return array Default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'debug_enabled' => config('app.debug', false),
            'store_xml' => true,
            'max_xml_size' => 1048576, // 1MB
            'log_level' => self::LEVEL_STANDARD,
            'slow_operation_threshold' => 5000, // 5 seconds in milliseconds
            'channel' => 'travelclick'
        ];
    }

    /**
     * Get performance statistics for recent operations
     *
     * @param int $minutes Number of minutes to look back
     * @return array Performance statistics
     */
    public function getPerformanceStats(int $minutes = 60): array
    {
        $cutoff = Carbon::now()->subMinutes($minutes);

        return DB::connection('centriumLog')
            ->table('TravelClickLogs')
            ->where('DateCreated', '>=', $cutoff)
            ->selectRaw('
                COUNT(*) as total_operations,
                AVG(DurationMs) as avg_duration_ms,
                MAX(DurationMs) as max_duration_ms,
                MIN(DurationMs) as min_duration_ms,
                SUM(CASE WHEN Status = "completed" THEN 1 ELSE 0 END) as successful_operations,
                SUM(CASE WHEN Status = "failed" THEN 1 ELSE 0 END) as failed_operations,
                SUM(CASE WHEN DurationMs > ? THEN 1 ELSE 0 END) as slow_operations
            ', [$this->config['slow_operation_threshold']])
            ->first()->toArray();
    }

    /**
     * Clean up old log entries for maintenance
     *
     * @param int $daysToKeep Number of days to keep logs
     * @return array Cleanup results
     */
    public function cleanupLogs(int $daysToKeep = 30): array
    {
        $cutoff = Carbon::now()->subDays($daysToKeep);

        $results = [
            'cutoff_date' => $cutoff->toDateString(),
            'logs_deleted' => 0,
            'error_logs_deleted' => 0
        ];

        // Delete old TravelClick logs (keep failed logs longer)
        $results['logs_deleted'] = TravelClickLog::where('DateCreated', '<', $cutoff)
            ->where('Status', '!=', SyncStatus::FAILED)
            ->delete();

        // Delete old error logs
        $results['error_logs_deleted'] = TravelClickErrorLog::where('DateCreated', '<', $cutoff)
            ->delete();

        $this->writeToLaravelLog('info', 'Log cleanup completed', $results);

        return $results;
    }

    /**
     * Generate comprehensive operation report
     *
     * @param string $messageId Message ID to report on
     * @return array Operation report
     */
    public function generateOperationReport(string $messageId): array
    {
        $log = TravelClickLog::where('MessageID', $messageId)->first();

        if (!$log) {
            return ['error' => 'Log entry not found'];
        }

        $errorLogs = TravelClickErrorLog::where('MessageID', $messageId)->get();

        return [
            'operation' => [
                'message_id' => $messageId,
                'direction' => $log->Direction->value,
                'type' => $log->MessageType->value,
                'status' => $log->Status->value,
                'hotel_code' => $log->HotelCode,
                'property_id' => $log->PropertyID,
            ],
            'timing' => [
                'started_at' => $log->StartedAt?->toISOString(),
                'completed_at' => $log->CompletedAt?->toISOString(),
                'duration_ms' => $log->DurationMs,
                'formatted_duration' => $log->getFormattedDuration(),
            ],
            'data_sizes' => [
                'request_xml_size' => strlen($log->RequestXML ?? ''),
                'response_xml_size' => strlen($log->ResponseXML ?? ''),
            ],
            'errors' => $errorLogs->map(function ($errorLog) {
                return [
                    'type' => $errorLog->ErrorType?->value,
                    'message' => $errorLog->ErrorMessage,
                    'severity' => $errorLog->Severity,
                    'context' => json_decode($errorLog->Context ?? '{}', true)
                ];
            })->toArray(),
            'metadata' => $log->Metadata,
            'user' => $log->systemUser ? [
                'id' => $log->SystemUserID,
                'username' => $log->systemUser->UserName ?? 'Unknown'
            ] : null,
            'performance_classification' => $this->classifyPerformance($log->DurationMs),
        ];
    }

    /**
     * Classify operation performance
     *
     * @param float|null $durationMs Duration in milliseconds
     * @return string Performance classification
     */
    private function classifyPerformance(?float $durationMs): string
    {
        if ($durationMs === null) {
            return 'unknown';
        }

        if ($durationMs < 1000) {
            return 'excellent';
        } elseif ($durationMs < 3000) {
            return 'good';
        } elseif ($durationMs < 5000) {
            return 'acceptable';
        } elseif ($durationMs < 10000) {
            return 'slow';
        } else {
            return 'very_slow';
        }
    }

    /**
     * Create a scoped logger for a specific operation
     *
     * @param string $operationType Type of operation
     * @param string $messageId Message ID
     * @return SoapLogger New logger instance with operation context
     */
    public static function forOperation(string $operationType, string $messageId): self
    {
        $config = config('travelclick.logging', []);
        $logger = new self($config);

        $logger->contextId = "{$operationType}_{$messageId}_" . uniqid();

        Context::add('soap_operation_context', [
            'operation_type' => $operationType,
            'message_id' => $messageId,
            'context_id' => $logger->contextId
        ]);

        return $logger;
    }
}
