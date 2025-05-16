<?php

declare(strict_types=1);

namespace App\TravelClick\Enums;

/**
 * Rate operation types supported by TravelClick HTNG 2011B interface
 *
 * This enum defines the different types of rate operations that can be performed
 * through the TravelClick integration. Think of it as the different "actions"
 * you can take with rates - like having different buttons on a remote control
 * for different functions.
 *
 * According to HTNG 2011B specification:
 * - Rate Update is mandatory for certification
 * - Other operations are optional but recommended for advanced integrations
 */
enum RateOperationType: string
{
/**
   * Rate Update - Update existing rate information
   *
   * This is the most common operation and the ONLY mandatory one for certification.
   * Use this when you need to modify existing rates in TravelClick.
   *
   * Example: Changing room rates for specific dates, updating seasonal pricing
   */
  case RATE_UPDATE = 'update';

/**
   * Rate Creation - Create new rate plans
   *
   * Optional operation to create entirely new rate plans in TravelClick.
   * Usually done during initial setup or when launching new packages.
   *
   * Example: Creating a new "Summer Special" rate plan
   */
  case RATE_CREATION = 'create';

/**
   * Inactive Rate - Mark existing rates as inactive
   *
   * Optional operation to deactivate rate plans without deleting them.
   * Useful for seasonal rates or discontinued packages.
   *
   * Example: Deactivating winter rates during summer season
   */
  case INACTIVE_RATE = 'inactive';

/**
   * Remove Room Types - Remove specific room types from a rate plan
   *
   * Optional operation to exclude certain room types from a rate plan
   * without affecting the plan itself.
   *
   * Example: Removing suites from a budget rate plan
   */
  case REMOVE_ROOM_TYPES = 'remove_room_types';

/**
   * Full Synchronization - Complete rate data sync
   *
   * Special operation type for full overlay synchronization.
   * Should ONLY be used when explicitly requested by user, not routinely.
   *
   * Note: TravelClick strongly recommends against daily full syncs
   * to minimize message traffic and processing delays.
   */
  case FULL_SYNC = 'full_sync';

/**
   * Delta Update - Send only changed rates
   *
   * Recommended operation type for regular synchronization.
   * Only sends rate plans that have been affected by changes.
   * This is the preferred method for real-time updates.
   */
  case DELTA_UPDATE = 'delta';

  /**
   * Get a human-readable description of the operation
   *
   * @return string Descriptive text explaining the operation
   */
  public function getDescription(): string
  {
    return match ($this) {
      self::RATE_UPDATE => 'Update existing rate information (Mandatory)',
      self::RATE_CREATION => 'Create new rate plans (Optional)',
      self::INACTIVE_RATE => 'Mark rates as inactive (Optional)',
      self::REMOVE_ROOM_TYPES => 'Remove room types from rate plan (Optional)',
      self::FULL_SYNC => 'Complete rate synchronization (Use sparingly)',
      self::DELTA_UPDATE => 'Send only changed rates (Recommended)',
    };
  }

  /**
   * Check if this operation type is mandatory for certification
   *
   * @return bool True if mandatory, false if optional
   */
  public function isMandatory(): bool
  {
    return match ($this) {
      self::RATE_UPDATE => true,
      default => false,
    };
  }

  /**
   * Check if this operation type supports linked rates
   *
   * Linked rates are rates derived from a master rate (e.g., AAA rate = BAR rate - 10%).
   * Not all operations support this feature.
   *
   * @return bool True if linked rates are supported
   */
  public function supportsLinkedRates(): bool
  {
    return match ($this) {
      self::RATE_UPDATE,
      self::RATE_CREATION,
      self::DELTA_UPDATE => true,
      default => false,
    };
  }

  /**
   * Get the recommended batch size for this operation type
   *
   * Different operations have different optimal batch sizes based on
   * processing complexity and TravelClick recommendations.
   *
   * @return int Recommended number of rate plans per batch
   */
  public function getRecommendedBatchSize(): int
  {
    return match ($this) {
      self::RATE_UPDATE,
      self::DELTA_UPDATE => 50,
      self::RATE_CREATION => 25,
      self::INACTIVE_RATE => 100,
      self::REMOVE_ROOM_TYPES => 75,
      self::FULL_SYNC => 10, // Smaller batches for full sync due to size
    };
  }

  /**
   * Get the timeout in seconds for this operation type
   *
   * Different operations may take different amounts of time to process
   * in TravelClick's systems.
   *
   * @return int Timeout in seconds
   */
  public function getTimeoutSeconds(): int
  {
    return match ($this) {
      self::RATE_UPDATE,
      self::DELTA_UPDATE => 90,
      self::RATE_CREATION => 120,
      self::INACTIVE_RATE => 60,
      self::REMOVE_ROOM_TYPES => 90,
      self::FULL_SYNC => 300, // Longer timeout for full sync
    };
  }

  /**
   * Check if this operation should trigger automatic inventory updates
   *
   * Some rate operations may affect inventory availability and should
   * trigger automatic inventory synchronization.
   *
   * @return bool True if should trigger inventory update
   */
  public function shouldTriggerInventoryUpdate(): bool
  {
    return match ($this) {
      self::RATE_CREATION,
      self::INACTIVE_RATE => true,
      default => false,
    };
  }

  /**
   * Get all mandatory operation types
   *
   * @return array<self> Array of mandatory operations
   */
  public static function getMandatoryOperations(): array
  {
    return [self::RATE_UPDATE];
  }

  /**
   * Get all optional operation types
   *
   * @return array<self> Array of optional operations
   */
  public static function getOptionalOperations(): array
  {
    return [
      self::RATE_CREATION,
      self::INACTIVE_RATE,
      self::REMOVE_ROOM_TYPES,
    ];
  }

  /**
   * Get operations that support batch processing
   *
   * @return array<self> Array of operations that can be batched
   */
  public static function getBatchableOperations(): array
  {
    return [
      self::RATE_UPDATE,
      self::DELTA_UPDATE,
      self::INACTIVE_RATE,
      self::REMOVE_ROOM_TYPES,
    ];
  }

  /**
   * Get the XML element name for this operation type
   *
   * Different operations may require different XML structures
   * or element names in the SOAP payload.
   *
   * @return string XML element name
   */
  public function getXmlElementName(): string
  {
    return match ($this) {
      self::RATE_UPDATE,
      self::RATE_CREATION,
      self::DELTA_UPDATE => 'RatePlan',
      self::INACTIVE_RATE => 'RatePlan',
      self::REMOVE_ROOM_TYPES => 'RatePlan',
      self::FULL_SYNC => 'RatePlan',
    };
  }

  /**
   * Get validation rules specific to this operation type
   *
   * @return array<string, mixed> Validation rules
   */
  public function getValidationRules(): array
  {
    $baseRules = [
      'rate_plan_code' => 'required|string|max:20',
      'hotel_code' => 'required|string|max:10',
      'start_date' => 'required|date',
      'end_date' => 'required|date|after:start_date',
    ];

    return match ($this) {
      self::RATE_CREATION => array_merge($baseRules, [
        'rate_plan_name' => 'required|string|max:100',
        'currency_code' => 'required|string|size:3',
        'first_adult_rate' => 'required|numeric|min:0',
        'second_adult_rate' => 'required|numeric|min:0',
      ]),
      self::RATE_UPDATE,
      self::DELTA_UPDATE => array_merge($baseRules, [
        'rates' => 'required|array|min:1',
        'rates.*.first_adult_rate' => 'required|numeric|min:0',
        'rates.*.second_adult_rate' => 'required|numeric|min:0',
      ]),
      self::INACTIVE_RATE => $baseRules,
      self::REMOVE_ROOM_TYPES => array_merge($baseRules, [
        'room_types' => 'required|array|min:1',
        'room_types.*' => 'string|max:20',
      ]),
      default => $baseRules,
    };
  }
}
