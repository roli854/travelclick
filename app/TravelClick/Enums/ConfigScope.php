<?php

namespace App\TravelClick\Enums;

/**
 * ConfigScope Enum for TravelClick Configuration
 *
 * Defines the different scopes for configuration management,
 * allowing for granular control over caching and configuration updates.
 */
enum ConfigScope: string
{
    case GLOBAL = 'global';
    case PROPERTY = 'property';
    case ENDPOINT = 'endpoint';
    case CREDENTIALS = 'credentials';
    case QUEUE = 'queue';
    case CACHE = 'cache';
    case ALL = 'all';

    /**
     * Get the display label for the scope
     */
    public function label(): string
    {
        return match ($this) {
            self::GLOBAL => 'Global Configuration',
            self::PROPERTY => 'Property Configuration',
            self::ENDPOINT => 'Endpoint Configuration',
            self::CREDENTIALS => 'Credentials Configuration',
            self::QUEUE => 'Queue Configuration',
            self::CACHE => 'Cache Configuration',
            self::ALL => 'All Configurations',
        };
    }

    /**
     * Get the cache key prefix for this scope
     */
    public function cacheKeyPrefix(): string
    {
        return match ($this) {
            self::GLOBAL => 'travelclick:config:global',
            self::PROPERTY => 'travelclick:config:property',
            self::ENDPOINT => 'travelclick:config:endpoint',
            self::CREDENTIALS => 'travelclick:config:credentials',
            self::QUEUE => 'travelclick:config:queue',
            self::CACHE => 'travelclick:config:cache',
            self::ALL => 'travelclick:config',
        };
    }

    /**
     * Get cache TTL (time to live) for this scope in seconds
     */
    public function cacheTtl(): int
    {
        return match ($this) {
            self::GLOBAL => 3600,        // 1 hour
            self::PROPERTY => 1800,      // 30 minutes
            self::ENDPOINT => 3600,      // 1 hour
            self::CREDENTIALS => 1800,   // 30 minutes
            self::QUEUE => 900,          // 15 minutes
            self::CACHE => 300,          // 5 minutes
            self::ALL => 1800,           // 30 minutes
        };
    }

    /**
     * Check if this scope requires property ID
     */
    public function requiresPropertyId(): bool
    {
        return match ($this) {
            self::PROPERTY => true,
            default => false,
        };
    }

    /**
     * Check if this scope is environment-specific
     */
    public function isEnvironmentSpecific(): bool
    {
        return match ($this) {
            self::ENDPOINT, self::CREDENTIALS => true,
            default => false,
        };
    }

    /**
     * Get configuration keys that belong to this scope
     */
    public function configKeys(): array
    {
        return match ($this) {
            self::GLOBAL => [
                'default_environment',
                'default_timeout',
                'default_retry_attempts',
                'logging_level',
            ],
            self::PROPERTY => [
                'hotel_code',
                'username',
                'password',
                'custom_settings',
                'override_global',
            ],
            self::ENDPOINT => [
                'url',
                'wsdl_url',
                'timeout',
                'ssl_verify',
            ],
            self::CREDENTIALS => [
                'username',
                'password',
                'authentication_type',
            ],
            self::QUEUE => [
                'connection',
                'queue_name',
                'retry_after',
                'max_tries',
            ],
            self::CACHE => [
                'store',
                'ttl',
                'prefix',
                'tags',
            ],
            self::ALL => [],
        };
    }

    /**
     * Get validation rules for this scope
     */
    public function validationRules(): array
    {
        return match ($this) {
            self::PROPERTY => [
                'hotel_code' => 'required|string|max:20',
                'username' => 'required|string|max:100',
                'password' => 'required|string|min:8',
                'custom_settings' => 'sometimes|array',
            ],
            self::ENDPOINT => [
                'url' => 'required|url',
                'wsdl_url' => 'required|url',
                'timeout' => 'integer|min:5|max:300',
                'ssl_verify' => 'boolean',
            ],
            self::CREDENTIALS => [
                'username' => 'required|string|max:100',
                'password' => 'required|string|min:8',
                'authentication_type' => 'string|in:basic,wsse',
            ],
            self::QUEUE => [
                'connection' => 'required|string',
                'queue_name' => 'required|string',
                'retry_after' => 'integer|min:1',
                'max_tries' => 'integer|min:1|max:10',
            ],
            default => [],
        };
    }

    /**
     * Get priority for this scope (lower number = higher priority)
     */
    public function priority(): int
    {
        return match ($this) {
            self::PROPERTY => 1,      // Highest priority - override everything
            self::CREDENTIALS => 2,   // High priority - security sensitive
            self::ENDPOINT => 3,      // Medium priority
            self::QUEUE => 4,         // Lower priority
            self::CACHE => 5,         // Lower priority
            self::GLOBAL => 6,        // Lowest priority - fallback
            self::ALL => 999,         // Special case
        };
    }

    /**
     * Check if this scope can be cached
     */
    public function isCacheable(): bool
    {
        return match ($this) {
            self::CACHE => false,  // Don't cache cache configuration itself
            default => true,
        };
    }

    /**
     * Get icon for UI representation
     */
    public function icon(): string
    {
        return match ($this) {
            self::GLOBAL => '🌍',
            self::PROPERTY => '🏨',
            self::ENDPOINT => '🔗',
            self::CREDENTIALS => '🔐',
            self::QUEUE => '📋',
            self::CACHE => '⚡',
            self::ALL => '📦',
        };
    }

    /**
     * Get color for UI representation
     */
    public function color(): string
    {
        return match ($this) {
            self::GLOBAL => '#2196F3',
            self::PROPERTY => '#4CAF50',
            self::ENDPOINT => '#FF9800',
            self::CREDENTIALS => '#F44336',
            self::QUEUE => '#9C27B0',
            self::CACHE => '#00BCD4',
            self::ALL => '#607D8B',
        };
    }

    /**
     * Get all scopes
     */
    public static function all(): array
    {
        return [
            self::GLOBAL,
            self::PROPERTY,
            self::ENDPOINT,
            self::CREDENTIALS,
            self::QUEUE,
            self::CACHE,
            self::ALL,
        ];
    }

    /**
     * Get scopes that require validation
     */
    public static function validatable(): array
    {
        return [
            self::PROPERTY,
            self::ENDPOINT,
            self::CREDENTIALS,
            self::QUEUE,
        ];
    }
}
