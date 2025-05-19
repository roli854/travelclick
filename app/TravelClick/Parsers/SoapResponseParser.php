<?php

namespace App\TravelClick\Parsers;

use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Enums\ErrorType;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use SoapFault;
use SimpleXMLElement;
use Throwable;

/**
 * Base class for parsing SOAP responses from TravelClick
 *
 * This class handles the common functionality for parsing SOAP responses,
 * extracting error information, and converting XML to structured data.
 * Specific message types should extend this class with their own parsing logic.
 */
class SoapResponseParser
{
  /**
   * XML namespaces used in TravelClick responses
   */
  protected const NAMESPACES = [
    'soap' => 'http://www.w3.org/2003/05/soap-envelope',
    'ota' => 'http://www.opentravel.org/OTA/2003/05',
    'wsa' => 'http://www.w3.org/2005/08/addressing',
  ];

  /**
   * Parse a SOAP response into a structured DTO
   *
   * @param string $messageId The unique message identifier for tracking
   * @param string $rawResponse The raw XML response from TravelClick
   * @param ?float $durationMs The time taken to receive the response in milliseconds
   * @param array $headers Optional SOAP headers from the response
   * @return SoapResponseDto The parsed response data
   */
  public function parse(
    string $messageId,
    string $rawResponse,
    ?float $durationMs = null,
    array $headers = []
  ): SoapResponseDto {
    try {
      // Handle empty responses
      if (empty($rawResponse)) {
        return SoapResponseDto::failure(
          messageId: $messageId,
          rawResponse: '',
          errorMessage: 'Empty response received',
          errorCode: 'EMPTY_RESPONSE',
          durationMs: $durationMs
        );
      }

      // Parse the XML
      $xml = $this->parseXml($rawResponse);

      // If we have a SOAP fault or errors in the response
      if ($this->hasSoapFault($xml)) {
        $faultInfo = $this->extractSoapFault($xml);
        return SoapResponseDto::failure(
          messageId: $messageId,
          rawResponse: $rawResponse,
          errorMessage: $faultInfo['message'],
          errorCode: $faultInfo['code'],
          durationMs: $durationMs
        );
      }

      // Extract echoToken (if present)
      $echoToken = $this->extractEchoToken($xml);

      // Check for warnings or errors in the OTA specific errors
      $warnings = $this->extractWarnings($xml);
      $otaErrors = $this->extractOtaErrors($xml);

      if (!empty($otaErrors)) {
        return SoapResponseDto::failure(
          messageId: $messageId,
          rawResponse: $rawResponse,
          errorMessage: $otaErrors['message'],
          errorCode: $otaErrors['code'],
          warnings: $warnings,
          durationMs: $durationMs
        );
      }

      // If we reach here, the response is successful
      return SoapResponseDto::success(
        messageId: $messageId,
        rawResponse: $rawResponse,
        echoToken: $echoToken,
        headers: $headers,
        durationMs: $durationMs
      );
    } catch (Throwable $e) {
      // Any exception during parsing is treated as a failure
      return SoapResponseDto::failure(
        messageId: $messageId,
        rawResponse: $rawResponse,
        errorMessage: "Failed to parse SOAP response: {$e->getMessage()}",
        errorCode: ErrorType::SOAP_XML->value,
        durationMs: $durationMs
      );
    }
  }

  /**
   * Parse raw XML into SimpleXMLElement
   *
   * @param string $rawXml The raw XML string
   * @return SimpleXMLElement The parsed XML object
   * @throws \Exception If the XML cannot be parsed
   */
  protected function parseXml(string $rawXml): SimpleXMLElement
  {
    // Suppress XML parsing warnings and throw exceptions instead
    libxml_use_internal_errors(true);

    try {
      $xml = new SimpleXMLElement($rawXml);

      // Register namespaces for XPath queries
      foreach (self::NAMESPACES as $prefix => $uri) {
        $xml->registerXPathNamespace($prefix, $uri);
      }

      return $xml;
    } catch (Throwable $e) {
      $errors = libxml_get_errors();
      libxml_clear_errors();

      $errorMessage = !empty($errors)
        ? $errors[0]->message
        : $e->getMessage();

      throw new \Exception("XML parsing error: $errorMessage", 0, $e);
    }
  }

  /**
   * Check if the SOAP response contains a fault
   *
   * @param SimpleXMLElement $xml The parsed XML
   * @return bool True if a SOAP fault is present
   */
  protected function hasSoapFault(SimpleXMLElement $xml): bool
  {
    // Check for SOAP 1.2 fault
    $faultNodes = $xml->xpath('//soap:Fault');
    return !empty($faultNodes);
  }

  /**
   * Extract fault information from SOAP response
   *
   * @param SimpleXMLElement $xml The parsed XML
   * @return array The fault code and message
   */
  protected function extractSoapFault(SimpleXMLElement $xml): array
  {
    $result = [
      'code' => 'SOAP_FAULT',
      'message' => 'Unknown SOAP fault',
    ];

    // Try to get detailed fault information
    $faultNodes = $xml->xpath('//soap:Fault');
    if (!empty($faultNodes)) {
      $fault = $faultNodes[0];

      // Get fault code (may be in different locations depending on SOAP version)
      $codeNodes = $fault->xpath('.//soap:Code/soap:Value');
      if (!empty($codeNodes)) {
        $result['code'] = (string)$codeNodes[0];
      } elseif (isset($fault->faultcode)) {
        $result['code'] = (string)$fault->faultcode;
      }

      // Get fault message (may be in different locations depending on SOAP version)
      $reasonNodes = $fault->xpath('.//soap:Reason/soap:Text');
      if (!empty($reasonNodes)) {
        $result['message'] = (string)$reasonNodes[0];
      } elseif (isset($fault->faultstring)) {
        $result['message'] = (string)$fault->faultstring;
      }
    }

    return $result;
  }

  /**
   * Extract Echo Token from SOAP response
   *
   * @param SimpleXMLElement $xml The parsed XML
   * @return string|null The echo token if present
   */
  protected function extractEchoToken(SimpleXMLElement $xml): ?string
  {
    // Look for OTA response elements with EchoToken attribute
    foreach ($xml->xpath('//*[@EchoToken]') as $element) {
      $attributes = $element->attributes();
      if (isset($attributes['EchoToken'])) {
        return (string)$attributes['EchoToken'];
      }
    }

    return null;
  }

  /**
   * Extract warnings from the response
   *
   * @param SimpleXMLElement $xml The parsed XML
   * @return array Warnings found in the response
   */
  protected function extractWarnings(SimpleXMLElement $xml): array
  {
    $warnings = [];

    // Check for OTA-style warnings
    $warningNodes = $xml->xpath('//ota:Warnings/ota:Warning');
    if (!empty($warningNodes)) {
      foreach ($warningNodes as $warning) {
        $warnings[] = trim((string)$warning);
      }
    }

    // Add additional warning checks here as needed for specific responses

    return $warnings;
  }

  /**
   * Extract OTA-specific errors from the response
   *
   * @param SimpleXMLElement $xml The parsed XML
   * @return array|null Error information if present
   */
  protected function extractOtaErrors(SimpleXMLElement $xml): ?array
  {
    // Check for OTA-style errors
    $errorNodes = $xml->xpath('//ota:Errors/ota:Error');
    if (empty($errorNodes)) {
      return null;
    }

    // Combine all error messages
    $messages = [];
    $errorCode = 'OTA_ERROR';

    foreach ($errorNodes as $error) {
      $attributes = $error->attributes();
      $message = (string)$error;

      // If the node has text content, use it as the message
      if (!empty($message)) {
        $messages[] = $message;
      }

      // Otherwise check for ShortText/value attributes
      if (isset($attributes['ShortText'])) {
        $messages[] = (string)$attributes['ShortText'];
      }

      // Get the error code if available
      if (isset($attributes['Code'])) {
        $errorCode = (string)$attributes['Code'];
      } elseif (isset($attributes['Type'])) {
        $errorCode = (string)$attributes['Type'];
      }
    }

    if (empty($messages)) {
      $messages[] = 'Unknown OTA error';
    }

    return [
      'code' => $errorCode,
      'message' => implode('; ', $messages),
    ];
  }

  /**
   * Extract response body content (remove SOAP envelope)
   *
   * @param SimpleXMLElement $xml The parsed XML
   * @return SimpleXMLElement|null The body content
   */
  protected function extractBodyContent(SimpleXMLElement $xml): ?SimpleXMLElement
  {
    // Try to extract the actual response from the SOAP body
    $bodyNodes = $xml->xpath('//soap:Body/*[1]');

    return !empty($bodyNodes) ? $bodyNodes[0] : null;
  }

  /**
   * Convert SimpleXMLElement to array
   *
   * @param SimpleXMLElement $xml The XML to convert
   * @return array The resulting array
   */
  protected function xmlToArray(SimpleXMLElement $xml): array
  {
    $json = json_encode($xml);
    return json_decode($json, true) ?? [];
  }

  /**
   * Create a parser from a SoapFault exception
   *
   * @param string $messageId The unique message identifier for tracking
   * @param SoapFault $fault The SOAP fault exception
   * @param ?float $durationMs The time taken before the fault occurred
   * @return SoapResponseDto The parsed fault as a response DTO
   */
  public function parseFromFault(
    string $messageId,
    SoapFault $fault,
    ?float $durationMs = null
  ): SoapResponseDto {
    return SoapResponseDto::fromSoapFault(
      messageId: $messageId,
      fault: $fault,
      durationMs: $durationMs
    );
  }

  /**
   * Get timestamp from response if available
   *
   * @param SimpleXMLElement $xml The parsed XML
   * @return Carbon|null The timestamp as a Carbon instance
   */
  protected function extractTimestamp(SimpleXMLElement $xml): ?Carbon
  {
    // Look for TimeStamp attribute on the root response element
    $responseNodes = $xml->xpath('//*[@TimeStamp]');
    if (!empty($responseNodes)) {
      $attributes = $responseNodes[0]->attributes();
      if (isset($attributes['TimeStamp'])) {
        try {
          return Carbon::parse((string)$attributes['TimeStamp']);
        } catch (Throwable $e) {
          // If parsing fails, return null
          return null;
        }
      }
    }

    return null;
  }
}
