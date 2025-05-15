<?php

namespace Database\Factories\TravelClick\Factories;

use App\TravelClick\Enums\SyncStatus;
use App\TravelClick\Models\TravelClickPropertyMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * TravelClickPropertyMapping Factory
 *
 * Factory for creating test instances of TravelClickPropertyMapping.
 * This is like having a template generator that can create different
 * types of property mappings for testing various scenarios.
 */
class TravelClickPropertyMappingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = TravelClickPropertyMapping::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $hotelCode = 'HTL' . $this->faker->numberBetween(1000, 9999);
        $propertyId = $this->faker->numberBetween(1, 1000);

        return [
            'PropertyID' => $propertyId,
            'TravelClickHotelCode' => $hotelCode,
            'TravelClickHotelName' => $this->faker->company() . ' Hotel',
            'IsActive' => true,
            'MappingConfiguration' => $this->getDefaultConfiguration(),
            'Notes' => $this->faker->optional(0.3)->paragraph(),
            'LastSyncAt' => $this->faker->optional(0.8)->dateTimeBetween('-7 days', 'now'),
            'SyncStatus' => $this->faker->randomElement(SyncStatus::cases()),
            'LastSyncError' => $this->faker->optional(0.2)->sentence(),
            'SystemUserID' => $this->faker->numberBetween(1, 100),
            'DateCreated' => $this->faker->dateTimeBetween('-30 days', 'now')
        ];
    }

    /**
     * Create an active mapping with successful sync
     */
    public function active(): static
    {
        return $this->state([
            'IsActive' => true,
            'SyncStatus' => SyncStatus::SUCCESS,
            'LastSyncAt' => $this->faker->dateTimeBetween('-24 hours', 'now'),
            'LastSyncError' => null
        ]);
    }

    /**
     * Create an inactive mapping
     */
    public function inactive(): static
    {
        return $this->state([
            'IsActive' => false,
            'SyncStatus' => SyncStatus::INACTIVE,
            'Notes' => 'Deactivated due to hotel closure'
        ]);
    }

    /**
     * Create a mapping with sync errors
     */
    public function withSyncError(): static
    {
        return $this->state([
            'SyncStatus' => SyncStatus::ERROR,
            'LastSyncError' => 'SOAP fault: Authentication failed for hotel code',
            'LastSyncAt' => $this->faker->dateTimeBetween('-24 hours', 'now')
        ]);
    }

    /**
     * Create a mapping that has never been synced
     */
    public function neverSynced(): static
    {
        return $this->state([
            'SyncStatus' => SyncStatus::PENDING,
            'LastSyncAt' => null,
            'LastSyncError' => null
        ]);
    }

    /**
     * Create a mapping with stale sync (over 7 days old)
     */
    public function staleSync(): static
    {
        return $this->state([
            'SyncStatus' => SyncStatus::SUCCESS,
            'LastSyncAt' => $this->faker->dateTimeBetween('-30 days', '-8 days'),
            'LastSyncError' => null
        ]);
    }

    /**
     * Create a mapping currently processing
     */
    public function processing(): static
    {
        return $this->state([
            'SyncStatus' => SyncStatus::PROCESSING,
            'LastSyncAt' => now()->subMinutes(5),
            'LastSyncError' => null
        ]);
    }

    /**
     * Create a mapping with comprehensive configuration
     */
    public function withFullConfiguration(): static
    {
        return $this->state([
            'MappingConfiguration' => [
                'sync_inventory' => true,
                'sync_rates' => true,
                'sync_restrictions' => true,
                'sync_reservations' => true,
                'sync_group_blocks' => true,
                'batch_size' => 100,
                'retry_attempts' => 5,
                'retry_delay_seconds' => 120,
                'timeout_seconds' => 60,
                'notification_emails' => ['admin@hotel.com', 'manager@hotel.com'],
                'custom_room_type_mappings' => [
                    'KING' => 'KNG',
                    'QUEEN' => 'QN',
                    'SUITE' => 'STE'
                ],
                'custom_rate_plan_mappings' => [
                    'BAR' => 'BEST_RATE',
                    'AAA' => 'AAA_DISCOUNT'
                ],
                'exclude_room_types' => ['MAINTENANCE'],
                'exclude_rate_plans' => ['EMPLOYEE_RATE']
            ]
        ]);
    }

    /**
     * Create a mapping with minimal configuration
     */
    public function withMinimalConfiguration(): static
    {
        return $this->state([
            'MappingConfiguration' => [
                'sync_inventory' => true,
                'sync_rates' => false,
                'sync_restrictions' => false,
                'sync_reservations' => true,
                'batch_size' => 20,
                'retry_attempts' => 1
            ]
        ]);
    }

    /**
     * Create a mapping specifically for a property ID
     */
    public function forProperty(int $propertyId): static
    {
        return $this->state([
            'PropertyID' => $propertyId
        ]);
    }

    /**
     * Create a mapping with specific hotel code
     */
    public function withHotelCode(string $hotelCode): static
    {
        return $this->state([
            'TravelClickHotelCode' => $hotelCode,
            'TravelClickHotelName' => "Hotel {$hotelCode}"
        ]);
    }

    /**
     * Create a mapping that failed sync
     */
    public function failedSync(): static
    {
        return $this->state([
            'SyncStatus' => SyncStatus::FAILED,
            'LastSyncError' => 'Connection timeout after 30 seconds',
            'LastSyncAt' => $this->faker->dateTimeBetween('-24 hours', 'now')
        ]);
    }

    /**
     * Create mappings for testing bulk operations
     */
    public function forBulkTesting(): static
    {
        return $this->count(10)->state(function (array $attributes, int $index) {
            return [
                'PropertyID' => 1000 + $index,
                'TravelClickHotelCode' => 'BULK' . str_pad($index, 3, '0', STR_PAD_LEFT),
                'IsActive' => $index % 2 === 0, // Half active, half inactive
                'SyncStatus' => $index < 5 ? SyncStatus::SUCCESS : SyncStatus::ERROR
            ];
        });
    }

    /**
     * Get default configuration for testing
     */
    private function getDefaultConfiguration(): array
    {
        return [
            'sync_inventory' => $this->faker->boolean(80),
            'sync_rates' => $this->faker->boolean(70),
            'sync_restrictions' => $this->faker->boolean(60),
            'sync_reservations' => $this->faker->boolean(90),
            'sync_group_blocks' => $this->faker->boolean(40),
            'batch_size' => $this->faker->numberBetween(20, 100),
            'retry_attempts' => $this->faker->numberBetween(1, 5),
            'retry_delay_seconds' => $this->faker->numberBetween(30, 300),
            'timeout_seconds' => $this->faker->numberBetween(15, 120),
            'notification_emails' => $this->faker->optional(0.5)->randomElements([
                'admin@example.com',
                'manager@example.com',
                'tech@example.com'
            ], $this->faker->numberBetween(1, 3)),
            'custom_room_type_mappings' => $this->faker->optional(0.3)->randomElements([
                'KING' => 'K',
                'QUEEN' => 'Q',
                'SUITE' => 'S',
                'DELUXE' => 'DX'
            ], $this->faker->numberBetween(1, 3)),
            'exclude_room_types' => $this->faker->optional(0.2)->randomElements([
                'MAINTENANCE',
                'BLOCKED',
                'TEMP'
            ], $this->faker->numberBetween(1, 2))
        ];
    }
}
