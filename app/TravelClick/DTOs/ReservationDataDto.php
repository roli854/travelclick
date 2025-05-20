<?php

declare(strict_types=1);

namespace App\TravelClick\DTOs;

use App\TravelClick\Enums\ReservationType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * Data Transfer Object for reservation information in TravelClick integrations
 *
 * This is the main DTO that integrates all aspects of a reservation for HTNG
 * message construction. It combines guest, room stay, profile and request data
 * into a single cohesive structure.
 */
class ReservationDataDto
{
  /**
   * Reservation type and identifiers
   */
  public readonly ReservationType $reservationType;
  public readonly string $reservationId;
  public readonly ?string $confirmationNumber;
  public readonly string $createDateTime;
  public readonly ?string $lastModifyDateTime;
  public readonly string $transactionIdentifier;
  public readonly string $transactionType; // 'new', 'modify', 'cancel'

  /**
   * Hotel information
   */
  public readonly string $hotelCode;
  public readonly ?string $chainCode;

  /**
   * Lead guest and additional guests
   */
  public readonly GuestDataDto $primaryGuest;
  public readonly Collection $additionalGuests;

  /**
   * Room stay details
   */
  public readonly Collection $roomStays;

  /**
   * Special requests and services
   */
  public readonly Collection $specialRequests;
  public readonly Collection $serviceRequests;

  /**
   * Profile information (for Travel Agency, Corporate, Group)
   */
  public readonly ?ProfileDataDto $profile;

  /**
   * Source information
   */
  public readonly string $sourceOfBusiness;
  public readonly ?string $marketSegment;
  public readonly ?string $departmentCode;

  /**
   * Payment information
   */
  public readonly ?string $guaranteeType;
  public readonly ?string $guaranteeCode;
  public readonly ?float $depositAmount;
  public readonly ?string $depositPaymentType;
  public readonly ?string $paymentCardNumber;
  public readonly ?string $paymentCardType;
  public readonly ?string $paymentCardExpiration;
  public readonly ?string $paymentCardHolderName;

  /**
   * Alternate payment info (for special deposits)
   */
  public readonly ?string $alternatePaymentType;
  public readonly ?string $alternatePaymentIdentifier;
  public readonly ?float $alternatePaymentAmount;

  /**
   * Group booking specific
   */
  public readonly ?string $invBlockCode;

  /**
   * Additional information
   */
  public readonly ?string $comments;
  public readonly bool $priorityProcessing;

  /**
   * Create a new reservation data DTO instance
   *
   * @param array<string, mixed> $data The reservation data
   * @throws InvalidArgumentException If required data is missing or invalid
   */
  public function __construct(array $data)
  {
    // Validate required fields
    if (!isset($data['reservationType'])) {
      throw new InvalidArgumentException('Reservation type is required');
    }

    if (!isset($data['hotelCode']) || empty($data['hotelCode'])) {
      throw new InvalidArgumentException('Hotel code is required');
    }

    if (!isset($data['primaryGuest'])) {
      throw new InvalidArgumentException('Primary guest information is required');
    }

    if (!isset($data['roomStays']) || empty($data['roomStays'])) {
      throw new InvalidArgumentException('At least one room stay is required');
    }

    // Set reservation type
    $this->reservationType = $data['reservationType'] instanceof ReservationType
      ? $data['reservationType']
      : ReservationType::from($data['reservationType']);

    // Set identifiers
    $this->reservationId = $data['reservationId'] ?? (string)Uuid::uuid4();
    $this->confirmationNumber = $data['confirmationNumber'] ?? null;
    $this->createDateTime = $data['createDateTime'] ?? now()->format('Y-m-d\TH:i:s');
    $this->lastModifyDateTime = $data['lastModifyDateTime'] ?? null;
    $this->transactionIdentifier = $data['transactionIdentifier'] ?? (string)Uuid::uuid4();
    $this->transactionType = $data['transactionType'] ?? 'new';

    // Validate transaction type
    if (!in_array($this->transactionType, ['new', 'modify', 'cancel'])) {
      throw new InvalidArgumentException("Invalid transaction type: {$this->transactionType}");
    }

    // Set hotel information
    $this->hotelCode = $data['hotelCode'];
    $this->chainCode = $data['chainCode'] ?? null;

    // Set guest information
    $this->primaryGuest = $data['primaryGuest'] instanceof GuestDataDto
      ? $data['primaryGuest']
      : new GuestDataDto(array_merge($data['primaryGuest'], ['isPrimaryGuest' => true]));

    // Process additional guests
    $this->additionalGuests = new Collection();
    if (isset($data['additionalGuests'])) {
      foreach ($data['additionalGuests'] as $guestData) {
        $this->additionalGuests->push(
          $guestData instanceof GuestDataDto
            ? $guestData
            : new GuestDataDto($guestData)
        );
      }
    }

    // Process room stays
    $this->roomStays = new Collection();
    foreach ($data['roomStays'] as $index => $stayData) {
      $this->roomStays->push(
        $stayData instanceof RoomStayDataDto
          ? $stayData
          : new RoomStayDataDto(array_merge($stayData, ['indexNumber' => $index + 1]))
      );
    }

    // Process special requests
    $this->specialRequests = new Collection();
    if (isset($data['specialRequests'])) {
      foreach ($data['specialRequests'] as $requestData) {
        $this->specialRequests->push(
          $requestData instanceof SpecialRequestDto
            ? $requestData
            : new SpecialRequestDto($requestData)
        );
      }
    }

    // Process service requests
    $this->serviceRequests = new Collection();
    if (isset($data['serviceRequests'])) {
      foreach ($data['serviceRequests'] as $serviceData) {
        $this->serviceRequests->push(
          $serviceData instanceof ServiceRequestDto
            ? $serviceData
            : new ServiceRequestDto($serviceData)
        );
      }
    }

    // Set profile information if provided
    $this->profile = isset($data['profile'])
      ? ($data['profile'] instanceof ProfileDataDto
        ? $data['profile']
        : new ProfileDataDto($data['profile']))
      : null;

    // Validate profile matches reservation type
    if ($this->profile) {
      $this->validateProfileMatchesReservationType();
    }

    // Set source information
    $this->sourceOfBusiness = $data['sourceOfBusiness'] ?? 'WEB';
    $this->marketSegment = $data['marketSegment'] ?? null;
    $this->departmentCode = $data['departmentCode'] ?? null;

    // Set payment information
    $this->guaranteeType = $data['guaranteeType'] ?? null;
    $this->guaranteeCode = $data['guaranteeCode'] ?? null;
    $this->depositAmount = isset($data['depositAmount']) ? (float)$data['depositAmount'] : null;
    $this->depositPaymentType = $data['depositPaymentType'] ?? null;
    $this->paymentCardNumber = $data['paymentCardNumber'] ?? null;
    $this->paymentCardType = $data['paymentCardType'] ?? null;
    $this->paymentCardExpiration = $data['paymentCardExpiration'] ?? null;
    $this->paymentCardHolderName = $data['paymentCardHolderName'] ?? null;

    // Set alternate payment info
    $this->alternatePaymentType = $data['alternatePaymentType'] ?? null;
    $this->alternatePaymentIdentifier = $data['alternatePaymentIdentifier'] ?? null;
    $this->alternatePaymentAmount = isset($data['alternatePaymentAmount'])
      ? (float)$data['alternatePaymentAmount']
      : null;

    // Set group booking specific info
    $this->invBlockCode = $data['invBlockCode'] ?? null;

    // Validate group block code for group reservations
    if ($this->reservationType === ReservationType::GROUP && empty($this->invBlockCode)) {
      throw new InvalidArgumentException('Group reservations require an inventory block code');
    }

    // Set additional information
    $this->comments = $data['comments'] ?? null;
    $this->priorityProcessing = $data['priorityProcessing'] ?? false;
  }

  /**
   * Validate that the profile matches the reservation type
   *
   * @throws InvalidArgumentException If profile doesn't match reservation type
   */
  private function validateProfileMatchesReservationType(): void
  {
    // Skip validation for null profile
    if ($this->profile === null) {
      return;
    }

    $expectedProfileType = match ($this->reservationType) {
      ReservationType::TRAVEL_AGENCY => ProfileDataDto::TYPE_TRAVEL_AGENCY,
      ReservationType::CORPORATE => ProfileDataDto::TYPE_CORPORATE,
      ReservationType::GROUP => ProfileDataDto::TYPE_GROUP,
      default => null,
    };

    if ($expectedProfileType !== null && $this->profile->profileType !== $expectedProfileType) {
      throw new InvalidArgumentException(
        "Profile type '{$this->profile->profileType}' doesn't match reservation type '{$this->reservationType->value}'"
      );
    }
  }

  /**
   * Get arrival date (from first room stay)
   *
   * @return Carbon The arrival date
   */
  public function getArrivalDate(): Carbon
  {
    return $this->roomStays->first()->checkInDate;
  }

  /**
   * Get departure date (from last room stay)
   *
   * @return Carbon The departure date
   */
  public function getDepartureDate(): Carbon
  {
    return $this->roomStays->last()->checkOutDate;
  }

  /**
   * Get total number of nights
   *
   * @return int Total nights across all room stays
   */
  public function getTotalNights(): int
  {
    return $this->roomStays->sum(fn($stay) => $stay->stayDurationNights);
  }

  /**
   * Calculate total reservation amount
   *
   * @return float Total amount across all room stays and services
   */
  public function getTotalAmount(): float
  {
    $roomTotal = $this->roomStays->sum(fn($stay) => $stay->totalAmount ?? $stay->rateAmount);
    $serviceTotal = $this->serviceRequests->sum(fn($service) => $service->getTotalCost());

    return $roomTotal + $serviceTotal;
  }

  /**
   * Check if this reservation has special requests
   *
   * @return bool True if there are special requests
   */
  public function hasSpecialRequests(): bool
  {
    return $this->specialRequests->isNotEmpty();
  }

  /**
   * Check if this reservation has service requests
   *
   * @return bool True if there are service requests
   */
  public function hasServiceRequests(): bool
  {
    return $this->serviceRequests->isNotEmpty();
  }

  /**
   * Check if this reservation has payment information
   *
   * @return bool True if payment information is available
   */
  public function hasPaymentInfo(): bool
  {
    return
      !empty($this->paymentCardNumber) ||
      !empty($this->guaranteeCode) ||
      $this->depositAmount !== null ||
      $this->alternatePaymentAmount !== null;
  }

  /**
   * Check if this reservation has a profile
   *
   * @return bool True if a profile is attached
   */
  public function hasProfile(): bool
  {
    return $this->profile !== null;
  }

  /**
   * Check if this is a modification
   *
   * @return bool True if this is a modification
   */
  public function isModification(): bool
  {
    return $this->transactionType === 'modify';
  }

  /**
   * Check if this is a cancellation
   *
   * @return bool True if this is a cancellation
   */
  public function isCancellation(): bool
  {
    return $this->transactionType === 'cancel';
  }

  /**
   * Check if this is a new reservation
   *
   * @return bool True if this is a new reservation
   */
  public function isNew(): bool
  {
    return $this->transactionType === 'new';
  }

  /**
   * Create from a Centrium booking
   *
   * @param mixed $booking The Centrium booking
   * @param ReservationType|null $type Override reservation type
   * @return self A new ReservationDataDto
   */
  public static function fromCentriumBooking($booking, ?ReservationType $type = null): self
  {
    // Convert to array if not already
    $bookingData = is_array($booking) ? $booking : $booking->toArray();

    // Extract property bookings
    $propertyBookings = $bookingData['property_bookings'] ?? [];
    $firstPropertyBooking = $propertyBookings[0] ?? [];

    // Determine reservation type
    $reservationType = $type ?? ReservationType::fromCentriumBookingSource(
      $bookingData['Source'] ?? '',
      $bookingData['BookingType'] ?? ''
    );

    // Build room stays from property room bookings
    $roomStays = [];
    $propertyRoomBookings = $firstPropertyBooking['property_room_bookings'] ?? [];
    foreach ($propertyRoomBookings as $index => $roomBooking) {
      $roomStays[] = RoomStayDataDto::fromCentriumPropertyRoomBooking($roomBooking, $index + 1);
    }

    // Create data array for constructor
    $data = [
      'reservationType' => $reservationType,
      'reservationId' => (string) $bookingData['BookingID'],
      'confirmationNumber' => $bookingData['BookingReference'] ?? null,
      'createDateTime' => $bookingData['BookingDate'] ?? now()->format('Y-m-d\TH:i:s'),
      'lastModifyDateTime' => $bookingData['LastModifiedDateTime'] ?? null,
      'transactionIdentifier' => Uuid::uuid4()->toString(),
      'transactionType' => 'new', // Default, override as needed
      'hotelCode' => $firstPropertyBooking['property_id'] ?? config('travelclick.credentials.hotel_code'),
      'primaryGuest' => GuestDataDto::fromCentriumBooking($bookingData),
      'roomStays' => $roomStays,
      'sourceOfBusiness' => $bookingData['Source'] ?? 'WEB',
    ];

    // Add profile data if applicable
    if ($reservationType === ReservationType::TRAVEL_AGENCY && isset($bookingData['agency'])) {
      $data['profile'] = ProfileDataDto::createTravelAgencyProfile($bookingData['agency']);
    } elseif ($reservationType === ReservationType::CORPORATE && isset($bookingData['trade'])) {
      $data['profile'] = ProfileDataDto::createCorporateProfile($bookingData['trade']);
    } elseif ($reservationType === ReservationType::GROUP && isset($bookingData['booking_group'])) {
      $data['profile'] = ProfileDataDto::createGroupProfile($bookingData['booking_group']);
      $data['invBlockCode'] = $bookingData['booking_group']['BookingGroupID'] ?? null;
    }

    // Add special requests if applicable
    if (isset($firstPropertyBooking['property_booking_comments'])) {
      $specialRequests = [];
      foreach ($firstPropertyBooking['property_booking_comments'] as $comment) {
        $request = SpecialRequestDto::fromCentriumPropertyBookingComment($comment);
        if ($request) {
          $specialRequests[] = $request;
        }
      }
      if (!empty($specialRequests)) {
        $data['specialRequests'] = $specialRequests;
      }
    }

    // Add service requests if applicable
    if (isset($propertyRoomBookings[0]['property_room_booking_adjusts'])) {
      $serviceRequests = [];
      foreach ($propertyRoomBookings[0]['property_room_booking_adjusts'] as $adjust) {
        $service = ServiceRequestDto::fromCentriumPropertyRoomBookingAdjust($adjust);
        if ($service) {
          $serviceRequests[] = $service;
        }
      }
      if (!empty($serviceRequests)) {
        $data['serviceRequests'] = $serviceRequests;
      }
    }

    // Add payment information if available
    if (isset($bookingData['booking_payment_schedules']) && !empty($bookingData['booking_payment_schedules'])) {
      $payment = $bookingData['booking_payment_schedules'][0];
      $data['depositAmount'] = $payment['Amount'] ?? null;
    }

    return new self($data);
  }

  /**
   * Create for a cancellation transaction
   *
   * @param string $reservationId The ID of the reservation to cancel
   * @param string $confirmationNumber The confirmation number if available
   * @param string $hotelCode The hotel code
   * @param string|null $cancellationReason Optional cancellation reason
   * @return self A new ReservationDataDto configured for cancellation
   */
  public static function createCancellation(
    string $reservationId,
    string $confirmationNumber,
    string $hotelCode,
    ?string $cancellationReason = null
  ): self {
    // Create minimal data for cancellation
    $primaryGuest = new GuestDataDto([
      'firstName' => 'Cancelled',
      'lastName' => 'Reservation',
      'isPrimaryGuest' => true,
    ]);

    $roomStay = new RoomStayDataDto([
      'checkInDate' => now(),
      'checkOutDate' => now()->addDay(),
      'roomTypeCode' => 'CANC',
      'ratePlanCode' => 'CANC',
      'rateAmount' => 0,
      'indexNumber' => 1,
    ]);

    $data = [
      'reservationType' => ReservationType::TRANSIENT, // Default type for cancellation
      'reservationId' => $reservationId,
      'confirmationNumber' => $confirmationNumber,
      'transactionType' => 'cancel',
      'hotelCode' => $hotelCode,
      'primaryGuest' => $primaryGuest,
      'roomStays' => [$roomStay],
      'comments' => $cancellationReason,
    ];

    return new self($data);
  }

  /**
   * Create for a modification transaction
   *
   * @param string $reservationId The ID of the reservation to modify
   * @param string $confirmationNumber The confirmation number
   * @param array<string, mixed> $modificationData The modified reservation data
   * @return self A new ReservationDataDto configured for modification
   */
  public static function createModification(
    string $reservationId,
    string $confirmationNumber,
    array $modificationData
  ): self {
    // Ensure key fields for modification
    $modificationData['reservationId'] = $reservationId;
    $modificationData['confirmationNumber'] = $confirmationNumber;
    $modificationData['transactionType'] = 'modify';
    $modificationData['lastModifyDateTime'] = now()->format('Y-m-d\TH:i:s');

    return new self($modificationData);
  }

  /**
   * Convert to array representation
   *
   * @return array<string, mixed> The reservation data as an array
   */
  public function toArray(): array
  {
    return [
      'reservationType' => $this->reservationType->value,
      'reservationId' => $this->reservationId,
      'confirmationNumber' => $this->confirmationNumber,
      'createDateTime' => $this->createDateTime,
      'lastModifyDateTime' => $this->lastModifyDateTime,
      'transactionIdentifier' => $this->transactionIdentifier,
      'transactionType' => $this->transactionType,
      'hotelCode' => $this->hotelCode,
      'chainCode' => $this->chainCode,
      'primaryGuest' => $this->primaryGuest->toArray(),
      'additionalGuests' => $this->additionalGuests->map(fn($guest) => $guest->toArray())->toArray(),
      'roomStays' => $this->roomStays->map(fn($stay) => $stay->toArray())->toArray(),
      'specialRequests' => $this->specialRequests->map(fn($req) => $req->toArray())->toArray(),
      'serviceRequests' => $this->serviceRequests->map(fn($srv) => $srv->toArray())->toArray(),
      'profile' => $this->profile?->toArray(),
      'sourceOfBusiness' => $this->sourceOfBusiness,
      'marketSegment' => $this->marketSegment,
      'departmentCode' => $this->departmentCode,
      'guaranteeType' => $this->guaranteeType,
      'guaranteeCode' => $this->guaranteeCode,
      'depositAmount' => $this->depositAmount,
      'depositPaymentType' => $this->depositPaymentType,
      'paymentCardNumber' => $this->paymentCardNumber,
      'paymentCardType' => $this->paymentCardType,
      'paymentCardExpiration' => $this->paymentCardExpiration,
      'paymentCardHolderName' => $this->paymentCardHolderName,
      'alternatePaymentType' => $this->alternatePaymentType,
      'alternatePaymentIdentifier' => $this->alternatePaymentIdentifier,
      'alternatePaymentAmount' => $this->alternatePaymentAmount,
      'invBlockCode' => $this->invBlockCode,
      'comments' => $this->comments,
      'priorityProcessing' => $this->priorityProcessing,
      'totalAmount' => $this->getTotalAmount(),
      'totalNights' => $this->getTotalNights(),
    ];
  }
}
