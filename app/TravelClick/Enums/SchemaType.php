<?php

declare(strict_types=1);

namespace App\TravelClick\Enums;

/**
 * SchemaType Enum
 *
 * Defines the types of XML schemas used in HTNG 2011B interface.
 * Each schema type corresponds to a specific message type and XSD file.
 */
enum SchemaType: string
{
    case INVENTORY = 'inventory';
    case RATE = 'rate';
    case RESERVATION = 'reservation';
    case BLOCK = 'block';
    case AVAILABILITY = 'availability';
    case RESTRICTION = 'restriction';
    case PROFILE = 'profile';
    case RESPONSE = 'response';

    /**
     * Get the XSD filename for this schema type
     */
    public function getXsdFilename(): string
    {
        return match ($this) {
            self::INVENTORY => 'OTA_HotelInvCountNotif.xsd',
            self::RATE => 'OTA_HotelRateAmountNotif.xsd',
            self::RESERVATION => 'OTA_HotelResNotif.xsd',
            self::BLOCK => 'OTA_HotelInvBlockNotif.xsd',
            self::AVAILABILITY => 'OTA_HotelAvailNotif.xsd',
            self::RESTRICTION => 'OTA_HotelAvailNotif.xsd',
            self::PROFILE => 'OTA_ProfileNotif.xsd',
            self::RESPONSE => 'OTA_ResRetrieveRS.xsd',
        };
    }

    /**
     * Get the root element name for this schema type
     */
    public function getRootElement(): string
    {
        return match ($this) {
            self::INVENTORY => 'OTA_HotelInvCountNotifRQ',
            self::RATE => 'OTA_HotelRateAmountNotifRQ',
            self::RESERVATION => 'OTA_HotelResNotifRQ',
            self::BLOCK => 'OTA_HotelInvBlockNotifRQ',
            self::AVAILABILITY => 'OTA_HotelAvailNotifRQ',
            self::RESTRICTION => 'OTA_HotelAvailNotifRQ',
            self::PROFILE => 'OTA_ProfileNotifRQ',
            self::RESPONSE => 'OTA_ResRetrieveRS',
        };
    }

    /**
     * Get the corresponding MessageType for this schema
     */
    public function getMessageType(): MessageType
    {
        return match ($this) {
            self::INVENTORY => MessageType::INVENTORY,
            self::RATE => MessageType::RATES,
            self::RESERVATION => MessageType::RESERVATION,
            self::BLOCK => MessageType::GROUP_BLOCK,
            self::AVAILABILITY => MessageType::AVAILABILITY,
            self::RESTRICTION => MessageType::RESTRICTIONS,
            self::PROFILE => MessageType::PROFILE,
            self::RESPONSE => MessageType::RESPONSE,
        };
    }

    /**
     * Get the namespace URI for this schema type
     */
    public function getNamespaceUri(): string
    {
        // All HTNG 2011B schemas use the same OTA namespace
        return 'http://www.opentravel.org/OTA/2003/05';
    }

    /**
     * Get the full path to the XSD file
     */
    public function getXsdPath(): string
    {
        return storage_path('schemas/htng/' . $this->getXsdFilename());
    }

    /**
     * Check if XSD file exists for this schema type
     */
    public function hasXsdFile(): bool
    {
        return file_exists($this->getXsdPath());
    }

    /**
     * Get required elements for this schema type
     *
     * @return array<string>
     */
    public function getRequiredElements(): array
    {
        return match ($this) {
            self::INVENTORY => [
                'Inventories',
                'Inventory',
                'StatusApplicationControl',
                'InvCounts',
                'InvCount'
            ],
            self::RATE => [
                'RateAmountMessages',
                'RateAmountMessage',
                'StatusApplicationControl',
                'Rates',
                'Rate'
            ],
            self::RESERVATION => [
                'HotelReservations',
                'HotelReservation',
                'ResGuests',
                'RoomStays'
            ],
            self::BLOCK => [
                'InvBlocks',
                'InvBlock',
                'InvBlockDates',
                'RoomTypes'
            ],
            self::AVAILABILITY => [
                'AvailStatusMessages',
                'AvailStatusMessage',
                'StatusApplicationControl'
            ],
            self::RESTRICTION => [
                'AvailStatusMessages',
                'AvailStatusMessage',
                'StatusApplicationControl',
                'LengthsOfStay'
            ],
            default => [],
        };
    }

    /**
     * Get valid operations for this schema type
     *
     * @return array<string>
     */
    public function getValidOperations(): array
    {
        return match ($this) {
            self::INVENTORY, self::RATE, self::AVAILABILITY, self::RESTRICTION => [
                'create',
                'modify',
                'remove'
            ],
            self::RESERVATION => [
                'create',
                'modify',
                'cancel'
            ],
            self::BLOCK => [
                'create',
                'modify',
                'cancel'
            ],
            self::PROFILE => [
                'create',
                'modify'
            ],
            self::RESPONSE => [
                'read'
            ],
        };
    }

    /**
     * Check if operation is valid for this schema type
     */
    public function isValidOperation(string $operation): bool
    {
        return in_array($operation, $this->getValidOperations());
    }

    /**
     * Get XML validation rules specific to this schema type
     *
     * @return array<string, mixed>
     */
    public function getValidationRules(): array
    {
        return match ($this) {
            self::INVENTORY => [
                'hotel_code_required' => true,
                'date_range_required' => true,
                'inv_type_required' => true,
                'count_type_validation' => true,
                'max_date_range_days' => 365,
            ],
            self::RATE => [
                'hotel_code_required' => true,
                'date_range_required' => true,
                'rate_plan_required' => true,
                'currency_required' => true,
                'amount_validation' => true,
                'max_date_range_days' => 365,
            ],
            self::RESERVATION => [
                'hotel_code_required' => true,
                'guest_info_required' => true,
                'room_stays_required' => true,
                'confirmation_number' => true,
                'arrival_departure_required' => true,
            ],
            self::BLOCK => [
                'hotel_code_required' => true,
                'block_code_required' => true,
                'date_range_required' => true,
                'room_types_required' => true,
                'contact_required' => true,
            ],
            default => [],
        };
    }

    /**
     * Get schema type from message type
     */
    public static function fromMessageType(MessageType $messageType): ?self
    {
        return match ($messageType) {
            MessageType::INVENTORY => self::INVENTORY,
            MessageType::RATES => self::RATE,
            MessageType::RESERVATION => self::RESERVATION,
            MessageType::GROUP_BLOCK => self::BLOCK,
            MessageType::AVAILABILITY => self::AVAILABILITY,
            MessageType::RESTRICTIONS => self::RESTRICTION,
            MessageType::PROFILE => self::PROFILE,
            MessageType::RESPONSE => self::RESPONSE,
        };
    }

    /**
     * Get schema type from root element name
     */
    public static function fromRootElement(string $rootElement): ?self
    {
        foreach (self::cases() as $schema) {
            if ($schema->getRootElement() === $rootElement) {
                return $schema;
            }
        }
        return null;
    }

    /**
     * Get all schema types that support a specific operation
     *
     * @param string $operation
     * @return array<self>
     */
    public static function getSupportingOperation(string $operation): array
    {
        return array_filter(
            self::cases(),
            fn(self $schema) => $schema->isValidOperation($operation)
        );
    }

    /**
     * Convert to array for configuration or API responses
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->value,
            'xsd_filename' => $this->getXsdFilename(),
            'root_element' => $this->getRootElement(),
            'namespace_uri' => $this->getNamespaceUri(),
            'message_type' => $this->getMessageType()->value,
            'required_elements' => $this->getRequiredElements(),
            'valid_operations' => $this->getValidOperations(),
            'validation_rules' => $this->getValidationRules(),
            'has_xsd_file' => $this->hasXsdFile(),
        ];
    }
}
