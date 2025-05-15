<?php

namespace App\TravelClick\Enums;

/**
 * MessageType Enum for TravelClick Integration
 *
 * Defines all the different types of messages we can send to or receive from TravelClick.
 * Each message type has specific XML structures and processing requirements.
 *
 * Think of these as different types of letters you might send:
 * - Inventory: "Here's our room availability"
 * - Rates: "Here are our prices"
 * - Reservation: "We have a new booking"
 * - etc.
 */
enum MessageType: string
{
/**
     * Inventory Messages - OTA_HotelInvCountNotifRQ
     * Used to update room availability and counts
     */
    case INVENTORY = 'inventory';

/**
     * Rate Messages - OTA_HotelRateNotifRQ
     * Used to update room rates and pricing
     */
    case RATES = 'rates';

/**
     * Reservation Messages - OTA_HotelResNotifRQ
     * Used to send new reservations, modifications, or cancellations
     */
    case RESERVATION = 'reservation';

/**
     * Restriction Messages - OTA_HotelAvailNotifRQ
     * Used to send availability restrictions (stop sale, min/max stay, etc.)
     */
    case RESTRICTIONS = 'restrictions';

/**
     * Group Block Messages - OTA_HotelInvBlockNotifRQ
     * Used to create, modify, or cancel group allocations
     */
    case GROUP_BLOCK = 'group_block';

/**
     * Response Messages - Various response types
     * Used for acknowledgments and error responses
     */
    case RESPONSE = 'response';

    case UNKNOWN = 'unknown'; // For any unknown or unsupported message types
    /**
     * Get the OTA message name for XML
     */
    public function getOTAMessageName(): string
    {
        return match ($this) {
            self::INVENTORY => 'OTA_HotelInvCountNotifRQ',
            self::RATES => 'OTA_HotelRateNotifRQ',
            self::RESERVATION => 'OTA_HotelResNotifRQ',
            self::RESTRICTIONS => 'OTA_HotelAvailNotifRQ',
            self::GROUP_BLOCK => 'OTA_HotelInvBlockNotifRQ',
            self::RESPONSE => 'OTA_HotelResNotifRS', // Generic response
        };
    }

    /**
     * Get the queue name for this message type
     */
    public function getQueueName(): string
    {
        return match ($this) {
            self::INVENTORY => config('travelclick.queues.outbound'),
            self::RATES => config('travelclick.queues.outbound'),
            self::RESERVATION => config('travelclick.queues.high_priority'),
            self::RESTRICTIONS => config('travelclick.queues.outbound'),
            self::GROUP_BLOCK => config('travelclick.queues.outbound'),
            self::RESPONSE => config('travelclick.queues.inbound'),
        };
    }

    /**
     * Get timeout for this message type (in seconds)
     */
    public function getTimeout(): int
    {
        return match ($this) {
            self::INVENTORY => config('travelclick.message_types.inventory.timeout_seconds', 60),
            self::RATES => config('travelclick.message_types.rates.timeout_seconds', 90),
            self::RESERVATION => config('travelclick.message_types.reservations.timeout_seconds', 120),
            self::RESTRICTIONS => config('travelclick.message_types.restrictions.timeout_seconds', 45),
            self::GROUP_BLOCK => config('travelclick.message_types.groups.timeout_seconds', 180),
            self::RESPONSE => 30,
        };
    }

    /**
     * Get batch size for this message type
     */
    public function getBatchSize(): int
    {
        return match ($this) {
            self::INVENTORY => config('travelclick.message_types.inventory.batch_size', 100),
            self::RATES => config('travelclick.message_types.rates.batch_size', 50),
            self::RESERVATION => config('travelclick.message_types.reservations.batch_size', 20),
            self::RESTRICTIONS => config('travelclick.message_types.restrictions.batch_size', 200),
            self::GROUP_BLOCK => config('travelclick.message_types.groups.batch_size', 10),
            self::RESPONSE => 1,
        };
    }

    /**
     * Check if this message type is enabled in config
     */
    public function isEnabled(): bool
    {
        return config("travelclick.message_types.{$this->value}.enabled", false);
    }

    /**
     * Get priority level (1 = highest, 10 = lowest)
     */
    public function getPriority(): int
    {
        return match ($this) {
            self::RESERVATION => 1,  // Highest priority
            self::RESPONSE => 2,
            self::INVENTORY => 3,
            self::RATES => 4,
            self::RESTRICTIONS => 5,
            self::GROUP_BLOCK => 6,  // Lowest priority
        };
    }

    /**
     * Get human-readable description
     */
    public function description(): string
    {
        return match ($this) {
            self::INVENTORY => 'Room inventory and availability updates',
            self::RATES => 'Room rates and pricing information',
            self::RESERVATION => 'Guest reservations and bookings',
            self::RESTRICTIONS => 'Booking restrictions and availability rules',
            self::GROUP_BLOCK => 'Group allocations and blocks',
            self::RESPONSE => 'Response and acknowledgment messages',
        };
    }

    /**
     * Get all outbound message types
     */
    public static function outboundTypes(): array
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
     * Get all inbound message types
     */
    public static function inboundTypes(): array
    {
        return [
            self::RESERVATION,
            self::RESPONSE,
        ];
    }
}
