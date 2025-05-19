<?php

namespace App\TravelClick\DTOs;

use Carbon\Carbon;

/**
 * Specialized DTO for Reservation SOAP responses
 *
 * Extends the base SoapResponseDto to include reservation-specific data payload
 */
class ReservationResponseDto extends SoapResponseDto
{
  /**
   * @var array|null The parsed reservation data
   */
  private ?array $payload;

  /**
   * Create a new ReservationResponseDto instance
   */
  public function __construct(
    string $messageId,
    bool $isSuccess,
    string $rawResponse,
    ?array $payload = null,
    ?string $errorMessage = null,
    ?string $errorCode = null,
    ?array $warnings = null,
    ?Carbon $timestamp = null,
    ?string $echoToken = null,
    ?array $headers = null,
    ?float $durationMs = null
  ) {
    parent::__construct(
      messageId: $messageId,
      isSuccess: $isSuccess,
      rawResponse: $rawResponse,
      errorMessage: $errorMessage,
      errorCode: $errorCode,
      warnings: $warnings,
      timestamp: $timestamp,
      echoToken: $echoToken,
      headers: $headers,
      durationMs: $durationMs
    );

    $this->payload = $payload;
  }

  /**
   * Create a successful reservation response
   *
   * This method name is different from the parent to avoid LSP violation
   */
  public static function successWithPayload(
    string $messageId,
    string $rawResponse,
    array $payload,
    ?string $echoToken = null,
    ?array $headers = null,
    ?float $durationMs = null
  ): self {
    return new self(
      messageId: $messageId,
      isSuccess: true,
      rawResponse: $rawResponse,
      payload: $payload,
      echoToken: $echoToken,
      headers: $headers,
      durationMs: $durationMs,
      timestamp: Carbon::now()
    );
  }

  /**
   * Create a successful response DTO
   *
   * Implementation to maintain compatibility with parent class
   */
  public static function success(
    string $messageId,
    string $rawResponse,
    ?string $echoToken = null,
    ?array $headers = null,
    ?float $durationMs = null
  ): self {
    return new self(
      messageId: $messageId,
      isSuccess: true,
      rawResponse: $rawResponse,
      payload: null,
      echoToken: $echoToken,
      headers: $headers,
      durationMs: $durationMs,
      timestamp: Carbon::now()
    );
  }

  /**
   * Create a reservation response from base SoapResponseDto
   */
  public static function fromSoapResponse(
    SoapResponseDto $response,
    ?array $payload = null
  ): self {
    return new self(
      messageId: $response->messageId,
      isSuccess: $response->isSuccess,
      rawResponse: $response->rawResponse,
      payload: $payload,
      errorMessage: $response->errorMessage,
      errorCode: $response->errorCode,
      warnings: $response->warnings,
      timestamp: $response->timestamp,
      echoToken: $response->echoToken,
      headers: $response->headers,
      durationMs: $response->durationMs
    );
  }

  /**
   * Get the reservation payload data
   */
  public function getPayload(): ?array
  {
    return $this->payload;
  }

  /**
   * Set or update the payload data
   */
  public function withPayload(array $payload): self
  {
    return new self(
      messageId: $this->messageId,
      isSuccess: $this->isSuccess,
      rawResponse: $this->rawResponse,
      payload: $payload,
      errorMessage: $this->errorMessage,
      errorCode: $this->errorCode,
      warnings: $this->warnings,
      timestamp: $this->timestamp,
      echoToken: $this->echoToken,
      headers: $this->headers,
      durationMs: $this->durationMs
    );
  }

  /**
   * Check if payload is available
   */
  public function hasPayload(): bool
  {
    return $this->payload !== null && !empty($this->payload);
  }

  /**
   * Convert DTO to array for logging purposes
   * Extends parent method to include payload information
   */
  public function toArray(): array
  {
    $baseArray = parent::toArray();

    return array_merge($baseArray, [
      'has_payload' => $this->hasPayload(),
      'payload_fields' => $this->hasPayload() ? array_keys($this->payload) : [],
    ]);
  }
}
