<?php

namespace App\TravelClick\Services;

use App\TravelClick\Services\Contracts\ConfigurationServiceInterface;
use App\TravelClick\DTOs\TravelClickConfigDto;
use App\TravelClick\DTOs\PropertyConfigDto;
use App\TravelClick\DTOs\EndpointConfigDto;
use App\TravelClick\Enums\Environment;
use App\TravelClick\Enums\ConfigScope;
use App\TravelClick\Exceptions\ConfigurationException;
use App\TravelClick\Exceptions\InvalidConfigurationException;
use App\TravelClick\Support\ConfigurationValidator;
use App\TravelClick\Support\ConfigurationCache;
use App\TravelClick\Models\TravelClickPropertyConfig;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Property;

/**
 * TravelClick Configuration Service
 *
 * Manages all configuration aspects for TravelClick integration including
 * global settings, property-specific configurations, and environment handling.
 */
class ConfigurationService implements ConfigurationServiceInterface
{
    protected ConfigurationValidator $validator;
    protected ConfigurationCache $cache;
    protected array $globalConfig;

    public function __construct(
        ConfigurationValidator $validator,
        ConfigurationCache $cache
    ) {
        $this->validator = $validator;
        $this->cache = $cache;
        $this->globalConfig = Config::get('travelclick', []);
    }

    /**
     * Get complete TravelClick configuration for a specific property
     */
    public function getPropertyConfig(int $propertyId): PropertyConfigDto
    {
        try {
            // Check cache first
            $cached = $this->cache->getPropertyConfig($propertyId);
            if ($cached) {
                return $cached;
            }

            // Load from database
            $propertyConfig = TravelClickPropertyConfig::where('property_id', $propertyId)
                ->where('is_active', true)
                ->first();

            if (!$propertyConfig) {
                throw new ConfigurationException(
                    "No active configuration found for property {$propertyId}"
                );
            }

            // Merge with global config using fallback logic
            $merged = $this->mergeWithGlobalConfig($propertyConfig->config);

            // Create DTO
            $dto = PropertyConfigDto::fromArray([
                'property_id' => $propertyId,
                'hotel_code' => $merged['hotel_code'] ?? null,
                'credentials' => [
                    'username' => $merged['credentials']['username'] ?? null,
                    'password' => $merged['credentials']['password'] ?? null,
                ],
                'endpoints' => $merged['endpoints'] ?? $this->globalConfig['endpoints'],
                'features' => $merged['features'] ?? $this->globalConfig['features']['default'],
                'sync_settings' => $merged['sync_settings'] ?? $this->globalConfig['sync_settings']['default'],
                'retry_policy' => $merged['retry_policy'] ?? $this->globalConfig['retry_policy'],
                'is_active' => $propertyConfig->is_active,
                'last_sync_at' => $propertyConfig->last_sync_at,
            ]);

            // Cache the result
            $this->cache->putPropertyConfig($propertyId, $dto);

            return $dto;
        } catch (\Exception $e) {
            Log::error('Failed to get property configuration', [
                'property_id' => $propertyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new ConfigurationException(
                "Failed to retrieve configuration for property {$propertyId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Get global TravelClick configuration
     */
    public function getGlobalConfig(): TravelClickConfigDto
    {
        try {
            $cached = $this->cache->getGlobalConfig();
            if ($cached) {
                return $cached;
            }

            $dto = TravelClickConfigDto::fromArray($this->globalConfig);

            $this->cache->putGlobalConfig($dto);

            return $dto;
        } catch (\Exception $e) {
            Log::error('Failed to get global configuration', [
                'error' => $e->getMessage()
            ]);

            throw new ConfigurationException(
                "Failed to retrieve global configuration: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Get endpoint configuration for current environment
     */
    public function getEndpointConfig(?Environment $environment = null): EndpointConfigDto
    {
        $env = $environment ?? Environment::from(Config::get('app.env'));

        try {
            $cached = $this->cache->getEndpointConfig($env);
            if ($cached) {
                return $cached;
            }

            $endpoints = $this->globalConfig['endpoints'] ?? [];

            $endpointUrl = match ($env) {
                Environment::PRODUCTION => $endpoints['production'] ?? null,
                Environment::TESTING => $endpoints['test'] ?? null,
                Environment::STAGING => $endpoints['staging'] ?? $endpoints['test'] ?? null,
                Environment::LOCAL => $endpoints['local'] ?? $endpoints['test'] ?? null,
            };

            if (!$endpointUrl) {
                throw new ConfigurationException(
                    "No endpoint configured for environment: {$env->value}"
                );
            }

            $dto = EndpointConfigDto::fromArray([
                'environment' => $env,
                'base_url' => $endpointUrl,
                'wsdl_url' => $endpoints['wsdl'][$env->value] ?? $endpointUrl . '?wsdl',
                'timeout' => $endpoints['timeout'] ?? 30,
                'connection_timeout' => $endpoints['connection_timeout'] ?? 10,
                'ssl_verify' => $endpoints['ssl_verify'] ?? true,
                'user_agent' => $endpoints['user_agent'] ?? 'Laravel-TravelClick-Integration/1.0',
            ]);

            $this->cache->putEndpointConfig($env, $dto);

            return $dto;
        } catch (\Exception $e) {
            Log::error('Failed to get endpoint configuration', [
                'environment' => $env->value,
                'error' => $e->getMessage()
            ]);

            throw new ConfigurationException(
                "Failed to retrieve endpoint configuration for {$env->value}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Validate configuration for a property
     */
    public function validatePropertyConfig(int $propertyId): array
    {
        try {
            $config = $this->getPropertyConfig($propertyId);
            return $this->validator->validatePropertyConfig($config);
        } catch (\Exception $e) {
            Log::warning('Property configuration validation failed', [
                'property_id' => $propertyId,
                'error' => $e->getMessage()
            ]);

            return [
                'valid' => false,
                'errors' => [$e->getMessage()],
                'warnings' => []
            ];
        }
    }

    /**
     * Cache configuration for performance
     */
    public function cacheConfiguration(int $propertyId): bool
    {
        try {
            // Load fresh configuration
            $this->clearCache(ConfigScope::PROPERTY, $propertyId);
            $config = $this->getPropertyConfig($propertyId);

            Log::info('Configuration cached successfully', [
                'property_id' => $propertyId
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to cache configuration', [
                'property_id' => $propertyId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Clear configuration cache
     */
    public function clearCache(ConfigScope $scope = ConfigScope::ALL, ?int $propertyId = null): bool
    {
        try {
            return match ($scope) {
                ConfigScope::GLOBAL => $this->cache->clearGlobalConfig(),
                ConfigScope::PROPERTY => $this->cache->clearPropertyConfig($propertyId),
                ConfigScope::ENDPOINT => $this->cache->clearEndpointConfigs(),
                ConfigScope::ALL => $this->cache->clearAll(),
            };
        } catch (\Exception $e) {
            Log::error('Failed to clear configuration cache', [
                'scope' => $scope->value,
                'property_id' => $propertyId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Update property-specific configuration
     */
    public function updatePropertyConfig(int $propertyId, array $config): PropertyConfigDto
    {
        try {
            // Validate input configuration
            $validationResult = $this->validator->validatePropertyConfig(PropertyConfigDto::fromArray($config));
            if (!$validationResult['valid']) {
                throw new InvalidConfigurationException(
                    'Configuration validation failed: ' . implode(', ', $validationResult['errors'])
                );
            }

            // Update or create configuration
            $propertyConfig = TravelClickPropertyConfig::updateOrCreate(
                ['property_id' => $propertyId],
                [
                    'config' => $config,
                    'is_active' => $config['is_active'] ?? true,
                    'last_sync_at' => now(),
                ]
            );

            // Clear cache for this property
            $this->clearCache(ConfigScope::PROPERTY, $propertyId);

            // Return fresh configuration
            return $this->getPropertyConfig($propertyId);
        } catch (\Exception $e) {
            Log::error('Failed to update property configuration', [
                'property_id' => $propertyId,
                'error' => $e->getMessage()
            ]);

            throw new ConfigurationException(
                "Failed to update configuration for property {$propertyId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Get configuration value with fallback logic
     */
    public function getConfigValue(string $key, int $propertyId = null, mixed $default = null): mixed
    {
        try {
            // If property ID is provided, try property-specific config first
            if ($propertyId) {
                $propertyConfig = $this->getPropertyConfig($propertyId);
                $value = data_get($propertyConfig->toArray(), $key);
                if ($value !== null) {
                    return $value;
                }
            }

            // Fallback to global config
            $globalConfig = $this->getGlobalConfig();
            $value = data_get($globalConfig->toArray(), $key);

            return $value ?? $default;
        } catch (\Exception $e) {
            Log::debug('Failed to get config value, using default', [
                'key' => $key,
                'property_id' => $propertyId,
                'default' => $default,
                'error' => $e->getMessage()
            ]);

            return $default;
        }
    }

    /**
     * Check if property has complete configuration
     */
    public function isPropertyConfigured(int $propertyId): bool
    {
        try {
            $validation = $this->validatePropertyConfig($propertyId);
            return $validation['valid'] ?? false;
        } catch (\Exception $e) {
            Log::debug('Property configuration check failed', [
                'property_id' => $propertyId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get all configured properties
     */
    public function getConfiguredProperties(): array
    {
        try {
            return TravelClickPropertyConfig::where('is_active', true)
                ->pluck('property_id')
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get configured properties', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Export configuration for a property
     */
    public function exportPropertyConfig(int $propertyId): array
    {
        try {
            $config = $this->getPropertyConfig($propertyId);

            // Remove sensitive data for export
            $exportData = $config->toArray();
            unset($exportData['credentials']['password']);

            return [
                'property_id' => $propertyId,
                'exported_at' => now()->toISOString(),
                'configuration' => $exportData,
                'version' => Config::get('travelclick.version', '1.0.0'),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to export property configuration', [
                'property_id' => $propertyId,
                'error' => $e->getMessage()
            ]);

            throw new ConfigurationException(
                "Failed to export configuration for property {$propertyId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Import configuration for a property
     */
    public function importPropertyConfig(int $propertyId, array $config): PropertyConfigDto
    {
        try {
            // Extract the configuration data
            $configData = $config['configuration'] ?? $config;

            // Import doesn't include sensitive credentials, so preserve existing ones
            $existingConfig = TravelClickPropertyConfig::where('property_id', $propertyId)->first();
            if ($existingConfig && isset($existingConfig->config['credentials'])) {
                $configData['credentials'] = array_merge(
                    $configData['credentials'] ?? [],
                    $existingConfig->config['credentials']
                );
            }

            return $this->updatePropertyConfig($propertyId, $configData);
        } catch (\Exception $e) {
            Log::error('Failed to import property configuration', [
                'property_id' => $propertyId,
                'error' => $e->getMessage()
            ]);

            throw new ConfigurationException(
                "Failed to import configuration for property {$propertyId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Merge property configuration with global configuration using fallback logic
     */
    protected function mergeWithGlobalConfig(array $propertyConfig): array
    {
        // Start with global config as base
        $merged = $this->globalConfig;

        // Override with property-specific values
        foreach ($propertyConfig as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                // Recursively merge arrays
                $merged[$key] = array_merge($merged[$key], $value);
            } else {
                // Direct override for non-array values
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
