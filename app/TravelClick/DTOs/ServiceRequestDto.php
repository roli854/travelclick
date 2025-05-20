<?php

declare(strict_types=1);

namespace App\TravelClick\DTOs;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Data Transfer Object for service requests in TravelClick integrations
 *
 * This DTO handles service requests that incur additional costs.
 * Examples include room service, spa treatments, transfers, etc.
 */
class ServiceRequestDto
{
  /**
   * Service details
   */
  public readonly string $serviceCode;
  public readonly string $serviceName;
  public readonly ?string $serviceDescription;
  public readonly int $quantity;

  /**
   * Service timing
   */
  public readonly ?Carbon $startDate;
  public readonly ?Carbon $endDate;
  public readonly ?string $deliveryTime;

  /**
   * Financial information
   */
  public readonly float $amount;
  public readonly ?float $totalAmount;
  public readonly string $currencyCode;
  public readonly bool $includedInRate;

  /**
   * Guest information
   */
  public readonly int $numberOfAdults;
  public readonly int $numberOfChildren;
  public readonly ?int $roomStayIndex;
  public readonly ?string $supplierConfirmationNumber;

  /**
   * Additional information
   */
  public readonly ?string $comments;
  public readonly bool $confirmed;

  /**
   * Create a new service request DTO instance
   *
   * @param array<string, mixed> $data The service request data
   * @throws InvalidArgumentException If required data is missing
   */
  public function __construct(array $data)
  {
    // Validate required fields
    if (!isset($data['serviceCode']) || empty($data['serviceCode'])) {
      throw new InvalidArgumentException('Service code is required');
    }

    if (!isset($data['serviceName']) || empty($data['serviceName'])) {
      throw new InvalidArgumentException('Service name is required');
    }

    if (!isset($data['amount'])) {
      throw new InvalidArgumentException('Service amount is required');
    }

    // Set service details
    $this->serviceCode = $data['serviceCode'];
    $this->serviceName = $data['serviceName'];
    $this->serviceDescription = $data['serviceDescription'] ?? null;
    $this->quantity = $data['quantity'] ?? 1;

    // Set timing information
    $this->startDate = isset($data['startDate'])
      ? Carbon::parse($data['startDate'])
      : null;

    $this->endDate = isset($data['endDate'])
      ? Carbon::parse($data['endDate'])
      : null;

    $this->deliveryTime = $data['deliveryTime'] ?? null;

    // Validate date logic if both are provided
    if ($this->startDate && $this->endDate && $this->startDate->greaterThan($this->endDate)) {
      throw new InvalidArgumentException('End date must be after start date for service request');
    }

    // Set financial information
    $this->amount = (float)$data['amount'];
    $this->totalAmount = isset($data['totalAmount']) ? (float)$data['totalAmount'] : null;
    $this->currencyCode = $data['currencyCode'] ?? 'USD';
    $this->includedInRate = $data['includedInRate'] ?? false;

    // Set guest information
    $this->numberOfAdults = $data['numberOfAdults'] ?? 1;
    $this->numberOfChildren = $data['numberOfChildren'] ?? 0;
    $this->roomStayIndex = $data['roomStayIndex'] ?? null;
    $this->supplierConfirmationNumber = $data['supplierConfirmationNumber'] ?? null;

    // Set additional information
    $this->comments = $data['comments'] ?? null;
    $this->confirmed = $data['confirmed'] ?? false;
  }

  /**
   * Get formatted start date (YYYY-MM-DD)
   *
   * @return string|null Formatted date or null if not set
   */
  public function getFormattedStartDate(): ?string
  {
    return $this->startDate?->format('Y-m-d');
  }

  /**
   * Get formatted end date (YYYY-MM-DD)
   *
   * @return string|null Formatted date or null if not set
   */
  public function getFormattedEndDate(): ?string
  {
    return $this->endDate?->format('Y-m-d');
  }

  /**
   * Check if this service applies to a specific stay
   *
   * @return bool True if this applies to a specific room stay
   */
  public function appliesToSpecificStay(): bool
  {
    return $this->roomStayIndex !== null;
  }

  /**
   * Check if this service has a confirmation number
   *
   * @return bool True if this service has a confirmation
   */
  public function hasConfirmation(): bool
  {
    return $this->supplierConfirmationNumber !== null;
  }

  /**
   * Calculate total cost of the service (unit price * quantity)
   *
   * @return float The total cost
   */
  public function getTotalCost(): float
  {
    return $this->amount * $this->quantity;
  }

  /**
   * Convert from Centrium property booking adjustment
   *
   * @param mixed $adjustment The Centrium property room booking adjustment
   * @return self|null A ServiceRequestDto if applicable, or null
   */
  public static function fromCentriumPropertyRoomBookingAdjust($adjustment): ?self
  {
    // Convert to array if not already
    $adjustData = is_array($adjustment)
      ? $adjustment
      : $adjustment->toArray();

    // Only process positive adjustments (charges)
    if (!isset($adjustData['Amount']) || (float)$adjustData['Amount'] <= 0) {
      return null;
    }

    $adjustType = $adjustData['AdjustType'] ?? '';
    $adjustName = $adjustData['Adjust'] ?? 'Service';

    return new self([
      'serviceCode' => 'SVC' . substr(md5($adjustType . $adjustName), 0, 5),
      'serviceName' => $adjustName,
      'serviceDescription' => $adjustData['Note'] ?? null,
      'amount' => (float)$adjustData['Amount'],
      'currencyCode' => 'USD',  // Default, adjust based on your system
      'comments' => $adjustData['Note'] ?? null,
      'quantity' => 1,
      'roomStayIndex' => $adjustData['property_room_booking_id'] ?? null,
      'confirmed' => true,
    ]);
  }

  /**
   * Convert to array representation
   *
   * @return array<string, mixed> The service request data as an array
   */
  public function toArray(): array
  {
    return [
      'serviceCode' => $this->serviceCode,
      'serviceName' => $this->serviceName,
      'serviceDescription' => $this->serviceDescription,
      'quantity' => $this->quantity,
      'startDate' => $this->getFormattedStartDate(),
      'endDate' => $this->getFormattedEndDate(),
      'deliveryTime' => $this->deliveryTime,
      'amount' => $this->amount,
      'totalAmount' => $this->totalAmount,
      'currencyCode' => $this->currencyCode,
      'includedInRate' => $this->includedInRate,
      'numberOfAdults' => $this->numberOfAdults,
      'numberOfChildren' => $this->numberOfChildren,
      'roomStayIndex' => $this->roomStayIndex,
      'supplierConfirmationNumber' => $this->supplierConfirmationNumber,
      'comments' => $this->comments,
      'confirmed' => $this->confirmed,
    ];
  }
}
