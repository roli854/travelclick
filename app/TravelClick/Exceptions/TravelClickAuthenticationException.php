<?php

namespace App\TravelClick\Exceptions;

/**
 * Exception thrown when authentication with TravelClick fails
 *
 * This exception is thrown specifically for authentication issues,
 * including invalid credentials, expired tokens, or authorization failures.
 */
class TravelClickAuthenticationException extends SoapException
{
    public function __construct(
        string $message,
        ?string $messageId = null,
        public readonly ?string $username = null,
        public readonly ?string $authenticationMethod = 'WSSE',
        public readonly ?string $faultDetail = null,
        ?\Throwable $previous = null
    ) {
        $enhancedMessage = $this->enhanceMessage($message);

        parent::__construct(
            message: $enhancedMessage,
            messageId: $messageId,
            context: [
                'username' => $this->username,
                'authentication_method' => $this->authenticationMethod,
                'fault_detail' => $this->faultDetail,
                'authentication_error' => true,
            ],
            previous: $previous
        );
    }

    /**
     * Create exception for invalid credentials
     */
    public static function invalidCredentials(
        string $username,
        ?string $messageId = null,
        ?string $details = null
    ): self {
        $message = "Invalid credentials for user: {$username}";
        if ($details) {
            $message .= " - {$details}";
        }

        return new self(
            message: $message,
            messageId: $messageId,
            username: $username,
            faultDetail: $details
        );
    }

    /**
     * Create exception for expired credentials
     */
    public static function expiredCredentials(
        string $username,
        ?string $messageId = null
    ): self {
        return new self(
            message: "Credentials have expired for user: {$username}",
            messageId: $messageId,
            username: $username,
            faultDetail: 'Credentials expired'
        );
    }

    /**
     * Create exception for insufficient permissions
     */
    public static function insufficientPermissions(
        string $username,
        string $requiredPermission,
        ?string $messageId = null
    ): self {
        return new self(
            message: "User {$username} lacks required permission: {$requiredPermission}",
            messageId: $messageId,
            username: $username,
            faultDetail: "Missing permission: {$requiredPermission}"
        );
    }

    /**
     * Create exception for authentication service unavailable
     */
    public static function serviceUnavailable(
        ?string $messageId = null,
        ?string $details = null
    ): self {
        $message = "TravelClick authentication service unavailable";
        if ($details) {
            $message .= " - {$details}";
        }

        return new self(
            message: $message,
            messageId: $messageId,
            faultDetail: $details
        );
    }

    /**
     * Enhance error message with authentication-specific context
     */
    private function enhanceMessage(string $message): string
    {
        $enhanced = "TravelClick Authentication Error: {$message}";

        if ($this->username) {
            $enhanced .= " (User: {$this->username})";
        }

        if ($this->authenticationMethod) {
            $enhanced .= " (Method: {$this->authenticationMethod})";
        }

        return $enhanced;
    }

    /**
     * Check if error is retryable
     */
    public function isRetryable(): bool
    {
        // Most authentication errors are not retryable without manual intervention
        // Exception: service unavailable errors can be retried
        return str_contains($this->getMessage(), 'service unavailable') ||
            str_contains($this->getMessage(), 'temporary');
    }

    /**
     * Get suggested retry delay in seconds
     */
    public function getSuggestedRetryDelay(): int
    {
        // Authentication errors need longer delays if retryable at all
        return 60;
    }

    /**
     * Get troubleshooting steps specific to authentication
     */
    public function getTroubleshootingSteps(): array
    {
        return [
            'Verify username and password in configuration',
            'Check if credentials have expired',
            'Verify user has required permissions in TravelClick',
            'Check if account is locked or suspended',
            'Confirm authentication method (WSSE) is correct',
            'Test credentials using TravelClick web interface',
            'Contact TravelClick support if issue persists',
        ];
    }

    /**
     * Get credential validation recommendations
     */
    public function getCredentialValidationSteps(): array
    {
        return [
            'Test credentials manually through TravelClick portal',
            'Verify username format and special characters',
            'Check password complexity requirements',
            'Ensure credentials are for the correct environment (test/production)',
            'Verify account has API access permissions',
        ];
    }

    /**
     * Security note for logging
     */
    public function getSecurityNote(): string
    {
        return 'Note: Credentials should never be logged in plain text. ' .
            'This exception only logs username and authentication method.';
    }
}
