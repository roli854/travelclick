<?php

declare(strict_types=1);

namespace App\TravelClick\Rules;

use App\TravelClick\Support\ValidationRulesHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * ValidHtngDate
 *
 * Custom validation rule for individual HTNG 2011B dates.
 * Validates single dates according to HTNG specifications and business rules.
 *
 * Features:
 * - Validates ISO 8601 date formats
 * - Supports HTNG-specific date formats
 * - Enforces business rules for past/future dates
 * - Configurable validation options
 */
class ValidHtngDate implements ValidationRule
{
    /**
     * Default validation options
     */
    private array $options = [
        'allow_past_dates' => true,
        'max_future_years' => ValidationRulesHelper::MAX_FUTURE_BOOKING_YEARS,
        'allow_null' => false,
    ];

    /**
     * Create a new rule instance
     *
     * @param array $options Custom validation options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Run the validation rule
     *
     * @param string $attribute The attribute being validated
     * @param mixed $value The value to validate
     * @param Closure(string): PotentiallyTranslatedString $fail The failure callback
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Handle null/empty values
        if (empty($value)) {
            if (!$this->options['allow_null']) {
                $fail('The :attribute date is required.');
            }
            return;
        }

        // Validate using ValidationRulesHelper
        $result = ValidationRulesHelper::validateAndParseHtngDate($value, $this->options['allow_null']);

        if (!$result['valid']) {
            $fail($result['error']);
            return;
        }

        $date = $result['date'];

        // Validate past dates
        if (!$this->options['allow_past_dates'] && $date->isPast()) {
            $fail('The :attribute date cannot be in the past.');
            return;
        }

        // Validate future limit
        $maxFutureDate = now()->addYears($this->options['max_future_years']);
        if ($date->isAfter($maxFutureDate)) {
            $fail("The :attribute date exceeds maximum future limit ({$this->options['max_future_years']} years).");
            return;
        }

        // Additional business rule validations
        $this->validateBusinessRules($date, $fail);
    }

    /**
     * Validate business-specific rules for the date
     *
     * @param \Carbon\Carbon $date Parsed date
     * @param Closure $fail The failure callback
     */
    private function validateBusinessRules(\Carbon\Carbon $date, Closure $fail): void
    {
        // Validate weekend restrictions if configured
        if (isset($this->options['exclude_weekends']) && $this->options['exclude_weekends']) {
            if ($date->isWeekend()) {
                $fail('The :attribute date cannot be on a weekend.');
                return;
            }
        }

        // Validate specific days of week if configured
        if (isset($this->options['allowed_days'])) {
            if (!in_array($date->dayOfWeek, $this->options['allowed_days'])) {
                $fail('The :attribute date is not on an allowed day of the week.');
                return;
            }
        }

        // Validate blackout dates if configured
        if (isset($this->options['blackout_dates'])) {
            foreach ($this->options['blackout_dates'] as $blackoutDate) {
                $blackout = \Carbon\Carbon::parse($blackoutDate);
                if ($date->isSameDay($blackout)) {
                    $fail("The :attribute date conflicts with blackout date ({$blackout->format('Y-m-d')}).");
                    return;
                }
            }
        }

        // Validate minimum advance booking if configured
        if (isset($this->options['min_advance_days'])) {
            $advanceDays = now()->diffInDays($date, false);
            if ($advanceDays < $this->options['min_advance_days']) {
                $fail("The :attribute date must be at least {$this->options['min_advance_days']} days in advance.");
                return;
            }
        }

        // Validate maximum advance booking if configured
        if (isset($this->options['max_advance_days'])) {
            $advanceDays = now()->diffInDays($date);
            if ($advanceDays > $this->options['max_advance_days']) {
                $fail("The :attribute date cannot be more than {$this->options['max_advance_days']} days in advance.");
                return;
            }
        }
    }

    /**
     * Create instance for arrival dates
     *
     * @return static
     */
    public static function forArrival(): static
    {
        return new static([
            'allow_past_dates' => false,
            'max_future_years' => 1,
            'min_advance_days' => 0, // Can arrive today
        ]);
    }

    /**
     * Create instance for departure dates
     *
     * @return static
     */
    public static function forDeparture(): static
    {
        return new static([
            'allow_past_dates' => false,
            'max_future_years' => 1,
            'min_advance_days' => 1, // Must be at least tomorrow
        ]);
    }

    /**
     * Create instance for booking dates
     *
     * @return static
     */
    public static function forBooking(): static
    {
        return new static([
            'allow_past_dates' => true, // Booking date can be in past
            'max_future_years' => 1,
        ]);
    }

    /**
     * Create instance for cancellation cutoff dates
     *
     * @return static
     */
    public static function forCancellationCutoff(): static
    {
        return new static([
            'allow_past_dates' => false,
            'max_future_years' => 2,
        ]);
    }

    /**
     * Create instance for inventory sync dates
     *
     * @return static
     */
    public static function forInventorySync(): static
    {
        return new static([
            'allow_past_dates' => false,
            'max_future_years' => 2,
            'max_advance_days' => 365,
        ]);
    }

    /**
     * Create instance with blackout dates
     *
     * @param array $blackoutDates Array of blackout dates (Y-m-d format)
     * @param array $additionalOptions Additional validation options
     * @return static
     */
    public static function withBlackoutDates(array $blackoutDates, array $additionalOptions = []): static
    {
        return new static(array_merge($additionalOptions, [
            'blackout_dates' => $blackoutDates,
        ]));
    }

    /**
     * Create instance with weekend restrictions
     *
     * @param bool $excludeWeekends Whether to exclude weekends
     * @param array $additionalOptions Additional validation options
     * @return static
     */
    public static function withWeekendRestrictions(bool $excludeWeekends = true, array $additionalOptions = []): static
    {
        return new static(array_merge($additionalOptions, [
            'exclude_weekends' => $excludeWeekends,
        ]));
    }

    /**
     * Create instance with specific allowed days
     *
     * @param array $allowedDays Array of allowed day numbers (0=Sunday, 1=Monday, etc.)
     * @param array $additionalOptions Additional validation options
     * @return static
     */
    public static function withAllowedDays(array $allowedDays, array $additionalOptions = []): static
    {
        return new static(array_merge($additionalOptions, [
            'allowed_days' => $allowedDays,
        ]));
    }

    /**
     * Get the validation error message
     *
     * @return string
     */
    public function message(): string
    {
        return 'The :attribute must be a valid HTNG date.';
    }
}
