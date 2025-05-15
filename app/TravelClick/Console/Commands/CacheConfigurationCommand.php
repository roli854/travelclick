<?php

namespace App\TravelClick\Console\Commands;

use App\TravelClick\Services\ConfigurationService;
use App\TravelClick\Models\TravelClickPropertyConfig;
use App\TravelClick\Enums\ConfigScope;
use Illuminate\Console\Command;

/**
 * Cache TravelClick Configuration Command
 *
 * Manages caching of TravelClick configurations for improved performance.
 * Can warm up cache, clear cache, or show cache statistics.
 */
class CacheConfigurationCommand extends Command
{
    protected $signature = 'travelclick:cache-config
                           {action : Action to perform: warm, clear, stats}
                           {--property= : Specific property ID for operations}
                           {--scope= : Cache scope: global, property, endpoints, all}
                           {--force : Force cache operations even if not needed}';

    protected $description = 'Manage TravelClick configuration cache';

    protected ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        parent::__construct();
        $this->configService = $configService;
    }

    public function handle(): int
    {
        $action = $this->argument('action');
        $propertyId = $this->option('property');
        $scope = $this->option('scope');
        $force = $this->option('force');

        return match ($action) {
            'warm' => $this->warmupCache($propertyId, $force),
            'clear' => $this->clearCache($scope, $propertyId),
            'stats' => $this->showCacheStats(),
            default => $this->handleInvalidAction($action),
        };
    }

    protected function warmupCache(?string $propertyId, bool $force): int
    {
        $this->info('Starting cache warmup...');

        if ($propertyId) {
            return $this->warmupSingleProperty((int) $propertyId, $force);
        }

        return $this->warmupAllProperties($force);
    }

    protected function warmupSingleProperty(int $propertyId, bool $force): int
    {
        $this->info("Warming up cache for property {$propertyId}...");

        try {
            // Check if cache warming is needed
            if (!$force && $this->configService->isPropertyConfigured($propertyId)) {
                $cachedConfig = app()->make(\App\TravelClick\Support\ConfigurationCache::class)
                    ->getPropertyConfig($propertyId);

                if ($cachedConfig) {
                    $this->info("Cache already warm for property {$propertyId}");
                    return Command::SUCCESS;
                }
            }

            // Warm up the cache
            $success = $this->configService->cacheConfiguration($propertyId);

            if ($success) {
                $this->info("✅ Cache warmed up successfully for property {$propertyId}");
                return Command::SUCCESS;
            } else {
                $this->error("❌ Failed to warm up cache for property {$propertyId}");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("Error warming up cache for property {$propertyId}: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function warmupAllProperties(bool $force): int
    {
        $properties = $this->configService->getConfiguredProperties();

        if (empty($properties)) {
            $this->warn('No active property configurations found');
            return Command::SUCCESS;
        }

        $this->info("Warming up cache for " . count($properties) . " properties...");

        $progressBar = $this->output->createProgressBar(count($properties));
        $progressBar->start();

        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($properties as $propertyId) {
            try {
                // Check if already cached (skip if not forced)
                if (!$force) {
                    $cached = app()->make(\App\TravelClick\Support\ConfigurationCache::class)
                        ->getPropertyConfig($propertyId);

                    if ($cached) {
                        $results['skipped']++;
                        $progressBar->advance();
                        continue;
                    }
                }

                $success = $this->configService->cacheConfiguration($propertyId);

                if ($success) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $this->newLine();
                $this->error("Failed to warm cache for property {$propertyId}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info("Cache Warmup Results:");
        $this->line("  ✅ Successfully warmed: {$results['success']}");
        $this->line("  ❌ Failed: {$results['failed']}");
        $this->line("  ⏭️ Skipped (already cached): {$results['skipped']}");

        return $results['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    protected function clearCache(?string $scope, ?string $propertyId): int
    {
        $this->info('Clearing TravelClick configuration cache...');

        try {
            $configScope = $scope ? ConfigScope::from($scope) : ConfigScope::ALL;

            if ($propertyId && $configScope !== ConfigScope::PROPERTY) {
                $this->warn('Property ID specified but scope is not "property". Using property scope.');
                $configScope = ConfigScope::PROPERTY;
            }

            $success = $this->configService->clearCache($configScope, $propertyId ? (int) $propertyId : null);

            if ($success) {
                $message = $this->getClearSuccessMessage($configScope, $propertyId);
                $this->info("✅ {$message}");
                return Command::SUCCESS;
            } else {
                $this->error("❌ Failed to clear cache");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("Error clearing cache: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function getClearSuccessMessage(ConfigScope $scope, ?string $propertyId): string
    {
        return match ($scope) {
            ConfigScope::GLOBAL => 'Global configuration cache cleared',
            ConfigScope::PROPERTY => $propertyId
                ? "Property {$propertyId} configuration cache cleared"
                : 'All property configuration caches cleared',
            ConfigScope::ENDPOINT => 'Endpoint configuration caches cleared',
            ConfigScope::ALL => 'All TravelClick configuration caches cleared',
        };
    }

    protected function showCacheStats(): int
    {
        $this->info('TravelClick Configuration Cache Statistics');
        $this->line(str_repeat('=', 50));

        try {
            $cache = app()->make(\App\TravelClick\Support\ConfigurationCache::class);
            $stats = $cache->getStats();

            $this->line("Cache Store: {$stats['store']}");
            $this->line("Cache Prefix: {$stats['prefix']}");
            $this->line("TTL: {$stats['ttl']} seconds");
            $this->line("Supports Tags: " . ($stats['supports_tags'] ? 'Yes' : 'No'));

            if (isset($stats['store_prefix'])) {
                $this->line("Store Prefix: {$stats['store_prefix']}");
            }

            $this->newLine();

            // Check cache status for some properties
            $properties = TravelClickPropertyConfig::active()->limit(5)->pluck('property_id');

            if ($properties->isNotEmpty()) {
                $this->info('Sample Property Cache Status:');
                foreach ($properties as $propertyId) {
                    $cached = $cache->getPropertyConfig($propertyId);
                    $status = $cached ? '✅ Cached' : '❌ Not Cached';
                    $this->line("  Property {$propertyId}: {$status}");
                }
            }

            // Global config status
            $globalCached = $cache->getGlobalConfig();
            $globalStatus = $globalCached ? '✅ Cached' : '❌ Not Cached';
            $this->line("Global Config: {$globalStatus}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error getting cache statistics: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function handleInvalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->info('Valid actions are: warm, clear, stats');
        $this->newLine();

        $this->info('Examples:');
        $this->line('  php artisan travelclick:cache-config warm --property=123');
        $this->line('  php artisan travelclick:cache-config warm --force');
        $this->line('  php artisan travelclick:cache-config clear --scope=property --property=123');
        $this->line('  php artisan travelclick:cache-config clear --scope=all');
        $this->line('  php artisan travelclick:cache-config stats');

        return Command::FAILURE;
    }
}
