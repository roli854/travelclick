<?php

namespace App\TravelClick\Services\Contracts;

use App\TravelClick\DTOs\TravelClickConfigDto;
use App\TravelClick\DTOs\PropertyConfigDto;
use App\TravelClick\DTOs\EndpointConfigDto;
use App\TravelClick\Enums\Environment;
use App\TravelClick\Enums\ConfigScope;

/**
 * Interface for TravelClick Configuration Service
 *
 * This interface defines the contract for managing all TravelClick configurations
 * including global, property-specific, and environment-based settings.
 */
interface ConfigurationServiceInterface
{
    /**
     * Get complete TravelClick configuration for a specific property
     */
    public function getPropertyConfig(int $propertyId): PropertyConfigDto;

    /**
     * Get global TravelClick configuration
     */
    public function getGlobalConfig(): TravelClickConfigDto;

    /**
     * Get endpoint configuration for current environment
     */
    public function getEndpointConfig(?Environment $environment = null): EndpointConfigDto;

    /**
     * Validate configuration for a property
     */
    public function validatePropertyConfig(int $propertyId): array;

    /**
     * Cache configuration for performance
     */
    public function cacheConfiguration(int $propertyId): bool;

    /**
     * Clear configuration cache
     */
    public function clearCache(ConfigScope $scope = ConfigScope::ALL, ?int $propertyId = null): bool;

    /**
     * Update property-specific configuration
     */
    public function updatePropertyConfig(int $propertyId, array $config): PropertyConfigDto;

    /**
     * Get configuration value with fallback logic
     */
    public function getConfigValue(string $key, int $propertyId = null, mixed $default = null): mixed;

    /**
     * Check if property has complete configuration
     */
    public function isPropertyConfigured(int $propertyId): bool;

    /**
     * Get all configured properties
     */
    public function getConfiguredProperties(): array;

    /**
     * Export configuration for a property
     */
    public function exportPropertyConfig(int $propertyId): array;

    /**
     * Import configuration for a property
     */
    public function importPropertyConfig(int $propertyId, array $config): PropertyConfigDto;
}
