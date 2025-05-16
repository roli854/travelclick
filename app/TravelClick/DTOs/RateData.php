<?php

declare(strict_types=1);

namespace App\TravelClick\DTOs;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Rate data structure for TravelClick HTNG 2011B integration
 *
 * This DTO represents a single rate structure that can be sent to TravelClick.
 * Think of it as a "rate card" that contains all the pricing information
 * for a specific room type and date range.
 *
 * Key requirements from HTNG 2011B specification:
 * - Must include rates for 1st and 2nd adults (mandatory for certification)
 * - Can include additional adult and child rates
 * - Supports various optional attributes for advanced rate management
 */
class RateData
{
  /**
   * Rate for the first adult (mandatory)
   * This is the base rate that must always be present
   */
  public readonly float $firstAdultRate;

  /**
   * Rate for the second adult (mandatory)
   * Required for certification even if same as first adult
   */
  public readonly float $secondAdultRate;

  /**
   * Rate for additional adults beyond the second (optional)
   * Used when 3rd, 4th+ adults have the same additional rate
   */
  public readonly ?float $additionalAdultRate;

  /**
   * Rate for additional children (optional)
   * Applied to children beyond included occupancy
   */
  public readonly ?float $additionalChildRate;

  /**
   * Currency code (ISO 3166 format)
   * Default pulled from configuration if not specified
   */
  public readonly string $currencyCode;

  /**
   * Start date for this rate (inclusive)
   */
  public readonly Carbon $startDate;

  /**
   * End date for this rate (inclusive)
   */
  public readonly Carbon $endDate;

  /**
   * Room type code this rate applies to
   */
  public readonly string $roomTypeCode;

  /**
   * Rate plan code this rate belongs to
   */
  public readonly string $ratePlanCode;

  /**
   * Whether this rate has restricted display (optional)
   * Used for special rates that shouldn't be publicly shown
   */
  public readonly ?bool $restrictedDisplayIndicator;

  /**
   * Whether this rate is commissionable (optional)
   * Important for travel agent bookings
   */
  public readonly ?bool $isCommissionable;

  /**
   * Rate plan qualifier (optional)
   * Additional categorization for the rate
   */
  public readonly ?string $ratePlanQualifier;

  /**
   * Market code (optional)
   * Associates rate with specific market segment
   */
  public readonly ?string $marketCode;

  /**
   * Maximum number of guests this rate applies to (optional)
   * Helps TravelClick understand occupancy limits
   */
  public readonly ?int $maxGuestApplicable;

  /**
   * Whether this is a linked rate (derived from master)
   * Linked rates should not be sent if external system handles them
   */
  public readonly bool $isLinkedRate;

  /**
   * Master rate plan code if this is a linked rate
   */
  public readonly ?string $masterRatePlanCode;

  /**
   * Offset amount for linked rates (can be positive or negative)
   */
  public readonly ?float $linkedRateOffset;

  /**
   * Offset percentage for linked rates (e.g., -10 for 10% discount)
   */
  public readonly ?float $linkedRatePercentage;

  public function __construct(
    float $firstAdultRate,
    float $secondAdultRate,
    string $roomTypeCode,
    string $ratePlanCode,
    Carbon|string $startDate,
    Carbon|string $endDate,
    ?float $additionalAdultRate = null,
    ?float $additionalChildRate = null,
    ?string $currencyCode = null,
    ?bool $restrictedDisplayIndicator = null,
    ?bool $isCommissionable = null,
    ?string $ratePlanQualifier = null,
    ?string $marketCode = null,
    ?int $maxGuestApplicable = null,
    bool $isLinkedRate = false,
    ?string $masterRatePlanCode = null,
    ?float $linkedRateOffset = null,
    ?float $linkedRatePercentage = null
  ) {
    // Validate mandatory rates
    $this->validateRate($firstAdultRate, 'first adult');
    $this->validateRate($secondAdultRate, 'second adult');

    // Validate room type code
    $this->validateRoomTypeCode($roomTypeCode);

    // Validate rate plan code
    $this->validateRatePlanCode($ratePlanCode);

    // Convert string dates to Carbon if needed
    $this->startDate = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
    $this->endDate = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

    // Validate date range
    $this->validateDateRange($this->startDate, $this->endDate);

    // Validate linked rate logic
    if ($isLinkedRate) {
      $this->validateLinkedRate($masterRatePlanCode, $linkedRateOffset, $linkedRatePercentage);
    }

    // Assign validated values
    $this->firstAdultRate = $firstAdultRate;
    $this->secondAdultRate = $secondAdultRate;
    $this->roomTypeCode = $roomTypeCode;
    $this->ratePlanCode = $ratePlanCode;
    $this->additionalAdultRate = $additionalAdultRate;
    $this->additionalChildRate = $additionalChildRate;
    $this->currencyCode = $currencyCode ?? config('travelclick.default_currency', 'USD');
    $this->restrictedDisplayIndicator = $restrictedDisplayIndicator;
    $this->isCommissionable = $isCommissionable;
    $this->ratePlanQualifier = $ratePlanQualifier;
    $this->marketCode = $marketCode;
    $this->maxGuestApplicable = $maxGuestApplicable;
    $this->isLinkedRate = $isLinkedRate;
    $this->masterRatePlanCode = $masterRatePlanCode;
    $this->linkedRateOffset = $linkedRateOffset;
    $this->linkedRatePercentage = $linkedRatePercentage;
  }

  /**
   * Create RateData from array
   * Useful when receiving data from API requests or database
   */
  public static function fromArray(array $data): self
  {
    return new self(
      firstAdultRate: (float) $data['first_adult_rate'],
      secondAdultRate: (float) $data['second_adult_rate'],
      roomTypeCode: $data['room_type_code'],
      ratePlanCode: $data['rate_plan_code'],
      startDate: $data['start_date'],
      endDate: $data['end_date'],
      additionalAdultRate: isset($data['additional_adult_rate']) ? (float) $data['additional_adult_rate'] : null,
      additionalChildRate: isset($data['additional_child_rate']) ? (float) $data['additional_child_rate'] : null,
      currencyCode: $data['currency_code'] ?? null,
      restrictedDisplayIndicator: $data['restricted_display_indicator'] ?? null,
      isCommissionable: $data['is_commissionable'] ?? null,
      ratePlanQualifier: $data['rate_plan_qualifier'] ?? null,
      marketCode: $data['market_code'] ?? null,
      maxGuestApplicable: $data['max_guest_applicable'] ?? null,
      isLinkedRate: $data['is_linked_rate'] ?? false,
      masterRatePlanCode: $data['master_rate_plan_code'] ?? null,
      linkedRateOffset: isset($data['linked_rate_offset']) ? (float) $data['linked_rate_offset'] : null,
      linkedRatePercentage: isset($data['linked_rate_percentage']) ? (float) $data['linked_rate_percentage'] : null,
    );
  }

  /**
   * Convert to array format suitable for XML building
   */
  public function toArray(): array
  {
    $baseRates = [
      '_attributes' => [
        'NumberOfGuests' => '1',
        'AmountBeforeTax' => number_format($this->firstAdultRate, 2, '.', ''),
      ],
    ];

    $secondAdultRates = [
      '_attributes' => [
        'NumberOfGuests' => '2',
        'AmountBeforeTax' => number_format($this->secondAdultRate, 2, '.', ''),
      ],
    ];

    $rates = [
      'BaseByGuestAmts' => [
        'BaseByGuestAmt' => [$baseRates, $secondAdultRates],
      ],
    ];

    // Add additional rates if present
    if ($this->additionalAdultRate !== null || $this->additionalChildRate !== null) {
      $additionalAmounts = [];

      if ($this->additionalAdultRate !== null) {
        $additionalAmounts[] = [
          '_attributes' => [
            'AgeQualifyingCode' => '10', // Adult
            'Amount' => number_format($this->additionalAdultRate, 2, '.', ''),
          ],
        ];
      }

      if ($this->additionalChildRate !== null) {
        $additionalAmounts[] = [
          '_attributes' => [
            'AgeQualifyingCode' => '8', // Child
            'Amount' => number_format($this->additionalChildRate, 2, '.', ''),
          ],
        ];
      }

      $rates['AdditionalGuestAmounts'] = [
        'AdditionalGuestAmount' => $additionalAmounts,
      ];
    }

    return $rates;
  }

  /**
   * Convert to XML attributes for RatePlan element
   */
  public function toXmlAttributes(): array
  {
    $attributes = [
      'RatePlanCode' => $this->ratePlanCode,
      'Start' => $this->startDate->format('Y-m-d'),
      'End' => $this->endDate->format('Y-m-d'),
    ];

    // Add optional attributes
    if ($this->maxGuestApplicable !== null) {
      $attributes['MaxGuestApplicable'] = (string) $this->maxGuestApplicable;
    }

    if ($this->restrictedDisplayIndicator !== null) {
      $attributes['RestrictedDisplayIndicator'] = $this->restrictedDisplayIndicator ? 'true' : 'false';
    }

    if ($this->isCommissionable !== null) {
      $attributes['IsCommissionable'] = $this->isCommissionable ? 'true' : 'false';
    }

    if ($this->ratePlanQualifier !== null) {
      $attributes['RatePlanQualifier'] = $this->ratePlanQualifier;
    }

    if ($this->marketCode !== null) {
      $attributes['MarketCode'] = $this->marketCode;
    }

    return $attributes;
  }

  /**
   * Check if this rate is valid for the given date
   */
  public function isValidForDate(Carbon $date): bool
  {
    return $date->between($this->startDate, $this->endDate);
  }

  /**
   * Get the rate amount for a specific number of guests
   * Useful for calculations and validations
   */
  public function getRateForGuests(int $guests): float
  {
    return match ($guests) {
      1 => $this->firstAdultRate,
      2 => $this->secondAdultRate,
      default => $this->secondAdultRate + (($guests - 2) * ($this->additionalAdultRate ?? 0)),
    };
  }

  /**
   * Check if this rate equals another rate (for deduplication)
   */
  public function equals(RateData $other): bool
  {
    return $this->firstAdultRate === $other->firstAdultRate
      && $this->secondAdultRate === $other->secondAdultRate
      && $this->roomTypeCode === $other->roomTypeCode
      && $this->ratePlanCode === $other->ratePlanCode
      && $this->startDate->eq($other->startDate)
      && $this->endDate->eq($other->endDate);
  }

  /**
   * Create a copy with modified dates
   * Useful for splitting rates across date ranges
   */
  public function withDateRange(Carbon $startDate, Carbon $endDate): self
  {
    return new self(
      firstAdultRate: $this->firstAdultRate,
      secondAdultRate: $this->secondAdultRate,
      roomTypeCode: $this->roomTypeCode,
      ratePlanCode: $this->ratePlanCode,
      startDate: $startDate,
      endDate: $endDate,
      additionalAdultRate: $this->additionalAdultRate,
      additionalChildRate: $this->additionalChildRate,
      currencyCode: $this->currencyCode,
      restrictedDisplayIndicator: $this->restrictedDisplayIndicator,
      isCommissionable: $this->isCommissionable,
      ratePlanQualifier: $this->ratePlanQualifier,
      marketCode: $this->marketCode,
      maxGuestApplicable: $this->maxGuestApplicable,
      isLinkedRate: $this->isLinkedRate,
      masterRatePlanCode: $this->masterRatePlanCode,
      linkedRateOffset: $this->linkedRateOffset,
      linkedRatePercentage: $this->linkedRatePercentage,
    );
  }

  /**
   * Validate rate amount
   */
  private function validateRate(float $rate, string $type): void
  {
    if ($rate < 0) {
      throw new InvalidArgumentException("Rate for {$type} cannot be negative");
    }

    // Check for reasonable maximum (configurable)
    $maxRate = config('travelclick.validation.max_rate_amount', 999999.99);
    if ($rate > $maxRate) {
      throw new InvalidArgumentException("Rate for {$type} cannot exceed {$maxRate}");
    }
  }

  /**
   * Validate room type code format
   */
  private function validateRoomTypeCode(string $roomTypeCode): void
  {
    $validation = config('travelclick.validation.room_type_code');

    if (strlen($roomTypeCode) < $validation['min_length'] || strlen($roomTypeCode) > $validation['max_length']) {
      throw new InvalidArgumentException(
        "Room type code length must be between {$validation['min_length']} and {$validation['max_length']} characters"
      );
    }

    if (!preg_match($validation['pattern'], $roomTypeCode)) {
      throw new InvalidArgumentException("Room type code contains invalid characters");
    }
  }

  /**
   * Validate rate plan code format
   */
  private function validateRatePlanCode(string $ratePlanCode): void
  {
    $validation = config('travelclick.validation.rate_plan_code');

    if (strlen($ratePlanCode) < $validation['min_length'] || strlen($ratePlanCode) > $validation['max_length']) {
      throw new InvalidArgumentException(
        "Rate plan code length must be between {$validation['min_length']} and {$validation['max_length']} characters"
      );
    }

    if (!preg_match($validation['pattern'], $ratePlanCode)) {
      throw new InvalidArgumentException("Rate plan code contains invalid characters");
    }
  }

  /**
   * Validate date range
   */
  private function validateDateRange(Carbon $startDate, Carbon $endDate): void
  {
    if ($endDate->lte($startDate)) {
      throw new InvalidArgumentException("End date must be after start date");
    }

    // Check for reasonable date range (configurable, default 2 years)
    $maxDaysInFuture = config('travelclick.validation.max_rate_days_in_future', 730);
    $maxFutureDate = now()->addDays($maxDaysInFuture);

    if ($endDate->gt($maxFutureDate)) {
      throw new InvalidArgumentException("Rate end date cannot be more than {$maxDaysInFuture} days in the future");
    }
  }

  /**
   * Validate linked rate configuration
   */
  private function validateLinkedRate(?string $masterRatePlanCode, ?float $offset, ?float $percentage): void
  {
    if ($masterRatePlanCode === null) {
      throw new InvalidArgumentException("Master rate plan code is required for linked rates");
    }

    if ($offset === null && $percentage === null) {
      throw new InvalidArgumentException("Either offset or percentage must be specified for linked rates");
    }

    if ($offset !== null && $percentage !== null) {
      throw new InvalidArgumentException("Cannot specify both offset and percentage for linked rates");
    }

    if ($percentage !== null && ($percentage <= -100 || $percentage >= 100)) {
      throw new InvalidArgumentException("Linked rate percentage must be between -100 and 100");
    }
  }
}
