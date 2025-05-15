<?php

namespace App\TravelClick\Enums;

/**
 * Environment Enum for TravelClick Integration
 *
 * Defines the different environments available for TravelClick operations.
 * Each environment has specific endpoints, credentials, and behavior.
 */
enum Environment: string
{
    case PRODUCTION = 'production';
    case TESTING = 'testing';
    case STAGING = 'staging';
    case DEVELOPMENT = 'development';
    case LOCAL = 'local'; // For local development

    /**
     * Get the display label for the environment
     */
    public function label(): string
    {
        return match ($this) {
            self::PRODUCTION => 'Production',
            self::TESTING => 'Testing',
            self::STAGING => 'Staging',
            self::DEVELOPMENT => 'Development',
            self::LOCAL => 'Local',
        };
    }

    /**
     * Get the endpoint URL for this environment
     */
    public function endpoint(): string
    {
        return match ($this) {
            self::PRODUCTION => 'https://pms.ihotelier.com/HTNGService/services/HTNG2011BService',
            self::TESTING => 'https://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService',
            self::LOCAL => 'https://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService',
            self::STAGING => 'https://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService',
            self::DEVELOPMENT => 'https://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService',
        };
    }

    /**
     * Get WSDL URL for this environment
     */
    public function wsdlUrl(): string
    {
        return $this->endpoint() . '?wsdl';
    }

    /**
     * Check if this is a production environment
     */
    public function isProduction(): bool
    {
        return $this === self::PRODUCTION;
    }

    /**
     * Check if this is a test environment (testing, staging, development)
     */
    public function isTest(): bool
    {
        return !$this->isProduction();
    }

    /**
     * Get timeout settings for this environment
     */
    public function timeouts(): array
    {
        return match ($this) {
            self::PRODUCTION => [
                'connection_timeout' => 30,
                'request_timeout' => 60,
            ],
            self::TESTING, self::STAGING => [
                'connection_timeout' => 20,
                'request_timeout' => 45,
            ],
            self::DEVELOPMENT => [
                'connection_timeout' => 10,
                'request_timeout' => 30,
            ],
            self::LOCAL => [
                'connection_timeout' => 10,
                'request_timeout' => 30,
            ],
        };
    }

    /**
     * Get retry policy for this environment
     */
    public function retryPolicy(): array
    {
        return match ($this) {
            self::PRODUCTION => [
                'max_attempts' => 3,
                'backoff_seconds' => [10, 30, 60],
            ],
            self::TESTING, self::STAGING => [
                'max_attempts' => 2,
                'backoff_seconds' => [5, 15],
            ],
            self::DEVELOPMENT => [
                'max_attempts' => 1,
                'backoff_seconds' => [5],
            ],
            self::LOCAL => [
                'max_attempts' => 1,
                'backoff_seconds' => [5],
            ],
        };
    }

    /**
     * Get debug level for this environment
     */
    public function debugLevel(): string
    {
        return match ($this) {
            self::PRODUCTION => 'error',
            self::TESTING, self::STAGING, self::LOCAL => 'warning',
            self::DEVELOPMENT => 'debug',
        };
    }

    /**
     * Get all environments
     */
    public static function all(): array
    {
        return [
            self::PRODUCTION,
            self::TESTING,
            self::STAGING,
            self::DEVELOPMENT,
            self::LOCAL,
        ];
    }

    /**
     * Get environment from current app environment
     */
    public static function fromApp(): self
    {
        $appEnv = config('app.env');

        return match ($appEnv) {
            'production' => self::PRODUCTION,
            'staging' => self::STAGING,
            'testing' => self::TESTING,
            'development' => self::DEVELOPMENT,
            'local' => self::LOCAL,
            default => self::DEVELOPMENT,
        };
    }

    /**
     * Get color for UI representation
     */
    public function color(): string
    {
        return match ($this) {
            self::PRODUCTION => '#FF0000',
            self::TESTING => '#FFA500',
            self::STAGING => '#FFFF00',
            self::DEVELOPMENT, self::LOCAL => '#00FF00',
        };
    }

    /**
     * Get icon for UI representation
     */
    public function icon(): string
    {
        return match ($this) {
            self::PRODUCTION => '🔴',
            self::TESTING => '🟡',
            self::STAGING => '🟠',
            self::DEVELOPMENT, self::LOCAL => '🟢',
        };
    }
}
