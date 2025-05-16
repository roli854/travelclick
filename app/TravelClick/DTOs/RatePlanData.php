<?php

declare(strict_types=1);

namespace App\TravelClick\DTOs;

use App\TravelClick\Enums\RateOperationType;
use Carbon\Carbon;
use InvalidArgumentException;
use Illuminate\Support\Collection;

/**
 * Rate plan data structure for TravelClick HTNG 2011B integration
 *
 * This DTO represents a complete rate plan that can contain multiple rates
 * across different date ranges and room types. Think of it as a "rate book"
 * that contains all the pricing information for a specific rate plan.
 *
 * A rate plan might have:
 * - Different rates for different dates (seasonal pricing)
 * - Different rates for different room types
 * - Linked rates derived from a master rate
 */
class RatePlanData
{
  /**
   * Rate plan code (unique identifier)
   */
  public readonly string $ratePlanCode;

  /**
   * Hotel code this rate plan belongs to
   */
  public readonly string $hotelCode;

  /**
   * Type of operation to perform on this rate plan
   */
  public readonly RateOperationType $operationType;

  /**
   * Collection of individual rates belonging to this plan
   * @var Collection<RateData>
   */
  public readonly Collection $rates;

  /**
   * Overall start date for the rate plan
   * (minimum start date from all rates)
   */
  public readonly Carbon $startDate;

  /**
   * Overall end date for the rate plan
   * (maximum end date from all rates)
   */
  public readonly Carbon $endDate;

  /**
   * Rate plan name/description (optional)
   */
  public readonly ?string $ratePlanName;

  /**
   * Currency code for all rates in this plan
   */
  public readonly string $currencyCode;

  /**
   * Whether this is a linked rate plan (derived from master)
   */
  public readonly bool $isLinkedRate;

  /**
   * Master rate plan code if this is linked
   */
  public readonly ?string $masterRatePlanCode;

  /**
   * Room types this rate plan applies to
   * @var Collection<string>
   */
  public readonly Collection $roomTypes;

  /**
   * Maximum number of guests this rate plan supports
   */
  public readonly ?int $maxGuestApplicable;

  /**
   * Whether this rate plan is commissionable
   */
  public readonly ?bool $isCommissionable;

  /**
   * Market codes associated with this rate plan
   * @var Collection<string>
   */
  public readonly Collection $marketCodes;

  /**
   * Whether to send this as a delta update (only changes)
   * or full synchronization
   */
  public readonly bool $isDeltaUpdate;

  /**
   * Timestamp when this rate plan was last modified
   * Used for delta update logic
   */
  public readonly ?Carbon $lastModified;

  public function __construct(
    string $ratePlanCode,
    string $hotelCode,
    RateOperationType $operationType,
    array|Collection $rates,
    ?string $ratePlanName = null,
    ?string $currencyCode = null,
    bool $isLinkedRate = false,
    ?string $masterRatePlanCode = null,
    ?int $maxGuestApplicable = null,
    ?bool $isCommissionable = null,
    array $marketCodes = [],
    bool $isDeltaUpdate = true,
    ?Carbon $lastModified = null
  ) {
    // Validate basic inputs
    $this->validateRatePlanCode($ratePlanCode);
    $this->validateHotelCode($hotelCode);

    // Convert rates to collection if array
    $ratesCollection = $rates instanceof Collection ? $rates : collect($rates);

    // Validate we have at least one rate for most operations
    if ($ratesCollection->isEmpty() && !in_array($operationType, [RateOperationType::INACTIVE_RATE, RateOperationType::REMOVE_ROOM_TYPES])) {
      throw new InvalidArgumentException("Rate plan must contain at least one rate for {$operationType->value} operations");
    }

    // Validate all rates are RateData instances
    $ratesCollection->each(function ($rate) {
      if (!$rate instanceof RateData) {
        throw new InvalidArgumentException("All rates must be RateData instances");
      }
    });

    // Extract date ranges and room types from rates
    $dates = $this->extractDateRanges($ratesCollection);
    $roomTypes = $this->extractRoomTypes($ratesCollection);

    // Validate linked rate setup
    if ($isLinkedRate && $masterRatePlanCode === null) {
      throw new InvalidArgumentException("Master rate plan code required for linked rate plans");
    }

    // Validate currency consistency
    $this->validateCurrencyConsistency($ratesCollection, $currencyCode);

    // Assign properties
    $this->ratePlanCode = $ratePlanCode;
    $this->hotelCode = $hotelCode;
    $this->operationType = $operationType;
    $this->rates = $ratesCollection;
    $this->startDate = $dates['start'];
    $this->endDate = $dates['end'];
    $this->ratePlanName = $ratePlanName;
    $this->currencyCode = $currencyCode ?? config('travelclick.default_currency', 'USD');
    $this->isLinkedRate = $isLinkedRate;
    $this->masterRatePlanCode = $masterRatePlanCode;
    $this->roomTypes = $roomTypes;
    $this->maxGuestApplicable = $maxGuestApplicable;
    $this->isCommissionable = $isCommissionable;
    $this->marketCodes = collect($marketCodes);
    $this->isDeltaUpdate = $isDeltaUpdate;
    $this->lastModified = $lastModified ?? now();
  }

  /**
   * Create RatePlanData from array
   */
  public static function fromArray(array $data): self
  {
    $rates = collect($data['rates'] ?? [])->map(fn($rateData) => RateData::fromArray($rateData));

    return new self(
      ratePlanCode: $data['rate_plan_code'],
      hotelCode: $data['hotel_code'],
      operationType: RateOperationType::from($data['operation_type'] ?? 'update'),
      rates: $rates,
      ratePlanName: $data['rate_plan_name'] ?? null,
      currencyCode: $data['currency_code'] ?? null,
      isLinkedRate: $data['is_linked_rate'] ?? false,
      masterRatePlanCode: $data['master_rate_plan_code'] ?? null,
      maxGuestApplicable: $data['max_guest_applicable'] ?? null,
      isCommissionable: $data['is_commissionable'] ?? null,
      marketCodes: $data['market_codes'] ?? [],
      isDeltaUpdate: $data['is_delta_update'] ?? true,
      lastModified: isset($data['last_modified']) ? Carbon::parse($data['last_modified']) : null,
    );
  }

  /**
   * Convert to array for XML building
   */
  public function toArray(): array
  {
    $ratePlanData = [];

    // Group rates by room type for XML structure
    $ratesByRoomType = $this->rates->groupBy('roomTypeCode');

    foreach ($ratesByRoomType as $roomTypeCode => $roomTypeRates) {
      $roomTypeData = [
        '_attributes' => ['RoomTypeCode' => $roomTypeCode],
        'RatePlans' => [
          'RatePlan' => $this->buildRatePlanXmlData($roomTypeRates),
        ],
      ];

      $ratePlanData[] = $roomTypeData;
    }

    return $ratePlanData;
  }

  /**
   * Get rates for a specific room type
   */
  public function getRatesForRoomType(string $roomTypeCode): Collection
  {
    return $this->rates->filter(fn(RateData $rate) => $rate->roomTypeCode === $roomTypeCode);
  }

  /**
   * Get rates valid for a specific date
   */
  public function getRatesForDate(Carbon $date): Collection
  {
    return $this->rates->filter(fn(RateData $rate) => $rate->isValidForDate($date));
  }

  /**
   * Check if rate plan has rates for specific room type
   */
  public function hasRatesForRoomType(string $roomTypeCode): bool
  {
    return $this->rates->contains(fn(RateData $rate) => $rate->roomTypeCode === $roomTypeCode);
  }

  /**
   * Get all unique currencies used in rates
   */
  public function getCurrencies(): Collection
  {
    return $this->rates->pluck('currencyCode')->unique();
  }

  /**
   * Check if rate plan is valid for certification
   * (must have rates with 1st and 2nd adult rates)
   */
  public function isValidForCertification(): bool
  {
    if ($this->rates->isEmpty()) {
      return false;
    }

    // All rates must have both first and second adult rates
    return $this->rates->every(
      fn(RateData $rate) =>
      $rate->firstAdultRate > 0 && $rate->secondAdultRate > 0
    );
  }

  /**
   * Split rate plan into multiple plans by date range
   * Useful for batch processing with size limits
   */
  public function splitByDateRanges(int $maxDaysPerPlan = 30): Collection
  {
    $plans = collect();
    $currentDate = $this->startDate->copy();

    while ($currentDate->lt($this->endDate)) {
      $endDate = $currentDate->copy()->addDays($maxDaysPerPlan - 1);
      if ($endDate->gt($this->endDate)) {
        $endDate = $this->endDate->copy();
      }

      $ratesInRange = $this->rates->filter(
        fn(RateData $rate) => $rate->startDate->lte($endDate) && $rate->endDate->gte($currentDate)
      );

      if ($ratesInRange->isNotEmpty()) {
        // Trim rates to fit the date range
        $trimmedRates = $ratesInRange->map(function (RateData $rate) use ($currentDate, $endDate) {
          $adjustedStart = $rate->startDate->gt($currentDate) ? $rate->startDate : $currentDate;
          $adjustedEnd = $rate->endDate->lt($endDate) ? $rate->endDate : $endDate;

          return $rate->withDateRange($adjustedStart, $adjustedEnd);
        });

        $plans->push(new self(
          ratePlanCode: $this->ratePlanCode,
          hotelCode: $this->hotelCode,
          operationType: $this->operationType,
          rates: $trimmedRates,
          ratePlanName: $this->ratePlanName,
          currencyCode: $this->currencyCode,
          isLinkedRate: $this->isLinkedRate,
          masterRatePlanCode: $this->masterRatePlanCode,
          maxGuestApplicable: $this->maxGuestApplicable,
          isCommissionable: $this->isCommissionable,
          marketCodes: $this->marketCodes->toArray(),
          isDeltaUpdate: $this->isDeltaUpdate,
          lastModified: $this->lastModified,
        ));
      }

      $currentDate->addDays($maxDaysPerPlan);
    }

    return $plans;
  }

  /**
   * Filter out linked rates if external system handles them
   * According to HTNG spec, only send master rates if external system manages linking
   */
  public function filterLinkedRatesIfNeeded(bool $externalSystemHandlesLinkedRates = false): self
  {
    if (!$externalSystemHandlesLinkedRates) {
      return $this;
    }

    $filteredRates = $this->rates->reject(fn(RateData $rate) => $rate->isLinkedRate);

    return new self(
      ratePlanCode: $this->ratePlanCode,
      hotelCode: $this->hotelCode,
      operationType: $this->operationType,
      rates: $filteredRates,
      ratePlanName: $this->ratePlanName,
      currencyCode: $this->currencyCode,
      isLinkedRate: $this->isLinkedRate,
      masterRatePlanCode: $this->masterRatePlanCode,
      maxGuestApplicable: $this->maxGuestApplicable,
      isCommissionable: $this->isCommissionable,
      marketCodes: $this->marketCodes->toArray(),
      isDeltaUpdate: $this->isDeltaUpdate,
      lastModified: $this->lastModified,
    );
  }

  /**
   * Build XML data for RatePlan element
   */
  private function buildRatePlanXmlData(Collection $rates): array
  {
    $ratePlanXml = [];

    // Group rates by date range to create separate RatePlan elements
    $ratesByDateRange = $this->groupRatesByDateRange($rates);

    foreach ($ratesByDateRange as $dateRange => $dateRangeRates) {
      $firstRate = $dateRangeRates->first();

      $ratePlanElement = [
        '_attributes' => $firstRate->toXmlAttributes(),
        'BaseByGuestAmts' => $this->buildBaseByGuestAmts($dateRangeRates),
      ];

      // Add additional guest amounts if needed
      if ($dateRangeRates->some(fn($rate) => $rate->additionalAdultRate !== null || $rate->additionalChildRate !== null)) {
        $ratePlanElement['AdditionalGuestAmounts'] = $this->buildAdditionalGuestAmounts($dateRangeRates);
      }

      $ratePlanXml[] = $ratePlanElement;
    }

    return count($ratePlanXml) === 1 ? $ratePlanXml[0] : $ratePlanXml;
  }

  /**
   * Group rates by date range
   */
  private function groupRatesByDateRange(Collection $rates): Collection
  {
    return $rates->groupBy(function (RateData $rate) {
      return $rate->startDate->format('Y-m-d') . '_' . $rate->endDate->format('Y-m-d');
    });
  }

  /**
   * Build BaseByGuestAmts XML structure
   */
  private function buildBaseByGuestAmts(Collection $rates): array
  {
    $baseAmounts = [];

    foreach ($rates as $rate) {
      $baseAmounts[] = [
        '_attributes' => [
          'NumberOfGuests' => '1',
          'AmountBeforeTax' => number_format($rate->firstAdultRate, 2, '.', ''),
        ],
      ];

      $baseAmounts[] = [
        '_attributes' => [
          'NumberOfGuests' => '2',
          'AmountBeforeTax' => number_format($rate->secondAdultRate, 2, '.', ''),
        ],
      ];

      break; // One rate should be enough for the date range
    }

    return ['BaseByGuestAmt' => $baseAmounts];
  }

  /**
   * Build AdditionalGuestAmounts XML structure
   */
  private function buildAdditionalGuestAmounts(Collection $rates): array
  {
    $additionalAmounts = [];

    foreach ($rates as $rate) {
      if ($rate->additionalAdultRate !== null) {
        $additionalAmounts[] = [
          '_attributes' => [
            'AgeQualifyingCode' => '10', // Adult
            'Amount' => number_format($rate->additionalAdultRate, 2, '.', ''),
          ],
        ];
      }

      if ($rate->additionalChildRate !== null) {
        $additionalAmounts[] = [
          '_attributes' => [
            'AgeQualifyingCode' => '8', // Child
            'Amount' => number_format($rate->additionalChildRate, 2, '.', ''),
          ],
        ];
      }

      break; // One rate should be enough for the date range
    }

    return ['AdditionalGuestAmount' => $additionalAmounts];
  }

  /**
   * Extract date ranges from rates collection
   */
  private function extractDateRanges(Collection $rates): array
  {
    if ($rates->isEmpty()) {
      return [
        'start' => now(),
        'end' => now()->addDay(),
      ];
    }

    return [
      'start' => $rates->min('startDate'),
      'end' => $rates->max('endDate'),
    ];
  }

  /**
   * Extract room types from rates collection
   */
  private function extractRoomTypes(Collection $rates): Collection
  {
    return $rates->pluck('roomTypeCode')->unique()->values();
  }

  /**
   * Validate rate plan code
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
   * Validate hotel code
   */
  private function validateHotelCode(string $hotelCode): void
  {
    $validation = config('travelclick.validation.hotel_code');

    if (strlen($hotelCode) < $validation['min_length'] || strlen($hotelCode) > $validation['max_length']) {
      throw new InvalidArgumentException(
        "Hotel code length must be between {$validation['min_length']} and {$validation['max_length']} characters"
      );
    }

    if (!preg_match($validation['pattern'], $hotelCode)) {
      throw new InvalidArgumentException("Hotel code contains invalid characters");
    }
  }

  /**
   * Validate currency consistency across rates
   */
  private function validateCurrencyConsistency(Collection $rates, ?string $expectedCurrency): void
  {
    if ($rates->isEmpty()) {
      return;
    }

    $currencies = $rates->pluck('currencyCode')->unique();

    if ($currencies->count() > 1) {
      throw new InvalidArgumentException("All rates in a rate plan must use the same currency");
    }

    if ($expectedCurrency && $currencies->first() !== $expectedCurrency) {
      throw new InvalidArgumentException("Rate currency does not match rate plan currency");
    }
  }
}
