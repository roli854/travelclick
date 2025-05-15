<?php

namespace Database\Factories\TravelClick\Factories;

use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\SyncStatus;
use App\TravelClick\Models\TravelClickSyncStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for TravelClickSyncStatus model
 *
 * This factory creates test data for sync status tracking.
 * It's like having a data generator that can create realistic
 * scenarios for testing our sync operations.
 *
 * @extends Factory<TravelClickSyncStatus>
 */
class TravelClickSyncStatusFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = TravelClickSyncStatus::class;

    /**
     * Define the model's default state.
     *
     * Creates a basic sync status that represents a typical
     * successful inventory sync for a hotel property.
     */
    public function definition(): array
    {
        $createdAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $lastSync = $this->faker->boolean(80) ?
            $this->faker->dateTimeBetween($createdAt, 'now') : null;

        $lastSuccess = $lastSync && $this->faker->boolean(70) ?
            $this->faker->dateTimeBetween($lastSync, 'now') : null;

        $recordsTotal = $this->faker->numberBetween(10, 1000);
        $recordsProcessed = $this->faker->boolean(80) ?
            $this->faker->numberBetween(0, $recordsTotal) : 0;

        $successRate = $recordsTotal > 0 ?
            round(($recordsProcessed / $recordsTotal) * 100, 2) : 0;

        return [
            'PropertyID' => $this->faker->numberBetween(1, 100),
            'MessageType' => $this->faker->randomElement(MessageType::cases()),
            'Status' => $this->faker->randomElement(SyncStatus::cases()),
            'LastSyncAttempt' => $lastSync,
            'LastSuccessfulSync' => $lastSuccess,
            'RetryCount' => $this->faker->numberBetween(0, 3),
            'MaxRetries' => $this->faker->randomElement([3, 5, 10]),
            'NextRetryAt' => $this->faker->boolean(20) ?
                $this->faker->dateTimeBetween('now', '+2 hours') : null,
            'ErrorMessage' => $this->faker->boolean(20) ?
                $this->faker->sentence() : null,
            'LastMessageID' => $this->faker->boolean(70) ?
                'MSG_' . $this->faker->uuid() : null,
            'RecordsProcessed' => $recordsProcessed,
            'RecordsTotal' => $recordsTotal,
            'SuccessRate' => $successRate,
            'IsActive' => $this->faker->boolean(95),
            'AutoRetryEnabled' => $this->faker->boolean(85),
            'LastSyncByUserID' => $this->faker->boolean(80) ?
                $this->faker->numberBetween(1, 50) : null,
            'DateCreated' => $createdAt,
            'DateModified' => $this->faker->boolean(60) ?
                $this->faker->dateTimeBetween($createdAt, 'now') : null,
            'Context' => $this->faker->boolean(50) ? [
                'batch_id' => $this->faker->uuid(),
                'source' => $this->faker->randomElement(['manual', 'scheduled', 'webhook']),
                'additional_info' => $this->faker->sentence(),
            ] : null,
        ];
    }

    /**
     * Create a sync status in completed state
     * Perfect for testing successful sync scenarios
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $total = $this->faker->numberBetween(50, 500);
            $processed = $this->faker->numberBetween((int)($total * 0.9), $total);

            return [
                'Status' => SyncStatus::COMPLETED,
                'LastSuccessfulSync' => $this->faker->dateTimeBetween('-1 day', 'now'),
                'RecordsProcessed' => $processed,
                'RecordsTotal' => $total,
                'SuccessRate' => round(($processed / $total) * 100, 2),
                'RetryCount' => 0,
                'NextRetryAt' => null,
                'ErrorMessage' => null,
            ];
        });
    }

    /**
     * Create a sync status in failed state
     * Useful for testing error handling and retry logic
     */
    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $retryCount = $this->faker->numberBetween(1, 3);
            $maxRetries = $attributes['MaxRetries'] ?? 3;

            return [
                'Status' => SyncStatus::FAILED,
                'RetryCount' => $retryCount,
                'NextRetryAt' => $retryCount < $maxRetries ?
                    $this->faker->dateTimeBetween('now', '+1 hour') : null,
                'ErrorMessage' => $this->faker->randomElement([
                    'SOAP connection timeout',
                    'Authentication failed',
                    'Invalid XML format',
                    'Rate limit exceeded',
                    'Property not found',
                ]),
                'RecordsProcessed' => $this->faker->numberBetween(0, 50),
                'Context' => [
                    'error_type' => $this->faker->randomElement(['network', 'auth', 'validation', 'business']),
                    'error_code' => $this->faker->randomElement(['TIMEOUT', 'AUTH_FAILED', 'INVALID_DATA']),
                    'last_attempt' => now()->toISOString(),
                ],
            ];
        });
    }

    /**
     * Create a sync status in running state
     * Great for testing long-running operations
     */
    public function running(): static
    {
        return $this->state(function (array $attributes) {
            $total = $this->faker->numberBetween(100, 1000);
            $processed = $this->faker->numberBetween(0, (int)($total * 0.7));

            return [
                'Status' => SyncStatus::RUNNING,
                'LastSyncAttempt' => $this->faker->dateTimeBetween('-30 minutes', 'now'),
                'RecordsProcessed' => $processed,
                'RecordsTotal' => $total,
                'SuccessRate' => $total > 0 ? round(($processed / $total) * 100, 2) : 0,
                'ErrorMessage' => null,
                'Context' => [
                    'started_at' => $this->faker->dateTimeBetween('-30 minutes', 'now')->toISOString(),
                    'current_operation' => $this->faker->randomElement(['validating', 'sending', 'processing']),
                    'estimated_completion' => $this->faker->dateTimeBetween('now', '+15 minutes')->toISOString(),
                ],
            ];
        });
    }

    /**
     * Create a sync status in pending state
     * Useful for testing queue scenarios
     */
    public function pending(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'Status' => SyncStatus::PENDING,
                'LastSyncAttempt' => null,
                'LastSuccessfulSync' => null,
                'RecordsProcessed' => 0,
                'SuccessRate' => 0,
                'RetryCount' => 0,
                'NextRetryAt' => null,
                'ErrorMessage' => null,
            ];
        });
    }

    /**
     * Create sync status that needs retry
     * Perfect for testing retry mechanisms
     */
    public function needsRetry(): static
    {
        return $this->state(function (array $attributes) {
            $maxRetries = $attributes['MaxRetries'] ?? 3;
            $retryCount = $this->faker->numberBetween(1, $maxRetries - 1);

            return [
                'Status' => SyncStatus::FAILED,
                'RetryCount' => $retryCount,
                'MaxRetries' => $maxRetries,
                'AutoRetryEnabled' => true,
                'NextRetryAt' => $this->faker->dateTimeBetween('-5 minutes', '+5 minutes'),
                'ErrorMessage' => 'Temporary connection issue',
                'Context' => [
                    'retry_strategy' => 'exponential_backoff',
                    'next_retry_delay_minutes' => pow(2, $retryCount) * 5,
                ],
            ];
        });
    }

    /**
     * Create sync status with low success rate
     * Good for testing health monitoring
     */
    public function lowSuccessRate(): static
    {
        return $this->state(function (array $attributes) {
            $total = $this->faker->numberBetween(100, 500);
            $processed = $this->faker->numberBetween((int)($total * 0.3), (int)($total * 0.7));

            return [
                'RecordsProcessed' => $processed,
                'RecordsTotal' => $total,
                'SuccessRate' => round(($processed / $total) * 100, 2),
                'LastSuccessfulSync' => $this->faker->dateTimeBetween('-7 days', '-1 day'),
                'Context' => [
                    'performance_issue' => true,
                    'potential_causes' => ['network_instability', 'data_quality', 'system_load'],
                ],
            ];
        });
    }

    /**
     * Create sync status for specific property
     */
    public function forProperty(int $propertyId): static
    {
        return $this->state([
            'PropertyID' => $propertyId,
        ]);
    }

    /**
     * Create sync status for specific message type
     */
    public function forMessageType(MessageType $messageType): static
    {
        return $this->state([
            'MessageType' => $messageType,
        ]);
    }

    /**
     * Create sync status with auto-retry disabled
     */
    public function withoutAutoRetry(): static
    {
        return $this->state([
            'AutoRetryEnabled' => false,
            'NextRetryAt' => null,
        ]);
    }

    /**
     * Create sync status with specific error
     */
    public function withError(string $errorMessage): static
    {
        return $this->state([
            'Status' => SyncStatus::FAILED,
            'ErrorMessage' => $errorMessage,
            'RetryCount' => $this->faker->numberBetween(1, 3),
        ]);
    }

    /**
     * Create batch of sync statuses for a property
     * All message types for comprehensive testing
     */
    public function batchForProperty(int $propertyId): static
    {
        return $this->forProperty($propertyId)
            ->count(count(MessageType::cases()))
            ->sequence(
                ...array_map(fn($type) => ['MessageType' => $type], MessageType::cases())
            );
    }

    /**
     * Create mixed health scenarios for testing dashboards
     */
    public function mixedHealth(): static
    {
        return $this->state(function (array $attributes) {
            $scenarios = [
                // Healthy scenario (40%)
                [
                    'Status' => SyncStatus::COMPLETED,
                    'SuccessRate' => $this->faker->numberBetween(95, 100),
                    'RetryCount' => 0,
                    'LastSuccessfulSync' => $this->faker->dateTimeBetween('-1 day', 'now'),
                ],
                // Warning scenario (30%)
                [
                    'Status' => SyncStatus::COMPLETED,
                    'SuccessRate' => $this->faker->numberBetween(70, 89),
                    'RetryCount' => $this->faker->numberBetween(1, 2),
                    'LastSuccessfulSync' => $this->faker->dateTimeBetween('-3 days', '-1 day'),
                ],
                // Critical scenario (20%)
                [
                    'Status' => SyncStatus::FAILED,
                    'SuccessRate' => $this->faker->numberBetween(30, 69),
                    'RetryCount' => $this->faker->numberBetween(2, 3),
                    'ErrorMessage' => 'Multiple sync failures detected',
                ],
                // Running scenario (10%)
                [
                    'Status' => SyncStatus::RUNNING,
                    'LastSyncAttempt' => $this->faker->dateTimeBetween('-1 hour', 'now'),
                ],
            ];

            $weights = [40, 30, 20, 10];
            $rand = $this->faker->numberBetween(1, 100);

            $cumulative = 0;
            $selectedIndex = 0;

            foreach ($weights as $index => $weight) {
                $cumulative += $weight;
                if ($rand <= $cumulative) {
                    $selectedIndex = $index;
                    break;
                }
            }

            return $scenarios[$selectedIndex];
        });
    }
}
