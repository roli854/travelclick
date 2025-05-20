<?php

declare(strict_types=1);

namespace App\TravelClick\DTOs;

use InvalidArgumentException;

/**
 * Data Transfer Object for profile information in TravelClick integrations
 *
 * This DTO handles profiles for Travel Agencies, Corporations, and Groups
 * when sending reservation data to TravelClick.
 */
class ProfileDataDto
{
  /**
   * Profile type constants
   */
  public const TYPE_TRAVEL_AGENCY = 'TravelAgency';
  public const TYPE_CORPORATE = 'Corporate';
  public const TYPE_GROUP = 'Group';

  /**
   * Profile details
   */
  public readonly string $profileType;
  public readonly string $profileId;
  public readonly string $name;
  public readonly ?string $shortName;
  public readonly ?string $iataNumber;

  /**
   * Contact details
   */
  public readonly ?string $contactName;
  public readonly ?string $email;
  public readonly ?string $phone;
  public readonly ?string $fax;

  /**
   * Address information
   */
  public readonly ?string $addressLine1;
  public readonly ?string $addressLine2;
  public readonly ?string $city;
  public readonly ?string $state;
  public readonly ?string $postalCode;
  public readonly ?string $countryCode;

  /**
   * Financial information
   */
  public readonly ?float $commissionPercentage;
  public readonly ?string $corporateId;
  public readonly ?string $travelAgentId;

  /**
   * Create a new profile data DTO instance
   *
   * @param array<string, mixed> $data The profile data
   * @throws InvalidArgumentException If required data is missing
   */
  public function __construct(array $data)
  {
    // Validate required fields
    if (!isset($data['profileType']) || empty($data['profileType'])) {
      throw new InvalidArgumentException('Profile type is required');
    }

    if (!isset($data['profileId']) || empty($data['profileId'])) {
      throw new InvalidArgumentException('Profile ID is required');
    }

    if (!isset($data['name']) || empty($data['name'])) {
      throw new InvalidArgumentException('Profile name is required');
    }

    // Validate profile type
    $this->profileType = $data['profileType'];
    if (!in_array($this->profileType, [self::TYPE_TRAVEL_AGENCY, self::TYPE_CORPORATE, self::TYPE_GROUP])) {
      throw new InvalidArgumentException('Invalid profile type: ' . $this->profileType);
    }

    // Set profile details
    $this->profileId = $data['profileId'];
    $this->name = $data['name'];
    $this->shortName = $data['shortName'] ?? null;
    $this->iataNumber = $data['iataNumber'] ?? null;

    // Set contact details
    $this->contactName = $data['contactName'] ?? null;
    $this->email = $data['email'] ?? null;
    $this->phone = $data['phone'] ?? null;
    $this->fax = $data['fax'] ?? null;

    // Set address information
    $this->addressLine1 = $data['addressLine1'] ?? null;
    $this->addressLine2 = $data['addressLine2'] ?? null;
    $this->city = $data['city'] ?? null;
    $this->state = $data['state'] ?? null;
    $this->postalCode = $data['postalCode'] ?? null;
    $this->countryCode = $data['countryCode'] ?? null;

    // Set financial information
    $this->commissionPercentage = isset($data['commissionPercentage'])
      ? (float)$data['commissionPercentage']
      : null;

    $this->corporateId = $data['corporateId'] ?? null;
    $this->travelAgentId = $data['travelAgentId'] ?? null;
  }

  /**
   * Check if this is a travel agency profile
   *
   * @return bool True if this is a travel agency profile
   */
  public function isTravelAgency(): bool
  {
    return $this->profileType === self::TYPE_TRAVEL_AGENCY;
  }

  /**
   * Check if this is a corporate profile
   *
   * @return bool True if this is a corporate profile
   */
  public function isCorporate(): bool
  {
    return $this->profileType === self::TYPE_CORPORATE;
  }

  /**
   * Check if this is a group profile
   *
   * @return bool True if this is a group profile
   */
  public function isGroup(): bool
  {
    return $this->profileType === self::TYPE_GROUP;
  }

  /**
   * Check if this profile has a valid address
   *
   * @return bool True if the profile has at least address line 1, city and country
   */
  public function hasValidAddress(): bool
  {
    return !empty($this->addressLine1)
      && !empty($this->city)
      && !empty($this->countryCode);
  }

  /**
   * Check if this profile has valid contact information
   *
   * @return bool True if the profile has at least an email or phone
   */
  public function hasValidContactInfo(): bool
  {
    return !empty($this->email) || !empty($this->phone);
  }

  /**
   * Check if this profile has commission information
   *
   * @return bool True if commission information is available
   */
  public function hasCommission(): bool
  {
    return $this->commissionPercentage !== null && $this->commissionPercentage > 0;
  }

  /**
   * Create a travel agency profile from Centrium agency data
   *
   * @param mixed $agencyData The Centrium agency data
   * @return self A new ProfileDataDto for the travel agency
   */
  public static function createTravelAgencyProfile($agencyData): self
  {
    // Convert to array if not already
    $agency = is_array($agencyData) ? $agencyData : $agencyData->toArray();

    return new self([
      'profileType' => self::TYPE_TRAVEL_AGENCY,
      'profileId' => $agency['AgencyID'] ?? $agency['id'] ?? '0',
      'name' => $agency['Name'] ?? $agency['TradeName'] ?? 'Unknown Agency',
      'shortName' => substr($agency['Name'] ?? $agency['TradeName'] ?? '', 0, 20),
      'iataNumber' => $agency['AgencyCode'] ?? $agency['ABTAATOLNumber'] ?? null,
      'phone' => $agency['Telephone'] ?? $agency['Telephone1'] ?? null,
      'addressLine1' => $agency['Address'] ?? $agency['Address1'] ?? null,
      'city' => $agency['TownCity'] ?? null,
      'state' => $agency['County'] ?? null,
      'postalCode' => $agency['Postcode'] ?? null,
      'countryCode' => $agency['BookingCountryID'] ?? null,
      'commissionPercentage' => $agency['CommissionRate'] ?? null,
    ]);
  }

  /**
   * Create a corporate profile from Centrium trade data
   *
   * @param mixed $tradeData The Centrium trade/company data
   * @return self A new ProfileDataDto for the corporation
   */
  public static function createCorporateProfile($tradeData): self
  {
    // Convert to array if not already
    $trade = is_array($tradeData) ? $tradeData : $tradeData->toArray();

    return new self([
      'profileType' => self::TYPE_CORPORATE,
      'profileId' => $trade['TradeID'] ?? $trade['id'] ?? '0',
      'name' => $trade['TradeName'] ?? 'Unknown Company',
      'shortName' => substr($trade['TradeName'] ?? '', 0, 20),
      'corporateId' => $trade['TradeCode'] ?? null,
      'phone' => $trade['Telephone'] ?? $trade['Telephone1'] ?? null,
      'email' => $trade['Email'] ?? null,
      'addressLine1' => $trade['Address1'] ?? null,
      'addressLine2' => $trade['Address2'] ?? null,
      'city' => $trade['TownCity'] ?? null,
      'state' => $trade['County'] ?? null,
      'postalCode' => $trade['Postcode'] ?? null,
      'countryCode' => $trade['BookingCountryID'] ?? null,
    ]);
  }

  /**
   * Create a group profile from Centrium booking group data
   *
   * @param mixed $bookingGroupData The Centrium booking group data
   * @return self A new ProfileDataDto for the group
   */
  public static function createGroupProfile($bookingGroupData): self
  {
    // Convert to array if not already
    $group = is_array($bookingGroupData) ? $bookingGroupData : $bookingGroupData->toArray();

    return new self([
      'profileType' => self::TYPE_GROUP,
      'profileId' => $group['BookingGroupID'] ?? $group['id'] ?? '0',
      'name' => $group['Name'] ?? 'Unknown Group',
      'phone' => $group['Telephone'] ?? null,
      'addressLine1' => $group['Address'] ?? null,
    ]);
  }

  /**
   * Convert to array representation
   *
   * @return array<string, mixed> The profile data as an array
   */
  public function toArray(): array
  {
    return [
      'profileType' => $this->profileType,
      'profileId' => $this->profileId,
      'name' => $this->name,
      'shortName' => $this->shortName,
      'iataNumber' => $this->iataNumber,
      'contactName' => $this->contactName,
      'email' => $this->email,
      'phone' => $this->phone,
      'fax' => $this->fax,
      'addressLine1' => $this->addressLine1,
      'addressLine2' => $this->addressLine2,
      'city' => $this->city,
      'state' => $this->state,
      'postalCode' => $this->postalCode,
      'countryCode' => $this->countryCode,
      'commissionPercentage' => $this->commissionPercentage,
      'corporateId' => $this->corporateId,
      'travelAgentId' => $this->travelAgentId,
    ];
  }
}
