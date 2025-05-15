<?php

namespace App\TravelClick\Enums;

/**
 * CountType Enum for TravelClick Inventory Messages
 *
 * These are the official count types supported by TravelClick HTNG 2011B interface.
 * Each type represents a different kind of room count in the inventory system.
 *
 * Think of these as different categories of room status:
 * - Physical: Actual physical rooms available
 * - Available: Rooms ready to be sold
 * - Definite Sold: Confirmed bookings
 * - Tentative: Group blocks or options
 * - Out of Order: Rooms unavailable (maintenance, etc.)
 * - Oversell: Additional rooms beyond physical capacity
 */
enum CountType: int
{
/**
   * Physical Rooms - The actual number of physical units available
   * Use only if the external system supports inventory messages at room and property level
   */
  case PHYSICAL = 1;

/**
   * Available Rooms - Actual count of rooms available for sale
   * Send only when inventory is managed for available rooms
   * Do not send with other count types
   */
  case AVAILABLE = 2;

/**
   * Definite Sold - Confirmed bookings/reservations
   * This is the main count for sold rooms
   */
  case DEFINITE_SOLD = 4;

/**
   * Tentative Sold - Group booking count, pickup count for group inventory
   * Must be passed with value of zero in calculated method
   */
  case TENTATIVE_SOLD = 5;

/**
   * Out of Order - Rooms unavailable due to maintenance, repairs, etc.
   * Optional count type
   */
  case OUT_OF_ORDER = 6;

/**
   * Oversell Rooms - Used to send oversell counts for specific dates/periods
   * Optional if oversell is supported
   */
  case OVERSELL = 99;

  /**
   * Get human-readable description of the count type
   */
  public function description(): string
  {
    return match ($this) {
      self::PHYSICAL => 'Physical Rooms - Actual room units available',
      self::AVAILABLE => 'Available Rooms - Ready for sale',
      self::DEFINITE_SOLD => 'Definite Sold - Confirmed bookings',
      self::TENTATIVE_SOLD => 'Tentative Sold - Options/group blocks',
      self::OUT_OF_ORDER => 'Out of Order - Maintenance/unavailable',
      self::OVERSELL => 'Oversell Rooms - Beyond physical capacity',
    };
  }

  /**
   * Check if this count type requires calculation
   * Some count types work together in calculated method
   */
  public function requiresCalculation(): bool
  {
    return in_array($this, [
      self::DEFINITE_SOLD,
      self::TENTATIVE_SOLD,
      self::OUT_OF_ORDER,
      self::OVERSELL
    ]);
  }

  /**
   * Check if this count type can be used alone
   */
  public function canBeUsedAlone(): bool
  {
    return in_array($this, [
      self::PHYSICAL,
      self::AVAILABLE
    ]);
  }

  /**
   * Get all count types valid for calculated method
   */
  public static function calculatedTypes(): array
  {
    return [
      self::DEFINITE_SOLD,
      self::TENTATIVE_SOLD,
      self::OUT_OF_ORDER,
      self::OVERSELL
    ];
  }

  /**
   * Get count types that don't require calculation
   */
  public static function directTypes(): array
  {
    return [
      self::PHYSICAL,
      self::AVAILABLE
    ];
  }

  /**
   * Map from Centrium inventory system to TravelClick
   * This will help convert Centrium data to TravelClick format
   */
  public static function fromCentriumInventory(array $inventoryData): array
  {
    $countTypes = [];

    // Map physical rooms if available
    if (isset($inventoryData['StandardAllocation'])) {
      $countTypes[self::PHYSICAL->value] = $inventoryData['StandardAllocation'];
    }

    // Map available rooms
    if (isset($inventoryData['Rooms'])) {
      $countTypes[self::AVAILABLE->value] = $inventoryData['Rooms'];
    }

    // Map oversell if exists
    if (isset($inventoryData['Overbook']) && $inventoryData['Overbook'] > 0) {
      $countTypes[self::OVERSELL->value] = $inventoryData['Overbook'];
    }

    return $countTypes;
  }
}
