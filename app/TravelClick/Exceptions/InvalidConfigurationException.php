<?php

namespace App\TravelClick\Exceptions;

use App\TravelClick\Enums\ConfigScope;

/**
 * Exception for invalid configuration values in TravelClick integration
 *
 * This exception is thrown when configuration values don't meet
 * the required format, type, or business rules.
 */
class InvalidConfigurationException extends ConfigurationException
{
    protected array $invalidFields;
    protected array $validationRules;

    public function __construct(
        string $message = '',
        array $invalidFields = [],
        array $validationRules = [],
        int $code = 0,
        ?\Throwable $previous = null,
        ConfigScope $scope = ConfigScope::ALL,
        ?int $propertyId = null,
        array $context = []
    ) {
        $this->invalidFields = $invalidFields;
        $this->validationRules = $validationRules;

        // Generate detailed message if not provided
        if (empty($message) && !empty($invalidFields)) {
            $message = $this->generateDetailedMessage();
        }

        parent::__construct(
            message: $message,
            code: $code ?: 2001,
            previous: $previous,
            scope: $scope,
            propertyId: $propertyId,
            context: array_merge($context, [
                'invalid_fields' => $invalidFields,
                'validation_rules' => $validationRules
            ]),
            suggestions: $this->generateSuggestions()
        );
    }

    /**
     * Get invalid fields
     */
    public function getInvalidFields(): array
    {
        return $this->invalidFields;
    }

    /**
     * Get validation rules
     */
    public function getValidationRules(): array
    {
        return $this->validationRules;
    }

    /**
     * Create exception for invalid hotel code
     */
    public static function invalidHotelCode(
        string $hotelCode,
        ?int $propertyId = null
    ): self {
        return new self(
            message: "Invalid hotel code: '{$hotelCode}'. Must be alphanumeric, 1-20 characters.",
            invalidFields: ['hotel_code' => $hotelCode],
            validationRules: ['hotel_code' => 'required|string|alpha_num|max:20'],
            code: 2001,
            scope: ConfigScope::PROPERTY,
            propertyId: $propertyId,
            context: ['field' => 'hotel_code', 'value' => $hotelCode]
        );
    }

    /**
     * Create exception for invalid credentials
     */
    public static function invalidCredentials(
        ?string $username = null,
        ?string $password = null,
        ?int $propertyId = null
    ): self {
        $invalidFields = [];
        $message = "Invalid credentials";

        if ($username !== null && empty($username)) {
            $invalidFields['username'] = $username;
            $message .= " - username is required";
        }

        if ($password !== null && strlen($password) < 8) {
            $invalidFields['password'] = '[HIDDEN]';
            $message .= " - password must be at least 8 characters";
        }

        return new self(
            message: $message,
            invalidFields: $invalidFields,
            validationRules: [
                'username' => 'required|string|max:100',
                'password' => 'required|string|min:8'
            ],
            code: 2002,
            scope: ConfigScope::CREDENTIALS,
            propertyId: $propertyId
        );
    }

    /**
     * Create exception for invalid timeout values
     */
    public static function invalidTimeout(
        int $timeout,
        string $type = 'connection',
        ?int $propertyId = null
    ): self {
        $minTimeout = $type === 'connection' ? 1 : 5;
        $maxTimeout = $type === 'connection' ? 60 : 300;

        return new self(
            message: "Invalid {$type} timeout: {$timeout}. Must be between {$minTimeout} and {$maxTimeout} seconds.",
            invalidFields: ["{$type}_timeout" => $timeout],
            validationRules: ["{$type}_timeout" => "integer|min:{$minTimeout}|max:{$maxTimeout}"],
            code: 2003,
            scope: ConfigScope::ENDPOINT,
            propertyId: $propertyId,
            context: [
                'timeout_type' => $type,
                'min_allowed' => $minTimeout,
                'max_allowed' => $maxTimeout
            ]
        );
    }

    /**
     * Create exception for invalid retry configuration
     */
    public static function invalidRetryConfig(
        ?int $attempts = null,
        ?array $backoffSeconds = null,
        ?int $propertyId = null
    ): self {
        $invalidFields = [];
        $validationRules = [];
        $messages = [];

        if ($attempts !== null && ($attempts < 1 || $attempts > 10)) {
            $invalidFields['retry_attempts'] = $attempts;
            $validationRules['retry_attempts'] = 'integer|min:1|max:10';
            $messages[] = "Retry attempts must be between 1 and 10";
        }

        if ($backoffSeconds !== null) {
            if (!is_array($backoffSeconds) || empty($backoffSeconds)) {
                $invalidFields['backoff_seconds'] = $backoffSeconds;
                $validationRules['backoff_seconds'] = 'array|min:1';
                $messages[] = "Backoff seconds must be a non-empty array";
            } else {
                foreach ($backoffSeconds as $index => $seconds) {
                    if (!is_int($seconds) || $seconds < 1 || $seconds > 300) {
                        $invalidFields["backoff_seconds.{$index}"] = $seconds;
                        $validationRules["backoff_seconds.*"] = 'integer|min:1|max:300';
                        $messages[] = "Each backoff value must be between 1 and 300 seconds";
                        break;
                    }
                }
            }
        }

        return new self(
            message: "Invalid retry configuration: " . implode(', ', $messages),
            invalidFields: $invalidFields,
            validationRules: $validationRules,
            code: 2004,
            scope: ConfigScope::GLOBAL,
            propertyId: $propertyId
        );
    }

    /**
     * Create exception for invalid environment configuration
     */
    public static function invalidEnvironment(
        string $environment,
        ?int $propertyId = null
    ): self {
        $validEnvironments = ['production', 'testing', 'staging', 'development'];

        return new self(
            message: "Invalid environment: '{$environment}'. Must be one of: " . implode(', ', $validEnvironments),
            invalidFields: ['environment' => $environment],
            validationRules: ['environment' => 'required|in:' . implode(',', $validEnvironments)],
            code: 2005,
            scope: ConfigScope::GLOBAL,
            propertyId: $propertyId,
            context: [
                'valid_environments' => $validEnvironments,
                'provided_environment' => $environment
            ]
        );
    }

    /**
     * Create exception for invalid message types
     */
    public static function invalidMessageTypes(
        array $messageTypes,
        ?int $propertyId = null
    ): self {
        $validTypes = ['inventory', 'rates', 'reservations', 'group_blocks'];
        $invalidTypes = array_diff($messageTypes, $validTypes);

        return new self(
            message: "Invalid message types: " . implode(', ', $invalidTypes) .
                ". Valid types: " . implode(', ', $validTypes),
            invalidFields: ['message_types' => $invalidTypes],
            validationRules: ['message_types.*' => 'in:' . implode(',', $validTypes)],
            code: 2006,
            scope: ConfigScope::PROPERTY,
            propertyId: $propertyId,
            context: [
                'valid_message_types' => $validTypes,
                'invalid_message_types' => $invalidTypes
            ]
        );
    }

    /**
     * Create exception for invalid endpoint URL
     */
    public static function invalidEndpoint(
        string $url,
        string $type = 'endpoint',
        ?int $propertyId = null
    ): self {
        return new self(
            message: "Invalid {$type} URL: '{$url}'. Must be a valid HTTPS URL.",
            invalidFields: ["{$type}_url" => $url],
            validationRules: ["{$type}_url" => 'required|url|regex:/^https:/'],
            code: 2007,
            scope: ConfigScope::ENDPOINT,
            propertyId: $propertyId,
            context: [
                'url_type' => $type,
                'url' => $url
            ]
        );
    }

    /**
     * Create exception for multiple field validation errors
     */
    public static function multipleFields(
        array $errors,
        ConfigScope $scope = ConfigScope::ALL,
        ?int $propertyId = null
    ): self {
        $invalidFields = [];
        $validationRules = [];

        foreach ($errors as $field => $error) {
            if (is_array($error)) {
                $invalidFields[$field] = $error['value'] ?? null;
                $validationRules[$field] = $error['rule'] ?? '';
            } else {
                $invalidFields[$field] = null;
                $validationRules[$field] = $error;
            }
        }

        $fieldCount = count($invalidFields);
        $message = "Validation failed for {$fieldCount} field" . ($fieldCount > 1 ? 's' : '') .
            ": " . implode(', ', array_keys($invalidFields));

        return new self(
            message: $message,
            invalidFields: $invalidFields,
            validationRules: $validationRules,
            code: 2008,
            scope: $scope,
            propertyId: $propertyId
        );
    }

    /**
     * Generate detailed message from invalid fields
     */
    protected function generateDetailedMessage(): string
    {
        if (empty($this->invalidFields)) {
            return 'Configuration validation failed';
        }

        $fieldNames = array_keys($this->invalidFields);
        $fieldCount = count($fieldNames);

        if ($fieldCount === 1) {
            return "Invalid configuration for field: {$fieldNames[0]}";
        }

        return "Invalid configuration for {$fieldCount} fields: " . implode(', ', $fieldNames);
    }

    /**
     * Generate suggestions based on invalid fields
     */
    protected function generateSuggestions(): array
    {
        $suggestions = [
            'Check configuration documentation for field requirements',
            'Validate configuration using: php artisan travelclick:validate-config',
        ];

        // Add field-specific suggestions
        foreach (array_keys($this->invalidFields) as $field) {
            $fieldSuggestions = $this->getFieldSuggestions($field);
            $suggestions = array_merge($suggestions, $fieldSuggestions);
        }

        return array_unique($suggestions);
    }

    /**
     * Get suggestions for specific fields
     */
    protected function getFieldSuggestions(string $field): array
    {
        return match ($field) {
            'hotel_code' => [
                'Ensure hotel code contains only letters and numbers',
                'Hotel code should not exceed 20 characters',
                'Check with TravelClick for the correct hotel code format'
            ],
            'username', 'password' => [
                'Verify credentials with TravelClick support',
                'Ensure environment variables are correctly set',
                'Test credentials in TravelClick test environment first'
            ],
            'connection_timeout', 'request_timeout' => [
                'Use reasonable timeout values for your environment',
                'Production systems typically use 30-60 second timeouts',
                'Test environments can use shorter timeouts (10-30 seconds)'
            ],
            'retry_attempts' => [
                'Use 3-5 retry attempts for production',
                'Fewer retries (1-2) for development/testing',
                'Consider the impact of retries on system performance'
            ],
            'backoff_seconds' => [
                'Use exponential backoff pattern: [5, 15, 30, 60]',
                'Each value should be progressively longer',
                'Maximum individual backoff should not exceed 300 seconds'
            ],
            'environment' => [
                'Use "production" for live environment',
                'Use "testing" for TravelClick test environment',
                'Ensure APP_ENV matches TravelClick environment'
            ],
            default => []
        };
    }

    /**
     * Get validation errors grouped by field
     */
    public function getGroupedErrors(): array
    {
        $grouped = [];

        foreach ($this->invalidFields as $field => $value) {
            $rule = $this->validationRules[$field] ?? '';
            $suggestions = $this->getFieldSuggestions($field);

            $grouped[$field] = [
                'value' => $value,
                'rule' => $rule,
                'suggestions' => $suggestions
            ];
        }

        return $grouped;
    }

    /**
     * Check if specific field is invalid
     */
    public function hasInvalidField(string $field): bool
    {
        return array_key_exists($field, $this->invalidFields);
    }

    /**
     * Get invalid value for specific field
     */
    public function getInvalidValue(string $field): mixed
    {
        return $this->invalidFields[$field] ?? null;
    }

    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        $base = parent::toArray();

        return array_merge($base, [
            'error' => 'InvalidConfigurationException',
            'invalid_fields' => $this->invalidFields,
            'validation_rules' => $this->validationRules,
            'grouped_errors' => $this->getGroupedErrors(),
            'field_count' => count($this->invalidFields)
        ]);
    }
}
