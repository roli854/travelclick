<?php

declare(strict_types=1);

namespace App\TravelClick\Support;

use App\TravelClick\DTOs\RateData;
use App\TravelClick\DTOs\RatePlanData;
use App\TravelClick\Enums\RateOperationType;
use App\TravelClick\Exceptions\ValidationException;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Rate Structure Validator for TravelClick HTNG 2011B Integration
 *
 * This validator enforces specific business rules for rate data that are required
 * by the HTNG 2011B specification and TravelClick's integration requirements.
 * Think of it as a quality inspector that ensures all rate data meets the
 * strict hotel industry standards before being sent to TravelClick.
 *
 * Key responsibilities:
 * - Validate mandatory 1st and 2nd adult rates (HTNG certification requirement)
 * - Verify linked rate logic and relationships
 * - Ensure date ranges are valid and coherent
 * - Check rate plan codes and structure consistency
 * - Validate currency consistency across rate plans
 * - Enforce operation-specific validation rules
 */
class RateStructureValidator
{
  /**
   * Configuration array loaded from travelclick config
   */
  private array $config;

  /**
   * Validation rules specific to rate operations
   */
  private array $validationRules;

  public function __construct()
  {
    $this->config = config('travelclick');
    $this->validationRules = config('travelclick.validation');
  }

  /**
   * Validate a single rate data structure
   *
   * This is like having a detailed checklist for each rate card.
   * Every rate must pass these checks before being processed.
   *
   * @param RateData $rateData The rate to validate
   * @param RateOperationType $operationType The operation being performed
   * @throws ValidationException If validation fails
   */
  public function validateRateData(RateData $rateData, RateOperationType $operationType): void
  {
    // 1. Validate mandatory adult rates (HTNG certification requirement)
    $this->validateMandatoryAdultRates($rateData);

    // 2. Validate rate values are reasonable
    $this->validateRateValues($rateData);

    // 3. Validate date range logic
    $this->validateDateRange($rateData);

    // 4. Validate room type and rate plan codes
    $this->validateCodes($rateData);

    // 5. Validate linked rate logic if applicable
    if ($rateData->isLinkedRate) {
      $this->validateLinkedRateLogic($rateData, $operationType);
    }

    // 6. Validate operation-specific requirements
    $this->validateOperationSpecificRules($rateData, $operationType);

    // 7. Validate currency code
    $this->validateCurrencyCode($rateData->currencyCode);

    // 8. Validate optional attributes if present
    $this->validateOptionalAttributes($rateData);
  }

  /**
   * Validate a complete rate plan structure
   *
   * This validates the entire rate plan ensuring all rates work together
   * cohesively and follow business logic rules.
   *
   * @param RatePlanData $ratePlan The rate plan to validate
   * @param RateOperationType $operationType The operation being performed
   * @throws ValidationException If validation fails
   */
  public function validateRatePlan(RatePlanData $ratePlan, RateOperationType $operationType): void
  {
    // 1. Validate rate plan has at least one rate
    if ($ratePlan->rates->isEmpty()) {
      throw new ValidationException('Rate plan cannot be empty. At least one rate is required.');
    }

    // 2. Validate each individual rate
    foreach ($ratePlan->rates as $rate) {
      $this->validateRateData($rate, $operationType);
    }

    // 3. Validate currency consistency (already handled in RatePlanData constructor)
    $this->validateAdditionalCurrencyRules($ratePlan);

    // 4. Validate date range coherence
    $this->validateRatePlanDateRanges($ratePlan);

    // 5. Validate room type coverage
    $this->validateRoomTypeCoverage($ratePlan, $operationType);

    // 6. Validate rate plan code consistency
    $this->validateRatePlanCodeConsistency($ratePlan);

    // 7. Validate linked rates if present
    $this->validateLinkedRatesInPlan($ratePlan, $operationType);

    // 8. Validate business rules specific to operation
    $this->validatePlanOperationRules($ratePlan, $operationType);
  }

  /**
   * Validate multiple rate plans for batch operations
   *
   * This ensures that when sending multiple rate plans together,
   * they don't conflict with each other.
   *
   * @param array<RatePlanData> $ratePlans Array of rate plans to validate
   * @param RateOperationType $operationType The operation being performed
   * @throws ValidationException If validation fails
   */
  public function validateBatchRatePlans(array $ratePlans, RateOperationType $operationType): void
  {
    if (empty($ratePlans)) {
      throw new ValidationException('Batch operation requires at least one rate plan.');
    }

    // Check batch size limits
    $maxBatchSize = $operationType->getRecommendedBatchSize();
    if (count($ratePlans) > $maxBatchSize) {
      throw new ValidationException(
        "Batch size (" . count($ratePlans) . ") exceeds recommended maximum ({$maxBatchSize}) for operation {$operationType->value}"
      );
    }

    // Validate each rate plan individually
    foreach ($ratePlans as $index => $ratePlan) {
      try {
        $this->validateRatePlan($ratePlan, $operationType);
      } catch (ValidationException $e) {
        throw new ValidationException("Rate plan at index {$index}: {$e->getMessage()}", previous: $e);
      }
    }

    // Validate cross-plan relationships
    $this->validateCrossPlanConsistency($ratePlans);

    // Validate no duplicate rate plan codes in batch
    $this->validateNoDuplicateRatePlans($ratePlans);
  }

  /**
   * Validate that 1st and 2nd adult rates are present (HTNG requirement)
   */
  private function validateMandatoryAdultRates(RateData $rateData): void
  {
    // This is explicitly required for HTNG 2011B certification
    if ($rateData->firstAdultRate <= 0) {
      throw new ValidationException(
        'First adult rate is mandatory and must be greater than 0 for HTNG 2011B certification'
      );
    }

    if ($rateData->secondAdultRate <= 0) {
      throw new ValidationException(
        'Second adult rate is mandatory and must be greater than 0 for HTNG 2011B certification'
      );
    }

    // Validate that second adult rate is logical compared to first
    // Usually second adult rate should be >= first adult rate, but allow flexibility
    if ($rateData->secondAdultRate < ($rateData->firstAdultRate * 0.5)) {
      throw new ValidationException(
        'Second adult rate seems unusually low compared to first adult rate. ' .
          'Please verify this is intentional.'
      );
    }
  }

  /**
   * Validate rate values are within reasonable bounds
   */
  private function validateRateValues(RateData $rateData): void
  {
    $maxRate = $this->config['validation']['max_rate_amount'] ?? 999999.99;
    $minRate = $this->config['validation']['min_rate_amount'] ?? 0.01;

    $rates = [
      'first_adult_rate' => $rateData->firstAdultRate,
      'second_adult_rate' => $rateData->secondAdultRate,
    ];

    if ($rateData->additionalAdultRate !== null) {
      $rates['additional_adult_rate'] = $rateData->additionalAdultRate;
    }

    if ($rateData->additionalChildRate !== null) {
      $rates['additional_child_rate'] = $rateData->additionalChildRate;
    }

    foreach ($rates as $type => $rate) {
      if ($rate < $minRate) {
        throw new ValidationException(
          ucfirst(str_replace('_', ' ', $type)) . " must be at least {$minRate}"
        );
      }

      if ($rate > $maxRate) {
        throw new ValidationException(
          ucfirst(str_replace('_', ' ', $type)) . " cannot exceed {$maxRate}"
        );
      }
    }
  }

  /**
   * Validate date ranges are logical and within allowed bounds
   */
  private function validateDateRange(RateData $rateData): void
  {
    // Ensure start date is not in the past (with grace period)
    $gracePeriodDays = $this->config['validation']['past_date_grace_days'] ?? 1;
    $minDate = now()->subDays($gracePeriodDays)->startOfDay();

    if ($rateData->startDate->lt($minDate)) {
      throw new ValidationException(
        "Rate start date cannot be more than {$gracePeriodDays} day(s) in the past"
      );
    }

    // Ensure end date is not too far in the future
    $maxFutureDays = $this->config['validation']['max_rate_days_in_future'] ?? 730;
    $maxDate = now()->addDays($maxFutureDays);

    if ($rateData->endDate->gt($maxDate)) {
      throw new ValidationException(
        "Rate end date cannot be more than {$maxFutureDays} days in the future"
      );
    }

    // Ensure minimum stay duration if configured
    $minStayDays = $this->config['validation']['min_rate_stay_days'] ?? 1;
    $actualDays = $rateData->startDate->diffInDays($rateData->endDate) + 1;

    if ($actualDays < $minStayDays) {
      throw new ValidationException(
        "Rate period must be at least {$minStayDays} day(s). Current period: {$actualDays} day(s)"
      );
    }
  }

  /**
   * Validate room type and rate plan codes follow proper format
   */
  private function validateCodes(RateData $rateData): void
  {
    // These validations are also in RateData constructor, but we double-check
    // here for business rules that might be more restrictive

    // Validate room type code
    $roomTypeRules = $this->validationRules['room_type_code'] ?? [];
    if (!empty($roomTypeRules['pattern']) && !preg_match($roomTypeRules['pattern'], $rateData->roomTypeCode)) {
      throw new ValidationException(
        "Room type code '{$rateData->roomTypeCode}' does not match required pattern"
      );
    }

    // Validate rate plan code
    $ratePlanRules = $this->validationRules['rate_plan_code'] ?? [];
    if (!empty($ratePlanRules['pattern']) && !preg_match($ratePlanRules['pattern'], $rateData->ratePlanCode)) {
      throw new ValidationException(
        "Rate plan code '{$rateData->ratePlanCode}' does not match required pattern"
      );
    }

    // Check for reserved/forbidden codes
    $forbiddenCodes = $this->config['validation']['forbidden_codes'] ?? [];
    if (in_array(strtoupper($rateData->ratePlanCode), array_map('strtoupper', $forbiddenCodes))) {
      throw new ValidationException(
        "Rate plan code '{$rateData->ratePlanCode}' is reserved and cannot be used"
      );
    }
  }

  /**
   * Validate linked rate logic and relationships
   */
  private function validateLinkedRateLogic(RateData $rateData, RateOperationType $operationType): void
  {
    // Ensure operation supports linked rates
    if (!$operationType->supportsLinkedRates()) {
      throw new ValidationException(
        "Operation '{$operationType->value}' does not support linked rates"
      );
    }

    // Master rate plan code is required
    if (empty($rateData->masterRatePlanCode)) {
      throw new ValidationException('Master rate plan code is required for linked rates');
    }

    // Linked rate cannot reference itself
    if ($rateData->masterRatePlanCode === $rateData->ratePlanCode) {
      throw new ValidationException('Linked rate cannot reference itself as master');
    }

    // Validate offset or percentage logic
    if ($rateData->linkedRateOffset === null && $rateData->linkedRatePercentage === null) {
      throw new ValidationException('Linked rates must specify either offset amount or percentage');
    }

    if ($rateData->linkedRateOffset !== null && $rateData->linkedRatePercentage !== null) {
      throw new ValidationException('Linked rates cannot specify both offset and percentage');
    }

    // Validate percentage bounds
    if ($rateData->linkedRatePercentage !== null) {
      if ($rateData->linkedRatePercentage <= -100 || $rateData->linkedRatePercentage >= 100) {
        throw new ValidationException(
          'Linked rate percentage must be between -100 and 100 (exclusive)'
        );
      }
    }

    // Validate offset bounds
    if ($rateData->linkedRateOffset !== null) {
      $maxOffset = $this->config['validation']['max_linked_rate_offset'] ?? 9999.99;
      if (abs($rateData->linkedRateOffset) > $maxOffset) {
        throw new ValidationException(
          "Linked rate offset cannot exceed {$maxOffset} in absolute value"
        );
      }
    }
  }

  /**
   * Validate operation-specific requirements
   */
  private function validateOperationSpecificRules(RateData $rateData, RateOperationType $operationType): void
  {
    switch ($operationType) {
      case RateOperationType::RATE_CREATION:
        // New rates must not be linked rates for creation
        if ($rateData->isLinkedRate) {
          throw new ValidationException(
            'Cannot create linked rates directly. Create master rate first, then link rates via update operation.'
          );
        }
        break;

      case RateOperationType::INACTIVE_RATE:
        // Inactive rates don't need full rate values
        // But date range must be valid for operational tracking
        break;

      case RateOperationType::REMOVE_ROOM_TYPES:
        // Ensure the room type being removed actually exists in the plan
        // This would be validated at a higher level with full plan context
        break;

      case RateOperationType::FULL_SYNC:
        // Full sync should include all mandatory fields
        // Additional validation for completeness
        if ($rateData->additionalAdultRate === null && ($this->config['full_sync_requires_additional_rates'] ?? false)) {
          throw new ValidationException(
            'Full synchronization requires additional adult rates to be specified'
          );
        }
        break;
    }
  }

  /**
   * Validate currency code format and supported currencies
   */
  private function validateCurrencyCode(string $currencyCode): void
  {
    // Must be 3-character ISO code
    if (!preg_match('/^[A-Z]{3}$/', $currencyCode)) {
      throw new ValidationException(
        "Currency code must be a 3-character ISO code (e.g., USD, EUR). Got: {$currencyCode}"
      );
    }

    // Check against allowed currencies if configured
    $allowedCurrencies = $this->config['validation']['allowed_currencies'] ?? [];
    if (!empty($allowedCurrencies) && !in_array($currencyCode, $allowedCurrencies)) {
      throw new ValidationException(
        "Currency {$currencyCode} is not in the list of allowed currencies: " . implode(', ', $allowedCurrencies)
      );
    }
  }

  /**
   * Validate optional attributes if present
   */
  private function validateOptionalAttributes(RateData $rateData): void
  {
    // Validate max guest applicable
    if ($rateData->maxGuestApplicable !== null) {
      if ($rateData->maxGuestApplicable < 1 || $rateData->maxGuestApplicable > 99) {
        throw new ValidationException(
          'Max guest applicable must be between 1 and 99 if specified'
        );
      }
    }

    // Validate market code format if present
    if ($rateData->marketCode !== null) {
      $marketCodeRules = $this->config['validation']['market_code'] ?? [];
      if (!empty($marketCodeRules['pattern']) && !preg_match($marketCodeRules['pattern'], $rateData->marketCode)) {
        throw new ValidationException(
          "Market code '{$rateData->marketCode}' does not match required pattern"
        );
      }
    }

    // Validate rate plan qualifier if present
    if ($rateData->ratePlanQualifier !== null) {
      $maxLength = $this->config['validation']['rate_plan_qualifier_max_length'] ?? 50;
      if (strlen($rateData->ratePlanQualifier) > $maxLength) {
        throw new ValidationException(
          "Rate plan qualifier cannot exceed {$maxLength} characters"
        );
      }
    }
  }

  /**
   * Validate additional currency rules beyond constructor validation
   */
  private function validateAdditionalCurrencyRules(RatePlanData $ratePlan): void
  {
    // The RatePlanData constructor already validates currency consistency
    // Here we can add additional business rules if needed

    // Example: Validate currency matches hotel's default currency if configured
    $hotelDefaultCurrency = $this->config['hotels'][$ratePlan->hotelCode]['default_currency'] ?? null;
    if ($hotelDefaultCurrency && $ratePlan->currencyCode !== $hotelDefaultCurrency) {
      // This might be a warning rather than an error, depending on business rules
      throw new ValidationException(
        "Rate plan currency ({$ratePlan->currencyCode}) differs from hotel default currency ({$hotelDefaultCurrency})"
      );
    }
  }

  /**
   * Validate date ranges within a rate plan don't have gaps or overlaps
   */
  private function validateRatePlanDateRanges(RatePlanData $ratePlan): void
  {
    $allowGaps = $this->config['validation']['allow_date_gaps_in_plan'] ?? true;
    $allowOverlaps = $this->config['validation']['allow_date_overlaps_in_plan'] ?? false;

    if ($allowGaps && $allowOverlaps) {
      return; // No validation needed if both are allowed
    }

    // Group rates by room type
    $ratesByRoomType = $ratePlan->rates->groupBy('roomTypeCode');

    foreach ($ratesByRoomType as $roomType => $roomTypeRates) {
      if ($roomTypeRates->isEmpty()) continue;

      // Sort rates by start date
      $sortedRates = $roomTypeRates->sortBy('startDate')->values();

      for ($i = 0; $i < $sortedRates->count() - 1; $i++) {
        $currentRate = $sortedRates[$i];
        $nextRate = $sortedRates[$i + 1];

        // Check for overlaps
        if (!$allowOverlaps && $currentRate->endDate->gte($nextRate->startDate)) {
          throw new ValidationException(
            "Rate date ranges overlap for room type {$roomType}: " .
              "{$currentRate->endDate->format('Y-m-d')} overlaps with {$nextRate->startDate->format('Y-m-d')}"
          );
        }

        // Check for gaps
        if (!$allowGaps && $currentRate->endDate->addDay()->lt($nextRate->startDate)) {
          throw new ValidationException(
            "Rate date ranges have gap for room type {$roomType}: " .
              "gap between {$currentRate->endDate->format('Y-m-d')} and {$nextRate->startDate->format('Y-m-d')}"
          );
        }
      }
    }
  }

  /**
   * Validate room type coverage requirements
   */
  private function validateRoomTypeCoverage(RatePlanData $ratePlan, RateOperationType $operationType): void
  {
    // For certain operations, we might require minimum room type coverage
    $requiredRoomTypes = $this->config['validation']['required_room_types_per_plan'] ?? [];

    if (empty($requiredRoomTypes)) {
      return; // No specific requirements
    }

    $presentRoomTypes = $ratePlan->roomTypes->toArray();
    $missingRoomTypes = array_diff($requiredRoomTypes, $presentRoomTypes);

    if (!empty($missingRoomTypes) && $operationType === RateOperationType::RATE_CREATION) {
      throw new ValidationException(
        'Rate plan creation requires the following room types: ' . implode(', ', $missingRoomTypes)
      );
    }
  }

  /**
   * Validate rate plan code consistency across all rates
   */
  private function validateRatePlanCodeConsistency(RatePlanData $ratePlan): void
  {
    $ratePlanCodes = $ratePlan->rates->pluck('ratePlanCode')->unique();

    if ($ratePlanCodes->count() > 1) {
      throw new ValidationException(
        'All rates in a plan must have the same rate plan code. Found: ' . $ratePlanCodes->implode(', ')
      );
    }

    // Also validate it matches the plan's rate plan code
    if ($ratePlanCodes->first() !== $ratePlan->ratePlanCode) {
      throw new ValidationException(
        "Rate plan code mismatch: Plan has '{$ratePlan->ratePlanCode}' but rates have '{$ratePlanCodes->first()}'"
      );
    }
  }

  /**
   * Validate linked rates within a plan have valid masters
   */
  private function validateLinkedRatesInPlan(RatePlanData $ratePlan, RateOperationType $operationType): void
  {
    if (!$operationType->supportsLinkedRates()) {
      return;
    }

    $linkedRates = $ratePlan->rates->filter(fn($rate) => $rate->isLinkedRate);

    foreach ($linkedRates as $linkedRate) {
      // Master rate plan should exist in the same batch or be known to TravelClick
      // For now, we just validate that it's not the same as the linked rate itself
      if ($linkedRate->masterRatePlanCode === $linkedRate->ratePlanCode) {
        throw new ValidationException(
          "Linked rate plan '{$linkedRate->ratePlanCode}' cannot reference itself as master"
        );
      }

      // If external system handles linked rates, linked rates shouldn't be sent
      $externalHandlesLinked = $this->config['message_types']['rates']['external_handles_linked'] ?? false;
      if ($externalHandlesLinked) {
        throw new ValidationException(
          'External system handles linked rates. Linked rate plans should not be sent to TravelClick.'
        );
      }
    }
  }

  /**
   * Validate plan-level operation rules
   */
  private function validatePlanOperationRules(RatePlanData $ratePlan, RateOperationType $operationType): void
  {
    switch ($operationType) {
      case RateOperationType::FULL_SYNC:
        // Full sync should have complete rate coverage
        $minRatesPerPlan = $this->config['validation']['min_rates_per_full_sync'] ?? 1;
        if ($ratePlan->rates->count() < $minRatesPerPlan) {
          throw new ValidationException(
            "Full sync requires at least {$minRatesPerPlan} rate(s) per plan"
          );
        }
        break;

      case RateOperationType::DELTA_UPDATE:
        // Delta updates should not be empty
        if ($ratePlan->rates->isEmpty()) {
          throw new ValidationException('Delta update cannot be empty');
        }
        break;
    }
  }

  /**
   * Validate consistency across multiple rate plans
   */
  private function validateCrossPlanConsistency(array $ratePlans): void
  {
    // Validate hotel codes are consistent if multiple plans from same hotel
    $hotelCodes = collect($ratePlans)->pluck('hotelCode')->unique();
    if ($hotelCodes->count() > 1) {
      throw new ValidationException(
        'All rate plans in a batch must belong to the same hotel. Found hotel codes: ' . $hotelCodes->implode(', ')
      );
    }

    // Validate no cross-plan linked rate issues
    $this->validateCrossPlanLinkedRates($ratePlans);
  }

  /**
   * Validate linked rates across plans don't create circular references
   */
  private function validateCrossPlanLinkedRates(array $ratePlans): void
  {
    $allRatePlanCodes = [];
    $linkedRateMasters = [];

    // Collect all rate plan codes and linked rate relationships
    foreach ($ratePlans as $plan) {
      $allRatePlanCodes[] = $plan->ratePlanCode;
      if ($plan->isLinkedRate && $plan->masterRatePlanCode) {
        $linkedRateMasters[$plan->ratePlanCode] = $plan->masterRatePlanCode;
      }
    }

    // Check for circular references
    foreach ($linkedRateMasters as $linkedCode => $masterCode) {
      if ($this->hasCircularReference($linkedCode, $masterCode, $linkedRateMasters)) {
        throw new ValidationException(
          "Circular reference detected in linked rates involving '{$linkedCode}'"
        );
      }
    }
  }

  /**
   * Detect circular references in linked rate relationships
   */
  private function hasCircularReference(string $startCode, string $currentCode, array $relationships, array $visited = []): bool
  {
    if ($startCode === $currentCode && !empty($visited)) {
      return true; // Circular reference found
    }

    if (in_array($currentCode, $visited)) {
      return false; // Already visited in this path
    }

    $visited[] = $currentCode;

    if (isset($relationships[$currentCode])) {
      return $this->hasCircularReference($startCode, $relationships[$currentCode], $relationships, $visited);
    }

    return false;
  }

  /**
   * Validate no duplicate rate plans in batch
   */
  private function validateNoDuplicateRatePlans(array $ratePlans): void
  {
    $planCodes = [];
    foreach ($ratePlans as $index => $plan) {
      if (in_array($plan->ratePlanCode, $planCodes)) {
        throw new ValidationException(
          "Duplicate rate plan code '{$plan->ratePlanCode}' found in batch at index {$index}"
        );
      }
      $planCodes[] = $plan->ratePlanCode;
    }
  }

  /**
   * Get validation summary for a rate plan
   * Useful for debugging and logging
   *
   * @param RatePlanData $ratePlan
   * @return array Summary of validation checks performed
   */
  public function getValidationSummary(RatePlanData $ratePlan): array
  {
    $summary = [
      'total_rates' => $ratePlan->rates->count(),
      'room_types' => $ratePlan->roomTypes->toArray(),
      'currencies' => $ratePlan->getCurrencies()->toArray(),
      'date_range' => [
        'earliest_start' => $ratePlan->startDate->format('Y-m-d'),
        'latest_end' => $ratePlan->endDate->format('Y-m-d'),
      ],
      'linked_rates' => $ratePlan->rates->filter(fn($rate) => $rate->isLinkedRate)->count(),
      'rate_plan_code' => $ratePlan->ratePlanCode,
      'hotel_code' => $ratePlan->hotelCode,
      'operation_type' => $ratePlan->operationType->value,
      'is_linked_plan' => $ratePlan->isLinkedRate,
      'master_rate_plan' => $ratePlan->masterRatePlanCode,
    ];

    return $summary;
  }
}
