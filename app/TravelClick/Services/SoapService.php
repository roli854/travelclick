<?php

namespace App\TravelClick\Services;

use App\TravelClick\Services\Contracts\SoapServiceInterface;
use App\TravelClick\DTOs\SoapRequestDto;
use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Support\SoapClientFactory;
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
 * OPTIMIZED: Now uses SoapClientFactory with automatic header injection
 * via the SoapHeaders class for proper WSSE authentication following
 * TravelClick HTNG 2011B specifications.
 */
class SoapService implements SoapServiceInterface
{
    private ?SoapClient $client = null;
    private array $config;
    private SoapClientFactory $clientFactory;
    private string $lastRequestId = '';

    public function __construct(SoapClientFactory $clientFactory = null)
    {
        $this->config = config('travelclick');
        $this->clientFactory = $clientFactory ?? $this->createOptimizedFactory();
        // Connection will be created per request for better header management
    }

    /**
     * Send a SOAP request to TravelClick
     */
    public function sendRequest(SoapRequestDto $request): SoapResponseDto
    {
        $this->lastRequestId = $request->messageId;
        $startTime = microtime(true);

        // Log the outbound request
        $log = $this->logRequest($request);

        try {
            // Create client with automatic header injection
            // This ensures each request gets properly formed headers
            $client = $this->clientFactory->createWithHeaders(
                $request->messageId,
                $request->action
            );

            // Execute the SOAP call using optimized envelope
            $response = $this->executeOptimizedRequest($client, $request);

            $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            // Parse and create response DTO
            $responseDto = $this->parseResponse($request->messageId, $response, $duration);

            // Log the successful response
            $this->logResponse($log, $responseDto);

            return $responseDto;
        } catch (SoapFault $fault) {
            $duration = (microtime(true) - $startTime) * 1000;

            // Handle different types of SOAP faults
            $responseDto = $this->handleSoapFault($request->messageId, $fault, $duration);
            $this->logError($log, $responseDto, $fault);

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

            $this->logError($log, $responseDto, $e);

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
     * Update inventory at TravelClick
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
     * Update rates at TravelClick
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
     * Send reservation to TravelClick
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
     * Test connection to TravelClick
     */
    public function testConnection(): bool
    {
        try {
            // Use factory's built-in connection test
            $result = $this->clientFactory->testConnection();

            if ($result) {
                // Cache the successful connection test
                Cache::put('travelclick.connection.test', true, now()->addMinutes(5));

                Log::info('TravelClick connection test successful', [
                    'factory_config' => $this->clientFactory->getConfigSummary()
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::warning('TravelClick connection test failed', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'factory_config' => $this->clientFactory->getConfigSummary()
            ]);

            return false;
        }
    }

    /**
     * Get the current SOAP client instance
     */
    public function getClient(): SoapClient
    {
        if ($this->client === null) {
            $this->client = $this->clientFactory->create();
        }
        return $this->client;
    }

    /**
     * Check if the service is currently connected
     */
    public function isConnected(): bool
    {
        // Check cached connection status first
        if (Cache::has('travelclick.connection.test')) {
            return true;
        }

        return $this->testConnection();
    }

    /**
     * Create optimized SOAP client factory from configuration
     */
    private function createOptimizedFactory(): SoapClientFactory
    {
        return SoapClientFactory::fromConfig($this->config);
    }

    /**
     * Execute request with optimized envelope and auto-injected headers
     */
    private function executeOptimizedRequest(SoapClient $client, SoapRequestDto $request): string
    {
        // Get headers that were injected by factory
        $headers = $client->__headers ?? '';

        // Build envelope with the pre-generated headers
        $envelope = $this->buildOptimizedEnvelope($request, $headers);

        // Set the SOAPAction header
        $client->__setSoapHeaders(null);

        // Execute the request
        $response = $client->__doRequest(
            $envelope,
            $this->determineEndpointUrl(),
            $request->action,
            SOAP_1_2
        );

        if ($response === null || $response === false) {
            throw new SoapException(
                'SOAP request returned null or false response',
                $request->messageId,
                context: [
                    'endpoint' => $this->determineEndpointUrl(),
                    'action' => $request->action
                ]
            );
        }

        return $response;
    }

    /**
     * Build optimized SOAP envelope with pre-generated headers
     */
    private function buildOptimizedEnvelope(SoapRequestDto $request, string $headers): string
    {
        return sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>
            <soapenv:Envelope xmlns:wsa="http://www.w3.org/2005/08/addressing"
                             xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"
                             xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
                             xmlns:htn="http://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService">
                <soapenv:Header>%s</soapenv:Header>
                <soapenv:Body>%s</soapenv:Body>
            </soapenv:Envelope>',
            $headers,  // Headers generated by SoapHeaders class via factory
            $request->xmlBody
        );
    }

    /**
     * Determine the appropriate endpoint URL from configuration
     */
    private function determineEndpointUrl(): string
    {
        $environment = app()->environment();

        if ($environment === 'production') {
            return $this->config['endpoints']['production'] ?? $this->config['endpoints']['url'] ?? '';
        }

        return $this->config['endpoints']['test'] ?? $this->config['endpoints']['url'] ?? '';
    }

    /**
     * Parse SOAP response into DTO
     */
    private function parseResponse(string $messageId, string $response, float $duration): SoapResponseDto
    {
        // Extract echo token if present
        $echoToken = $this->extractEchoToken($response);

        // Check for SOAP faults in response
        if (str_contains($response, 'soap:Fault') || str_contains($response, 'faultcode')) {
            $faultMessage = $this->extractFaultMessage($response);
            return SoapResponseDto::failure(
                $messageId,
                $response,
                $faultMessage,
                null,
                null,
                $duration
            );
        }

        // Check for business logic warnings
        $warnings = $this->extractWarnings($response);

        return SoapResponseDto::success(
            $messageId,
            $response,
            $echoToken,
            null,
            $duration
        );
    }

    /**
     * Handle SOAP fault exceptions
     */
    private function handleSoapFault(string $messageId, SoapFault $fault, float $duration): SoapResponseDto
    {
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
                faultDetail: "fault_code : [{$fault->getCode()}]",
                previous: $fault
            );
        }

        // Check for connection errors
        if (
            str_contains($faultString, 'Connection') ||
            str_contains($faultString, 'timeout') ||
            str_contains($faultString, 'network') ||
            str_contains($faultString, 'refused')
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
     * Log outbound request
     */
    private function logRequest(SoapRequestDto $request): TravelClickLog
    {
        return TravelClickLog::create([
            'MessageID' => $request->messageId,
            'Direction' => MessageDirection::OUTBOUND->value,
            'MessageType' => $this->determineMessageType($request->xmlBody)->value,
            'PropertyID' => null, // Will be set by caller if needed
            'HotelCode' => $request->hotelCode,
            'RequestXML' => $request->xmlBody,
            'Status' => 'Pending',
            'StartTime' => Carbon::now(),
            'SystemUserID' => auth()->id() ?? 1,
        ]);
    }

    /**
     * Log successful response
     */
    private function logResponse(TravelClickLog $log, SoapResponseDto $response): void
    {
        $log->update([
            'ResponseXML' => $response->rawResponse,
            'Status' => $response->isSuccess ? 'Completed' : 'Failed',
            'EndTime' => Carbon::now(),
            'DurationMS' => $response->durationMs,
            'ErrorMessage' => $response->errorMessage,
        ]);

        Log::info('TravelClick SOAP request completed', [
            'message_id' => $response->messageId,
            'duration' => $response->getFormattedDuration(),
            'success' => $response->isSuccess,
            'has_warnings' => $response->hasWarnings(),
        ]);
    }

    /**
     * Log error details
     */
    private function logError(TravelClickLog $log, SoapResponseDto $response, \Throwable $exception): void
    {
        $log->update([
            'Status' => 'Failed',
            'EndTime' => Carbon::now(),
            'DurationMS' => $response->durationMs,
            'ErrorMessage' => $response->errorMessage,
            'ResponseXML' => $response->rawResponse,
        ]);

        Log::error('TravelClick SOAP request failed', [
            'message_id' => $response->messageId,
            'error' => $response->errorMessage,
            'exception_class' => get_class($exception),
            'duration' => $response->getFormattedDuration(),
            'fault_code' => $exception instanceof SoapFault ? $exception->getCode() : null,
        ]);
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
     * Get last request ID for debugging
     */
    public function getLastRequestId(): string
    {
        return $this->lastRequestId;
    }

    /**
     * Get current configuration summary
     */
    public function getConfigSummary(): array
    {
        return [
            'factory_config' => $this->clientFactory->getConfigSummary(),
            'last_request_id' => $this->lastRequestId,
            'connection_status' => $this->isConnected(),
            'endpoint' => $this->determineEndpointUrl(),
        ];
    }

    /**
     * Force reconnection by clearing cached client
     */
    public function reconnect(): void
    {
        $this->client = null;
        Cache::forget('travelclick.connection.test');

        Log::info('TravelClick connection reset', [
            'factory_config' => $this->clientFactory->getConfigSummary()
        ]);
    }

    /**
     * Cleanup resources
     */
    public function __destruct()
    {
        if ($this->client) {
            $this->client = null;
        }
    }
}
