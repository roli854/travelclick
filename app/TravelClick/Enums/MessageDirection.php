<?php

namespace App\TravelClick\Enums;

/**
 * MessageDirection Enum for TravelClick Integration
 *
 * Tracks whether a message is going to TravelClick (outbound) or coming from TravelClick (inbound).
 * This is essential for logging, monitoring, and organizing our message flow.
 *
 * Like marking letters as "incoming mail" or "outgoing mail".
 */
enum MessageDirection: string
{
/**
     * Messages sent from Centrium to TravelClick
     * Examples: Inventory updates, rate updates, new reservations
     */
    case OUTBOUND = 'outbound';

/**
     * Messages received from TravelClick to Centrium
     * Examples: New reservations, reservation modifications, responses
     */
    case INBOUND = 'inbound';

    /**
     * Get human-readable description
     */
    public function description(): string
    {
        return match ($this) {
            self::OUTBOUND => 'Sent to TravelClick',
            self::INBOUND => 'Received from TravelClick',
        };
    }

    /**
     * Get the opposite direction
     * Useful for response messages
     */
    public function opposite(): self
    {
        return match ($this) {
            self::OUTBOUND => self::INBOUND,
            self::INBOUND => self::OUTBOUND,
        };
    }

    /**
     * Get appropriate log level for this direction
     */
    public function getLogLevel(): string
    {
        return match ($this) {
            self::OUTBOUND => 'info',  // We control outbound, so info level
            self::INBOUND => 'debug',  // Inbound needs more detailed logging
        };
    }

    /**
     * Get default queue for this direction
     */
    public function getDefaultQueue(): string
    {
        return match ($this) {
            self::OUTBOUND => config('travelclick.queues.outbound'),
            self::INBOUND => config('travelclick.queues.inbound'),
        };
    }

    /**
     * Check if this direction allows specific message types
     */
    public function allowsMessageType(MessageType $messageType): bool
    {
        return match ($this) {
            self::OUTBOUND => in_array($messageType, [
                MessageType::INVENTORY,
                MessageType::RATES,
                MessageType::RESERVATION,
                MessageType::RESTRICTIONS,
                MessageType::GROUP_BLOCK,
            ]),
            self::INBOUND => in_array($messageType, [
                MessageType::RESERVATION,
                MessageType::RESPONSE,
            ]),
        };
    }

    /**
     * Get icon for UI display
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::OUTBOUND => 'arrow-up-right',
            self::INBOUND => 'arrow-down-left',
        };
    }

    /**
     * Get color for UI display
     */
    public function getColor(): string
    {
        return match ($this) {
            self::OUTBOUND => 'blue',
            self::INBOUND => 'green',
        };
    }
}
