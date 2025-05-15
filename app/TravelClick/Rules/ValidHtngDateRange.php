<?php

declare(strict_types=1);

namespace App\TravelClick\Rules;

use App\TravelClick\Support\ValidationRulesHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * ValidHtngDateRange
 *
 * Custom validation rule for HTNG 2011B date ranges.
 * Validates start and end dates according to HTNG specifications, ensuring proper
 * format, logical ordering, and business rule compliance.
 *
 * Features:
 * - Validates ISO 8601 date formats
 * - Ensures end date is after start date
 * - Enforces maximum date range limits
 * - Validates against past/future date restrictions
 * - Supports configurable validation options
 */
class ValidHtngDateRange implements ValidationRule
{
    /**
     * Default validation options
     */
    private array $options = [
        'max_days' => ValidationRulesHelper::MAX_DATE_RANGE_DAYS,
        'allow_past_dates' => true,
        'max_future_years' => ValidationRulesHelper::MAX_FUTURE_BOOKING_YEARS,
        'require_end_date' => true,
        'allow_same_date' => true,
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
     * @param mixed $value The value to validate (expected to be array with 'start' and 'end' keys)
     * @param Closure(string): PotentiallyTranslatedString $fail The failure callback
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Validate input structure
        if (!is_array($value)) {
            $fail('The :attribute must be an array with start and end dates.');
            return;
        }

        // Extract start and end dates
        $startDate = $value['start'] ?? $value['start_date'] ?? '';
        $endDate = $value['end'] ?? $value['end_date'] ?? '';

        // Handle empty values
        if (empty($startDate)) {
            $fail('The start date is required.');
            return;
        }

        if (empty($endDate) && $this->options['require_end_date']) {
            $fail('The end date is required.');
            return;
        }

        // Use ValidationRulesHelper for comprehensive validation
        $validation = ValidationRulesHelper::validateDateRange(
            $startDate,
            $endDate,
            $this->options
        );

        // Handle validation errors
        if (!$validation['valid']) {
            foreach ($validation['errors'] as $error) {
                $fail($error);
            }
            return;
        }

        // Additional business logic validations
        $this->validateBusinessRules($validation['start'], $validation['end'], $fail);
    }

    /**
     * Validate business-specific rules for date ranges
     *
     * @param \Carbon\Carbon $startDate Parsed start date
     * @param \Carbon\Carbon $endDate Parsed end date
     * @param Closure $fail The failure callback
     */
    private function validateBusinessRules(
        \Carbon\Carbon $startDate,
        \Carbon\Carbon $endDate,
        Closure $fail
    ): void {
        // Check if dates are the same when not allowed
        if (!$this->options['allow_same_date'] && $startDate->isSameDay($endDate)) {
            $fail('Start and end dates must be different.');
            return;
        }

        // Validate weekend restrictions if configured
        if (isset($this->options['exclude_weekends']) && $this->options['exclude_weekends']) {
            if ($startDate->isWeekend()) {
                $fail('Start date cannot be on a weekend.');
                return;
            }

            if ($endDate->isWeekend()) {
                $fail('End date cannot be on a weekend.');
                return;
            }
        }

        // Validate specific days of week if configured
        if (isset($this->options['allowed_days'])) {
            $allowedDays = $this->options['allowed_days'];

            if (!in_array($startDate->dayOfWeek, $allowedDays)) {
                $fail('Start date is not on an allowed day of the week.');
                return;
            }

            if (!in_array($endDate->dayOfWeek, $allowedDays)) {
                $fail('End date is not on an allowed day of the week.');
                return;
            }
        }

        // Validate blackout dates if configured
        if (isset($this->options['blackout_dates'])) {
            foreach ($this->options['blackout_dates'] as $blackoutDate) {
                $blackout = \Carbon\Carbon::parse($blackoutDate);

                if ($startDate->isSameDay($blackout)) {
                    $fail("Start date conflicts with blackout date ({$blackout->format('Y-m-d')}).");
                    return;
                }

                if ($endDate->isSameDay($blackout)) {
                    $fail("End date conflicts with blackout date ({$blackout->format('Y-m-d')}).");
                    return;
                }

                // Check if blackout falls within the range
                if ($blackout->between($startDate, $endDate)) {
                    $fail("Date range includes blackout date ({$blackout->format('Y-m-d')}).");
                    return;
                }
            }
        }

        // Validate minimum stay requirements if configured
        if (isset($this->options['min_stay_days'])) {
            $stayDays = $startDate->diffInDays($endDate);
            if ($stayDays < $this->options['min_stay_days']) {
                $fail("Minimum stay of {$this->options['min_stay_days']} day(s) required.");
                return;
            }
        }

        // Validate maximum advance booking if configured
        if (isset($this->options['max_advance_days'])) {
            $advanceDays = now()->diffInDays($startDate);
            if ($advanceDays > $this->options['max_advance_days']) {
                $fail("Cannot book more than {$this->options['max_advance_days']} days in advance.");
                return;
            }
        }
    }

    /**
     * Create instance for inventory date range validation
     *
     * @return static
     */
    public static function forInventory(): static
    {
        return new static([
            'max_days' => 365,
            'allow_past_dates' => false,
            'max_future_years' => 2,
            'require_end_date' => true,
            'allow_same_date' => true,
        ]);
    }

    /**
     * Create instance for rate date range validation
     *
     * @return static
     */
    public static function forRates(): static
    {
        return new static([
            'max_days' => 365,
            'allow_past_dates' => false,
            'max_future_years' => 2,
            'require_end_date' => true,
            'allow_same_date' => false, // Rates typically need a range
        ]);
    }

    /**
     * Create instance for reservation date range validation
     *
     * @return static
     */
    public static function forReservation(): static
    {
        return new static([
            'max_days' => 30, // Shorter max stay for individual reservations
            'allow_past_dates' => false,
            'max_future_years' => 1,
            'require_end_date' => true,
            'allow_same_date' => false, // Check-in and check-out must be different
            'min_stay_days' => 1,
        ]);
    }

    /**
     * Create instance for group block date range validation
     *
     * @return static
     */
    public static function forGroupBlock(): static
    {
        return new static([
            'max_days' => 60, // Longer stays allowed for groups
            'allow_past_dates' => false,
            'max_future_years' => 2,
            'require_end_date' => true,
            'allow_same_date' => false,
            'min_stay_days' => 1,
        ]);
    }

    /**
     * Create instance for restriction date range validation
     *
     * @return static
     */
    public static function forRestrictions(): static
    {
        return new static([
            'max_days' => 365,
            'allow_past_dates' => false,
            'max_future_years' => 2,
            'require_end_date' => true,
            'allow_same_date' => true,
        ]);
    }

    /**
     * Create instance with custom blackout dates
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
     * Create instance with minimum stay requirement
     *
     * @param int $minStayDays Minimum number of days required
     * @param array $additionalOptions Additional validation options
     * @return static
     */
    public static function withMinimumStay(int $minStayDays, array $additionalOptions = []): static
    {
        return new static(array_merge($additionalOptions, [
            'min_stay_days' => $minStayDays,
        ]));
    }

    /**
     * Create instance with maximum advance booking restriction
     *
     * @param int $maxAdvanceDays Maximum days in advance booking is allowed
     * @param array $additionalOptions Additional validation options
     * @return static
     */
    public static function withMaxAdvanceBooking(int $maxAdvanceDays, array $additionalOptions = []): static
    {
        return new static(array_merge($additionalOptions, [
            'max_advance_days' => $maxAdvanceDays,
        ]));
    }

    /**
     * Get the validation error message
     *
     * @return string
     */
    public function message(): string
    {
        return 'The :attribute must be a valid HTNG date range.';
    }
}
