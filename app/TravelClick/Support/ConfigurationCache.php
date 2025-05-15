<?php

namespace App\TravelClick\Support;

use App\TravelClick\DTOs\TravelClickConfigDto;
use App\TravelClick\DTOs\PropertyConfigDto;
use App\TravelClick\DTOs\EndpointConfigDto;
use App\TravelClick\Enums\Environment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Configuration Cache Handler
 *
 * Manages caching of TravelClick configurations for optimal performance.
 * Uses Laravel's cache system with intelligent TTL management.
 */
class ConfigurationCache
{
    protected string $prefix;
    protected int $ttl;
    protected string $store;

    public function __construct()
    {
        $this->prefix = Config::get('travelclick.cache.prefix', 'travelclick:config:');
        $this->ttl = Config::get('travelclick.cache.ttl', 3600); // 1 hour default
        $this->store = Config::get('travelclick.cache.store', 'redis');
    }

    /**
     * Get cached property configuration
     */
    public function getPropertyConfig(int $propertyId): ?PropertyConfigDto
    {
        try {
            $key = $this->getPropertyKey($propertyId);
            $cached = Cache::store($this->store)->get($key);

            if ($cached) {
                Log::debug('Configuration cache hit', [
                    'type' => 'property',
                    'property_id' => $propertyId,
                    'key' => $key
                ]);

                return PropertyConfigDto::fromArray($cached);
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to retrieve property config from cache', [
                'property_id' => $propertyId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Cache property configuration
     */
    public function putPropertyConfig(int $propertyId, PropertyConfigDto $config): bool
    {
        try {
            $key = $this->getPropertyKey($propertyId);
            $tags = ['travelclick', 'config', 'property', "property:{$propertyId}"];

            Cache::store($this->store)
                ->tags($tags)
                ->put($key, $config->toArray(), $this->ttl);

            Log::debug('Property configuration cached', [
                'property_id' => $propertyId,
                'key' => $key,
                'ttl' => $this->ttl
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to cache property configuration', [
                'property_id' => $propertyId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get cached global configuration
     */
    public function getGlobalConfig(): ?TravelClickConfigDto
    {
        try {
            $key = $this->getGlobalKey();
            $cached = Cache::store($this->store)->get($key);

            if ($cached) {
                Log::debug('Configuration cache hit', [
                    'type' => 'global',
                    'key' => $key
                ]);

                return TravelClickConfigDto::fromArray($cached);
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to retrieve global config from cache', [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Cache global configuration
     */
    public function putGlobalConfig(TravelClickConfigDto $config): bool
    {
        try {
            $key = $this->getGlobalKey();
            $tags = ['travelclick', 'config', 'global'];

            Cache::store($this->store)
                ->tags($tags)
                ->put($key, $config->toArray(), $this->ttl);

            Log::debug('Global configuration cached', [
                'key' => $key,
                'ttl' => $this->ttl
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to cache global configuration', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get cached endpoint configuration
     */
    public function getEndpointConfig(Environment $environment): ?EndpointConfigDto
    {
        try {
            $key = $this->getEndpointKey($environment);
            $cached = Cache::store($this->store)->get($key);

            if ($cached) {
                Log::debug('Configuration cache hit', [
                    'type' => 'endpoint',
                    'environment' => $environment->value,
                    'key' => $key
                ]);

                return EndpointConfigDto::fromArray($cached);
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to retrieve endpoint config from cache', [
                'environment' => $environment->value,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Cache endpoint configuration
     */
    public function putEndpointConfig(Environment $environment, EndpointConfigDto $config): bool
    {
        try {
            $key = $this->getEndpointKey($environment);
            $tags = ['travelclick', 'config', 'endpoint', "env:{$environment->value}"];

            Cache::store($this->store)
                ->tags($tags)
                ->put($key, $config->toArray(), $this->ttl);

            Log::debug('Endpoint configuration cached', [
                'environment' => $environment->value,
                'key' => $key,
                'ttl' => $this->ttl
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to cache endpoint configuration', [
                'environment' => $environment->value,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Clear property configuration cache
     */
    public function clearPropertyConfig(?int $propertyId = null): bool
    {
        try {
            if ($propertyId) {
                // Clear specific property
                $key = $this->getPropertyKey($propertyId);
                Cache::store($this->store)->forget($key);

                // Also clear by tag if supported
                if (Cache::store($this->store)->getStore() instanceof \Illuminate\Cache\TaggedCache) {
                    Cache::store($this->store)->tags(["property:{$propertyId}"])->flush();
                }

                Log::info('Property configuration cache cleared', [
                    'property_id' => $propertyId
                ]);
            } else {
                // Clear all property configurations
                if (Cache::store($this->store)->getStore() instanceof \Illuminate\Cache\TaggedCache) {
                    Cache::store($this->store)->tags(['property'])->flush();
                    Log::info('All property configuration caches cleared');
                } else {
                    Log::warning('Cannot clear all property configs - cache store does not support tags');
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear property configuration cache', [
                'property_id' => $propertyId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Clear global configuration cache
     */
    public function clearGlobalConfig(): bool
    {
        try {
            $key = $this->getGlobalKey();
            Cache::store($this->store)->forget($key);

            // Also clear by tag if supported
            if (Cache::store($this->store)->getStore() instanceof \Illuminate\Cache\TaggedCache) {
                Cache::store($this->store)->tags(['global'])->flush();
            }

            Log::info('Global configuration cache cleared');

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear global configuration cache', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Clear endpoint configurations cache
     */
    public function clearEndpointConfigs(): bool
    {
        try {
            // Clear all endpoint configurations
            if (Cache::store($this->store)->getStore() instanceof \Illuminate\Cache\TaggedCache) {
                Cache::store($this->store)->tags(['endpoint'])->flush();
                Log::info('All endpoint configuration caches cleared');
            } else {
                // Fallback: clear for each environment
                foreach (Environment::cases() as $env) {
                    $key = $this->getEndpointKey($env);
                    Cache::store($this->store)->forget($key);
                }
                Log::info('Endpoint configuration caches cleared (fallback method)');
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear endpoint configuration cache', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Clear all TravelClick configuration caches
     */
    public function clearAll(): bool
    {
        try {
            if (Cache::store($this->store)->getStore() instanceof \Illuminate\Cache\TaggedCache) {
                Cache::store($this->store)->tags(['travelclick'])->flush();
                Log::info('All TravelClick configuration caches cleared');
            } else {
                // Fallback method
                $this->clearGlobalConfig();
                $this->clearPropertyConfig();
                $this->clearEndpointConfigs();
                Log::info('All TravelClick configuration caches cleared (fallback method)');
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear all configuration caches', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Warm up cache for a property
     */
    public function warmup(int $propertyId): bool
    {
        try {
            // This would typically be called from the ConfigurationService
            // after ensuring the configuration is loaded fresh
            Log::info('Cache warmup requested', [
                'property_id' => $propertyId
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to warm up cache', [
                'property_id' => $propertyId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get statistics about cache usage
     */
    public function getStats(): array
    {
        try {
            $stats = [
                'store' => $this->store,
                'prefix' => $this->prefix,
                'ttl' => $this->ttl,
                'supports_tags' => Cache::store($this->store)->getStore() instanceof \Illuminate\Cache\TaggedCache,
            ];

            // Add cache store specific stats if available
            $store = Cache::store($this->store)->getStore();
            if (method_exists($store, 'getPrefix')) {
                $stats['store_prefix'] = $store->getPrefix();
            }

            return $stats;
        } catch (\Exception $e) {
            Log::error('Failed to get cache statistics', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Generate cache key for property configuration
     */
    protected function getPropertyKey(int $propertyId): string
    {
        return "{$this->prefix}property:{$propertyId}";
    }

    /**
     * Generate cache key for global configuration
     */
    protected function getGlobalKey(): string
    {
        return "{$this->prefix}global";
    }

    /**
     * Generate cache key for endpoint configuration
     */
    protected function getEndpointKey(Environment $environment): string
    {
        return "{$this->prefix}endpoint:{$environment->value}";
    }
}
