<?php

namespace App\TravelClick\Services;

use App\TravelClick\Services\Contracts\SoapServiceInterface;
use App\TravelClick\DTOs\SoapRequestDto;
use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Support\SoapClientFactory;
use App\TravelClick\Support\SoapLogger;
use App\TravelClick\Support\SoapHeaders;
use App\TravelClick\Exceptions\SoapException;
use App\TravelClick\Exceptions\TravelClickConnectionException;
use App\TravelClick\Exceptions\TravelClickAuthenticationException;
use App\TravelClick\Models\TravelClickLog;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\MessageDirection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use SoapClient;
use SoapFault;

/**
 * Service for handling all SOAP communications with TravelClick
 *
 * This service acts as the primary gateway for all TravelClick SOAP operations.
 * It handles authentication, error handling, comprehensive logging, and retry logic.
 *
 * Enhanced with detailed SOAP logging and performance monitoring.
 */
class SoapService implements SoapServiceInterface
{
    private ?SoapClient $client = null;
    private array $config;
    private SoapClientFactory $clientFactory;
    private SoapLogger $logger;
    private string $lastRequestId = '';
    private ?int $propertyId = null;
    private ?string $jobId = null;

    public function __construct(
        SoapClientFactory $clientFactory = null,
        SoapLogger $logger = null
    ) {
        $this->config = config('travelclick');
        $this->clientFactory = $clientFactory ?? $this->createDefaultFactory();
        $this->logger = $logger ?? SoapLogger::create();
        $this->ensureConnection();
    }

    /**
     * Set property ID for logging context
     *
     * @param int $propertyId Property ID
     * @return self
     */
    public function forProperty(int $propertyId): self
    {
        $this->propertyId = $propertyId;
        return $this;
    }

    /**
     * Set job ID for logging context
     *
     * @param string $jobId Job ID
     * @return self
     */
    public function forJob(string $jobId): self
    {
        $this->jobId = $jobId;
        return $this;
    }

    /**
     * Send a SOAP request to TravelClick with comprehensive logging
     */
    public function sendRequest(SoapRequestDto $request): SoapResponseDto
    {
        $this->lastRequestId = $request->messageId;

        // Create operation-scoped logger
        $operationLogger = SoapLogger::forOperation(
            $this->determineOperationType($request->xmlBody),
            $request->messageId
        );

        // Log request start with detailed context
        $log = $operationLogger->logRequestStart($request, $this->propertyId, $this->jobId);

        // Debug logging for request details
        $operationLogger->logDebug('Initiating SOAP request', [
            'action' => $request->action,
            'hotel_code' => $request->hotelCode,
            'xml_size' => strlen($request->xmlBody),
            'property_id' => $this->propertyId,
            'job_id' => $this->jobId
        ]);

        $startTime = microtime(true);

        try {
            $this->ensureConnection();

            // Build SOAP envelope with enhanced headers
            $envelope = $this->buildSoapEnvelopeWithHeaders($request);

            // Debug log the envelope (without sensitive data)
            $operationLogger->logDebug('SOAP envelope prepared', [
                'envelope_size' => strlen($envelope),
                'has_auth_headers' => str_contains($envelope, 'wsse:Security')
            ]);

            // Execute the SOAP request
            $response = $this->executeRequest($envelope, $request->action);

            $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            // Parse and create response DTO
            $responseDto = $this->parseResponse($request->messageId, $response, $duration);

            // Log successful response with performance metrics
            $log = $operationLogger->logResponseSuccess($log, $responseDto);
            $operationLogger->logPerformanceMetrics($log, [
                'envelope_size' => strlen($envelope),
                'connection_reused' => $this->client !== null,
                'operation_type' => $this->determineOperationType($request->xmlBody)
            ]);

            // Debug log response parsing
            $operationLogger->logDebug('SOAP response parsed successfully', [
                'echo_token' => $responseDto->echoToken,
                'has_warnings' => $responseDto->hasWarnings(),
                'response_classification' => $this->classifyResponse($responseDto)
            ]);

            return $responseDto;
        } catch (SoapFault $fault) {
            $duration = (microtime(true) - $startTime) * 1000;

            // Create failure response DTO
            $responseDto = $this->handleSoapFault($request->messageId, $fault, $duration);

            // Log detailed error information
            $operationLogger->logResponseFailure($log, $responseDto, $fault);

            // Debug log fault details
            $operationLogger->logDebug('SOAP fault occurred', [
                'fault_code' => $fault->faultcode ?? 'unknown',
                'fault_string' => $fault->faultstring ?? $fault->getMessage(),
                'fault_actor' => $fault->faultactor ?? null,
                'fault_detail' => $fault->detail ?? null
            ]);

            // Throw appropriate exception based on fault type
            $this->throwAppropriateException($fault, $request->messageId);
        } catch (\Exception $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            $responseDto = SoapResponseDto::failure(
                $request->messageId,
                $e->getMessage(),
                "Unexpected error: {$e->getMessage()}",
                $e->getCode(),
                null,
                $duration
            );

            // Log unexpected error with full context
            $operationLogger->logResponseFailure($log, $responseDto, $e);

            // Debug log exception details
            $operationLogger->logDebug('Unexpected exception occurred', [
                'exception_class' => get_class($e),
                'exception_code' => $e->getCode(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine()
            ]);

            throw new SoapException(
                "Unexpected error during SOAP request: {$e->getMessage()}",
                $request->messageId,
                context: ['original_exception' => get_class($e)],
                previous: $e
            );
        }

        return $responseDto; // This line should never be reached due to exceptions
    }

    /**
     * Update inventory at TravelClick with enhanced logging
     */
    public function updateInventory(string $xml, string $hotelCode): SoapResponseDto
    {
        $messageId = SoapHeaders::generateMessageId('INV');

        $request = SoapRequestDto::forInventory(
            messageId: $messageId,
            xmlBody: $xml,
            hotelCode: $hotelCode,
            echoToken: $messageId
        );

        return $this->sendRequest($request);
    }

    /**
     * Update rates at TravelClick with enhanced logging
     */
    public function updateRates(string $xml, string $hotelCode): SoapResponseDto
    {
        $messageId = SoapHeaders::generateMessageId('RATE');

        $request = SoapRequestDto::forRates(
            messageId: $messageId,
            xmlBody: $xml,
            hotelCode: $hotelCode,
            echoToken: $messageId
        );

        return $this->sendRequest($request);
    }

    /**
     * Send reservation to TravelClick with enhanced logging
     */
    public function sendReservation(string $xml, string $hotelCode): SoapResponseDto
    {
        $messageId = SoapHeaders::generateMessageId('RES');

        $request = SoapRequestDto::forReservation(
            messageId: $messageId,
            xmlBody: $xml,
            hotelCode: $hotelCode,
            echoToken: $messageId
        );

        return $this->sendRequest($request);
    }

    /**
     * Test connection to TravelClick with logging
     */
    public function testConnection(): bool
    {
        $this->logger->logDebug('Testing TravelClick connection');

        try {
            $this->ensureConnection();

            // Attempt a simple operation to verify connection
            $functions = $this->client->__getFunctions();

            $this->logger->logDebug('Connection test successful', [
                'available_functions' => count($functions),
                'client_wsdl' => $this->config['endpoints']['wsdl'] ?? 'N/A'
            ]);

            // Cache the successful connection test
            Cache::put('travelclick.connection.test', true, now()->addMinutes(5));

            return true;
        } catch (\Exception $e) {
            $this->logger->logDebug('Connection test failed', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'wsdl_url' => $this->config['endpoints']['wsdl'] ?? 'N/A'
            ]);

            Log::warning('TravelClick connection test failed', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e)
            ]);

            return false;
        }
    }

    /**
     * Get the current SOAP client instance
     */
    public function getClient(): SoapClient
    {
        $this->ensureConnection();
        return $this->client;
    }

    /**
     * Check if the service is currently connected
     */
    public function isConnected(): bool
    {
        return $this->client !== null && $this->testConnection();
    }

    /**
     * Get operation statistics from SoapLogger
     */
    public function getOperationStats(int $minutes = 60): array
    {
        return $this->logger->getPerformanceStats($minutes);
    }

    /**
     * Generate detailed operation report
     */
    public function getOperationReport(string $messageId): array
    {
        return $this->logger->generateOperationReport($messageId);
    }

    /**
     * Ensure SOAP client is connected with logging
     */
    private function ensureConnection(): void
    {
        if ($this->client === null) {
            $this->logger->logDebug('Creating new SOAP client');

            $this->client = $this->clientFactory->create();

            $this->logger->logDebug('SOAP client created successfully', [
                'wsdl' => $this->config['endpoints']['wsdl'] ?? 'N/A',
                'client_class' => get_class($this->client)
            ]);

            Log::info('TravelClick SOAP client created', [
                'wsdl' => $this->config['endpoints']['wsdl'],
                'client_class' => get_class($this->client)
            ]);
        }
    }

    /**
     * Create default SOAP client factory
     */
    private function createDefaultFactory(): SoapClientFactory
    {
        return new SoapClientFactory(
            wsdl: $this->config['endpoints']['wsdl'],
            username: $this->config['credentials']['username'],
            password: $this->config['credentials']['password'],
            options: $this->config['soap_options'] ?? []
        );
    }

    /**
     * Build SOAP envelope with enhanced headers using SoapHeaders class
     */
    private function buildSoapEnvelopeWithHeaders(SoapRequestDto $request): string
    {
        // Generate WSSE headers using SoapHeaders
        [$headers, $messageId] = SoapHeaders::forOperation(
            $this->determineOperationType($request->xmlBody),
            $request->messageId
        );

        // Build complete SOAP envelope
        $envelope = sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>
            <soapenv:Envelope xmlns:wsa="http://www.w3.org/2005/08/addressing"
                             xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
                             xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
                             xmlns:htn="http://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService">
                <soapenv:Header>%s</soapenv:Header>
                <soapenv:Body>%s</soapenv:Body>
            </soapenv:Envelope>',
            $headers,
            $request->xmlBody
        );

        // Validate headers if in debug mode
        if (config('app.debug')) {
            $isValid = SoapHeaders::validateHeaders($headers);
            $this->logger->logDebug('Headers validation', [
                'valid' => $isValid,
                'headers_size' => strlen($headers),
                'envelope_size' => strlen($envelope)
            ]);
        }

        return $envelope;
    }

    /**
     * Execute the actual SOAP request with enhanced error handling
     */
    private function executeRequest(string $envelope, string $action): string
    {
        $this->logger->logDebug('Executing SOAP request', [
            'action' => $action,
            'endpoint' => $this->config['endpoints']['url'],
            'envelope_size' => strlen($envelope)
        ]);

        // Clear any existing headers and prepare for request
        $this->client->__setSoapHeaders(null);

        // Execute the SOAP call
        $response = $this->client->__doRequest(
            $envelope,
            $this->config['endpoints']['url'],
            $action,
            SOAP_1_2
        );

        if ($response === null || $response === false) {
            $lastError = $this->client->__getLastResponse();

            $this->logger->logDebug('SOAP request returned null/false', [
                'last_response' => $lastError ? substr($lastError, 0, 500) : 'No response',
                'last_request_headers' => $this->client->__getLastRequestHeaders(),
                'last_response_headers' => $this->client->__getLastResponseHeaders()
            ]);

            throw new SoapException(
                'SOAP request returned null or false response',
                $this->lastRequestId
            );
        }

        $this->logger->logDebug('SOAP request executed successfully', [
            'response_size' => strlen($response),
            'response_type' => $this->detectResponseType($response)
        ]);

        return $response;
    }

    /**
     * Parse SOAP response into DTO with enhanced logging
     */
    private function parseResponse(string $messageId, string $response, float $duration): SoapResponseDto
    {
        $this->logger->logDebug('Parsing SOAP response', [
            'message_id' => $messageId,
            'response_size' => strlen($response),
            'duration_ms' => $duration
        ]);

        // Extract echo token if present
        $echoToken = $this->extractEchoToken($response);

        // Check for SOAP faults in response
        if (str_contains($response, 'soap:Fault') || str_contains($response, 'faultcode')) {
            $faultMessage = $this->extractFaultMessage($response);

            $this->logger->logDebug('SOAP fault detected in response', [
                'fault_message' => $faultMessage,
                'echo_token' => $echoToken
            ]);

            return SoapResponseDto::failure(
                $messageId,
                $response,
                $faultMessage,
                null,
                null,
                $duration
            );
        }

        // Extract warnings if present
        $warnings = $this->extractWarnings($response);

        if ($warnings) {
            $this->logger->logDebug('Warnings found in response', [
                'warning_count' => count($warnings),
                'warnings' => $warnings
            ]);
        }

        // Extract any business logic errors
        $businessErrors = $this->extractBusinessErrors($response);

        if ($businessErrors) {
            $this->logger->logDebug('Business errors found in response', [
                'error_count' => count($businessErrors),
                'errors' => $businessErrors
            ]);
        }

        return SoapResponseDto::success(
            $messageId,
            $response,
            $echoToken,
            null,
            $duration
        );
    }

    /**
     * Handle SOAP fault exceptions with detailed logging
     */
    private function handleSoapFault(string $messageId, SoapFault $fault, float $duration): SoapResponseDto
    {
        $faultDetails = [
            'fault_code' => $fault->faultcode ?? 'UNKNOWN',
            'fault_string' => $fault->faultstring ?? $fault->getMessage(),
            'fault_actor' => $fault->faultactor ?? null,
            'fault_detail' => $fault->detail ?? null
        ];

        $this->logger->logDebug('Processing SOAP fault', $faultDetails);

        return SoapResponseDto::fromSoapFault($messageId, $fault, $duration);
    }

    /**
     * Throw appropriate exception based on fault type
     */
    private function throwAppropriateException(SoapFault $fault, string $messageId): void
    {
        $faultString = $fault->faultstring ?? $fault->getMessage();

        // Check for authentication errors
        if (
            str_contains($faultString, 'Authentication') ||
            str_contains($faultString, 'Unauthorized') ||
            str_contains($faultString, 'Invalid credentials')
        ) {
            throw new TravelClickAuthenticationException(
                "Authentication failed: {$faultString}",
                $messageId,
                previous: $fault
            );
        }

        // Check for connection errors
        if (
            str_contains($faultString, 'Connection') ||
            str_contains($faultString, 'timeout') ||
            str_contains($faultString, 'network')
        ) {
            throw new TravelClickConnectionException(
                "Connection error: {$faultString}",
                $messageId,
                previous: $fault
            );
        }

        // Default to SoapException
        throw SoapException::fromSoapFault($fault, $messageId);
    }

    /**
     * Determine operation type from XML content
     */
    private function determineOperationType(string $xml): string
    {
        if (str_contains($xml, 'OTA_HotelInvCountNotifRQ')) {
            return 'inventory';
        }
        if (str_contains($xml, 'OTA_HotelRateNotifRQ')) {
            return 'rates';
        }
        if (str_contains($xml, 'OTA_HotelResNotifRQ')) {
            return 'reservation';
        }
        if (str_contains($xml, 'OTA_HotelInvBlockNotifRQ')) {
            return 'groups';
        }
        return 'unknown';
    }

    /**
     * Determine message type from XML content
     */
    private function determineMessageType(string $xml): MessageType
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

        return MessageType::UNKNOWN;
    }

    /**
     * Detect response type for logging
     */
    private function detectResponseType(string $response): string
    {
        if (str_contains($response, 'soap:Fault')) {
            return 'fault';
        }
        if (str_contains($response, 'Success')) {
            return 'success';
        }
        if (str_contains($response, 'Warning')) {
            return 'warning';
        }
        if (str_contains($response, 'Error')) {
            return 'error';
        }
        return 'unknown';
    }

    /**
     * Classify response for analytical purposes
     */
    private function classifyResponse(SoapResponseDto $response): string
    {
        if (!$response->isSuccess) {
            return 'failure';
        }
        if ($response->hasWarnings()) {
            return 'success_with_warnings';
        }
        return 'clean_success';
    }

    /**
     * Extract echo token from response XML
     */
    private function extractEchoToken(string $response): ?string
    {
        if (preg_match('/EchoToken="([^"]+)"/', $response, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract fault message from response
     */
    private function extractFaultMessage(string $response): string
    {
        if (preg_match('/<faultstring>([^<]+)<\/faultstring>/', $response, $matches)) {
            return $matches[1];
        }
        if (preg_match('/<soap:Reason><soap:Text[^>]*>([^<]+)<\/soap:Text><\/soap:Reason>/', $response, $matches)) {
            return $matches[1];
        }
        return 'Unknown SOAP fault';
    }

    /**
     * Extract warnings from response
     */
    private function extractWarnings(string $response): ?array
    {
        $warnings = [];

        if (preg_match_all('/<Warning[^>]*>([^<]+)<\/Warning>/', $response, $matches)) {
            $warnings = $matches[1];
        }

        return empty($warnings) ? null : $warnings;
    }

    /**
     * Extract business logic errors from response
     */
    private function extractBusinessErrors(string $response): ?array
    {
        $errors = [];

        // Look for HotelError elements
        if (preg_match_all('/<Error[^>]*>([^<]+)<\/Error>/', $response, $matches)) {
            $errors = array_merge($errors, $matches[1]);
        }

        // Look for BusinessLevelError elements
        if (preg_match_all('/<BusinessLevelError[^>]*>([^<]+)<\/BusinessLevelError>/', $response, $matches)) {
            $errors = array_merge($errors, $matches[1]);
        }

        return empty($errors) ? null : $errors;
    }

    /**
     * Clean up resources with logging
     */
    public function __destruct()
    {
        if ($this->client) {
            $this->logger->logDebug('Cleaning up SOAP client resources');
            $this->client = null;
        }
    }

    /**
     * Perform maintenance operations
     */
    public function performMaintenance(int $logRetentionDays = 30): array
    {
        $this->logger->logDebug('Starting SOAP service maintenance', [
            'log_retention_days' => $logRetentionDays
        ]);

        $results = [
            'maintenance_started_at' => Carbon::now()->toISOString(),
            'connection_test' => $this->testConnection(),
            'cleanup_results' => $this->logger->cleanupLogs($logRetentionDays),
            'performance_stats' => $this->getOperationStats(24 * 60) // Last 24 hours
        ];

        $this->logger->logDebug('SOAP service maintenance completed', $results);

        return $results;
    }
}
