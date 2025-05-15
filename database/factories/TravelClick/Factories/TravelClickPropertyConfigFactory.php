<?php

namespace Database\Factories\TravelClick\Factories;

use App\TravelClick\Models\TravelClickPropertyConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\TravelClick\Models\TravelClickPropertyConfig>
 */
class TravelClickPropertyConfigFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TravelClickPropertyConfig::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->faker->unique()->numberBetween(1000, 9999),
            'config' => [
                'hotel_code' => $this->faker->numerify('######'),
                'credentials' => [
                    'username' => $this->faker->userName(),
                    'password' => $this->faker->password(8, 20),
                ],
                'features' => [
                    'inventory' => $this->faker->boolean(80),
                    'rates' => $this->faker->boolean(80),
                    'restrictions' => $this->faker->boolean(60),
                    'reservations' => $this->faker->boolean(90),
                    'group_blocks' => $this->faker->boolean(40),
                ],
                'sync_settings' => [
                    'batch_size' => $this->faker->randomElement([50, 100, 200]),
                    'retry_attempts' => $this->faker->numberBetween(1, 5),
                    'sync_interval' => $this->faker->randomElement([300, 600, 900, 1800]), // seconds
                ],
                'endpoints' => [
                    'custom_production' => $this->faker->boolean(20) ? $this->faker->url() : null,
                    'custom_test' => $this->faker->boolean(30) ? $this->faker->url() : null,
                ],
            ],
            'is_active' => $this->faker->boolean(85),
            'last_sync_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 week', 'now'),
        ];
    }

    /**
     * Indicate that the property configuration is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the property configuration is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a configuration with minimal required fields only.
     */
    public function minimal(): static
    {
        return $this->state(fn(array $attributes) => [
            'config' => [
                'hotel_code' => $this->faker->numerify('######'),
                'credentials' => [
                    'username' => $this->faker->userName(),
                    'password' => $this->faker->password(8, 20),
                ],
            ],
        ]);
    }

    /**
     * Create a configuration with all features enabled.
     */
    public function fullFeatures(): static
    {
        return $this->state(function (array $attributes) {
            $config = $attributes['config'] ?? [];
            $config['features'] = [
                'inventory' => true,
                'rates' => true,
                'restrictions' => true,
                'reservations' => true,
                'group_blocks' => true,
            ];
            return ['config' => $config];
        });
    }

    /**
     * Create a configuration with custom hotel code.
     */
    public function withHotelCode(string $hotelCode): static
    {
        return $this->state(function (array $attributes) use ($hotelCode) {
            $config = $attributes['config'] ?? [];
            $config['hotel_code'] = $hotelCode;
            return ['config' => $config];
        });
    }

    /**
     * Create a configuration with custom credentials.
     */
    public function withCredentials(string $username, string $password): static
    {
        return $this->state(function (array $attributes) use ($username, $password) {
            $config = $attributes['config'] ?? [];
            $config['credentials'] = [
                'username' => $username,
                'password' => $password,
            ];
            return ['config' => $config];
        });
    }

    /**
     * Create a configuration that hasn't been synced recently.
     */
    public function needsSync(): static
    {
        return $this->state(fn(array $attributes) => [
            'last_sync_at' => $this->faker->dateTimeBetween('-2 weeks', '-1 day'),
        ]);
    }

    /**
     * Create a configuration with health status tracking.
     */
    public function withHealthStatus(bool $healthy = true): static
    {
        return $this->state(function (array $attributes) use ($healthy) {
            $config = $attributes['config'] ?? [];
            $config['health_status'] = [
                'healthy' => $healthy,
                'last_check' => now()->toISOString(),
                'last_healthy' => $healthy ? now()->toISOString() : now()->subHours(24)->toISOString(),
            ];
            return ['config' => $config];
        });
    }

    /**
     * Create a configuration with custom sync settings.
     */
    public function withSyncSettings(array $settings): static
    {
        return $this->state(function (array $attributes) use ($settings) {
            $config = $attributes['config'] ?? [];
            $config['sync_settings'] = array_merge(
                $config['sync_settings'] ?? [],
                $settings
            );
            return ['config' => $config];
        });
    }

    /**
     * Create a configuration with testing endpoints.
     */
    public function withTestEndpoints(): static
    {
        return $this->state(function (array $attributes) {
            $config = $attributes['config'] ?? [];
            $config['endpoints'] = [
                'custom_production' => null,
                'custom_test' => 'https://test-api.example.com/htng',
                'timeout' => 60,
                'ssl_verify' => false,
            ];
            return ['config' => $config];
        });
    }

    /**
     * Create a configuration with validation errors (for testing validation).
     */
    public function withValidationErrors(): static
    {
        return $this->state(fn(array $attributes) => [
            'config' => [
                'hotel_code' => '', // Empty hotel code
                'credentials' => [
                    'username' => '', // Empty username
                    // Missing password
                ],
                'features' => [
                    'invalid_feature' => true, // Invalid feature
                ],
            ],
        ]);
    }

    /**
     * Create a configuration for a specific property ID.
     */
    public function forProperty(int $propertyId): static
    {
        return $this->state(fn(array $attributes) => [
            'property_id' => $propertyId,
        ]);
    }

    /**
     * Create a configuration with recent activity.
     */
    public function recentlyActive(): static
    {
        return $this->state(fn(array $attributes) => [
            'last_sync_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
