<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\TravelClick\Services\Contracts\SoapServiceInterface;
use App\TravelClick\Services\SoapService;
use App\TravelClick\Services\Contracts\ConfigurationServiceInterface;
use App\TravelClick\Services\ConfigurationService;
use App\TravelClick\Support\SoapClientFactory;
use App\TravelClick\Support\ConfigurationValidator;
use App\TravelClick\Support\ConfigurationCache;

/**
 * Service Provider for TravelClick integration services
 *
 * This provider handles the registration and configuration of all
 * TravelClick-related services in the Laravel application container.
 */
class TravelClickServiceProvider extends ServiceProvider
{
    /**
     * Register services in the container
     */
    public function register(): void
    {
        // Register Configuration-related support classes
        $this->registerConfigurationServices();

        // Register the SoapClientFactory as a singleton
        $this->app->singleton(SoapClientFactory::class, function ($app) {
            $config = config('travelclick');

            return new SoapClientFactory(
                wsdl: $config['endpoints']['wsdl'],
                username: $config['credentials']['username'],
                password: $config['credentials']['password'],
                options: $config['soap_options'] ?? []
            );
        });

        // Register the SoapService interface binding
        $this->app->bind(SoapServiceInterface::class, SoapService::class);

        // Register the SoapService as a singleton to reuse connections
        $this->app->singleton(SoapService::class, function ($app) {
            return new SoapService($app->make(SoapClientFactory::class));
        });

        // Register additional TravelClick services here as they are created
        // Example for future services:
        // $this->app->bind(InventoryServiceInterface::class, InventoryService::class);
        // $this->app->bind(RateServiceInterface::class, RateService::class);
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Publish configuration file
        $this->publishes([
            __DIR__ . '/../../config/travelclick.php' => config_path('travelclick.php'),
        ], 'travelclick-config');

        // Publish TravelClick migrations
        $this->publishes([
            __DIR__ . '/../TravelClick/Database/Migrations' => database_path('migrations'),
        ], 'travelclick-migrations');

        // Load TravelClick migrations
        $this->loadMigrationsFrom(__DIR__ . '/../TravelClick/Database/Migrations');

        // Load package routes if needed
        // $this->loadRoutesFrom(__DIR__.'/../TravelClick/Http/routes.php');

        // Load package views if needed
        // $this->loadViewsFrom(__DIR__.'/../resources/views/travelclick', 'travelclick');

        // Register custom validation rules if needed
        $this->registerValidationRules();

        // Register command classes for artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Configuration commands
                \App\TravelClick\Console\Commands\ValidateConfigurationCommand::class,
                \App\TravelClick\Console\Commands\CacheConfigurationCommand::class,
                // Add custom artisan commands here as they are created
                // \App\TravelClick\Console\SyncInventoryCommand::class,
                // \App\TravelClick\Console\TestConnectionCommand::class,
            ]);
        }

        // Register event listeners
        $this->registerEventListeners();
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [
            SoapServiceInterface::class,
            SoapService::class,
            SoapClientFactory::class,
            ConfigurationServiceInterface::class,
            ConfigurationService::class,
            ConfigurationValidator::class,
            ConfigurationCache::class,
        ];
    }

    /**
     * Register Configuration Service and related dependencies
     */
    private function registerConfigurationServices(): void
    {
        // Register ConfigurationValidator
        $this->app->singleton(ConfigurationValidator::class, function ($app) {
            return new ConfigurationValidator();
        });

        // Register ConfigurationCache
        $this->app->singleton(ConfigurationCache::class, function ($app) {
            return new ConfigurationCache();
        });

        // Register ConfigurationService interface binding
        $this->app->bind(ConfigurationServiceInterface::class, ConfigurationService::class);

        // Register ConfigurationService as singleton
        $this->app->singleton(ConfigurationService::class, function ($app) {
            return new ConfigurationService(
                $app->make(ConfigurationValidator::class),
                $app->make(ConfigurationCache::class)
            );
        });
    }

    /**
     * Register custom validation rules for TravelClick data
     */
    private function registerValidationRules(): void
    {
        // Example: Register custom validation rule for hotel codes
        \Illuminate\Support\Facades\Validator::extend('hotel_code', function ($attribute, $value, $parameters, $validator) {
            // Hotel codes should be alphanumeric, 3-20 characters
            return preg_match('/^[A-Za-z0-9]{3,20}$/', $value);
        });

        // Add more custom validation rules as needed
        \Illuminate\Support\Facades\Validator::extend('message_id', function ($attribute, $value, $parameters, $validator) {
            // Message IDs should follow the pattern PREFIX_YYYYMMDD_HHMMSS_SUFFIX
            return preg_match('/^[A-Z]+_\d{8}_\d{6}_[A-Za-z0-9]+$/', $value);
        });

        // Configuration-specific validation rules
        \Illuminate\Support\Facades\Validator::extend('travelclick_hotel_code', function ($attribute, $value, $parameters, $validator) {
            // TravelClick hotel codes should be numeric, 1-10 digits
            return preg_match('/^\d{1,10}$/', $value);
        });

        \Illuminate\Support\Facades\Validator::extend('travelclick_username', function ($attribute, $value, $parameters, $validator) {
            // TravelClick usernames should be alphanumeric with possible underscores/hyphens
            return preg_match('/^[A-Za-z0-9_-]{3,50}$/', $value);
        });
    }

    /**
     * Register event listeners for TravelClick operations
     */
    private function registerEventListeners(): void
    {
        // Register event listeners here as they are created
        // Event::listen(TravelClickOperationCompleted::class, LogOperationListener::class);
        // Event::listen(TravelClickOperationFailed::class, NotifyAdministratorListener::class);

        // Configuration-related events
        // Event::listen(ConfigurationUpdated::class, ClearConfigurationCacheListener::class);
        // Event::listen(ConfigurationValidationFailed::class, NotifyAdministratorListener::class);
    }
}
