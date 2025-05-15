<?php

namespace App\TravelClick\Support;

use App\TravelClick\DTOs\TravelClickConfigDto;
use App\TravelClick\DTOs\PropertyConfigDto;
use App\TravelClick\DTOs\EndpointConfigDto;
use App\TravelClick\Enums\Environment;
use App\TravelClick\Enums\ConfigScope;
use App\TravelClick\Exceptions\InvalidConfigurationException;
use Respect\Validation\Validator as v;

/**
 * Configuration Validator for TravelClick
 *
 * This class provides comprehensive validation for all TravelClick configurations
 * using business rules, format validation, and connectivity tests.
 */
class ConfigurationValidator
{
    protected array $validationErrors = [];
    protected array $validationWarnings = [];

    /**
     * Validate global TravelClick configuration
     */
    public function validateGlobalConfig(TravelClickConfigDto $config): array
    {
        $this->resetErrors();

        // Validate default environment
        if (!$this->validateEnvironment($config->defaultEnvironment)) {
            $this->addError('default_environment', 'Invalid default environment');
        }

        // Validate timeout values
        if (!$this->validateTimeout($config->defaultTimeout, 'default_timeout')) {
            $this->addError('default_timeout', 'Default timeout must be between 5 and 300 seconds');
        }

        // Validate retry configuration
        if (!$this->validateRetryAttempts($config->defaultRetryAttempts)) {
            $this->addError('default_retry_attempts', 'Retry attempts must be between 1 and 10');
        }

        if (!$this->validateBackoffSeconds($config->defaultBackoffSeconds)) {
            $this->addError('default_backoff_seconds', 'Invalid backoff configuration');
        }

        // Validate logging level
        if (!$this->validateLoggingLevel($config->loggingLevel)) {
            $this->addError('logging_level', 'Invalid logging level');
        }

        // Validate message types
        if (!$this->validateMessageTypes($config->supportedMessageTypes)) {
            $this->addError('supported_message_types', 'Invalid or empty message types');
        }

        // Validate queue configuration
        if (!$this->validateQueueConfig($config->queueConfig)) {
            $this->addError('queue', 'Invalid queue configuration');
        }

        // Validate SSL configuration
        if (!$this->validateSslConfig($config->sslConfig)) {
            $this->addError('ssl', 'Invalid SSL configuration');
        }

        return $this->getValidationResult();
    }

    /**
     * Validate property-specific configuration
     */
    public function validatePropertyConfig(PropertyConfigDto $config): array
    {
        $this->resetErrors();

        // Validate required fields
        if (!$this->validateHotelCode($config->hotelCode)) {
            $this->addError('hotel_code', 'Invalid hotel code format');
        }

        if (!$this->validateCredentials($config->username, $config->password)) {
            $this->addError('credentials', 'Invalid username or password');
        }

        if (!$this->validateEnvironment($config->environment)) {
            $this->addError('environment', 'Invalid environment');
        }

        // Validate optional overrides
        if ($config->timeout !== null && !$this->validateTimeout($config->timeout, 'timeout')) {
            $this->addError('timeout', 'Invalid timeout override');
        }

        if ($config->retryAttempts !== null && !$this->validateRetryAttempts($config->retryAttempts)) {
            $this->addError('retry_attempts', 'Invalid retry attempts override');
        }

        if ($config->backoffSeconds !== null && !$this->validateBackoffSeconds($config->backoffSeconds)) {
            $this->addError('backoff_seconds', 'Invalid backoff seconds override');
        }

        // Validate enabled message types
        if (!empty($config->enabledMessageTypes) && !$this->validateMessageTypes($config->enabledMessageTypes)) {
            $this->addError('enabled_message_types', 'Invalid enabled message types');
        }

        // Validate custom settings
        if (!$this->validateCustomSettings($config->customSettings)) {
            $this->addError('custom_settings', 'Invalid custom settings format');
        }

        return $this->getValidationResult();
    }

    /**
     * Validate endpoint configuration
     */
    public function validateEndpointConfig(EndpointConfigDto $config): array
    {
        $this->resetErrors();

        // Validate URLs
        if (!$this->validateUrl($config->url, 'endpoint_url')) {
            $this->addError('url', 'Invalid endpoint URL');
        }

        if (!$this->validateUrl($config->wsdlUrl, 'wsdl_url')) {
            $this->addError('wsdl_url', 'Invalid WSDL URL');
        }

        // Validate timeouts
        if (!$this->validateTimeout($config->connectionTimeout, 'connection_timeout', 1, 60)) {
            $this->addError('connection_timeout', 'Connection timeout must be between 1 and 60 seconds');
        }

        if (!$this->validateTimeout($config->requestTimeout, 'request_timeout', 5, 600)) {
            $this->addError('request_timeout', 'Request timeout must be between 5 and 600 seconds');
        }

        // Validate SSL settings
        if (!$this->validateSslSettings($config)) {
            $this->addError('ssl_settings', 'Invalid SSL configuration');
        }

        // Validate SOAP options
        if (!$this->validateSoapOptions($config->soapOptions)) {
            $this->addError('soap_options', 'Invalid SOAP options');
        }

        return $this->getValidationResult();
    }

    /**
     * Test endpoint connectivity
     */
    public function testEndpointConnectivity(EndpointConfigDto $config): array
    {
        $this->resetErrors();

        try {
            // Test basic connectivity
            $context = stream_context_create([
                'http' => [
                    'timeout' => min($config->connectionTimeout, 10),
                    'method' => 'HEAD',
                ],
                'ssl' => [
                    'verify_peer' => $config->sslVerifyPeer,
                    'verify_peer_name' => $config->sslVerifyHost,
                ]
            ]);

            $headers = @get_headers($config->url, 1, $context);

            if ($headers === false) {
                $this->addError('connectivity', 'Cannot connect to TravelClick endpoint');
                return $this->getValidationResult();
            }

            // Check response code
            $responseCode = $this->extractResponseCode($headers);
            if ($responseCode >= 400) {
                $this->addWarning('http_status', "HTTP {$responseCode} response from endpoint");
            }

            // Test WSDL accessibility
            $wsdlHeaders = @get_headers($config->wsdlUrl, 1, $context);
            if ($wsdlHeaders === false) {
                $this->addError('wsdl_connectivity', 'Cannot access WSDL URL');
            } else {
                $wsdlResponseCode = $this->extractResponseCode($wsdlHeaders);
                if ($wsdlResponseCode >= 400) {
                    $this->addError('wsdl_status', "WSDL returns HTTP {$wsdlResponseCode}");
                }
            }
        } catch (\Exception $e) {
            $this->addError('connectivity_exception', 'Connection test failed: ' . $e->getMessage());
        }

        return $this->getValidationResult();
    }

    /**
     * Perform comprehensive configuration validation
     */
    public function validateComplete(
        TravelClickConfigDto $globalConfig,
        ?PropertyConfigDto $propertyConfig = null,
        ?EndpointConfigDto $endpointConfig = null
    ): array {
        $results = [];

        // Validate global config
        $results['global'] = $this->validateGlobalConfig($globalConfig);

        // Validate property config if provided
        if ($propertyConfig) {
            $results['property'] = $this->validatePropertyConfig($propertyConfig);

            // Cross-validate property against global
            $crossValidation = $this->crossValidatePropertyWithGlobal($propertyConfig, $globalConfig);
            if (!empty($crossValidation['errors'])) {
                $results['cross_validation'] = $crossValidation;
            }
        }

        // Validate endpoint config if provided
        if ($endpointConfig) {
            $results['endpoint'] = $this->validateEndpointConfig($endpointConfig);
            $results['connectivity'] = $this->testEndpointConnectivity($endpointConfig);
        }

        return $results;
    }

    /**
     * Validate hotel code format
     */
    protected function validateHotelCode(string $hotelCode): bool
    {
        return v::stringType()
            ->length(1, 20)
            ->alnum()
            ->validate($hotelCode);
    }

    /**
     * Validate credentials
     */
    protected function validateCredentials(string $username, string $password): bool
    {
        $usernameValid = v::stringType()
            ->notEmpty()
            ->length(1, 100)
            ->validate($username);

        $passwordValid = v::stringType()
            ->notEmpty()
            ->length(8, null)
            ->validate($password);

        return $usernameValid && $passwordValid;
    }

    /**
     * Validate environment
     */
    protected function validateEnvironment(Environment $environment): bool
    {
        return in_array($environment, Environment::all());
    }

    /**
     * Validate timeout value
     */
    protected function validateTimeout(int $timeout, string $type, int $min = 5, int $max = 300): bool
    {
        return v::intVal()
            ->min($min)
            ->max($max)
            ->validate($timeout);
    }

    /**
     * Validate retry attempts
     */
    protected function validateRetryAttempts(int $attempts): bool
    {
        return v::intVal()
            ->min(1)
            ->max(10)
            ->validate($attempts);
    }

    /**
     * Validate backoff seconds array
     */
    protected function validateBackoffSeconds(array $backoffSeconds): bool
    {
        if (empty($backoffSeconds)) {
            return false;
        }

        foreach ($backoffSeconds as $seconds) {
            if (!v::intVal()->min(1)->max(300)->validate($seconds)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate logging level
     */
    protected function validateLoggingLevel(string $level): bool
    {
        $validLevels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
        return in_array(strtolower($level), $validLevels);
    }

    /**
     * Validate message types
     */
    protected function validateMessageTypes(array $messageTypes): bool
    {
        if (empty($messageTypes)) {
            return false;
        }

        $validTypes = ['inventory', 'rates', 'reservations', 'group_blocks'];

        foreach ($messageTypes as $type) {
            if (!in_array($type, $validTypes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate queue configuration
     */
    protected function validateQueueConfig(array $queueConfig): bool
    {
        $required = ['connection', 'queue', 'retry_after'];

        foreach ($required as $key) {
            if (!isset($queueConfig[$key])) {
                return false;
            }
        }

        // Validate retry_after is numeric
        if (!is_numeric($queueConfig['retry_after'])) {
            return false;
        }

        return true;
    }

    /**
     * Validate SSL configuration
     */
    protected function validateSslConfig(array $sslConfig): bool
    {
        // SSL config is optional, but if provided should have valid boolean values
        $booleanFields = ['verify_peer', 'verify_host'];

        foreach ($booleanFields as $field) {
            if (isset($sslConfig[$field]) && !is_bool($sslConfig[$field])) {
                return false;
            }
        }

        // If cafile is specified, it should be readable
        if (isset($sslConfig['cafile']) && $sslConfig['cafile'] !== null) {
            if (!is_readable($sslConfig['cafile'])) {
                $this->addWarning('ssl_cafile', 'SSL CA file is not readable');
            }
        }

        return true;
    }

    /**
     * Validate URL format
     */
    protected function validateUrl(string $url, string $type): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Ensure HTTPS for production endpoints
        if (strpos($url, 'https://') !== 0) {
            $this->addWarning($type, 'Endpoint should use HTTPS');
        }

        return true;
    }

    /**
     * Validate SSL settings for endpoint
     */
    protected function validateSslSettings(EndpointConfigDto $config): bool
    {
        // For production, SSL verification should be enabled
        if ($config->environment->isProduction()) {
            if (!$config->sslVerifyPeer || !$config->sslVerifyHost) {
                $this->addWarning('ssl_production', 'SSL verification should be enabled in production');
            }
        }

        return true;
    }

    /**
     * Validate SOAP options
     */
    protected function validateSoapOptions(array $soapOptions): bool
    {
        // Check for incompatible or deprecated options
        $deprecated = ['login', 'password', 'proxy_host', 'proxy_port'];

        foreach ($deprecated as $option) {
            if (isset($soapOptions[$option])) {
                $this->addWarning('soap_options', "Deprecated SOAP option: {$option}");
            }
        }

        return true;
    }

    /**
     * Validate custom settings format
     */
    protected function validateCustomSettings(array $customSettings): bool
    {
        // Custom settings should be a valid associative array
        return is_array($customSettings);
    }

    /**
     * Cross-validate property config with global config
     */
    protected function crossValidatePropertyWithGlobal(
        PropertyConfigDto $property,
        TravelClickConfigDto $global
    ): array {
        $this->resetErrors();

        // Check environment compatibility
        if ($property->environment !== $global->defaultEnvironment) {
            $this->addWarning(
                'environment_mismatch',
                "Property environment ({$property->environment->value}) differs from global default ({$global->defaultEnvironment->value})"
            );
        }

        // Check if enabled message types are subset of supported types
        if (!empty($property->enabledMessageTypes)) {
            $unsupported = array_diff($property->enabledMessageTypes, $global->supportedMessageTypes);
            if (!empty($unsupported)) {
                $this->addError(
                    'unsupported_message_types',
                    'Property enables unsupported message types: ' . implode(', ', $unsupported)
                );
            }
        }

        // Validate timeout overrides make sense
        if ($property->timeout !== null && $property->timeout > $global->defaultTimeout * 2) {
            $this->addWarning(
                'timeout_override',
                'Property timeout significantly higher than global default'
            );
        }

        return $this->getValidationResult();
    }

    /**
     * Extract HTTP response code from headers
     */
    protected function extractResponseCode($headers): int
    {
        if (is_array($headers) && isset($headers[0])) {
            if (preg_match('/\s(\d{3})\s/', $headers[0], $matches)) {
                return (int) $matches[1];
            }
        }
        return 0;
    }

    /**
     * Reset validation errors and warnings
     */
    protected function resetErrors(): void
    {
        $this->validationErrors = [];
        $this->validationWarnings = [];
    }

    /**
     * Add validation error
     */
    protected function addError(string $field, string $message): void
    {
        $this->validationErrors[$field] = $message;
    }

    /**
     * Add validation warning
     */
    protected function addWarning(string $field, string $message): void
    {
        $this->validationWarnings[$field] = $message;
    }

    /**
     * Get validation result
     */
    protected function getValidationResult(): array
    {
        return [
            'valid' => empty($this->validationErrors),
            'errors' => $this->validationErrors,
            'warnings' => $this->validationWarnings,
            'error_count' => count($this->validationErrors),
            'warning_count' => count($this->validationWarnings)
        ];
    }

    /**
     * Generate validation report
     */
    public function generateReport(array $validationResults): string
    {
        $report = "=== TravelClick Configuration Validation Report ===\n\n";

        foreach ($validationResults as $scope => $result) {
            $report .= "## {$scope} Configuration\n";
            $report .= "Status: " . ($result['valid'] ? "✓ VALID" : "✗ INVALID") . "\n";

            if (!empty($result['errors'])) {
                $report .= "Errors:\n";
                foreach ($result['errors'] as $field => $message) {
                    $report .= "  - {$field}: {$message}\n";
                }
            }

            if (!empty($result['warnings'])) {
                $report .= "Warnings:\n";
                foreach ($result['warnings'] as $field => $message) {
                    $report .= "  - {$field}: {$message}\n";
                }
            }

            $report .= "\n";
        }

        return $report;
    }
}
