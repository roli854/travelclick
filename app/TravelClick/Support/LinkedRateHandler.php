<?php

declare(strict_types=1);

namespace App\TravelClick\Support;

use App\TravelClick\DTOs\RateData;
use App\TravelClick\DTOs\RatePlanData;
use App\TravelClick\Enums\RateOperationType;
use App\TravelClick\Exceptions\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Linked Rate Handler for TravelClick HTNG 2011B Integration
 *
 * This handler manages the complex logic around linked rates - rates that are derived
 * from other "master" rates using offsets or percentages. Think of it as a calculator
 * that knows how to derive AAA rates from BAR rates, or corporate rates from rack rates.
 *
 * In hotel terminology:
 * - BAR (Best Available Rate) might be the master at $200/night
 * - AAA rate (linked) could be BAR - 10% = $180/night
 * - Corporate rate (linked) could be BAR - $20 = $180/night
 *
 * Key responsibilities:
 * - Apply linked rate calculations (offset/percentage)
 * - Identify master vs derived rate relationships
 * - Handle configuration for external system linking
 * - Provide utilities for linked rate management
 */
class LinkedRateHandler
{
  /**
   * Configuration array loaded from travelclick config
   */
  private array $config;

  public function __construct()
  {
    $this->config = config('travelclick');
  }

  /**
   * Apply linked rate calculations to a collection of rates
   *
   * This method processes a collection of rates and calculates the actual values
   * for linked rates based on their master rates. It's like having a spreadsheet
   * that automatically updates derived formulas when master values change.
   *
   * @param Collection<RateData> $rates Collection of rates including masters and linked
   * @param RateOperationType $operationType The operation being performed
   * @return Collection<RateData> Rates with linked rate calculations applied
   * @throws ValidationException If linked rate calculation fails
   */
  public function applyLinkedRateCalculations(Collection $rates, RateOperationType $operationType): Collection
  {
    if (!$operationType->supportsLinkedRates()) {
      return $rates;
    }

    // Separate master rates from linked rates
    $masterRates = $rates->filter(fn(RateData $rate) => !$rate->isLinkedRate);
    $linkedRates = $rates->filter(fn(RateData $rate) => $rate->isLinkedRate);

    if ($linkedRates->isEmpty()) {
      return $rates;
    }

    // Create a lookup map for master rates by rate plan code
    $masterRateMap = $this->createMasterRateMap($masterRates);

    // Calculate linked rates
    $calculatedLinkedRates = $linkedRates->map(function (RateData $linkedRate) use ($masterRateMap) {
      return $this->calculateLinkedRate($linkedRate, $masterRateMap);
    });

    // Return combined collection
    return $masterRates->merge($calculatedLinkedRates)->values();
  }

  /**
   * Determine whether to send linked rates based on configuration
   *
   * According to HTNG spec, if the external system handles linked rates
   * (like the PMS calculating AAA rates from BAR rates automatically),
   * then we should only send master rates to avoid duplication.
   *
   * @param RateOperationType $operationType The operation being performed
   * @return bool True if should send linked rates, false if filter them out
   */
  public function shouldSendLinkedRates(RateOperationType $operationType): bool
  {
    if (!$operationType->supportsLinkedRates()) {
      return false;
    }

    // Check configuration - external system handling takes precedence
    $externalHandlesLinked = $this->config['message_types']['rates']['external_handles_linked'] ?? false;

    if ($externalHandlesLinked) {
      return false; // Don't send linked rates if external system handles them
    }

    // Check if TravelClick supports linked rates for this operation
    $travelClickSupportsLinked = $this->config['message_types']['rates']['supports_linked_rates'] ?? true;

    return $travelClickSupportsLinked;
  }

  /**
   * Filter a rate plan removing linked rates if needed
   *
   * This is a convenience method that leverages the existing logic in RatePlanData
   * but adds the business logic for determining when to filter.
   *
   * @param RatePlanData $ratePlan The rate plan to potentially filter
   * @param RateOperationType $operationType The operation being performed
   * @return RatePlanData Rate plan with linked rates filtered if appropriate
   */
  public function filterLinkedRatesIfNeeded(RatePlanData $ratePlan, RateOperationType $operationType): RatePlanData
  {
    $shouldFilter = !$this->shouldSendLinkedRates($operationType);
    return $ratePlan->filterLinkedRatesIfNeeded($shouldFilter);
  }

  /**
   * Identify all master rate dependencies for a set of linked rates
   *
   * This method analyzes linked rates and returns a list of master rate plan codes
   * that need to be included to satisfy all dependencies. It's like mapping out
   * a family tree to understand which ancestors are needed.
   *
   * @param Collection<RateData> $linkedRates Collection of linked rates
   * @return Collection<string> Collection of required master rate plan codes
   */
  public function getRequiredMasterRates(Collection $linkedRates): Collection
  {
    return $linkedRates
      ->filter(fn(RateData $rate) => $rate->isLinkedRate)
      ->pluck('masterRatePlanCode')
      ->filter()
      ->unique()
      ->values();
  }

  /**
   * Validate linked rate relationships across multiple rate plans
   *
   * This method ensures that if you're sending linked rates, their master rates
   * are either included in the same batch or already exist in TravelClick.
   *
   * @param Collection<RatePlanData> $ratePlans Collection of rate plans
   * @param RateOperationType $operationType The operation being performed
   * @throws ValidationException If dependency validation fails
   */
  public function validateLinkedRateDependencies(Collection $ratePlans, RateOperationType $operationType): void
  {
    if (!$operationType->supportsLinkedRates()) {
      return;
    }

    // Collect all rate plan codes in this batch
    $allRatePlanCodes = $ratePlans->pluck('ratePlanCode')->toArray();

    // Collect all linked rates and their required masters
    $allLinkedRates = $ratePlans->flatMap(
      fn(RatePlanData $plan) =>
      $plan->rates->filter(fn(RateData $rate) => $rate->isLinkedRate)
    );

    $requiredMasters = $this->getRequiredMasterRates($allLinkedRates);

    // Check for missing master rates
    $missingMasters = $requiredMasters->reject(
      fn(string $masterCode) =>
      in_array($masterCode, $allRatePlanCodes)
    );

    // Allow missing masters for UPDATE operations (masters may already exist in TravelClick)
    if ($missingMasters->isNotEmpty() && $operationType === RateOperationType::RATE_CREATION) {
      throw new ValidationException(
        'Missing master rates for CREATION operation: ' . $missingMasters->implode(', ') .
          '. Master rates must be created before their linked rates.'
      );
    }

    // Validate no linked rate references itself
    foreach ($allLinkedRates as $linkedRate) {
      if ($linkedRate->masterRatePlanCode === $linkedRate->ratePlanCode) {
        throw new ValidationException(
          "Linked rate '{$linkedRate->ratePlanCode}' cannot reference itself as master"
        );
      }
    }
  }

  /**
   * Get linked rate information for debugging/logging
   *
   * Provides a summary of linked rate relationships useful for troubleshooting
   * and operational visibility.
   *
   * @param Collection<RateData> $rates Collection of rates to analyze
   * @return array Summary of linked rate information
   */
  public function getLinkedRateSummary(Collection $rates): array
  {
    $summary = [
      'total_rates' => $rates->count(),
      'master_rates' => $rates->filter(fn(RateData $rate) => !$rate->isLinkedRate)->count(),
      'linked_rates' => $rates->filter(fn(RateData $rate) => $rate->isLinkedRate)->count(),
      'linked_rate_details' => [],
      'master_rate_codes' => [],
      'potential_issues' => [],
    ];

    // Collect master rate codes
    $summary['master_rate_codes'] = $rates
      ->filter(fn(RateData $rate) => !$rate->isLinkedRate)
      ->pluck('ratePlanCode')
      ->unique()
      ->toArray();

    // Analyze each linked rate
    $linkedRates = $rates->filter(fn(RateData $rate) => $rate->isLinkedRate);

    foreach ($linkedRates as $linkedRate) {
      $detail = [
        'rate_plan_code' => $linkedRate->ratePlanCode,
        'master_rate_plan_code' => $linkedRate->masterRatePlanCode,
        'calculation_type' => $linkedRate->linkedRateOffset !== null ? 'offset' : 'percentage',
        'calculation_value' => $linkedRate->linkedRateOffset ?? $linkedRate->linkedRatePercentage,
        'room_type' => $linkedRate->roomTypeCode,
      ];

      $summary['linked_rate_details'][] = $detail;

      // Check for potential issues
      if (!in_array($linkedRate->masterRatePlanCode, $summary['master_rate_codes'])) {
        $summary['potential_issues'][] = "Master rate '{$linkedRate->masterRatePlanCode}' not found in batch for linked rate '{$linkedRate->ratePlanCode}'";
      }
    }

    return $summary;
  }

  /**
   * Create a new rate data with linked rate calculations applied
   *
   * This method creates a copy of a linked rate with calculated values based on
   * the master rate. It's like having a formula in Excel that automatically
   * updates when the reference cell changes.
   *
   * @param RateData $linkedRate The linked rate to calculate
   * @param RateData $masterRate The master rate to base calculations on
   * @return RateData New rate with calculated values
   * @throws ValidationException If calculation fails
   */
  public function calculateLinkedRateFromMaster(RateData $linkedRate, RateData $masterRate): RateData
  {
    if (!$linkedRate->isLinkedRate) {
      throw new ValidationException('Rate is not a linked rate');
    }

    // Ensure the master rate matches
    if ($masterRate->ratePlanCode !== $linkedRate->masterRatePlanCode) {
      throw new ValidationException(
        "Master rate code mismatch. Expected '{$linkedRate->masterRatePlanCode}', got '{$masterRate->ratePlanCode}'"
      );
    }

    // Calculate new rates based on offset or percentage
    if ($linkedRate->linkedRateOffset !== null) {
      $calculatedFirstAdult = max(0.01, $masterRate->firstAdultRate + $linkedRate->linkedRateOffset);
      $calculatedSecondAdult = max(0.01, $masterRate->secondAdultRate + $linkedRate->linkedRateOffset);
      $calculatedAdditionalAdult = $masterRate->additionalAdultRate !== null
        ? max(0, $masterRate->additionalAdultRate + $linkedRate->linkedRateOffset)
        : null;
      $calculatedAdditionalChild = $masterRate->additionalChildRate !== null
        ? max(0, $masterRate->additionalChildRate + $linkedRate->linkedRateOffset)
        : null;
    } else {
      // Percentage calculation
      $percentage = $linkedRate->linkedRatePercentage;
      $multiplier = 1 + ($percentage / 100);

      $calculatedFirstAdult = max(0.01, $masterRate->firstAdultRate * $multiplier);
      $calculatedSecondAdult = max(0.01, $masterRate->secondAdultRate * $multiplier);
      $calculatedAdditionalAdult = $masterRate->additionalAdultRate !== null
        ? max(0, $masterRate->additionalAdultRate * $multiplier)
        : null;
      $calculatedAdditionalChild = $masterRate->additionalChildRate !== null
        ? max(0, $masterRate->additionalChildRate * $multiplier)
        : null;
    }

    // Create new RateData with calculated values
    // We'll use the same approach as RateData::fromArray but build the array manually
    $calculatedArray = [
      'first_adult_rate' => $calculatedFirstAdult,
      'second_adult_rate' => $calculatedSecondAdult,
      'room_type_code' => $linkedRate->roomTypeCode,
      'rate_plan_code' => $linkedRate->ratePlanCode,
      'start_date' => $linkedRate->startDate->format('Y-m-d'),
      'end_date' => $linkedRate->endDate->format('Y-m-d'),
      'additional_adult_rate' => $calculatedAdditionalAdult,
      'additional_child_rate' => $calculatedAdditionalChild,
      'currency_code' => $linkedRate->currencyCode,
      'restricted_display_indicator' => $linkedRate->restrictedDisplayIndicator,
      'is_commissionable' => $linkedRate->isCommissionable,
      'rate_plan_qualifier' => $linkedRate->ratePlanQualifier,
      'market_code' => $linkedRate->marketCode,
      'max_guest_applicable' => $linkedRate->maxGuestApplicable,
      'is_linked_rate' => false, // Important: calculated rate is no longer "linked"
      'master_rate_plan_code' => null,
      'linked_rate_offset' => null,
      'linked_rate_percentage' => null,
    ];

    return RateData::fromArray($calculatedArray);
  }

  /**
   * Calculate a single linked rate based on available master rates
   *
   * @param RateData $linkedRate The linked rate to calculate
   * @param array $masterRateMap Map of master rate plan codes to rates
   * @return RateData Rate with calculations applied
   * @throws ValidationException If master rate not found or calculation fails
   */
  private function calculateLinkedRate(RateData $linkedRate, array $masterRateMap): RateData
  {
    $masterCode = $linkedRate->masterRatePlanCode;

    if (!isset($masterRateMap[$masterCode])) {
      throw new ValidationException(
        "Master rate '{$masterCode}' not found for linked rate '{$linkedRate->ratePlanCode}'"
      );
    }

    // Find the correct master rate for the same room type and overlapping dates
    $candidateMasters = $masterRateMap[$masterCode];
    $masterRate = $candidateMasters->first(function (RateData $master) use ($linkedRate) {
      return $master->roomTypeCode === $linkedRate->roomTypeCode &&
        $master->startDate->lte($linkedRate->endDate) &&
        $master->endDate->gte($linkedRate->startDate);
    });

    if (!$masterRate) {
      throw new ValidationException(
        "No matching master rate found for linked rate '{$linkedRate->ratePlanCode}' " .
          "in room type '{$linkedRate->roomTypeCode}' for date range " .
          "{$linkedRate->startDate->format('Y-m-d')} to {$linkedRate->endDate->format('Y-m-d')}"
      );
    }

    return $this->calculateLinkedRateFromMaster($linkedRate, $masterRate);
  }

  /**
   * Create a map of master rates organized by rate plan code
   *
   * @param Collection<RateData> $masterRates Collection of master rates
   * @return array Map of rate plan code to collection of rates
   */
  private function createMasterRateMap(Collection $masterRates): array
  {
    return $masterRates->groupBy('ratePlanCode')->toArray();
  }

  /**
   * Validate that a rate qualifies as a proper master rate
   *
   * Master rates should be complete rates (not linked themselves) with
   * valid pricing for both adults.
   *
   * @param RateData $rate The rate to validate as master
   * @throws ValidationException If rate cannot be a master
   */
  public function validateMasterRate(RateData $rate): void
  {
    if ($rate->isLinkedRate) {
      throw new ValidationException(
        "Linked rate '{$rate->ratePlanCode}' cannot be used as a master rate"
      );
    }

    if ($rate->firstAdultRate <= 0 || $rate->secondAdultRate <= 0) {
      throw new ValidationException(
        "Master rate '{$rate->ratePlanCode}' must have valid first and second adult rates"
      );
    }
  }

  /**
   * Get configuration for external system handling of linked rates
   *
   * @return bool True if external system handles linked rates
   */
  public function externalSystemHandlesLinkedRates(): bool
  {
    return $this->config['message_types']['rates']['external_handles_linked'] ?? false;
  }

  /**
   * Get recommended strategy for handling linked rates
   *
   * Provides a recommendation based on configuration and operation type.
   *
   * @param RateOperationType $operationType The operation being performed
   * @return array Strategy recommendation with explanation
   */
  public function getLinkedRateStrategy(RateOperationType $operationType): array
  {
    $externalHandles = $this->externalSystemHandlesLinkedRates();
    $operationSupports = $operationType->supportsLinkedRates();

    if (!$operationSupports) {
      return [
        'action' => 'ignore',
        'send_linked_rates' => false,
        'reason' => "Operation '{$operationType->value}' does not support linked rates",
      ];
    }

    if ($externalHandles) {
      return [
        'action' => 'filter_out',
        'send_linked_rates' => false,
        'reason' => 'External system handles linked rate calculations. Send only master rates.',
      ];
    }

    return [
      'action' => 'include',
      'send_linked_rates' => true,
      'reason' => 'TravelClick will handle linked rate calculations. Send all rates including linked.',
    ];
  }
}
