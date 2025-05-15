<?php

namespace App\TravelClick\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * TravelClick Property Configuration Model
 *
 * Stores property-specific configurations for TravelClick integration.
 * Each property can have its own credentials, endpoints, and feature flags.
 *
 * @property int $id
 * @property int $property_id
 * @property array $config
 * @property bool $is_active
 * @property Carbon|null $last_sync_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class TravelClickPropertyConfig extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'travel_click_property_configs';

    protected $fillable = [
        'property_id',
        'config',
        'is_active',
        'last_sync_at',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the property that owns this configuration
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Scope to get only active configurations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get configurations that need sync
     */
    public function scopeNeedsSync($query, int $hoursThreshold = 24)
    {
        return $query->where(function ($q) use ($hoursThreshold) {
            $q->whereNull('last_sync_at')
                ->orWhere('last_sync_at', '<', now()->subHours($hoursThreshold));
        });
    }

    /**
     * Scope to get configurations for specific properties
     */
    public function scopeForProperties($query, array $propertyIds)
    {
        return $query->whereIn('property_id', $propertyIds);
    }

    /**
     * Get a specific configuration value
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Set a specific configuration value
     */
    public function setConfigValue(string $key, mixed $value): self
    {
        $config = $this->config ?? [];
        data_set($config, $key, $value);
        $this->config = $config;

        return $this;
    }

    /**
     * Check if configuration has required fields
     */
    public function hasRequiredFields(): bool
    {
        $required = [
            'hotel_code',
            'credentials.username',
            'credentials.password',
        ];

        foreach ($required as $field) {
            if (empty($this->getConfigValue($field))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mark configuration as synced
     */
    public function markAsSynced(): self
    {
        $this->last_sync_at = now();
        $this->save();

        return $this;
    }

    /**
     * Get configuration with masked credentials for logging
     */
    public function getConfigForLogging(): array
    {
        $config = $this->config ?? [];

        // Mask sensitive information
        if (isset($config['credentials']['password'])) {
            $config['credentials']['password'] = '***MASKED***';
        }

        return $config;
    }

    /**
     * Merge with another configuration array
     */
    public function mergeConfig(array $newConfig): self
    {
        $this->config = array_merge_recursive($this->config ?? [], $newConfig);

        return $this;
    }

    /**
     * Validate configuration structure
     */
    public function validateConfig(): array
    {
        $errors = [];
        $config = $this->config ?? [];

        // Check required fields
        if (empty($config['hotel_code'])) {
            $errors[] = 'Hotel code is required';
        }

        if (empty($config['credentials']['username'])) {
            $errors[] = 'Username is required';
        }

        if (empty($config['credentials']['password'])) {
            $errors[] = 'Password is required';
        }

        // Validate hotel code format
        if (!empty($config['hotel_code']) && !preg_match('/^\d+$/', $config['hotel_code'])) {
            $errors[] = 'Hotel code must be numeric';
        }

        // Validate feature flags if present
        if (isset($config['features'])) {
            $validFeatures = ['inventory', 'rates', 'restrictions', 'reservations', 'group_blocks'];
            foreach ($config['features'] as $feature => $enabled) {
                if (!in_array($feature, $validFeatures)) {
                    $errors[] = "Invalid feature: {$feature}";
                }
            }
        }

        return $errors;
    }

    /**
     * Get property configurations that are due for health check
     */
    public static function getDueForHealthCheck(int $intervalHours = 6): Collection
    {
        $threshold = now()->subHours($intervalHours);

        return static::active()
            ->where(function ($query) use ($threshold) {
                $query->whereNull('last_health_check_at')
                    ->orWhere('last_health_check_at', '<', $threshold);
            })
            ->get();
    }

    /**
     * Update last health check timestamp
     */
    public function updateHealthCheck(bool $healthy = true): self
    {
        $config = $this->config ?? [];
        $config['health_status'] = [
            'healthy' => $healthy,
            'last_check' => now()->toISOString(),
            'last_healthy' => $healthy ? now()->toISOString() : ($config['health_status']['last_healthy'] ?? null),
        ];

        $this->config = $config;
        $this->save();

        return $this;
    }

    /**
     * Check if configuration is healthy
     */
    public function isHealthy(): bool
    {
        return $this->getConfigValue('health_status.healthy', false);
    }

    /**
     * Get last health check time
     */
    public function getLastHealthCheck(): ?Carbon
    {
        $lastCheck = $this->getConfigValue('health_status.last_check');

        return $lastCheck ? Carbon::parse($lastCheck) : null;
    }
}
