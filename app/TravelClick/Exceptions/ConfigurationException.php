<?php

namespace App\TravelClick\Exceptions;

use Exception;
use App\TravelClick\Enums\ConfigScope;

/**
 * Base exception for configuration-related errors in TravelClick integration
 *
 * This exception is thrown when configuration issues are encountered,
 * providing context about what went wrong and how to fix it.
 */
class ConfigurationException extends Exception
{
    protected ConfigScope $scope;
    protected ?int $propertyId;
    protected array $context;
    protected array $suggestions;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ConfigScope $scope = ConfigScope::ALL,
        ?int $propertyId = null,
        array $context = [],
        array $suggestions = []
    ) {
        parent::__construct($message, $code, $previous);

        $this->scope = $scope;
        $this->propertyId = $propertyId;
        $this->context = $context;
        $this->suggestions = $suggestions;
    }

    /**
     * Get the configuration scope this error relates to
     */
    public function getScope(): ConfigScope
    {
        return $this->scope;
    }

    /**
     * Get the property ID if this is a property-specific error
     */
    public function getPropertyId(): ?int
    {
        return $this->propertyId;
    }

    /**
     * Get additional context about the error
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get suggestions for resolving the error
     */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    /**
     * Create exception for missing configuration
     */
    public static function missing(
        string $configKey,
        ConfigScope $scope = ConfigScope::ALL,
        ?int $propertyId = null
    ): self {
        $message = "Missing configuration: {$configKey}";

        if ($propertyId && $scope === ConfigScope::PROPERTY) {
            $message .= " for property {$propertyId}";
        }

        $suggestions = [
            "Check if {$configKey} is defined in the configuration file",
            "Verify environment variables are properly set",
            "Run 'php artisan travelclick:validate-config' for detailed validation"
        ];

        if ($scope === ConfigScope::PROPERTY) {
            $suggestions[] = "Check property-specific configuration in database";
            $suggestions[] = "Verify property is properly configured for TravelClick";
        }

        return new self(
            message: $message,
            code: 1001,
            scope: $scope,
            propertyId: $propertyId,
            context: ['config_key' => $configKey],
            suggestions: $suggestions
        );
    }

    /**
     * Create exception for invalid configuration value
     */
    public static function invalid(
        string $configKey,
        mixed $value,
        string $expectedType = null,
        ConfigScope $scope = ConfigScope::ALL,
        ?int $propertyId = null
    ): self {
        $message = "Invalid configuration value for {$configKey}";

        if ($expectedType) {
            $message .= ". Expected {$expectedType}";
        }

        $suggestions = [
            "Check the value format for {$configKey}",
            "Refer to configuration documentation for valid values",
            "Use configuration validation command to check all settings"
        ];

        return new self(
            message: $message,
            code: 1002,
            scope: $scope,
            propertyId: $propertyId,
            context: [
                'config_key' => $configKey,
                'invalid_value' => $value,
                'expected_type' => $expectedType
            ],
            suggestions: $suggestions
        );
    }

    /**
     * Create exception for cache-related configuration errors
     */
    public static function cache(
        string $operation,
        string $reason = '',
        ?int $propertyId = null
    ): self {
        $message = "Configuration cache error during {$operation}";

        if ($reason) {
            $message .= ": {$reason}";
        }

        return new self(
            message: $message,
            code: 1003,
            scope: ConfigScope::CACHE,
            propertyId: $propertyId,
            context: [
                'operation' => $operation,
                'reason' => $reason
            ],
            suggestions: [
                'Check cache connection and permissions',
                'Verify cache configuration in config/cache.php',
                'Try clearing cache with: php artisan travelclick:cache --clear'
            ]
        );
    }

    /**
     * Create exception for property not found
     */
    public static function propertyNotFound(int $propertyId): self
    {
        return new self(
            message: "Property configuration not found for property ID: {$propertyId}",
            code: 1004,
            scope: ConfigScope::PROPERTY,
            propertyId: $propertyId,
            context: ['property_id' => $propertyId],
            suggestions: [
                'Verify property ID is correct',
                'Check if property is configured for TravelClick integration',
                'Run property setup if this is a new property',
                'Check database connectivity and permissions'
            ]
        );
    }

    /**
     * Create exception for environment mismatch
     */
    public static function environmentMismatch(
        string $expected,
        string $actual,
        ?int $propertyId = null
    ): self {
        $message = "Environment mismatch. Expected: {$expected}, Got: {$actual}";

        return new self(
            message: $message,
            code: 1005,
            scope: ConfigScope::GLOBAL,
            propertyId: $propertyId,
            context: [
                'expected_environment' => $expected,
                'actual_environment' => $actual,
                'property_id' => $propertyId
            ],
            suggestions: [
                'Check APP_ENV in your .env file',
                'Verify TravelClick environment configuration',
                'Ensure property is configured for correct environment'
            ]
        );
    }

    /**
     * Create exception for validation failure
     */
    public static function validationFailed(
        array $errors,
        ConfigScope $scope = ConfigScope::ALL,
        ?int $propertyId = null
    ): self {
        $message = "Configuration validation failed";

        if (count($errors) === 1) {
            $message .= ": " . $errors[0];
        } else {
            $message .= " with " . count($errors) . " errors";
        }

        return new self(
            message: $message,
            code: 1006,
            scope: $scope,
            propertyId: $propertyId,
            context: [
                'validation_errors' => $errors,
                'error_count' => count($errors)
            ],
            suggestions: [
                'Review and correct the configuration errors',
                'Use php artisan travelclick:validate-config for details',
                'Check configuration documentation for requirements'
            ]
        );
    }

    /**
     * Get detailed error information for logging
     */
    public function getDetailedInfo(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'scope' => $this->scope->value,
            'property_id' => $this->propertyId,
            'context' => $this->context,
            'suggestions' => $this->suggestions,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString()
        ];
    }

    /**
     * Get user-friendly error message
     */
    public function getUserMessage(): string
    {
        $baseMessage = $this->getMessage();

        if ($this->propertyId && $this->scope === ConfigScope::PROPERTY) {
            $baseMessage .= " (Property ID: {$this->propertyId})";
        }

        return $baseMessage;
    }

    /**
     * Check if error is recoverable
     */
    public function isRecoverable(): bool
    {
        return match ($this->getCode()) {
            1001, 1002, 1006 => true,  // Missing/invalid config, validation errors
            1003 => true,              // Cache errors (can retry)
            1004 => false,             // Property not found (needs setup)
            1005 => false,             // Environment mismatch (needs admin)
            default => true
        };
    }

    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        return [
            'error' => 'ConfigurationException',
            'message' => $this->getUserMessage(),
            'code' => $this->getCode(),
            'scope' => $this->scope->value,
            'scope_label' => $this->scope->label(),
            'property_id' => $this->propertyId,
            'recoverable' => $this->isRecoverable(),
            'suggestions' => $this->suggestions,
            'context' => $this->context
        ];
    }
}
