<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;
use App\TravelClick\Services\Contracts\SoapServiceInterface;
use App\TravelClick\Services\SoapService;
use App\TravelClick\Services\Contracts\ConfigurationServiceInterface;
use App\TravelClick\Services\ConfigurationService;
use App\TravelClick\Services\Contracts\ValidationServiceInterface;
use App\TravelClick\Services\ValidationService;
use App\TravelClick\Support\SoapClientFactory;
use App\TravelClick\Support\ConfigurationValidator;
use App\TravelClick\Support\ConfigurationCache;
use App\TravelClick\Support\XmlValidator;
use App\TravelClick\Support\BusinessRulesValidator;
use App\TravelClick\Support\ValidationRulesHelper;
use App\TravelClick\Rules\ValidCountType;
use App\TravelClick\Rules\ValidHtngDate;
use App\TravelClick\Rules\ValidHtngDateRange;
use App\TravelClick\Rules\ValidRoomType;
use App\TravelClick\Rules\ValidCurrencyCode;

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

        // Register Validation-related services
        $this->registerValidationServices();

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
        // Publish configuration files
        $this->publishes([
            __DIR__ . '/../../config/travelclick.php' => config_path('travelclick.php'),
        ], 'travelclick-config');

        $this->publishes([
            __DIR__ . '/../../config/travelclick/validation.php' => config_path('travelclick/validation.php'),
        ], 'travelclick-validation-config');

        // Publish schema files
        $this->publishes([
            __DIR__ . '/../../storage/schemas' => storage_path('schemas'),
        ], 'travelclick-schemas');

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

        // Register custom validation rules
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

        \App\TravelClick\Models\TravelClickMessageHistory::observe(\App\TravelClick\Observers\TravelClickMessageHistoryObserver::class);
        \App\TravelClick\Models\TravelClickErrorLog::observe(\App\TravelClick\Observers\TravelClickErrorLogObserver::class);
        \App\TravelClick\Models\TravelClickPropertyMapping::observe(\App\TravelClick\Observers\TravelClickPropertyMappingObserver::class);
        \App\TravelClick\Models\TravelClickSyncStatus::observe(\App\TravelClick\Observers\TravelClickSyncStatusObserver::class);
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
            ValidationServiceInterface::class,
            ValidationService::class,
            XmlValidator::class,
            BusinessRulesValidator::class,
            ValidationRulesHelper::class,
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
     * Register Validation Service and related dependencies
     */
    private function registerValidationServices(): void
    {
        // Register ValidationRulesHelper
        $this->app->singleton(ValidationRulesHelper::class, function ($app) {
            return new ValidationRulesHelper();
        });

        // Register XmlValidator
        $this->app->singleton(XmlValidator::class, function ($app) {
            $config = config('travelclick.validation', []);
            return new XmlValidator($config);
        });

        // Register BusinessRulesValidator
        $this->app->singleton(BusinessRulesValidator::class, function ($app) {
            return new BusinessRulesValidator(
                $app->make(ValidationRulesHelper::class)
            );
        });

        // Register ValidationService interface binding
        $this->app->bind(ValidationServiceInterface::class, ValidationService::class);

        // Register ValidationService as singleton - corrected to match actual constructor
        $this->app->singleton(ValidationService::class, function ($app) {
            return new ValidationService(
                $app->make(ConfigurationServiceInterface::class),
                $app->make(ConfigurationValidator::class)
            );
        });
    }

    /**
     * Register custom validation rules for TravelClick data
     */
    private function registerValidationRules(): void
    {
        // Register HTNG-specific validation rules
        Validator::extend('valid_count_type', ValidCountType::class . '@validate');
        Validator::extend('valid_htng_date', ValidHtngDate::class . '@validate');
        Validator::extend('valid_htng_date_range', ValidHtngDateRange::class . '@validate');
        Validator::extend('valid_room_type', ValidRoomType::class . '@validate');
        Validator::extend('valid_currency_code', ValidCurrencyCode::class . '@validate');

        // Register existing validation rules
        Validator::extend('hotel_code', function ($attribute, $value, $parameters, $validator) {
            // Hotel codes should be alphanumeric, 3-20 characters
            return preg_match('/^[A-Za-z0-9]{3,20}$/', $value);
        });

        Validator::extend('message_id', function ($attribute, $value, $parameters, $validator) {
            // Message IDs should follow the pattern PREFIX_YYYYMMDD_HHMMSS_SUFFIX
            return preg_match('/^[A-Z]+_\d{8}_\d{6}_[A-Za-z0-9]+$/', $value);
        });

        // Configuration-specific validation rules
        Validator::extend('travelclick_hotel_code', function ($attribute, $value, $parameters, $validator) {
            // TravelClick hotel codes should be numeric, 1-10 digits
            return preg_match('/^\d{1,10}$/', $value);
        });

        Validator::extend('travelclick_username', function ($attribute, $value, $parameters, $validator) {
            // TravelClick usernames should be alphanumeric with possible underscores/hyphens
            return preg_match('/^[A-Za-z0-9_-]{3,50}$/', $value);
        });

        // Register validation rule messages
        $this->registerValidationRuleMessages();
    }

    /**
     * Register custom validation rule error messages
     */
    private function registerValidationRuleMessages(): void
    {
        Validator::replacer('valid_count_type', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':attribute', $attribute, 'The :attribute must be a valid HTNG count type (1, 2, 4, 5, 6, or 99).');
        });

        Validator::replacer('valid_htng_date', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':attribute', $attribute, 'The :attribute must be a valid HTNG date in ISO format.');
        });

        Validator::replacer('valid_htng_date_range', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':attribute', $attribute, 'The :attribute must be a valid HTNG date range with start date before end date.');
        });

        Validator::replacer('valid_room_type', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':attribute', $attribute, 'The :attribute must be a valid room type code.');
        });

        Validator::replacer('valid_currency_code', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':attribute', $attribute, 'The :attribute must be a valid ISO 4217 currency code.');
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
        // Event::listen(ConfigurationValidationFailed::class, NotifyConfigurationManagerListener::class);

        // Validation-related events
        // Event::listen(ValidationFailed::class, LogValidationErrorListener::class);
        // Event::listen(XmlValidationCompleted::class, UpdateValidationMetricsListener::class);
    }
}
