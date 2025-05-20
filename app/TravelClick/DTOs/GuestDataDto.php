<?php

declare(strict_types=1);

namespace App\TravelClick\DTOs;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Data Transfer Object for guest information in TravelClick integrations
 *
 * This DTO handles all guest-related data for constructing reservation XML.
 * It provides structured access to guest details while enforcing validation
 * for required fields based on TravelClick HTNG 2011B specifications.
 */
class GuestDataDto
{
  /**
   * Guest personal details
   */
  public readonly string $title;
  public readonly string $firstName;
  public readonly string $lastName;
  public readonly ?string $middleName;
  public readonly ?string $suffix;
  public readonly ?Carbon $dateOfBirth;
  public readonly ?string $passportNumber;

  /**
   * Guest contact information
   */
  public readonly ?string $email;
  public readonly ?string $phone;
  public readonly ?string $phoneMobile;
  public readonly ?string $fax;

  /**
   * Guest address details
   */
  public readonly ?string $addressLine1;
  public readonly ?string $addressLine2;
  public readonly ?string $city;
  public readonly ?string $state;
  public readonly ?string $postalCode;
  public readonly ?string $countryCode;

  /**
   * Guest type and age classification
   */
  public readonly string $guestType; // 'adult', 'child', 'youth', 'infant'
  public readonly ?int $age;
  public readonly bool $isPrimaryGuest;

  /**
   * Create a new guest data DTO instance
   *
   * @param array<string, mixed> $data The guest data array
   * @throws InvalidArgumentException If required data is missing or invalid
   */
  public function __construct(array $data)
  {
    // Validate required fields
    if (!isset($data['firstName']) || empty($data['firstName'])) {
      throw new InvalidArgumentException('Guest first name is required');
    }

    if (!isset($data['lastName']) || empty($data['lastName'])) {
      throw new InvalidArgumentException('Guest last name is required');
    }

    // Set core personal details
    $this->title = $data['title'] ?? 'Mr';
    $this->firstName = $data['firstName'];
    $this->lastName = $data['lastName'];
    $this->middleName = $data['middleName'] ?? null;
    $this->suffix = $data['suffix'] ?? null;

    // Process date of birth if provided
    $this->dateOfBirth = isset($data['dateOfBirth'])
      ? Carbon::parse($data['dateOfBirth'])
      : null;

    $this->passportNumber = $data['passportNumber'] ?? null;

    // Set contact information
    $this->email = $data['email'] ?? null;
    $this->phone = $data['phone'] ?? null;
    $this->phoneMobile = $data['phoneMobile'] ?? null;
    $this->fax = $data['fax'] ?? null;

    // Set address details
    $this->addressLine1 = $data['addressLine1'] ?? null;
    $this->addressLine2 = $data['addressLine2'] ?? null;
    $this->city = $data['city'] ?? null;
    $this->state = $data['state'] ?? null;
    $this->postalCode = $data['postalCode'] ?? null;
    $this->countryCode = $data['countryCode'] ?? null;

    // Set guest classification
    $this->guestType = $data['guestType'] ?? 'adult';
    $this->age = $data['age'] ?? null;
    $this->isPrimaryGuest = $data['isPrimaryGuest'] ?? false;
  }

  /**
   * Check if the guest is an adult
   *
   * @return bool True if guest is an adult
   */
  public function isAdult(): bool
  {
    return $this->guestType === 'adult';
  }

  /**
   * Check if the guest is a child
   *
   * @return bool True if guest is a child
   */
  public function isChild(): bool
  {
    return $this->guestType === 'child';
  }

  /**
   * Check if the guest is a youth
   *
   * @return bool True if guest is a youth
   */
  public function isYouth(): bool
  {
    return $this->guestType === 'youth';
  }

  /**
   * Check if the guest is an infant
   *
   * @return bool True if guest is an infant
   */
  public function isInfant(): bool
  {
    return $this->guestType === 'infant';
  }

  /**
   * Check if the guest has a valid address
   *
   * @return bool True if guest has at least address line 1, city and country
   */
  public function hasValidAddress(): bool
  {
    return !empty($this->addressLine1)
      && !empty($this->city)
      && !empty($this->countryCode);
  }

  /**
   * Check if guest has valid contact information
   *
   * @return bool True if guest has either email or phone
   */
  public function hasValidContactInfo(): bool
  {
    return !empty($this->email) || !empty($this->phone) || !empty($this->phoneMobile);
  }

  /**
   * Get the full name of the guest (first + last)
   *
   * @return string The guest's full name
   */
  public function getFullName(): string
  {
    return trim("{$this->firstName} {$this->lastName}");
  }

  /**
   * Get the formal name with title (Mr. John Smith)
   *
   * @return string The guest's name with title
   */
  public function getFormalName(): string
  {
    return trim("{$this->title} {$this->firstName} {$this->lastName}");
  }

  /**
   * Create from a lead guest in a Centrium booking
   *
   * @param mixed $booking The Centrium booking object or array
   * @return self A new GuestDataDto instance
   */
  public static function fromCentriumBooking($booking): self
  {
    $bookingData = is_array($booking) ? $booking : $booking->toArray();

    return new self([
      'title' => Arr::get($bookingData, 'LeadGuestTitle', 'Mr'),
      'firstName' => Arr::get($bookingData, 'LeadGuestFirstName'),
      'lastName' => Arr::get($bookingData, 'LeadGuestLastName'),
      'dateOfBirth' => Arr::get($bookingData, 'DateOfBirth'),
      'passportNumber' => Arr::get($bookingData, 'PassportNumber'),
      'email' => Arr::get($bookingData, 'LeadGuestEmail'),
      'phone' => Arr::get($bookingData, 'LeadGuestPhone'),
      'addressLine1' => Arr::get($bookingData, 'LeadGuestAddress1'),
      'addressLine2' => Arr::get($bookingData, 'LeadGuestAddress2'),
      'city' => Arr::get($bookingData, 'LeadGuestTownCity'),
      'state' => Arr::get($bookingData, 'LeadGuestCounty'),
      'postalCode' => Arr::get($bookingData, 'LeadGuestPostcode'),
      'countryCode' => Arr::get($bookingData, 'LeadGuestBookingCountryID'),
      'guestType' => 'adult',
      'isPrimaryGuest' => true,
    ]);
  }

  /**
   * Convert to array representation
   *
   * @return array<string, mixed> The guest data as an array
   */
  public function toArray(): array
  {
    return [
      'title' => $this->title,
      'firstName' => $this->firstName,
      'lastName' => $this->lastName,
      'middleName' => $this->middleName,
      'suffix' => $this->suffix,
      'dateOfBirth' => $this->dateOfBirth?->format('Y-m-d'),
      'passportNumber' => $this->passportNumber,
      'email' => $this->email,
      'phone' => $this->phone,
      'phoneMobile' => $this->phoneMobile,
      'fax' => $this->fax,
      'addressLine1' => $this->addressLine1,
      'addressLine2' => $this->addressLine2,
      'city' => $this->city,
      'state' => $this->state,
      'postalCode' => $this->postalCode,
      'countryCode' => $this->countryCode,
      'guestType' => $this->guestType,
      'age' => $this->age,
      'isPrimaryGuest' => $this->isPrimaryGuest,
    ];
  }
}
