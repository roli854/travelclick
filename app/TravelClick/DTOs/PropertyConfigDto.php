<?php

namespace App\TravelClick\DTOs;

use App\TravelClick\Enums\Environment;
use Carbon\Carbon;

/**
 * Property Configuration DTO
 *
 * This DTO encapsulates property-specific TravelClick configuration data.
 * It provides structured access to hotel-specific settings and overrides.
 */
class PropertyConfigDto
{
    public function __construct(
        public readonly int $propertyId,
        public readonly string $hotelCode,
        public readonly string $propertyName,
        public readonly Environment $environment,
        public readonly string $username,
        public readonly string $password,
        public readonly ?int $timeout = null,
        public readonly ?int $retryAttempts = null,
        public readonly ?array $backoffSeconds = null,
        public readonly array $enabledMessageTypes = [],
        public readonly array $customSettings = [],
        public readonly bool $overrideGlobal = false,
        public readonly bool $isActive = true,
        public readonly array $queueOverrides = [],
        public readonly array $endpointOverrides = [],
        public readonly ?Carbon $lastSyncDate = null,
        public readonly ?Carbon $lastUpdated = null,
        public readonly ?string $notes = null
    ) {}

    /**
     * Create from array data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            propertyId: $data['property_id'],
            hotelCode: $data['hotel_code'],
            propertyName: $data['property_name'] ?? '',
            environment: Environment::from($data['environment'] ?? 'testing'),
            username: $data['username'],
            password: $data['password'],
            timeout: $data['timeout'] ?? null,
            retryAttempts: $data['retry_attempts'] ?? null,
            backoffSeconds: $data['backoff_seconds'] ?? null,
            enabledMessageTypes: $data['enabled_message_types'] ?? [],
            customSettings: $data['custom_settings'] ?? [],
            overrideGlobal: $data['override_global'] ?? false,
            isActive: $data['is_active'] ?? true,
            queueOverrides: $data['queue_overrides'] ?? [],
            endpointOverrides: $data['endpoint_overrides'] ?? [],
            lastSyncDate: isset($data['last_sync_date'])
                ? Carbon::parse($data['last_sync_date'])
                : null,
            lastUpdated: isset($data['last_updated'])
                ? Carbon::parse($data['last_updated'])
                : null,
            notes: $data['notes'] ?? null
        );
    }

    /**
     * Create from database model
     */
    public static function fromModel(\App\TravelClick\Models\TravelClickPropertyConfig $model): self
    {
        return self::fromArray([
            'property_id' => $model->PropertyID,
            'hotel_code' => $model->HotelCode,
            'property_name' => $model->PropertyName,
            'environment' => $model->Environment,
            'username' => $model->Username,
            'password' => $model->Password,
            'timeout' => $model->Timeout,
            'retry_attempts' => $model->RetryAttempts,
            'backoff_seconds' => $model->BackoffSeconds ? json_decode($model->BackoffSeconds, true) : null,
            'enabled_message_types' => $model->EnabledMessageTypes ? json_decode($model->EnabledMessageTypes, true) : [],
            'custom_settings' => $model->CustomSettings ? json_decode($model->CustomSettings, true) : [],
            'override_global' => $model->OverrideGlobal,
            'is_active' => $model->IsActive,
            'queue_overrides' => $model->QueueOverrides ? json_decode($model->QueueOverrides, true) : [],
            'endpoint_overrides' => $model->EndpointOverrides ? json_decode($model->EndpointOverrides, true) : [],
            'last_sync_date' => $model->LastSyncDate,
            'last_updated' => $model->LastUpdated,
            'notes' => $model->Notes
        ]);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'property_id' => $this->propertyId,
            'hotel_code' => $this->hotelCode,
            'property_name' => $this->propertyName,
            'environment' => $this->environment->value,
            'username' => $this->username,
            'password' => $this->password,
            'timeout' => $this->timeout,
            'retry_attempts' => $this->retryAttempts,
            'backoff_seconds' => $this->backoffSeconds,
            'enabled_message_types' => $this->enabledMessageTypes,
            'custom_settings' => $this->customSettings,
            'override_global' => $this->overrideGlobal,
            'is_active' => $this->isActive,
            'queue_overrides' => $this->queueOverrides,
            'endpoint_overrides' => $this->endpointOverrides,
            'last_sync_date' => $this->lastSyncDate?->toISOString(),
            'last_updated' => $this->lastUpdated?->toISOString(),
            'notes' => $this->notes
        ];
    }

    /**
     * Convert to database format
     */
    public function toDatabase(): array
    {
        return [
            'PropertyID' => $this->propertyId,
            'HotelCode' => $this->hotelCode,
            'PropertyName' => $this->propertyName,
            'Environment' => $this->environment->value,
            'Username' => $this->username,
            'Password' => $this->password,
            'Timeout' => $this->timeout,
            'RetryAttempts' => $this->retryAttempts,
            'BackoffSeconds' => $this->backoffSeconds ? json_encode($this->backoffSeconds) : null,
            'EnabledMessageTypes' => json_encode($this->enabledMessageTypes),
            'CustomSettings' => json_encode($this->customSettings),
            'OverrideGlobal' => $this->overrideGlobal,
            'IsActive' => $this->isActive,
            'QueueOverrides' => json_encode($this->queueOverrides),
            'EndpointOverrides' => json_encode($this->endpointOverrides),
            'LastSyncDate' => $this->lastSyncDate,
            'LastUpdated' => $this->lastUpdated ?? now(),
            'Notes' => $this->notes
        ];
    }

    /**
     * Get effective timeout (property or fallback to global)
     */
    public function getEffectiveTimeout(int $globalTimeout): int
    {
        return $this->overrideGlobal && $this->timeout
            ? $this->timeout
            : $globalTimeout;
    }

    /**
     * Get effective retry attempts (property or fallback to global)
     */
    public function getEffectiveRetryAttempts(int $globalRetryAttempts): int
    {
        return $this->overrideGlobal && $this->retryAttempts
            ? $this->retryAttempts
            : $globalRetryAttempts;
    }

    /**
     * Get effective backoff seconds (property or fallback to global)
     */
    public function getEffectiveBackoffSeconds(array $globalBackoffSeconds): array
    {
        return $this->overrideGlobal && $this->backoffSeconds
            ? $this->backoffSeconds
            : $globalBackoffSeconds;
    }

    /**
     * Check if a message type is enabled for this property
     */
    public function isMessageTypeEnabled(string $messageType): bool
    {
        return in_array($messageType, $this->enabledMessageTypes);
    }

    /**
     * Get custom setting value
     */
    public function getCustomSetting(string $key, mixed $default = null): mixed
    {
        return $this->customSettings[$key] ?? $default;
    }

    /**
     * Check if property configuration is complete
     */
    public function isComplete(): bool
    {
        return !empty($this->hotelCode)
            && !empty($this->username)
            && !empty($this->password)
            && $this->isActive;
    }

    /**
     * Check if property requires sync
     */
    public function requiresSync(int $maxDaysWithoutSync = 7): bool
    {
        if (!$this->lastSyncDate) {
            return true;
        }

        return $this->lastSyncDate->diffInDays(now()) > $maxDaysWithoutSync;
    }

    /**
     * Merge with global configuration
     */
    public function mergeWithGlobal(TravelClickConfigDto $global): self
    {
        if (!$this->overrideGlobal) {
            return new self(
                propertyId: $this->propertyId,
                hotelCode: $this->hotelCode,
                propertyName: $this->propertyName,
                environment: $this->environment,
                username: $this->username,
                password: $this->password,
                timeout: $global->defaultTimeout,
                retryAttempts: $global->defaultRetryAttempts,
                backoffSeconds: $global->defaultBackoffSeconds,
                enabledMessageTypes: $this->enabledMessageTypes ?: $global->supportedMessageTypes,
                customSettings: $this->customSettings,
                overrideGlobal: $this->overrideGlobal,
                isActive: $this->isActive,
                queueOverrides: $this->queueOverrides,
                endpointOverrides: $this->endpointOverrides,
                lastSyncDate: $this->lastSyncDate,
                lastUpdated: $this->lastUpdated,
                notes: $this->notes
            );
        }

        return $this;
    }

    /**
     * Get cache key for this property configuration
     */
    public function getCacheKey(): string
    {
        return sprintf(
            '%s:%d',
            \App\TravelClick\Enums\ConfigScope::PROPERTY->cacheKeyPrefix(),
            $this->propertyId
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

    /**
     * Validate property configuration
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->hotelCode)) {
            $errors[] = 'Hotel code is required';
        } elseif (strlen($this->hotelCode) > 20) {
            $errors[] = 'Hotel code must be 20 characters or less';
        }

        if (empty($this->username)) {
            $errors[] = 'Username is required';
        }

        if (empty($this->password)) {
            $errors[] = 'Password is required';
        } elseif (strlen($this->password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        if ($this->timeout && ($this->timeout < 5 || $this->timeout > 300)) {
            $errors[] = 'Timeout must be between 5 and 300 seconds';
        }

        if ($this->retryAttempts && ($this->retryAttempts < 1 || $this->retryAttempts > 10)) {
            $errors[] = 'Retry attempts must be between 1 and 10';
        }

        return $errors;
    }
}
