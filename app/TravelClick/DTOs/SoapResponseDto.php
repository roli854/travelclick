<?php

namespace App\TravelClick\DTOs;

use Carbon\Carbon;

/**
 * Data Transfer Object for SOAP Response
 *
 * This DTO encapsulates the response received from TravelClick SOAP calls.
 * It provides structured access to response data and metadata.
 */
class SoapResponseDto
{
    public function __construct(
        public readonly string $messageId,
        public readonly bool $isSuccess,
        public readonly string $rawResponse,
        public readonly ?string $errorMessage = null,
        public readonly ?string $errorCode = null,
        public readonly ?array $warnings = null,
        public readonly ?Carbon $timestamp = null,
        public readonly ?string $echoToken = null,
        public readonly ?array $headers = null,
        public readonly ?float $durationMs = null
    ) {}

    /**
     * Create a successful response DTO
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
            echoToken: $echoToken,
            headers: $headers,
            durationMs: $durationMs,
            timestamp: Carbon::now()
        );
    }

    /**
     * Create a failed response DTO
     */
    public static function failure(
        string $messageId,
        string $rawResponse,
        string $errorMessage,
        ?string $errorCode = null,
        ?array $warnings = null,
        ?float $durationMs = null
    ): self {
        return new self(
            messageId: $messageId,
            isSuccess: false,
            rawResponse: $rawResponse,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
            warnings: $warnings,
            durationMs: $durationMs,
            timestamp: Carbon::now()
        );
    }

    /**
     * Create response from SoapFault
     */
    public static function fromSoapFault(
        string $messageId,
        \SoapFault $fault,
        ?float $durationMs = null
    ): self {
        return new self(
            messageId: $messageId,
            isSuccess: false,
            rawResponse: $fault->getMessage(),
            errorMessage: $fault->getMessage(),
            errorCode: $fault->getCode(),
            durationMs: $durationMs,
            timestamp: Carbon::now()
        );
    }

    /**
     * Check if response contains warnings
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Get warnings as a formatted string
     */
    public function getWarningsAsString(): string
    {
        if (!$this->hasWarnings()) {
            return '';
        }

        return implode('; ', $this->warnings);
    }

    /**
     * Get formatted duration for logging
     */
    public function getFormattedDuration(): string
    {
        if ($this->durationMs === null) {
            return 'N/A';
        }

        if ($this->durationMs < 1000) {
            return number_format($this->durationMs, 2) . 'ms';
        }

        return number_format($this->durationMs / 1000, 2) . 's';
    }

    /**
     * Convert DTO to array for logging purposes
     */
    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'is_success' => $this->isSuccess,
            'error_message' => $this->errorMessage,
            'error_code' => $this->errorCode,
            'has_warnings' => $this->hasWarnings(),
            'warnings_count' => $this->warnings ? count($this->warnings) : 0,
            'echo_token' => $this->echoToken,
            'duration_ms' => $this->durationMs,
            'timestamp' => $this->timestamp?->toISOString(),
            'response_size_bytes' => strlen($this->rawResponse),
        ];
    }

    /**
     * Get log context for detailed logging
     */
    public function getLogContext(): array
    {
        return [
            'soap_response' => $this->toArray(),
            'duration' => $this->getFormattedDuration(),
            'status' => $this->isSuccess ? 'success' : 'failure',
        ];
    }
}
