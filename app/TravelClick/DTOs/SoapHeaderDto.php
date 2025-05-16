<?php

declare(strict_types=1);

namespace App\TravelClick\DTOs;

use Ramsey\Uuid\Uuid;

/**
 * Data Transfer Object for SOAP headers required by HTNG 2011B interface
 *
 * This DTO encapsulates all the standard SOAP headers needed for communicating
 * with TravelClick's PMS Connect service, including authentication and addressing.
 */
readonly class SoapHeaderDto
{
  public function __construct(
    public string $messageId,
    public string $to,
    public string $replyTo,
    public string $action,
    public string $from,
    public string $hotelCode,
    public string $username,
    public string $password,
    public ?string $timeStamp = null,
    public ?string $echoToken = null,
  ) {}

  /**
   * Create a SoapHeaderDto instance with common defaults for TravelClick
   *
   * @param string $action The SOAP action being performed
   * @param string $hotelCode The hotel code for this property
   * @param string $username Authentication username
   * @param string $password Authentication password
   * @param string|null $endpoint Custom endpoint override
   * @param string|null $replyToEndpoint Custom reply-to endpoint
   * @return self
   */
  public static function create(
    string $action,
    string $hotelCode,
    string $username,
    string $password,
    ?string $endpoint = null,
    ?string $replyToEndpoint = null
  ): self {
    $config = config('travelclick');

    // Use provided endpoint or fall back to config
    $to = $endpoint ?? $config['endpoints']['production'];
    $replyTo = $replyToEndpoint ?? $config['endpoints']['production'];

    return new self(
      messageId: self::generateMessageId(),
      to: $to,
      replyTo: $replyTo,
      action: self::buildAction($action),
      from: $to, // From is typically the same as To in HTNG
      hotelCode: $hotelCode,
      username: $username,
      password: $password,
      timeStamp: self::generateTimeStamp(),
      echoToken: self::generateEchoToken(),
    );
  }

  /**
   * Create SoapHeaderDto for inventory operations
   *
   * @param string $hotelCode
   * @param string $username
   * @param string $password
   * @return self
   */
  public static function forInventory(
    string $hotelCode,
    string $username,
    string $password
  ): self {
    return self::create(
      action: 'HTNG2011B_SubmitRequest',
      hotelCode: $hotelCode,
      username: $username,
      password: $password
    );
  }

  /**
   * Create SoapHeaderDto for rate operations
   *
   * @param string $hotelCode
   * @param string $username
   * @param string $password
   * @return self
   */
  public static function forRates(
    string $hotelCode,
    string $username,
    string $password
  ): self {
    return self::create(
      action: 'HTNG2011B_SubmitRequest',
      hotelCode: $hotelCode,
      username: $username,
      password: $password
    );
  }

  /**
   * Create SoapHeaderDto for reservation operations
   *
   * @param string $hotelCode
   * @param string $username
   * @param string $password
   * @return self
   */
  public static function forReservation(
    string $hotelCode,
    string $username,
    string $password
  ): self {
    return self::create(
      action: 'HTNG2011B_SubmitRequest',
      hotelCode: $hotelCode,
      username: $username,
      password: $password
    );
  }

  /**
   * Create SoapHeaderDto for group operations
   *
   * @param string $hotelCode
   * @param string $username
   * @param string $password
   * @return self
   */
  public static function forGroup(
    string $hotelCode,
    string $username,
    string $password
  ): self {
    return self::create(
      action: 'HTNG2011B_SubmitRequest',
      hotelCode: $hotelCode,
      username: $username,
      password: $password
    );
  }

  /**
   * Generate SOAP headers array for use with SOAP client
   *
   * @return array<string, mixed>
   */
  public function toSoapHeaders(): array
  {
    return [
      'MessageID' => $this->messageId,
      'To' => $this->to,
      'ReplyTo' => [
        'Address' => $this->replyTo
      ],
      'Action' => $this->action,
      'From' => [
        'ReferenceProperties' => [
          'HotelCode' => $this->hotelCode
        ]
      ],
      'Security' => [
        'UsernameToken' => [
          'Username' => $this->username,
          'Password' => $this->password
        ]
      ]
    ];
  }

  /**
   * Generate headers array with proper namespace prefixes
   *
   * @return array<string, mixed>
   */
  public function toNamespacedHeaders(): array
  {
    return [
      'wsa:MessageID' => $this->messageId,
      'wsa:To' => $this->to,
      'wsa:ReplyTo' => [
        'wsa:Address' => $this->replyTo
      ],
      'wsa:Action' => $this->action,
      'wsa:From' => [
        'wsa:ReferenceProperties' => [
          'htn:HotelCode' => $this->hotelCode
        ]
      ],
      'wsse:Security' => [
        'wsse:UsernameToken' => [
          'wsse:Username' => $this->username,
          'wsse:Password' => $this->password
        ]
      ]
    ];
  }

  /**
   * Generate a unique message ID for this SOAP call
   *
   * @return string
   */
  private static function generateMessageId(): string
  {
    return Uuid::uuid4()->toString();
  }

  /**
   * Generate the current timestamp in the required format
   *
   * @return string
   */
  private static function generateTimeStamp(): string
  {
    return now()->toISOString();
  }

  /**
   * Generate a unique echo token for tracking responses
   *
   * @return string
   */
  private static function generateEchoToken(): string
  {
    return str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
  }

  /**
   * Build the full SOAP action URL
   *
   * @param string $action The action name
   * @return string
   */
  private static function buildAction(string $action): string
  {
    $baseUrl = config('travelclick.endpoints.production');
    return "{$baseUrl}/{$action}";
  }

  /**
   * Get headers in array format suitable for XML building
   *
   * @return array<string, mixed>
   */
  public function toArray(): array
  {
    return [
      'message_id' => $this->messageId,
      'to' => $this->to,
      'reply_to' => $this->replyTo,
      'action' => $this->action,
      'from' => $this->from,
      'hotel_code' => $this->hotelCode,
      'username' => $this->username,
      'password' => $this->password,
      'time_stamp' => $this->timeStamp,
      'echo_token' => $this->echoToken,
    ];
  }

  /**
   * Validate that all required fields are present and valid
   *
   * @return bool
   * @throws \InvalidArgumentException If validation fails
   */
  public function validate(): bool
  {
    $errors = [];

    if (empty($this->messageId)) {
      $errors[] = 'Message ID is required';
    }

    if (empty($this->to)) {
      $errors[] = 'To endpoint is required';
    }

    if (empty($this->hotelCode)) {
      $errors[] = 'Hotel code is required';
    }

    if (empty($this->username)) {
      $errors[] = 'Username is required';
    }

    if (empty($this->password)) {
      $errors[] = 'Password is required';
    }

    if (!empty($errors)) {
      throw new \InvalidArgumentException(
        'SOAP header validation failed: ' . implode(', ', $errors)
      );
    }

    return true;
  }

  /**
   * Create SoapHeaderDto from configuration
   *
   * @param string $action
   * @param array<string, mixed>|null $overrides
   * @return self
   */
  public static function fromConfig(string $action, ?array $overrides = null): self
  {
    $config = config('travelclick');
    $credentials = $config['credentials'];

    $defaults = [
      'hotelCode' => $credentials['hotel_code'],
      'username' => $credentials['username'],
      'password' => $credentials['password'],
    ];

    $params = array_merge($defaults, $overrides ?? []);

    return self::create(
      action: $action,
      hotelCode: $params['hotelCode'],
      username: $params['username'],
      password: $params['password'],
      endpoint: $overrides['endpoint'] ?? null,
      replyToEndpoint: $overrides['replyToEndpoint'] ?? null
    );
  }
}
