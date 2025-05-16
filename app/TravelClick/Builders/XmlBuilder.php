<?php

declare(strict_types=1);

namespace App\TravelClick\Builders;

use App\TravelClick\DTOs\SoapHeaderDto;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Support\XmlNamespaces;
use App\TravelClick\Support\XmlValidator;
use Spatie\ArrayToXml\ArrayToXml;
use DOMDocument;
use InvalidArgumentException;

/**
 * Abstract base class for building HTNG 2011B XML messages
 *
 * This class provides common functionality for all XML builders in the TravelClick
 * integration. It handles SOAP envelope structure, namespaces, headers, and
 * validation. Think of it as the foundation blueprint that all specific
 * message builders will use to construct their XML.
 */
abstract class XmlBuilder
{
  /**
   * The message type this builder handles
   */
  protected MessageType $messageType;

  /**
   * SOAP headers for the message
   */
  protected SoapHeaderDto $soapHeaders;

  /**
   * Whether to validate the built XML
   */
  protected bool $validateXml = true;

  /**
   * Whether to format the output XML (pretty print)
   */
  protected bool $formatOutput = false;

  public function __construct(
    MessageType $messageType,
    SoapHeaderDto $soapHeaders,
    bool $validateXml = true,
    bool $formatOutput = false
  ) {
    $this->messageType = $messageType;
    $this->soapHeaders = $soapHeaders;
    $this->validateXml = $validateXml;
    $this->formatOutput = $formatOutput;
  }

  /**
   * Build the complete XML message
   *
   * @param array<string, mixed> $messageData The data to include in the message body
   * @return string The complete XML message
   * @throws InvalidArgumentException If the data is invalid
   */
  public function build(array $messageData): string
  {
    // Validate input data first
    $this->validateMessageData($messageData);

    // Build the complete SOAP envelope
    $envelopeData = $this->buildSoapEnvelope($messageData);

    // Convert to XML using spatie/array-to-xml
    $xml = $this->convertToXml($envelopeData);

    // Validate the resulting XML if requested
    if ($this->validateXml) {
      XmlValidator::validateAgainstSchema($xml, $this->messageType);
    }

    return $xml;
  }

  /**
   * Build the SOAP envelope structure
   *
   * @param array<string, mixed> $messageData
   * @return array<string, mixed>
   */
  protected function buildSoapEnvelope(array $messageData): array
  {
    return [
      '_attributes' => XmlNamespaces::getSoapEnvelopeAttributes(),
      'soapenv:Header' => $this->buildSoapHeader(),
      'soapenv:Body' => $this->buildMessageBody($messageData),
    ];
  }

  /**
   * Build the SOAP header section
   *
   * @return array<string, mixed>
   */
  protected function buildSoapHeader(): array
  {
    return [
      'wsa:MessageID' => $this->soapHeaders->messageId,
      'wsa:To' => $this->soapHeaders->to,
      'wsa:ReplyTo' => [
        'wsa:Address' => $this->soapHeaders->replyTo,
      ],
      'wsa:Action' => $this->soapHeaders->action,
      'wsse:Security' => [
        'wsse:UsernameToken' => [
          'wsse:Username' => $this->soapHeaders->username,
          'wsse:Password' => $this->soapHeaders->password,
        ],
      ],
      'wsa:From' => [
        'wsa:ReferenceProperties' => [
          'htn:HotelCode' => $this->soapHeaders->hotelCode,
        ],
      ],
    ];
  }

  /**
   * Build the message body - implemented by specific builders
   *
   * @param array<string, mixed> $messageData
   * @return array<string, mixed>
   */
  abstract protected function buildMessageBody(array $messageData): array;

  /**
   * Validate the message data before building - implemented by specific builders
   *
   * @param array<string, mixed> $messageData
   * @throws InvalidArgumentException If validation fails
   */
  abstract protected function validateMessageData(array $messageData): void;

  /**
   * Convert array data to XML string
   *
   * @param array<string, mixed> $data
   * @return string
   */
  protected function convertToXml(array $data): string
  {
    $xml = ArrayToXml::convert(
      data: $data,
      rootElement: 'soapenv:Envelope',
      replaceSpacesByUnderScoresInKeyNames: false,
      xmlEncoding: 'UTF-8',
      xmlVersion: '1.0',
      domProperties: $this->getDomProperties(),
      xmlStandalone: null
    );

    // Apply formatting if requested
    if ($this->formatOutput) {
      return $this->formatXml($xml);
    }

    return $xml;
  }

  /**
   * Get DOM properties for XML conversion
   *
   * @return array<string, mixed>
   */
  protected function getDomProperties(): array
  {
    return [
      'formatOutput' => $this->formatOutput,
      'preserveWhiteSpace' => false,
    ];
  }

  /**
   * Format XML for better readability
   *
   * @param string $xml
   * @return string
   */
  protected function formatXml(string $xml): string
  {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $dom->preserveWhiteSpace = false;
    $dom->loadXML($xml);

    return $dom->saveXML() ?: $xml;
  }

  /**
   * Get the OTA message root element name for this message type
   *
   * @return string
   */
  protected function getOtaRootElement(): string
  {
    return match ($this->messageType) {
      MessageType::INVENTORY => 'OTA_HotelInvCountNotifRQ',
      MessageType::RATES => 'OTA_HotelRateNotifRQ',
      MessageType::RESERVATION => 'OTA_HotelResNotifRQ',
      MessageType::GROUP_BLOCK => 'OTA_HotelInvBlockNotifRQ',
      default => throw new InvalidArgumentException("Unknown message type: {$this->messageType->value}"),
    };
  }

  /**
   * Get the OTA message attributes
   *
   * @return array<string, string>
   */
  protected function getOtaMessageAttributes(): array
  {
    $attributes = XmlNamespaces::buildNamespaceAttributes(
      XmlNamespaces::getOtaNamespaces()
    );

    // Add common OTA attributes
    $attributes['EchoToken'] = $this->soapHeaders->echoToken ?? '';
    $attributes['TimeStamp'] = $this->soapHeaders->timeStamp ?? now()->toISOString();
    $attributes['Target'] = config('travelclick.environment', 'Production');
    $attributes['Version'] = '1.0';

    return $attributes;
  }

  /**
   * Build common hotel reference structure
   *
   * @return array<string, string>
   */
  protected function buildHotelReference(): array
  {
    return [
      '_attributes' => [
        'HotelCode' => $this->soapHeaders->hotelCode,
      ],
    ];
  }

  /**
   * Validate date format and convert to HTNG format
   *
   * @param string $date
   * @param string $format Expected format (default: Y-m-d)
   * @return string Formatted date in HTNG format
   * @throws InvalidArgumentException If date is invalid
   */
  protected function validateAndFormatDate(string $date, string $format = 'Y-m-d'): string
  {
    $dateTime = \DateTime::createFromFormat($format, $date);

    if (!$dateTime || $dateTime->format($format) !== $date) {
      throw new InvalidArgumentException("Invalid date format: $date. Expected: $format");
    }

    return $dateTime->format('Y-m-d');
  }

  /**
   * Validate hotel code format
   *
   * @param string $hotelCode
   * @throws InvalidArgumentException If hotel code is invalid
   */
  protected function validateHotelCode(string $hotelCode): void
  {
    $validation = config('travelclick.validation.hotel_code');

    if (
      strlen($hotelCode) < $validation['min_length'] ||
      strlen($hotelCode) > $validation['max_length']
    ) {
      throw new InvalidArgumentException(
        "Hotel code length must be between {$validation['min_length']} and {$validation['max_length']} characters"
      );
    }

    if (!preg_match($validation['pattern'], $hotelCode)) {
      throw new InvalidArgumentException(
        "Hotel code contains invalid characters. Only alphanumeric characters are allowed."
      );
    }
  }

  /**
   * Validate room type code format
   *
   * @param string $roomTypeCode
   * @throws InvalidArgumentException If room type code is invalid
   */
  protected function validateRoomTypeCode(string $roomTypeCode): void
  {
    $validation = config('travelclick.validation.room_type_code');

    if (
      strlen($roomTypeCode) < $validation['min_length'] ||
      strlen($roomTypeCode) > $validation['max_length']
    ) {
      throw new InvalidArgumentException(
        "Room type code length must be between {$validation['min_length']} and {$validation['max_length']} characters"
      );
    }

    if (!preg_match($validation['pattern'], $roomTypeCode)) {
      throw new InvalidArgumentException(
        "Room type code contains invalid characters"
      );
    }
  }

  /**
   * Build status application control element
   *
   * @param string $startDate
   * @param string $endDate
   * @param string|null $roomTypeCode
   * @param string|null $ratePlanCode
   * @param bool $allInvCode For property-level operations
   * @return array<string, mixed>
   */
  protected function buildStatusApplicationControl(
    string $startDate,
    string $endDate,
    ?string $roomTypeCode = null,
    ?string $ratePlanCode = null,
    bool $allInvCode = false
  ): array {
    $attributes = [
      'Start' => $this->validateAndFormatDate($startDate),
      'End' => $this->validateAndFormatDate($endDate),
    ];

    if ($allInvCode) {
      $attributes['AllInvCode'] = 'true';
    } elseif ($roomTypeCode) {
      $this->validateRoomTypeCode($roomTypeCode);
      $attributes['InvTypeCode'] = $roomTypeCode;
    }

    if ($ratePlanCode) {
      $attributes['RatePlanCode'] = $ratePlanCode;
    }

    return ['_attributes' => $attributes];
  }

  /**
   * Set whether to validate the built XML
   *
   * @param bool $validate
   * @return self
   */
  public function withValidation(bool $validate = true): self
  {
    $this->validateXml = $validate;
    return $this;
  }

  /**
   * Set whether to format the output XML
   *
   * @param bool $format
   * @return self
   */
  public function withFormatting(bool $format = true): self
  {
    $this->formatOutput = $format;
    return $this;
  }

  /**
   * Get the message type this builder handles
   *
   * @return MessageType
   */
  public function getMessageType(): MessageType
  {
    return $this->messageType;
  }

  /**
   * Get the SOAP headers
   *
   * @return SoapHeaderDto
   */
  public function getSoapHeaders(): SoapHeaderDto
  {
    return $this->soapHeaders;
  }
}
