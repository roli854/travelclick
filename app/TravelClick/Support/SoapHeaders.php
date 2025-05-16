<?php

namespace App\TravelClick\Support;

use DateTime;
use DateTimeZone;
use InvalidArgumentException;

/**
 * SoapHeaders - Manages WSSE authentication headers for TravelClick integration
 *
 * This class generates WS-Security headers required by TravelClick's HTNG 2011B interface.
 * It implements UsernameToken authentication with timestamp and nonce for security.
 *
 * Based on TravelClick HTNG 2011B specification requirements:
 * - MessageID for tracking
 * - WS-Addressing headers (To, From, Action, ReplyTo)
 * - WS-Security headers with UsernameToken
 * - Timestamp and Nonce for replay attack prevention
 *
 * @package App\TravelClick\Support
 */
class SoapHeaders
{
  private string $username;
  private string $password;
  private string $hotelCode;
  private string $endpoint;

  // Namespace constants
  private const WSA_NAMESPACE = 'http://www.w3.org/2005/08/addressing';
  private const WSSE_NAMESPACE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
  private const WSSU_NAMESPACE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
  private const HTN_NAMESPACE = 'http://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService';

  // Action endpoints for different operations
  private const ACTION_SUBMIT = 'HTNG2011B_SubmitRequest';

  /**
   * Constructor
   *
   * @param string $username TravelClick username
   * @param string $password TravelClick password
   * @param string $hotelCode Hotel identification code
   * @param string $endpoint TravelClick endpoint URL
   */
  public function __construct(string $username, string $password, string $hotelCode, string $endpoint)
  {
    $this->validateCredentials($username, $password, $hotelCode);

    $this->username = $username;
    $this->password = $password;
    $this->hotelCode = $hotelCode;
    $this->endpoint = $endpoint;
  }

  /**
   * Create complete SOAP headers for TravelClick request
   *
   * @param string $messageId Unique message identifier
   * @param string $action SOAP action (defaults to HTNG2011B_SubmitRequest)
   * @return string Complete XML headers string
   */
  public function createHeaders(string $messageId, string $action = self::ACTION_SUBMIT): string
  {
    return sprintf(
      '<wsa:MessageID>%s</wsa:MessageID>%s%s%s%s%s',
      $this->escapeXml($messageId),
      $this->buildToHeader(),
      $this->buildReplyToHeader(),
      $this->buildActionHeader($action),
      $this->buildSecurityHeader(),
      $this->buildFromHeader()
    );
  }

  /**
   * Create headers from Laravel configuration
   *
   * @param array $config TravelClick configuration array
   * @param string $messageId Unique message identifier
   * @param string $action SOAP action
   * @return string Complete XML headers string
   * @throws InvalidArgumentException If configuration is invalid
   */
  public static function fromConfig(array $config, string $messageId, string $action = self::ACTION_SUBMIT): string
  {
    $username = $config['credentials']['username'] ?? null;
    $password = $config['credentials']['password'] ?? null;
    $hotelCode = $config['credentials']['hotel_code'] ?? null;

    // Determine endpoint based on environment
    $endpoint = self::determineEndpoint($config);

    if (!$username || !$password || !$hotelCode) {
      throw new InvalidArgumentException('TravelClick credentials (username, password, hotel_code) are required');
    }

    $headerManager = new self($username, $password, $hotelCode, $endpoint);
    return $headerManager->createHeaders($messageId, $action);
  }

  /**
   * Create headers with environment auto-detection
   *
   * @param string $messageId Unique message identifier
   * @param string $action SOAP action
   * @return string Complete XML headers string
   */
  public static function create(string $messageId, string $action = self::ACTION_SUBMIT): string
  {
    $config = config('travelclick');
    return self::fromConfig($config, $messageId, $action);
  }

  /**
   * Build WS-Addressing To header
   */
  private function buildToHeader(): string
  {
    return sprintf(
      '<wsa:To>%s</wsa:To>',
      $this->escapeXml($this->endpoint)
    );
  }

  /**
   * Build WS-Addressing ReplyTo header
   */
  private function buildReplyToHeader(): string
  {
    return '<wsa:ReplyTo><wsa:Address>http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous</wsa:Address></wsa:ReplyTo>';
  }

  /**
   * Build WS-Addressing Action header
   *
   * @param string $action SOAP action name
   */
  private function buildActionHeader(string $action): string
  {
    $fullAction = $this->endpoint . '/' . $action;
    return sprintf(
      '<wsa:Action>%s</wsa:Action>',
      $this->escapeXml($fullAction)
    );
  }

  /**
   * Build WS-Security header with UsernameToken
   */
  private function buildSecurityHeader(): string
  {
    $timestamp = $this->generateTimestamp();
    $nonce = $this->generateNonce();

    return sprintf(
      '<wsse:Security xmlns:wsse="%s" xmlns:wsu="%s" SOAP-ENV:mustUnderstand="1">
                <wsu:Timestamp wsu:Id="Timestamp-1">
                    <wsu:Created>%s</wsu:Created>
                    <wsu:Expires>%s</wsu:Expires>
                </wsu:Timestamp>
                <wsse:UsernameToken wsu:Id="UsernameToken-1">
                    <wsse:Username>%s</wsse:Username>
                    <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">%s</wsse:Password>
                    <wsse:Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">%s</wsse:Nonce>
                    <wsu:Created>%s</wsu:Created>
                </wsse:UsernameToken>
            </wsse:Security>',
      self::WSSE_NAMESPACE,
      self::WSSU_NAMESPACE,
      $timestamp['created'],
      $timestamp['expires'],
      $this->escapeXml($this->username),
      $this->escapeXml($this->password),
      $nonce,
      $timestamp['created']
    );
  }

  /**
   * Build WS-Addressing From header with hotel code reference
   */
  private function buildFromHeader(): string
  {
    return sprintf(
      '<wsa:From>
                <wsa:ReferenceProperties>
                    <htn:HotelCode xmlns:htn="%s">%s</htn:HotelCode>
                </wsa:ReferenceProperties>
            </wsa:From>',
      self::HTN_NAMESPACE,
      $this->escapeXml($this->hotelCode)
    );
  }

  /**
   * Generate timestamp for security headers
   *
   * @return array Contains 'created' and 'expires' timestamps
   */
  private function generateTimestamp(): array
  {
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $expires = clone $now;
    $expires->modify('+5 minutes'); // Standard 5-minute validity window

    return [
      'created' => $now->format('Y-m-d\TH:i:s.v\Z'),
      'expires' => $expires->format('Y-m-d\TH:i:s.v\Z')
    ];
  }

  /**
   * Generate cryptographically secure nonce
   *
   * @return string Base64 encoded nonce
   */
  private function generateNonce(): string
  {
    return base64_encode(random_bytes(16));
  }

  /**
   * Determine appropriate endpoint from configuration
   *
   * @param array $config Configuration array
   * @return string Endpoint URL
   */
  private static function determineEndpoint(array $config): string
  {
    $environment = app()->environment();

    // Check if we're in production
    if ($environment === 'production') {
      return $config['endpoints']['production'] ?? $config['endpoints']['url'] ?? '';
    }

    // Default to test endpoint for all non-production environments
    return $config['endpoints']['test'] ?? $config['endpoints']['url'] ?? '';
  }

  /**
   * Validate required credentials
   *
   * @param string $username
   * @param string $password
   * @param string $hotelCode
   * @throws InvalidArgumentException
   */
  private function validateCredentials(string $username, string $password, string $hotelCode): void
  {
    if (empty($username)) {
      throw new InvalidArgumentException('TravelClick username cannot be empty');
    }

    if (empty($password)) {
      throw new InvalidArgumentException('TravelClick password cannot be empty');
    }

    if (empty($hotelCode)) {
      throw new InvalidArgumentException('Hotel code cannot be empty');
    }

    // Validate hotel code format (alphanumeric, 1-10 characters)
    if (!preg_match('/^[A-Za-z0-9]{1,10}$/', $hotelCode)) {
      throw new InvalidArgumentException('Hotel code must be alphanumeric and 1-10 characters long');
    }
  }

  /**
   * Safely escape XML special characters
   *
   * @param string $value Value to escape
   * @return string Escaped value
   */
  private function escapeXml(string $value): string
  {
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
  }

  /**
   * Generate message ID with timestamp and unique suffix
   *
   * @param string $prefix Message prefix (e.g., 'INV', 'RATE', 'RES')
   * @return string Unique message ID
   */
  public static function generateMessageId(string $prefix = 'MSG'): string
  {
    return sprintf(
      '%s_%s_%s_%s',
      strtoupper($prefix),
      date('Ymd_His'),
      substr(uniqid(), -6),
      random_int(1000, 9999)
    );
  }

  /**
   * Create headers for specific operation types with validation
   *
   * @param string $operationType Type of operation (inventory, rates, reservation, etc.)
   * @param string|null $messageId Optional message ID (will be generated if null)
   * @return array [headers, messageId] tuple
   */
  public static function forOperation(string $operationType, ?string $messageId = null): array
  {
    $messageId = $messageId ?? self::generateMessageId(strtoupper(substr($operationType, 0, 3)));

    $prefixMap = [
      'inventory' => 'INV',
      'rates' => 'RATE',
      'reservation' => 'RES',
      'restrictions' => 'REST',
      'groups' => 'GRP',
    ];

    if (isset($prefixMap[$operationType])) {
      $messageId = self::generateMessageId($prefixMap[$operationType]);
    }

    $headers = self::create($messageId);

    return [$headers, $messageId];
  }

  /**
   * Create headers with custom namespace declarations
   *
   * @param string $messageId Unique message identifier
   * @param array $customNamespaces Additional namespaces to declare
   * @param string $action SOAP action
   * @return string Complete XML headers string
   */
  public function createHeadersWithNamespaces(
    string $messageId,
    array $customNamespaces = [],
    string $action = self::ACTION_SUBMIT
  ): string {
    $baseHeaders = $this->createHeaders($messageId, $action);

    if (empty($customNamespaces)) {
      return $baseHeaders;
    }

    // Add custom namespace declarations
    $namespaceDeclarations = '';
    foreach ($customNamespaces as $prefix => $uri) {
      $namespaceDeclarations .= sprintf(' xmlns:%s="%s"', $prefix, $this->escapeXml($uri));
    }

    // Insert namespace declarations at the beginning if needed
    return $baseHeaders . $namespaceDeclarations;
  }

  /**
   * Validate headers format for debugging
   *
   * @param string $headers Headers XML string
   * @return bool True if valid XML structure
   */
  public static function validateHeaders(string $headers): bool
  {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string("<headers>{$headers}</headers>");
    $errors = libxml_get_errors();
    libxml_clear_errors();

    return empty($errors) && $xml !== false;
  }
}
