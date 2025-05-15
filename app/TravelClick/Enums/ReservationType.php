<?php

namespace App\TravelClick\Enums;

/**
 * ReservationType Enum for TravelClick Integration
 *
 * Defines the different types of reservations supported by TravelClick.
 * Each type has specific processing requirements and XML structures.
 *
 * Based on the TravelClick documentation, these are the mandatory reservation types:
 * - Transient (individual guest)
 * - Travel Agency
 * - Corporate
 * - Package
 * - Group
 * - Alternate Payment (special payment scenarios)
 */
enum ReservationType: string
{
/**
     * Standard individual guest reservations
     * Most common type - regular travelers booking directly or through third parties
     */
    case TRANSIENT = 'transient';

/**
     * Travel Agency reservations
     * Include travel agency profile and IATA information
     * Require commission handling
     */
    case TRAVEL_AGENCY = 'travel_agency';

/**
     * Corporate reservations
     * Include company profile and corporate rates
     * May have special terms and conditions
     */
    case CORPORATE = 'corporate';

/**
     * Package reservations
     * Include bundled services (room + amenities/services)
     * Rate plan code identifies the package
     */
    case PACKAGE = 'package';

/**
     * Group reservations
     * Associated with a group block
     * Decrement group inventory allocation
     */
    case GROUP = 'group';

/**
     * Reservations with alternate payment methods
     * For scenarios with special payment processing (deposits, etc.)
     */
    case ALTERNATE_PAYMENT = 'alternate_payment';

    /**
     * Get human-readable description
     */
    public function description(): string
    {
        return match ($this) {
            self::TRANSIENT => 'Individual guest reservation',
            self::TRAVEL_AGENCY => 'Travel agency booking with commission',
            self::CORPORATE => 'Corporate booking with company profile',
            self::PACKAGE => 'Package booking with bundled services',
            self::GROUP => 'Group reservation from block allocation',
            self::ALTERNATE_PAYMENT => 'Reservation with special payment processing',
        };
    }

    /**
     * Check if this reservation type is mandatory to support
     */
    public function isMandatory(): bool
    {
        // All types are mandatory according to TravelClick docs
        return true;
    }

    /**
     * Check if this reservation type requires a profile
     */
    public function requiresProfile(): bool
    {
        return match ($this) {
            self::TRAVEL_AGENCY => true,  // Travel agency profile
            self::CORPORATE => true,      // Company profile
            self::GROUP => true,          // Group profile
            self::TRANSIENT, self::PACKAGE, self::ALTERNATE_PAYMENT => false,
        };
    }

    /**
     * Get the profile type required for this reservation
     */
    public function getRequiredProfileType(): ?string
    {
        return match ($this) {
            self::TRAVEL_AGENCY => 'TravelAgency',
            self::CORPORATE => 'Company',
            self::GROUP => 'Group',
            default => null,
        };
    }

    /**
     * Check if this reservation type affects inventory
     */
    public function affectsInventory(): bool
    {
        return match ($this) {
            self::GROUP => false,  // Groups affect block inventory, not general inventory
            default => true,       // All others affect general room inventory
        };
    }

    /**
     * Check if this reservation type supports commission
     */
    public function supportsCommission(): bool
    {
        return match ($this) {
            self::TRAVEL_AGENCY => true,
            self::CORPORATE => true,  // Some corporate rates have commissions
            default => false,
        };
    }

    /**
     * Get priority for processing (1 = highest, 10 = lowest)
     */
    public function getProcessingPriority(): int
    {
        return match ($this) {
            self::GROUP => 1,              // Highest - affects allocations
            self::TRAVEL_AGENCY => 2,      // High - commission sensitive
            self::CORPORATE => 3,          // High - business critical
            self::TRANSIENT => 4,          // Standard priority
            self::PACKAGE => 5,            // Standard priority
            self::ALTERNATE_PAYMENT => 6,  // Lower - special handling
        };
    }

    /**
     * Map from Centrium booking source to reservation type
     */
    public static function fromCentriumBookingSource(string $source, ?string $bookingType = null): self
    {
        // Map based on Centrium booking source and type
        return match ($source) {
            'AGENT', 'TA' => self::TRAVEL_AGENCY,
            'CORPORATE', 'CORP' => self::CORPORATE,
            'GROUP' => self::GROUP,
            'PACKAGE', 'PKG' => self::PACKAGE,
            default => self::TRANSIENT,
        };
    }

    /**
     * Get Centrium source value for this reservation type
     */
    public function getCentriumSource(): string
    {
        return 'XML_TRVC';  // All TravelClick reservations use this source
    }

    /**
     * Get fields required in Centrium booking for this type
     */
    public function getRequiredCentriumFields(): array
    {
        $baseFields = ['LeadGuestFirstName', 'LeadGuestLastName', 'ArrivalDate', 'DepartureDate'];

        return match ($this) {
            self::TRAVEL_AGENCY => array_merge($baseFields, ['AgentID']),
            self::CORPORATE => array_merge($baseFields, ['CompanyID']),
            self::GROUP => array_merge($baseFields, ['BookingGroupID']),
            default => $baseFields,
        };
    }

    /**
     * Get all reservation types that TravelClick can send to us
     */
    public static function inboundTypes(): array
    {
        return [
            self::TRANSIENT,
            self::TRAVEL_AGENCY,
            self::CORPORATE,
            self::PACKAGE,
            self::GROUP,
            self::ALTERNATE_PAYMENT,
        ];
    }

    /**
     * Get reservation types we can send to TravelClick
     */
    public static function outboundTypes(): array
    {
        // We can send all types to TravelClick
        return self::inboundTypes();
    }
}
