<?php

namespace App\TravelClick\Enums;

/**
 * ErrorType Enum for TravelClick Integration
 *
 * Categorizes different types of errors that can occur during TravelClick integration.
 * This helps with error handling, logging, and automated recovery procedures.
 *
 * Think of this as a filing system for problems - each type goes in its own drawer.
 */
enum ErrorType: string
{
/**
     * Connection errors - Can't reach TravelClick services
     */
    case CONNECTION = 'connection';

/**
     * Authentication errors - Wrong credentials or expired tokens
     */
    case AUTHENTICATION = 'authentication';

/**
     * Validation errors - Data doesn't match required format/rules
     */
    case VALIDATION = 'validation';

/**
     * SOAP/XML errors - Invalid XML structure or SOAP faults
     */
    case SOAP_XML = 'soap_xml';

/**
     * Business logic errors - Data conflicts, inventory issues, etc.
     */
    case BUSINESS_LOGIC = 'business_logic';

/**
     * Rate limiting - Too many requests to TravelClick
     */
    case RATE_LIMIT = 'rate_limit';

/**
     * Timeout errors - Request took too long
     */
    case TIMEOUT = 'timeout';

/**
     * Configuration errors - Wrong settings, missing config, etc.
     */
    case CONFIGURATION = 'configuration';

/**
     * Data mapping errors - Problems converting between systems
     */
    case DATA_MAPPING = 'data_mapping';

/**
     * Unknown/unexpected errors
     */
    case UNKNOWN = 'unknown';

    /**
     * Get human-readable description
     */
    public function description(): string
    {
        return match ($this) {
            self::CONNECTION => 'Unable to connect to TravelClick service',
            self::AUTHENTICATION => 'Authentication failed with TravelClick',
            self::VALIDATION => 'Data validation error',
            self::SOAP_XML => 'SOAP/XML processing error',
            self::BUSINESS_LOGIC => 'Business rule violation',
            self::RATE_LIMIT => 'Too many requests sent',
            self::TIMEOUT => 'Request timeout',
            self::CONFIGURATION => 'Configuration error',
            self::DATA_MAPPING => 'Data mapping/conversion error',
            self::UNKNOWN => 'Unknown error occurred',
        };
    }

    /**
     * Check if this error type can be automatically retried
     */
    public function canRetry(): bool
    {
        return match ($this) {
            self::CONNECTION => true,      // Network issues are often temporary
            self::TIMEOUT => true,         // Timeouts can be retried
            self::RATE_LIMIT => true,      // Can retry after delay
            self::SOAP_XML => false,       // XML errors won't fix themselves
            self::VALIDATION => false,     // Validation errors need data fixes
            self::AUTHENTICATION => false, // Need credential update
            self::CONFIGURATION => false,  // Need config change
            self::BUSINESS_LOGIC => false, // Need business rule review
            self::DATA_MAPPING => false,   // Need mapping fix
            self::UNKNOWN => true,         // Give unknown errors a chance
        };
    }

    /**
     * Get retry delay in seconds for this error type
     */
    public function getRetryDelay(): int
    {
        return match ($this) {
            self::CONNECTION => 30,        // Quick retry for connection
            self::TIMEOUT => 60,           // Wait a bit for timeout
            self::RATE_LIMIT => 300,       // Wait 5 minutes for rate limit
            self::UNKNOWN => 120,          // Wait 2 minutes for unknown
            default => 0,                  // No delay for non-retryable
        };
    }

    /**
     * Get severity level (1 = critical, 2 = high, 3 = medium, 4 = low)
     */
    public function getSeverity(): int
    {
        return match ($this) {
            self::AUTHENTICATION => 1,     // Critical - service unusable
            self::CONFIGURATION => 1,      // Critical - service unusable
            self::CONNECTION => 2,         // High - affects operations
            self::BUSINESS_LOGIC => 2,     // High - data integrity
            self::SOAP_XML => 3,           // Medium - specific request
            self::VALIDATION => 3,         // Medium - specific data
            self::DATA_MAPPING => 3,       // Medium - specific conversion
            self::TIMEOUT => 4,            // Low - often temporary
            self::RATE_LIMIT => 4,         // Low - temporary throttling
            self::UNKNOWN => 3,            // Medium - needs investigation
        };
    }

    /**
     * Check if this error requires immediate attention
     */
    public function requiresImmediateAttention(): bool
    {
        return $this->getSeverity() <= 2;
    }

    /**
     * Get notification type for this error
     */
    public function getNotificationType(): string
    {
        return match ($this->getSeverity()) {
            1 => 'critical',
            2 => 'urgent',
            3 => 'warning',
            4 => 'info',
        };
    }

    /**
     * Get icon for UI display
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::CONNECTION => 'wifi-off',
            self::AUTHENTICATION => 'lock',
            self::VALIDATION => 'alert-circle',
            self::SOAP_XML => 'code',
            self::BUSINESS_LOGIC => 'briefcase',
            self::RATE_LIMIT => 'clock',
            self::TIMEOUT => 'timer',
            self::CONFIGURATION => 'settings',
            self::DATA_MAPPING => 'shuffle',
            self::UNKNOWN => 'help-circle',
        };
    }

    /**
     * Get color for UI display
     */
    public function getColor(): string
    {
        return match ($this->getSeverity()) {
            1 => 'red',    // Critical
            2 => 'orange', // High
            3 => 'yellow', // Medium
            4 => 'blue',   // Low
        };
    }

    /**
     * Map from exception type to error category
     */
    public static function fromException(\Throwable $exception): self
    {
        $message = strtolower($exception->getMessage());
        $class = get_class($exception);

        // Check exception type first
        if (str_contains($class, 'SoapFault') || str_contains($class, 'XmlException')) {
            return self::SOAP_XML;
        }

        if (str_contains($class, 'ConnectionException') || str_contains($class, 'ConnectException')) {
            return self::CONNECTION;
        }

        if (str_contains($class, 'TimeoutException')) {
            return self::TIMEOUT;
        }

        if (str_contains($class, 'ValidationException')) {
            return self::VALIDATION;
        }

        // Check message content
        if (str_contains($message, 'authentication') || str_contains($message, 'unauthorized')) {
            return self::AUTHENTICATION;
        }

        if (str_contains($message, 'timeout')) {
            return self::TIMEOUT;
        }

        if (str_contains($message, 'rate limit') || str_contains($message, 'too many requests')) {
            return self::RATE_LIMIT;
        }

        if (str_contains($message, 'validation') || str_contains($message, 'invalid')) {
            return self::VALIDATION;
        }

        if (str_contains($message, 'connection')) {
            return self::CONNECTION;
        }

        return self::UNKNOWN;
    }

    /**
     * Get all error types that are considered critical
     */
    public static function criticalTypes(): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->getSeverity() <= 2
        );
    }

    /**
     * Get all error types that can be retried
     */
    public static function retryableTypes(): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->canRetry()
        );
    }
}
