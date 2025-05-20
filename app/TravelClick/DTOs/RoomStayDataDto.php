<?php

declare(strict_types=1);

namespace App\TravelClick\DTOs;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Data Transfer Object for room stay information in TravelClick integration
 *
 * This DTO encapsulates all details about a room stay, including dates,
 * room type, rate plan, guest counts, and pricing information. It enforces
 * validation and provides structured access to data for XML construction.
 */
class RoomStayDataDto
{
  /**
   * Room stay dates
   */
  public readonly Carbon $checkInDate;
  public readonly Carbon $checkOutDate;
  public readonly int $stayDurationNights;

  /**
   * Room configuration
   */
  public readonly string $roomTypeCode;
  public readonly string $ratePlanCode;
  public readonly ?string $upgradedRoomTypeCode;
  public readonly ?string $mealPlanCode;

  /**
   * Guest counts
   */
  public readonly int $adultCount;
  public readonly int $childCount;
  public readonly int $infantCount;
  public readonly int $totalGuestCount;

  /**
   * Financial information
   */
  public readonly float $rateAmount;         // Base rate amount
  public readonly ?float $totalAmount;       // Total including taxes/fees
  public readonly ?float $discountAmount;    // Any applied discounts
  public readonly ?float $taxAmount;         // Total taxes
  public readonly string $currencyCode;      // ISO currency code

  /**
   * Room stay identifiers
   */
  public readonly int $indexNumber;          // Room stay sequence
  public readonly ?string $confirmationNumber;  // Property confirmation code
  public readonly ?string $specialRequestCode;  // Any special request code

  /**
   * Additional information
   */
  public readonly ?string $roomDescription;
  public readonly ?array $dailyRates;        // Daily rate breakdown
  public readonly ?array $supplements;       // Additional charges
  public readonly ?array $specialOffers;     // Applied special offers

  /**
   * Create a new room stay data DTO instance
   *
   * @param array<string, mixed> $data The room stay data array
   * @throws InvalidArgumentException If required data is missing or invalid
   */
  public function __construct(array $data)
  {
    // Validate required fields
    if (!isset($data['checkInDate'])) {
      throw new InvalidArgumentException('Check-in date is required for room stay');
    }

    if (!isset($data['checkOutDate'])) {
      throw new InvalidArgumentException('Check-out date is required for room stay');
    }

    if (!isset($data['roomTypeCode']) || empty($data['roomTypeCode'])) {
      throw new InvalidArgumentException('Room type code is required for room stay');
    }

    if (!isset($data['ratePlanCode']) || empty($data['ratePlanCode'])) {
      throw new InvalidArgumentException('Rate plan code is required for room stay');
    }

    // Parse dates
    $this->checkInDate = $data['checkInDate'] instanceof Carbon
      ? $data['checkInDate']
      : Carbon::parse($data['checkInDate']);

    $this->checkOutDate = $data['checkOutDate'] instanceof Carbon
      ? $data['checkOutDate']
      : Carbon::parse($data['checkOutDate']);

    // Validate date logic
    if ($this->checkInDate->greaterThanOrEqualTo($this->checkOutDate)) {
      throw new InvalidArgumentException('Check-out date must be after check-in date');
    }

    // Calculate stay duration
    $this->stayDurationNights = intVal($this->checkInDate->diffInDays($this->checkOutDate));

    // Set room configuration
    $this->roomTypeCode = $data['roomTypeCode'];
    $this->ratePlanCode = $data['ratePlanCode'];
    $this->upgradedRoomTypeCode = $data['upgradedRoomTypeCode'] ?? null;
    $this->mealPlanCode = $data['mealPlanCode'] ?? null;

    // Set guest counts
    $this->adultCount = $data['adultCount'] ?? 1;
    $this->childCount = $data['childCount'] ?? 0;
    $this->infantCount = $data['infantCount'] ?? 0;
    $this->totalGuestCount = $this->adultCount + $this->childCount + $this->infantCount;

    // Ensure guest count logic
    if ($this->adultCount < 1) {
      throw new InvalidArgumentException('At least one adult is required per room stay');
    }

    if ($this->totalGuestCount < 1) {
      throw new InvalidArgumentException('Total guest count must be at least 1');
    }

    // Set financial information
    $this->rateAmount = $data['rateAmount'] ?? 0.0;
    $this->totalAmount = $data['totalAmount'] ?? null;
    $this->discountAmount = $data['discountAmount'] ?? null;
    $this->taxAmount = $data['taxAmount'] ?? null;
    $this->currencyCode = $data['currencyCode'] ?? 'USD';

    // Set room stay identifiers
    $this->indexNumber = $data['indexNumber'] ?? 1;
    $this->confirmationNumber = $data['confirmationNumber'] ?? null;
    $this->specialRequestCode = $data['specialRequestCode'] ?? null;

    // Set additional information
    $this->roomDescription = $data['roomDescription'] ?? null;
    $this->dailyRates = $data['dailyRates'] ?? null;
    $this->supplements = $data['supplements'] ?? null;
    $this->specialOffers = $data['specialOffers'] ?? null;
  }

  /**
   * Get check-in date in HTNG format (YYYY-MM-DD)
   *
   * @return string The check-in date
   */
  public function getFormattedCheckInDate(): string
  {
    return $this->checkInDate->format('Y-m-d');
  }

  /**
   * Get check-out date in HTNG format (YYYY-MM-DD)
   *
   * @return string The check-out date
   */
  public function getFormattedCheckOutDate(): string
  {
    return $this->checkOutDate->format('Y-m-d');
  }

  /**
   * Check if this stay has daily rate breakdown available
   *
   * @return bool True if daily rates are available
   */
  public function hasDailyRates(): bool
  {
    return !empty($this->dailyRates);
  }

  /**
   * Check if this stay has supplements
   *
   * @return bool True if supplements are available
   */
  public function hasSupplements(): bool
  {
    return !empty($this->supplements);
  }

  /**
   * Check if this stay has special offers applied
   *
   * @return bool True if special offers are applied
   */
  public function hasSpecialOffers(): bool
  {
    return !empty($this->specialOffers);
  }

  /**
   * Check if this stay has a confirmation number
   *
   * @return bool True if confirmation number is available
   */
  public function hasConfirmationNumber(): bool
  {
    return !empty($this->confirmationNumber);
  }

  /**
   * Check if this is a package rate
   *
   * @return bool True if this is a package rate
   */
  public function isPackageRate(): bool
  {
    // Package rates often have specific naming conventions
    // This is a simplified approach - adapt to your actual logic
    return str_starts_with(strtoupper($this->ratePlanCode), 'PKG') ||
      str_contains(strtoupper($this->ratePlanCode), 'PACKAGE');
  }

  /**
   * Create from a Centrium property room booking
   *
   * @param mixed $propertyRoomBooking The Centrium property room booking
   * @param int $index The index/sequence number for this room
   * @return self A new RoomStayDataDto instance
   */
  public static function fromCentriumPropertyRoomBooking($propertyRoomBooking, int $index = 1): self
  {
    // Convert to array if not already
    $bookingData = is_array($propertyRoomBooking)
      ? $propertyRoomBooking
      : $propertyRoomBooking->toArray();

    // Get the property booking for dates
    $propertyBooking = $bookingData['property_booking'] ?? [];

    return new self([
      'checkInDate' => $propertyBooking['ArrivalDate'] ?? null,
      'checkOutDate' => $propertyBooking['DepartureDate'] ?? null,
      'roomTypeCode' => $bookingData['property_room_type']['Code'] ?? '',
      'ratePlanCode' => $bookingData['contract_id'] ?? '',
      'upgradedRoomTypeCode' => $bookingData['upgrade_property_room_type_id'] ?? null,
      'mealPlanCode' => $bookingData['meal_basis_id'] ?? null,
      'adultCount' => $bookingData['Adults'] ?? 1,
      'childCount' => $bookingData['Children'] ?? 0,
      'infantCount' => isset($bookingData['Infants']) ? (int)$bookingData['Infants'] : 0,
      'rateAmount' => $bookingData['SubTotal'] ?? 0.0,
      'totalAmount' => $bookingData['Total'] ?? null,
      'discountAmount' => $bookingData['TotalDiscount'] ?? null,
      'currencyCode' => $propertyBooking['currency_id'] ?? 'USD',
      'indexNumber' => $index,
      'confirmationNumber' => $propertyBooking['PropertyBookingReference'] ?? null,
      'roomDescription' => $bookingData['property_room_type']['RoomType'] ?? null,
    ]);
  }

  /**
   * Convert to array representation
   *
   * @return array<string, mixed> The room stay data as an array
   */
  public function toArray(): array
  {
    return [
      'checkInDate' => $this->getFormattedCheckInDate(),
      'checkOutDate' => $this->getFormattedCheckOutDate(),
      'stayDurationNights' => $this->stayDurationNights,
      'roomTypeCode' => $this->roomTypeCode,
      'ratePlanCode' => $this->ratePlanCode,
      'upgradedRoomTypeCode' => $this->upgradedRoomTypeCode,
      'mealPlanCode' => $this->mealPlanCode,
      'adultCount' => $this->adultCount,
      'childCount' => $this->childCount,
      'infantCount' => $this->infantCount,
      'totalGuestCount' => $this->totalGuestCount,
      'rateAmount' => $this->rateAmount,
      'totalAmount' => $this->totalAmount,
      'discountAmount' => $this->discountAmount,
      'taxAmount' => $this->taxAmount,
      'currencyCode' => $this->currencyCode,
      'indexNumber' => $this->indexNumber,
      'confirmationNumber' => $this->confirmationNumber,
      'specialRequestCode' => $this->specialRequestCode,
      'roomDescription' => $this->roomDescription,
      'dailyRates' => $this->dailyRates,
      'supplements' => $this->supplements,
      'specialOffers' => $this->specialOffers,
    ];
  }
}
