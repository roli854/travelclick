<?php

namespace App\TravelClick\DTOs;

use App\TravelClick\Enums\Environment;
use App\TravelClick\Enums\ConfigScope;
use Carbon\Carbon;

/**
 * TravelClick Global Configuration DTO
 *
 * This DTO encapsulates all global TravelClick configuration data.
 * It provides structured access to system-wide settings and defaults.
 */
class TravelClickConfigDto
{
    public function __construct(
        public readonly Environment $defaultEnvironment,
        public readonly int $defaultTimeout,
        public readonly int $defaultRetryAttempts,
        public readonly array $defaultBackoffSeconds,
        public readonly string $loggingLevel,
        public readonly bool $enableCache,
        public readonly int $defaultCacheTtl,
        public readonly array $supportedMessageTypes,
        public readonly array $queueConfig,
        public readonly array $sslConfig,
        public readonly array $customHeaders,
        public readonly bool $debug,
        public readonly ?Carbon $lastUpdated = null,
        public readonly ?string $version = null
    ) {}

    /**
     * Create from configuration array
     */
    public static function fromArray(array $config): self
    {
        return new self(
            defaultEnvironment: Environment::from($config['default_environment'] ?? 'testing'),
            defaultTimeout: $config['default_timeout'] ?? 30,
            defaultRetryAttempts: $config['default_retry_attempts'] ?? 3,
            defaultBackoffSeconds: $config['default_backoff_seconds'] ?? [10, 30, 60],
            loggingLevel: $config['logging_level'] ?? 'info',
            enableCache: $config['enable_cache'] ?? true,
            defaultCacheTtl: $config['default_cache_ttl'] ?? 3600,
            supportedMessageTypes: $config['supported_message_types'] ?? [
                'inventory',
                'rates',
                'reservations',
                'group_blocks'
            ],
            queueConfig: $config['queue'] ?? [
                'connection' => 'redis',
                'queue' => 'travelclick',
                'retry_after' => 180
            ],
            sslConfig: $config['ssl'] ?? [
                'verify_peer' => true,
                'verify_host' => true,
                'cafile' => null
            ],
            customHeaders: $config['custom_headers'] ?? [],
            debug: $config['debug'] ?? false,
            lastUpdated: isset($config['last_updated'])
                ? Carbon::parse($config['last_updated'])
                : null,
            version: $config['version'] ?? null
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'default_environment' => $this->defaultEnvironment->value,
            'default_timeout' => $this->defaultTimeout,
            'default_retry_attempts' => $this->defaultRetryAttempts,
            'default_backoff_seconds' => $this->defaultBackoffSeconds,
            'logging_level' => $this->loggingLevel,
            'enable_cache' => $this->enableCache,
            'default_cache_ttl' => $this->defaultCacheTtl,
            'supported_message_types' => $this->supportedMessageTypes,
            'queue' => $this->queueConfig,
            'ssl' => $this->sslConfig,
            'custom_headers' => $this->customHeaders,
            'debug' => $this->debug,
            'last_updated' => $this->lastUpdated?->toISOString(),
            'version' => $this->version
        ];
    }

    /**
     * Create from config file
     */
    public static function fromConfig(): self
    {
        $config = config('travelclick', []);
        return self::fromArray($config);
    }

    /**
     * Get timeout for specific operation
     */
    public function getTimeoutForOperation(string $operation): int
    {
        $operationTimeouts = [
            'inventory' => $this->defaultTimeout,
            'rates' => $this->defaultTimeout,
            'reservations' => $this->defaultTimeout + 10,  // Reservations may take longer
            'group_blocks' => $this->defaultTimeout + 15,  // Group operations are complex
        ];

        return $operationTimeouts[$operation] ?? $this->defaultTimeout;
    }

    /**
     * Get retry attempts for specific operation
     */
    public function getRetryAttemptsForOperation(string $operation): int
    {
        $operationRetries = [
            'inventory' => $this->defaultRetryAttempts,
            'rates' => $this->defaultRetryAttempts,
            'reservations' => max(1, $this->defaultRetryAttempts - 1),  // Less retries for reservations
            'group_blocks' => $this->defaultRetryAttempts + 1,  // More retries for group operations
        ];

        return $operationRetries[$operation] ?? $this->defaultRetryAttempts;
    }

    /**
     * Check if message type is supported
     */
    public function isMessageTypeSupported(string $messageType): bool
    {
        return in_array($messageType, $this->supportedMessageTypes);
    }

    /**
     * Get queue name for operation
     */
    public function getQueueForOperation(string $operation): string
    {
        $operationQueues = [
            'inventory' => $this->queueConfig['queue'] . '-inventory',
            'rates' => $this->queueConfig['queue'] . '-rates',
            'reservations' => $this->queueConfig['queue'] . '-reservations',
            'group_blocks' => $this->queueConfig['queue'] . '-groups',
        ];

        return $operationQueues[$operation] ?? $this->queueConfig['queue'];
    }

    /**
     * Check if configuration is valid
     */
    public function isValid(): bool
    {
        return $this->defaultTimeout > 0
            && $this->defaultRetryAttempts > 0
            && count($this->defaultBackoffSeconds) > 0
            && !empty($this->loggingLevel)
            && !empty($this->supportedMessageTypes);
    }

    /**
     * Get cache key for this configuration
     */
    public function getCacheKey(): string
    {
        return ConfigScope::GLOBAL->cacheKeyPrefix();
    }

    /**
     * Get cache TTL for this configuration
     */
    public function getCacheTtl(): int
    {
        return $this->enableCache ? $this->defaultCacheTtl : 0;
    }

    /**
     * Merge with another configuration (other takes precedence)
     */
    public function mergeWith(self $other): self
    {
        return new self(
            defaultEnvironment: $other->defaultEnvironment,
            defaultTimeout: $other->defaultTimeout,
            defaultRetryAttempts: $other->defaultRetryAttempts,
            defaultBackoffSeconds: $other->defaultBackoffSeconds,
            loggingLevel: $other->loggingLevel,
            enableCache: $other->enableCache,
            defaultCacheTtl: $other->defaultCacheTtl,
            supportedMessageTypes: array_unique(array_merge(
                $this->supportedMessageTypes,
                $other->supportedMessageTypes
            )),
            queueConfig: array_merge($this->queueConfig, $other->queueConfig),
            sslConfig: array_merge($this->sslConfig, $other->sslConfig),
            customHeaders: array_merge($this->customHeaders, $other->customHeaders),
            debug: $other->debug,
            lastUpdated: $other->lastUpdated ?? $this->lastUpdated,
            version: $other->version ?? $this->version
        );
    }

    /**
     * Create a copy with updated values
     */
    public function with(array $updates): self
    {
        $current = $this->toArray();
        $merged = array_merge($current, $updates);
        return self::fromArray($merged);
    }
}
