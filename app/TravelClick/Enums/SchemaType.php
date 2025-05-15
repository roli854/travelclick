<?php

declare(strict_types=1);

namespace App\TravelClick\Enums;

/**
 * XSD Schema Types for HTNG 2011B Messages
 *
 * Maps message types to their corresponding XSD schema files for validation.
 * Each schema type corresponds to a specific HTNG 2011B message structure.
 */
enum SchemaType: string
{
    case INVENTORY = 'OTA_HotelInvCountNotif.xsd';
    case RATES = 'OTA_HotelRateNotif.xsd';
    case RESERVATION = 'OTA_HotelResNotif.xsd';
    case RESTRICTIONS = 'OTA_HotelAvailNotif.xsd';
    case GROUP_BLOCK = 'OTA_HotelInvBlockNotif.xsd';

    /**
     * Get the corresponding MessageType for this schema
     */
    public function getMessageType(): MessageType
    {
        return match ($this) {
            self::INVENTORY => MessageType::INVENTORY,
            self::RATES => MessageType::RATES,
            self::RESERVATION => MessageType::RESERVATION,
            self::RESTRICTIONS => MessageType::RESTRICTIONS,
            self::GROUP_BLOCK => MessageType::GROUP_BLOCK,
        };
    }

    /**
     * Get schema from MessageType
     */
    public static function fromMessageType(MessageType $messageType): self
    {
        return match ($messageType) {
            MessageType::INVENTORY => self::INVENTORY,
            MessageType::RATES => self::RATES,
            MessageType::RESERVATION => self::RESERVATION,
            MessageType::RESTRICTIONS => self::RESTRICTIONS,
            MessageType::GROUP_BLOCK => self::GROUP_BLOCK,
            MessageType::RESPONSE => self::RESERVATION, // Response uses reservation schema structure
            MessageType::UNKNOWN => throw new \InvalidArgumentException('Cannot determine schema for unknown message type'),
        };
    }

    /**
     * Get the full path to the schema file
     */
    public function getSchemaPath(): string
    {
        return storage_path('schemas/htng/' . $this->value);
    }

    /**
     * Get the schema namespace
     */
    public function getNamespace(): string
    {
        return match ($this) {
            self::INVENTORY => 'http://www.opentravel.org/OTA/2003/05',
            self::RATES => 'http://www.opentravel.org/OTA/2003/05',
            self::RESERVATION => 'http://www.opentravel.org/OTA/2003/05',
            self::RESTRICTIONS => 'http://www.opentravel.org/OTA/2003/05',
            self::GROUP_BLOCK => 'http://www.opentravel.org/OTA/2003/05',
        };
    }

    /**
     * Get the root element name for the schema
     */
    public function getRootElement(): string
    {
        return match ($this) {
            self::INVENTORY => 'OTA_HotelInvCountNotifRQ',
            self::RATES => 'OTA_HotelRateNotifRQ',
            self::RESERVATION => 'OTA_HotelResNotifRQ',
            self::RESTRICTIONS => 'OTA_HotelAvailNotifRQ',
            self::GROUP_BLOCK => 'OTA_HotelInvBlockNotifRQ',
        };
    }

    /**
     * Get the corresponding OTA message name
     */
    public function getOTAMessageName(): string
    {
        return $this->getMessageType()->getOTAMessageName();
    }

    /**
     * Check if schema file exists
     */
    public function exists(): bool
    {
        return file_exists($this->getSchemaPath());
    }

    /**
     * Get schema validation rules specific to this type
     */
    public function getValidationRules(): array
    {
        return match ($this) {
            self::INVENTORY => [
                'required_elements' => ['Inventories', 'Inventory', 'InvCounts'],
                'required_attributes' => ['HotelCode', 'CountType'],
                'count_types' => [1, 2, 4, 5, 6, 99], // Valid CountType values
                'allow_overbook' => true,
            ],

            self::RATES => [
                'required_elements' => ['RateAmountMessages', 'RateAmountMessage'],
                'required_attributes' => ['HotelCode', 'RatePlanCode'],
                'currency_required' => true,
                'allow_derived_rates' => true,
            ],

            self::RESERVATION => [
                'required_elements' => ['HotelReservations', 'HotelReservation'],
                'required_attributes' => ['HotelCode'],
                'guest_required' => true,
                'status_required' => true,
                'payment_optional' => true,
            ],

            self::RESTRICTIONS => [
                'required_elements' => ['AvailStatusMessages', 'AvailStatusMessage'],
                'required_attributes' => ['HotelCode'],
                'restrictions_allowed' => [
                    'StopSale',
                    'MinLengthOfStay',
                    'MaxLengthOfStay',
                    'ClosedToArrival',
                    'ClosedToDeparture'
                ],
            ],

            self::GROUP_BLOCK => [
                'required_elements' => ['InvBlocks', 'InvBlock'],
                'required_attributes' => ['HotelCode', 'InvBlockCode'],
                'room_types_required' => true,
                'allocation_required' => true,
                'contact_optional' => true,
            ],
        };
    }

    /**
     * Get all schema types
     */
    public static function getAllSchemas(): array
    {
        return [
            self::INVENTORY,
            self::RATES,
            self::RESERVATION,
            self::RESTRICTIONS,
            self::GROUP_BLOCK,
        ];
    }

    /**
     * Get primary schemas (most commonly used)
     */
    public static function getPrimarySchemas(): array
    {
        return [
            self::INVENTORY,
            self::RATES,
            self::RESERVATION,
            self::GROUP_BLOCK,
        ];
    }

    /**
     * Get schema types that support batching
     */
    public static function getBatchableSchemas(): array
    {
        return [
            self::INVENTORY,
            self::RATES,
            self::RESTRICTIONS,
        ];
    }

    /**
     * Get the corresponding response schema name (for validation)
     */
    public function getResponseSchemaName(): string
    {
        return match ($this) {
            self::INVENTORY => 'OTA_HotelInvCountNotifRS',
            self::RATES => 'OTA_HotelRateNotifRS',
            self::RESERVATION => 'OTA_HotelResNotifRS',
            self::RESTRICTIONS => 'OTA_HotelAvailNotifRS',
            self::GROUP_BLOCK => 'OTA_HotelInvBlockNotifRS',
        };
    }

    /**
     * Check if this schema supports the given count type (for inventory)
     */
    public function supportsCountType(int $countType): bool
    {
        if ($this !== self::INVENTORY) {
            return false;
        }

        return in_array($countType, $this->getValidationRules()['count_types']);
    }

    /**
     * Get validation error messages for this schema type
     */
    public function getValidationMessages(): array
    {
        return match ($this) {
            self::INVENTORY => [
                'hotel_code.required' => 'Hotel code is required for inventory messages',
                'count_type.invalid' => 'Invalid count type. Must be one of: 1,2,4,5,6,99',
                'room_type.required' => 'Room type is required for inventory updates',
            ],

            self::RATES => [
                'hotel_code.required' => 'Hotel code is required for rate messages',
                'rate_plan.required' => 'Rate plan code is required',
                'currency.required' => 'Currency code is required for rates',
            ],

            self::RESERVATION => [
                'hotel_code.required' => 'Hotel code is required for reservation messages',
                'guest.required' => 'Guest information is required',
                'arrival_date.required' => 'Arrival date is required',
            ],

            self::RESTRICTIONS => [
                'hotel_code.required' => 'Hotel code is required for restriction messages',
                'date_range.required' => 'Date range is required for restrictions',
            ],

            self::GROUP_BLOCK => [
                'hotel_code.required' => 'Hotel code is required for group block messages',
                'block_code.required' => 'Block code is required',
                'room_allocation.required' => 'Room allocation is required',
            ],
        };
    }
}
