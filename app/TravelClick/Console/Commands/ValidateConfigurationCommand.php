<?php

namespace App\TravelClick\Console\Commands;

use App\TravelClick\Services\ConfigurationService;
use App\TravelClick\Models\TravelClickPropertyConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Validate TravelClick Configuration Command
 *
 * Validates the configuration for one or more properties to ensure
 * all required fields are present and properly formatted.
 */
class ValidateConfigurationCommand extends Command
{
    protected $signature = 'travelclick:validate-config
                           {--property= : Specific property ID to validate}
                           {--all : Validate all active property configurations}
                           {--fix : Attempt to fix common configuration issues}
                           {--verbose : Show detailed validation results}';

    protected $description = 'Validate TravelClick configuration for properties';

    protected ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        parent::__construct();
        $this->configService = $configService;
    }

    public function handle(): int
    {
        $propertyId = $this->option('property');
        $validateAll = $this->option('all');
        $fix = $this->option('fix');
        $verbose = $this->option('verbose');

        if (!$propertyId && !$validateAll) {
            $this->error('Please specify either --property=ID or --all option');
            return Command::FAILURE;
        }

        if ($propertyId && $validateAll) {
            $this->error('Cannot use both --property and --all options together');
            return Command::FAILURE;
        }

        $this->info('Starting TravelClick configuration validation...');

        if ($propertyId) {
            return $this->validateSingleProperty((int) $propertyId, $fix, $verbose);
        }

        return $this->validateAllProperties($fix, $verbose);
    }

    protected function validateSingleProperty(int $propertyId, bool $fix, bool $verbose): int
    {
        $this->info("Validating configuration for property {$propertyId}...");

        try {
            $validation = $this->configService->validatePropertyConfig($propertyId);

            if ($validation['valid']) {
                $this->info("✅ Configuration for property {$propertyId} is valid");

                if ($verbose) {
                    $config = $this->configService->getPropertyConfig($propertyId);
                    $this->displayConfigDetails($propertyId, $config->toArray());
                }

                return Command::SUCCESS;
            }

            $this->displayValidationErrors($propertyId, $validation, $verbose);

            if ($fix) {
                return $this->attemptFix($propertyId, $validation);
            }

            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error("Failed to validate property {$propertyId}: {$e->getMessage()}");

            if ($verbose) {
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    protected function validateAllProperties(bool $fix, bool $verbose): int
    {
        $properties = $this->configService->getConfiguredProperties();

        if (empty($properties)) {
            $this->warn('No active property configurations found');
            return Command::SUCCESS;
        }

        $this->info("Found " . count($properties) . " active property configurations");

        $results = [
            'valid' => [],
            'invalid' => [],
            'errors' => []
        ];

        $progressBar = $this->output->createProgressBar(count($properties));
        $progressBar->start();

        foreach ($properties as $propertyId) {
            try {
                $validation = $this->configService->validatePropertyConfig($propertyId);

                if ($validation['valid']) {
                    $results['valid'][] = $propertyId;
                } else {
                    $results['invalid'][] = [
                        'property_id' => $propertyId,
                        'validation' => $validation
                    ];

                    if ($fix) {
                        $this->attemptFix($propertyId, $validation);
                    }
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'property_id' => $propertyId,
                    'error' => $e->getMessage()
                ];
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display summary
        $this->displayValidationSummary($results, $verbose);

        // Return appropriate exit code
        return empty($results['invalid']) && empty($results['errors'])
            ? Command::SUCCESS
            : Command::FAILURE;
    }

    protected function displayValidationErrors(int $propertyId, array $validation, bool $verbose): void
    {
        $this->error("❌ Configuration for property {$propertyId} has issues:");

        if (!empty($validation['errors'])) {
            $this->error("Errors:");
            foreach ($validation['errors'] as $error) {
                $this->error("  - {$error}");
            }
        }

        if (!empty($validation['warnings'])) {
            $this->warn("Warnings:");
            foreach ($validation['warnings'] as $warning) {
                $this->warn("  - {$warning}");
            }
        }

        if ($verbose && !empty($validation['details'])) {
            $this->info("Validation Details:");
            foreach ($validation['details'] as $key => $detail) {
                $this->line("  {$key}: {$detail}");
            }
        }
    }

    protected function displayConfigDetails(int $propertyId, array $config): void
    {
        $this->info("Configuration Details for Property {$propertyId}:");
        $this->line("  Hotel Code: " . ($config['hotel_code'] ?? 'NOT SET'));
        $this->line("  Username: " . ($config['credentials']['username'] ?? 'NOT SET'));
        $this->line("  Password: " . (isset($config['credentials']['password']) ? '***SET***' : 'NOT SET'));
        $this->line("  Is Active: " . ($config['is_active'] ? 'Yes' : 'No'));
        $this->line("  Last Sync: " . ($config['last_sync_at'] ?? 'Never'));

        if (!empty($config['features'])) {
            $this->line("  Features:");
            foreach ($config['features'] as $feature => $enabled) {
                $status = $enabled ? '✅' : '❌';
                $this->line("    {$status} {$feature}");
            }
        }
    }

    protected function displayValidationSummary(array $results, bool $verbose): void
    {
        $this->info("Validation Summary:");
        $this->line("  ✅ Valid configurations: " . count($results['valid']));
        $this->line("  ❌ Invalid configurations: " . count($results['invalid']));
        $this->line("  🔥 Error configurations: " . count($results['errors']));

        if (!empty($results['invalid']) && $verbose) {
            $this->newLine();
            $this->error("Invalid Configurations:");
            foreach ($results['invalid'] as $invalid) {
                $this->error("  Property {$invalid['property_id']}:");
                foreach ($invalid['validation']['errors'] as $error) {
                    $this->error("    - {$error}");
                }
            }
        }

        if (!empty($results['errors']) && $verbose) {
            $this->newLine();
            $this->error("Error Configurations:");
            foreach ($results['errors'] as $error) {
                $this->error("  Property {$error['property_id']}: {$error['error']}");
            }
        }
    }

    protected function attemptFix(int $propertyId, array $validation): int
    {
        $this->warn("Attempting to fix property {$propertyId}...");

        try {
            $config = TravelClickPropertyConfig::where('property_id', $propertyId)->first();

            if (!$config) {
                $this->error("No configuration found for property {$propertyId}");
                return Command::FAILURE;
            }

            $configArray = $config->config ?? [];
            $modified = false;

            // Fix common issues
            foreach ($validation['errors'] as $error) {
                if (str_contains($error, 'hotel_code') && empty($configArray['hotel_code'])) {
                    $hotelCode = $this->ask("Enter hotel code for property {$propertyId}");
                    if ($hotelCode) {
                        $configArray['hotel_code'] = $hotelCode;
                        $modified = true;
                    }
                }

                if (str_contains($error, 'username') && empty($configArray['credentials']['username'])) {
                    $username = $this->ask("Enter username for property {$propertyId}");
                    if ($username) {
                        $configArray['credentials']['username'] = $username;
                        $modified = true;
                    }
                }

                if (str_contains($error, 'password') && empty($configArray['credentials']['password'])) {
                    $password = $this->secret("Enter password for property {$propertyId}");
                    if ($password) {
                        $configArray['credentials']['password'] = $password;
                        $modified = true;
                    }
                }
            }

            if ($modified) {
                $this->configService->updatePropertyConfig($propertyId, $configArray);
                $this->info("Configuration fixed for property {$propertyId}");

                // Re-validate
                $revalidation = $this->configService->validatePropertyConfig($propertyId);
                if ($revalidation['valid']) {
                    $this->info("✅ Property {$propertyId} is now valid");
                    return Command::SUCCESS;
                } else {
                    $this->warn("⚠️  Property {$propertyId} still has issues after fix attempt");
                    return Command::FAILURE;
                }
            } else {
                $this->warn("No automatic fixes could be applied to property {$propertyId}");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("Failed to fix property {$propertyId}: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
