<?php

namespace App\TravelClick\Parsers;

use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Enums\ErrorType;
use SimpleXMLElement;
use SoapFault;
use Throwable;
use Illuminate\Support\Facades\Log;

/**
 * Specialized parser for error responses from TravelClick SOAP API
 *
 * This class extends the base SoapResponseParser to provide more detailed
 * error analysis, categorization, and structured information for debugging.
 * It specializes in extracting error codes, messages, and mapping them to
 * appropriate error types for better error handling and recovery.
 */
class ErrorResponseParser extends SoapResponseParser
{
    /**
     * Common HTNG error codes and their descriptions
     *
     * These are standard error codes returned by TravelClick/HTNG services
     * that we can map to specific error messages and types.
     */
    protected const HTNG_ERROR_CODES = [
        // Authentication errors
        'AUT01' => 'Invalid credentials',
        'AUT02' => 'Token expired',
        'AUT03' => 'Access denied',

        // Validation errors
        'VAL01' => 'Missing required field',
        'VAL02' => 'Invalid data format',
        'VAL03' => 'Invalid date range',
        'VAL04' => 'Invalid room type code',
        'VAL05' => 'Invalid rate plan code',

        // System errors
        'SYS01' => 'System temporarily unavailable',
        'SYS02' => 'Database error',
        'SYS03' => 'Internal server error',

        // Business logic errors
        'BUS01' => 'Inventory not available',
        'BUS02' => 'Rate not available',
        'BUS03' => 'Booking window closed',
        'BUS04' => 'Minimum stay not satisfied',
        'BUS05' => 'Maximum stay exceeded',

        // Connection/timeout errors
        'CON01' => 'Connection timeout',
        'CON02' => 'Service unavailable',

        // Rate limit errors
        'LIM01' => 'Rate limit exceeded',
        'LIM02' => 'Too many requests',
    ];

    /**
     * Parse an error response from TravelClick
     *
     * @param string $messageId The unique message identifier for tracking
     * @param string $rawResponse The raw XML response from TravelClick
     * @param ?float $durationMs The time taken to receive the response
     * @return SoapResponseDto The parsed response
     */
    public function parseError(
        string $messageId,
        string $rawResponse,
        ?float $durationMs = null
    ): SoapResponseDto {
        try {
            // First use the base parser to get a basic SoapResponseDto
            $responseDto = parent::parse($messageId, $rawResponse, $durationMs);

            // If it's already a successful response, just return it
            if ($responseDto->isSuccess) {
                return $responseDto;
            }

            // Now we'll get more detailed error information
            $errorDetails = $this->extractDetailedErrorInfo($rawResponse);

            // Determine the error type based on code and message
            $errorType = $this->categorizeError($errorDetails['code'], $errorDetails['message']);

            // Log enhanced error details (since we can't include them in the DTO directly)
            $this->logEnhancedErrorDetails($messageId, $errorType, $errorDetails);

            // Create warnings array with useful information
            $warnings = [];

            // Add error category to warnings
            $warnings[] = "Error category: " . $errorType->value;

            // Add retryability information to warnings
            $canRetry = $errorType->canRetry();
            $warnings[] = $canRetry
                ? "This error can be retried after {$errorType->getRetryDelay()} seconds"
                : "This error cannot be automatically retried";

            // Add error details note if there are any
            if (!empty($errorDetails['details'])) {
                $warnings[] = "Enhanced error details logged with message ID: $messageId";
            }

            // Add any warnings from the response
            if (!empty($errorDetails['warnings'])) {
                $warnings = array_merge($warnings, $errorDetails['warnings']);
            }

            // Return a failure response with the enhanced information
            return SoapResponseDto::failure(
                messageId: $messageId,
                rawResponse: $rawResponse,
                errorMessage: $errorDetails['message'],
                errorCode: $errorDetails['code'],
                warnings: $warnings,
                durationMs: $durationMs
            );
        } catch (Throwable $e) {
            // If we get an exception while parsing the error, create a basic error response
            Log::error('Exception while parsing error response', [
                'message_id' => $messageId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return SoapResponseDto::failure(
                messageId: $messageId,
                rawResponse: $rawResponse,
                errorMessage: "Failed to parse error response: {$e->getMessage()}",
                errorCode: 'PARSE_ERROR',
                warnings: ["Error categorization failed due to parsing exception"],
                durationMs: $durationMs
            );
        }
    }

    /**
     * Extract detailed error information from a raw SOAP response
     *
     * @param string $rawResponse The raw XML response
     * @return array Detailed error information including code, message, details and warnings
     */
    protected function extractDetailedErrorInfo(string $rawResponse): array
    {
        $result = [
            'code' => 'UNKNOWN_ERROR',
            'message' => 'Unknown error occurred',
            'details' => [],
            'warnings' => [],
        ];

        try {
            // If empty response, return generic error
            if (empty($rawResponse)) {
                $result['code'] = 'EMPTY_RESPONSE';
                $result['message'] = 'Empty response received from TravelClick';
                return $result;
            }

            // Parse the XML
            $xml = $this->parseXml($rawResponse);

            // First check for SOAP faults (they take precedence)
            if ($this->hasSoapFault($xml)) {
                $faultInfo = $this->extractEnhancedSoapFault($xml);
                return array_merge($result, $faultInfo);
            }

            // Check for OTA-specific errors
            $otaErrors = $this->extractEnhancedOtaErrors($xml);
            if ($otaErrors) {
                return array_merge($result, $otaErrors);
            }

            // Check for warning nodes that might contain useful information
            $warnings = $this->extractWarnings($xml);
            if (!empty($warnings)) {
                $result['warnings'] = $warnings;
                $result['code'] = 'WARNING';
                $result['message'] = implode('; ', $warnings);
            }

            return $result;
        } catch (Throwable $e) {
            // If XML parsing fails, add the exception info to the result
            $result['code'] = 'XML_PARSE_ERROR';
            $result['message'] = "Failed to parse error XML: {$e->getMessage()}";
            $result['details']['exception'] = [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];

            return $result;
        }
    }

    /**
     * Extract enhanced SOAP fault information with additional details
     *
     * @param SimpleXMLElement $xml The parsed XML
     * @return array Enhanced fault information
     */
    protected function extractEnhancedSoapFault(SimpleXMLElement $xml): array
    {
        // Get basic fault info from parent method
        $faultInfo = $this->extractSoapFault($xml);

        $result = [
            'code' => $faultInfo['code'],
            'message' => $faultInfo['message'],
            'details' => [],
        ];

        // Attempt to extract additional fault details if available
        $faultNodes = $xml->xpath('//soap:Fault');
        if (!empty($faultNodes)) {
            $fault = $faultNodes[0];

            // Extract fault detail elements if they exist
            $detailNodes = $fault->xpath('.//soap:Detail/*');
            if (!empty($detailNodes)) {
                foreach ($detailNodes as $detailNode) {
                    // Convert the detail element to an array and add to details
                    $detail = $this->xmlToArray($detailNode);
                    if (!empty($detail)) {
                        $detailName = $detailNode->getName();
                        $result['details'][$detailName] = $detail;
                    }
                }
            }

            // Look for HTNG-specific subcodes
            $subcodeNodes = $fault->xpath('.//soap:Code/soap:Subcode/soap:Value');
            if (!empty($subcodeNodes)) {
                $subcode = (string)$subcodeNodes[0];
                if (!empty($subcode)) {
                    $result['code'] = $subcode;
                    // If we have a known HTNG code, add its description
                    if (isset(self::HTNG_ERROR_CODES[$subcode])) {
                        $result['details']['description'] = self::HTNG_ERROR_CODES[$subcode];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Extract enhanced OTA-specific errors with additional details
     *
     * @param SimpleXMLElement $xml The parsed XML
     * @return array|null Enhanced error information if present
     */
    protected function extractEnhancedOtaErrors(SimpleXMLElement $xml): ?array
    {
        // Check for OTA-style errors
        $errorNodes = $xml->xpath('//ota:Errors/ota:Error');
        if (empty($errorNodes)) {
            return null;
        }

        $result = [
            'code' => 'OTA_ERROR',
            'message' => 'Error in OTA response',
            'details' => [],
            'warnings' => [],
        ];

        $messages = [];
        $details = [];

        foreach ($errorNodes as $index => $error) {
            $errorDetail = [];
            $attributes = $error->attributes();

            // Extract all available attributes
            foreach ($attributes as $key => $value) {
                $errorDetail[$key] = (string)$value;
            }

            // Get the error code if available
            if (isset($attributes['Code'])) {
                $errorCode = (string)$attributes['Code'];
                $result['code'] = $errorCode;

                // If we have a known HTNG code, add its description
                if (isset(self::HTNG_ERROR_CODES[$errorCode])) {
                    $errorDetail['description'] = self::HTNG_ERROR_CODES[$errorCode];
                }
            } elseif (isset($attributes['Type'])) {
                $errorDetail['type'] = (string)$attributes['Type'];
            }

            // Get the error message from various possible sources
            $message = (string)$error;
            if (!empty($message)) {
                $messages[] = $message;
                $errorDetail['message'] = $message;
            } elseif (isset($attributes['ShortText'])) {
                $messages[] = (string)$attributes['ShortText'];
                $errorDetail['message'] = (string)$attributes['ShortText'];
            }

            // Add additional attribute data
            if (isset($attributes['Status'])) {
                $errorDetail['status'] = (string)$attributes['Status'];
            }

            if (isset($attributes['RecordID'])) {
                $errorDetail['recordId'] = (string)$attributes['RecordID'];
            }

            // Extract any nested elements
            $childNodes = $error->children();
            if (count($childNodes) > 0) {
                foreach ($childNodes as $name => $child) {
                    $errorDetail[$name] = (string)$child;
                }
            }

            // Add this error's details to the collection
            $details["error_{$index}"] = $errorDetail;
        }

        if (!empty($messages)) {
            $result['message'] = implode('; ', $messages);
        }

        if (!empty($details)) {
            $result['details'] = $details;
        }

        return $result;
    }

    /**
     * Parse a SoapFault exception with enhanced error details
     *
     * @param string $messageId The unique message identifier for tracking
     * @param SoapFault $fault The SOAP fault exception
     * @param ?float $durationMs The time taken before the fault occurred
     * @return SoapResponseDto Enhanced error response
     */
    public function parseFromFault(
        string $messageId,
        SoapFault $fault,
        ?float $durationMs = null
    ): SoapResponseDto {
        // First get the basic response from parent
        $responseDto = parent::parseFromFault($messageId, $fault, $durationMs);

        // Determine error type from the fault
        $errorType = $this->categorizeFromException($fault);

        // Extract any detail information if available
        $details = [];

        if (isset($fault->detail)) {
            $details['detail'] = $fault->detail;
        }

        if (isset($fault->faultactor)) {
            $details['faultActor'] = $fault->faultactor;
        }

        // Get fault code and message
        $faultCode = $fault->faultcode ?? 'SOAP_FAULT';
        $faultMessage = $fault->faultstring ?? 'Unknown SOAP fault';

        // Map to HTNG error codes if possible
        foreach (self::HTNG_ERROR_CODES as $code => $description) {
            if (
                stripos($faultMessage, $code) !== false ||
                stripos($faultMessage, $description) !== false
            ) {
                $faultCode = $code;
                $details['description'] = $description;
                break;
            }
        }

        // Log enhanced error details
        $this->logEnhancedErrorDetails($messageId, $errorType, [
            'code' => $faultCode,
            'message' => $faultMessage,
            'details' => $details
        ]);

        // Create warnings array with useful information
        $warnings = [];
        $warnings[] = "Error category: " . $errorType->value;
        $warnings[] = $errorType->canRetry()
            ? "This error can be retried after {$errorType->getRetryDelay()} seconds"
            : "This error cannot be automatically retried";
        $warnings[] = "Enhanced error details logged with message ID: $messageId";

        // Return a failure response with enhanced information in warnings
        return SoapResponseDto::failure(
            messageId: $messageId,
            rawResponse: $responseDto->rawResponse,
            errorMessage: $faultMessage,
            errorCode: $faultCode,
            warnings: $warnings,
            durationMs: $durationMs
        );
    }

    /**
     * Categorize an error based on its code and message
     *
     * @param string $errorCode The error code
     * @param string $errorMessage The error message
     * @return ErrorType The categorized error type
     */
    public function categorizeError(string $errorCode, string $errorMessage): ErrorType
    {
        // First check code prefixes for standard HTNG codes
        $codePrefix = substr($errorCode, 0, 3);

        switch ($codePrefix) {
            case 'AUT':
                return ErrorType::AUTHENTICATION;

            case 'VAL':
                return ErrorType::VALIDATION;

            case 'SYS':
                return ErrorType::SOAP_XML;

            case 'BUS':
                return ErrorType::BUSINESS_LOGIC;

            case 'CON':
                return ErrorType::CONNECTION;

            case 'LIM':
                return ErrorType::RATE_LIMIT;
        }

        // If not a standard code, check the full code
        if (in_array($errorCode, ['EMPTY_RESPONSE', 'CONNECTION_ERROR'])) {
            return ErrorType::CONNECTION;
        }

        if (in_array($errorCode, ['XML_PARSE_ERROR', 'SOAP_FAULT'])) {
            return ErrorType::SOAP_XML;
        }

        if ($errorCode === 'WARNING') {
            return ErrorType::WARNING;
        }

        // If we couldn't categorize by code, check the message content
        $message = strtolower($errorMessage);

        if (
            strpos($message, 'authentica') !== false ||
            strpos($message, 'credential') !== false ||
            strpos($message, 'access denied') !== false
        ) {
            return ErrorType::AUTHENTICATION;
        }

        if (
            strpos($message, 'valid') !== false ||
            strpos($message, 'required field') !== false ||
            strpos($message, 'format') !== false
        ) {
            return ErrorType::VALIDATION;
        }

        if (strpos($message, 'timeout') !== false) {
            return ErrorType::TIMEOUT;
        }

        if (strpos($message, 'connect') !== false) {
            return ErrorType::CONNECTION;
        }

        if (
            strpos($message, 'limit') !== false ||
            strpos($message, 'too many') !== false
        ) {
            return ErrorType::RATE_LIMIT;
        }

        if (
            strpos($message, 'xml') !== false ||
            strpos($message, 'soap') !== false ||
            strpos($message, 'parse') !== false
        ) {
            return ErrorType::SOAP_XML;
        }

        // If we still can't categorize it, return UNKNOWN
        return ErrorType::UNKNOWN;
    }

    /**
     * Categorize an error from an exception
     *
     * @param Throwable $exception The exception to categorize
     * @return ErrorType The categorized error type
     */
    public function categorizeFromException(Throwable $exception): ErrorType
    {
        return ErrorType::fromException($exception);
    }

    /**
     * Map HTNG error code to human readable description
     *
     * @param string $errorCode The HTNG error code
     * @return string The description or the original code if not found
     */
    public function getHtngErrorDescription(string $errorCode): string
    {
        return self::HTNG_ERROR_CODES[$errorCode] ?? $errorCode;
    }

    /**
     * Check if an error is retryable based on its type
     *
     * @param string $errorCode The error code
     * @param string $errorMessage The error message
     * @return bool True if the error is retryable
     */
    public function isRetryableError(string $errorCode, string $errorMessage): bool
    {
        $errorType = $this->categorizeError($errorCode, $errorMessage);
        return $errorType->canRetry();
    }

    /**
     * Get recommended retry delay for an error in seconds
     *
     * @param string $errorCode The error code
     * @param string $errorMessage The error message
     * @return int The recommended delay in seconds
     */
    public function getRetryDelay(string $errorCode, string $errorMessage): int
    {
        $errorType = $this->categorizeError($errorCode, $errorMessage);
        return $errorType->getRetryDelay();
    }

    /**
     * Log enhanced error details for later analysis
     *
     * @param string $messageId The message ID for correlation
     * @param ErrorType $errorType The categorized error type
     * @param array $errorDetails The detailed error information
     * @return void
     */
    protected function logEnhancedErrorDetails(string $messageId, ErrorType $errorType, array $errorDetails): void
    {
        // Determine log level based on error severity
        $logMethod = match ($errorType->getSeverity()) {
            1 => 'critical',
            2 => 'error',
            3 => 'warning',
            4 => 'info',
            default => 'error'
        };

        // Log with correlation ID for easy lookup
        Log::$logMethod('TravelClick error details', [
            'message_id' => $messageId,
            'error_type' => $errorType->value,
            'error_code' => $errorDetails['code'],
            'error_message' => $errorDetails['message'],
            'details' => $errorDetails['details'] ?? [],
            'can_retry' => $errorType->canRetry(),
            'retry_delay' => $errorType->getRetryDelay(),
            'severity' => $errorType->getSeverity(),
        ]);
    }
}
