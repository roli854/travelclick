<?php

namespace App\TravelClick\Exceptions;

/**
 * Exception thrown when there are connection issues with TravelClick
 *
 * This exception is thrown specifically for network-related issues,
 * timeouts, and other connection problems with the TravelClick service.
 */
class TravelClickConnectionException extends SoapException
{
    public function __construct(
        string $message,
        ?string $messageId = null,
        public readonly ?int $timeoutSeconds = null,
        public readonly ?string $endpoint = null,
        ?\Throwable $previous = null
    ) {
        $enhancedMessage = $this->enhanceMessage($message);

        parent::__construct(
            message: $enhancedMessage,
            messageId: $messageId,
            context: [
                'timeout_seconds' => $this->timeoutSeconds,
                'endpoint' => $this->endpoint,
                'connection_error' => true,
            ],
            previous: $previous
        );
    }

    /**
     * Create exception for connection timeout
     */
    public static function timeout(
        int $timeoutSeconds,
        string $endpoint,
        ?string $messageId = null
    ): self {
        return new self(
            message: "Connection to TravelClick timed out after {$timeoutSeconds} seconds",
            messageId: $messageId,
            timeoutSeconds: $timeoutSeconds,
            endpoint: $endpoint
        );
    }

    /**
     * Create exception for network unreachable
     */
    public static function unreachable(
        string $endpoint,
        ?string $messageId = null,
        ?string $details = null
    ): self {
        $message = "TravelClick endpoint unreachable: {$endpoint}";
        if ($details) {
            $message .= " - {$details}";
        }

        return new self(
            message: $message,
            messageId: $messageId,
            endpoint: $endpoint
        );
    }

    /**
     * Create exception for SSL/TLS issues
     */
    public static function sslError(
        string $sslError,
        string $endpoint,
        ?string $messageId = null
    ): self {
        return new self(
            message: "SSL/TLS error connecting to TravelClick: {$sslError}",
            messageId: $messageId,
            endpoint: $endpoint,
            previous: null
        );
    }

    /**
     * Enhance error message with connection-specific context
     */
    private function enhanceMessage(string $message): string
    {
        $enhanced = "TravelClick Connection Error: {$message}";

        if ($this->endpoint) {
            $enhanced .= " (Endpoint: {$this->endpoint})";
        }

        if ($this->timeoutSeconds) {
            $enhanced .= " (Timeout: {$this->timeoutSeconds}s)";
        }

        return $enhanced;
    }

    /**
     * Check if error is retryable
     */
    public function isRetryable(): bool
    {
        // Connection errors are generally retryable
        return true;
    }

    /**
     * Get suggested retry delay in seconds
     */
    public function getSuggestedRetryDelay(): int
    {
        // For connection errors, start with a longer delay
        return 30;
    }

    /**
     * Get troubleshooting suggestions
     */
    public function getTroubleshootingSteps(): array
    {
        return [
            'Verify TravelClick service status',
            'Check network connectivity',
            'Verify firewall settings',
            'Check SSL certificate validity',
            'Verify endpoint URL configuration',
            'Consider increasing timeout values',
        ];
    }
}
