<?php

namespace App\TravelClick\Services;

use App\TravelClick\Services\Contracts\SoapServiceInterface;
use App\TravelClick\DTOs\SoapRequestDto;
use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Support\SoapClientFactory;
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
 * It handles authentication, error handling, logging, and retry logic.
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
        $this->clientFactory = $clientFactory ?? $this->createDefaultFactory();
        $this->ensureConnection();
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
            $this->ensureConnection();

            // Prepare the SOAP envelope
            $envelope = $this->buildSoapEnvelope($request);

            // Execute the SOAP call
            $response = $this->executeRequest($envelope, $request->action);

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
        $messageId = $this->generateMessageId('INV');

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
        $messageId = $this->generateMessageId('RATE');

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
        $messageId = $this->generateMessageId('RES');

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
            $this->ensureConnection();

            // Attempt a simple operation to verify connection
            $this->client->__getFunctions();

            // Cache the successful connection test
            Cache::put('travelclick.connection.test', true, now()->addMinutes(5));

            return true;
        } catch (\Exception $e) {
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
     * Ensure SOAP client is connected
     */
    private function ensureConnection(): void
    {
        if ($this->client === null) {
            $this->client = $this->clientFactory->create();

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
     * Build the complete SOAP envelope for the request
     */
    private function buildSoapEnvelope(SoapRequestDto $request): string
    {
        $headers = $this->buildSoapHeaders($request);

        return sprintf(
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
    }

    /**
     * Build SOAP headers according to HTNG 2011B specification
     */
    private function buildSoapHeaders(SoapRequestDto $request): string
    {
        $endpoint = $this->config['endpoints']['url'];

        return sprintf(
            '<wsa:MessageID>%s</wsa:MessageID>
             <wsa:To>%s</wsa:To>
             <wsa:ReplyTo>
                 <wsa:Address>http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous</wsa:Address>
             </wsa:ReplyTo>
             <wsa:Action>%s/%s</wsa:Action>
             <wsse:Security>
                 <wsse:UsernameToken>
                     <wsse:Username>%s</wsse:Username>
                     <wsse:Password>%s</wsse:Password>
                 </wsse:UsernameToken>
             </wsse:Security>
             <wsa:From>
                 <wsa:ReferenceProperties>
                     <htn:HotelCode>%s</htn:HotelCode>
                 </wsa:ReferenceProperties>
             </wsa:From>',
            $request->messageId,
            $endpoint,
            $endpoint,
            $request->action,
            $this->config['credentials']['username'],
            $this->config['credentials']['password'],
            $request->hotelCode
        );
    }

    /**
     * Execute the actual SOAP request
     */
    private function executeRequest(string $envelope, string $action): string
    {
        // Set the SOAPAction header
        $this->client->__setSoapHeaders(null);

        // Call the service using the raw envelope
        $response = $this->client->__doRequest(
            $envelope,
            $this->config['endpoints']['url'],
            $action,
            SOAP_1_2
        );

        if ($response === null || $response === false) {
            throw new SoapException(
                'SOAP request returned null or false response',
                $this->lastRequestId
            );
        }

        return $response;
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

        // Check for business logic errors
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
     * Generate a unique message ID
     */
    private function generateMessageId(string $prefix = 'MSG'): string
    {
        return sprintf(
            '%s_%s_%s_%s',
            $prefix,
            now()->format('Ymd_His'),
            substr(uniqid(), -6),
            random_int(1000, 9999)
        );
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
     * Cleanup resources
     */
    public function __destruct()
    {
        if ($this->client) {
            $this->client = null;
        }
    }
}
