<?php

namespace App\TravelClick\Support;

use App\TravelClick\Enums\MessageType;
use Ramsey\Uuid\Uuid;
use InvalidArgumentException;

/**
 * MessageIdGenerator - Generates unique identifiers for SOAP messages
 *
 * This class creates standardized, traceable message IDs for all communications
 * with TravelClick PMS Connect. These IDs are critical for:
 *
 * - Message tracing and debugging
 * - Correlation of requests/responses
 * - Prevention of duplicate message processing
 * - Auditing and compliance
 */
class MessageIdGenerator
{
    /**
     * Standard format for message IDs: hotel-msgtype-uuid
     */
    private const ID_FORMAT = '%s-%s-%s';

    /**
     * Timestamp format for traceable IDs: hotel-msgtype-timestamp-uuid
     */
    private const TIMESTAMP_FORMAT = '%s-%s-%s-%s';

    /**
     * Pattern used to validate and parse message IDs
     */
    private const ID_PATTERN = '/^(\d+)-([a-z_]+)-([a-f0-9\-]+)$/i';

    /**
     * Generate a unique message ID for a specific hotel and message type
     *
     * @param string|int $hotelId      The hotel identifier
     * @param MessageType $messageType The type of message being sent
     * @param string|null $prefix      Optional prefix for the ID
     *
     * @return string The generated message ID
     */
    public static function generate($hotelId, MessageType $messageType, ?string $prefix = null): string
    {
        // Generate a UUID v4 (random-based)
        $uuid = Uuid::uuid4()->toString();

        // Create message ID with optional prefix
        if ($prefix) {
            return sprintf(
                self::ID_FORMAT,
                $hotelId,
                $prefix . '_' . $messageType->value,
                $uuid
            );
        }

        return sprintf(
            self::ID_FORMAT,
            $hotelId,
            $messageType->value,
            $uuid
        );
    }

    /**
     * Generate a timestamp-based message ID for traceability
     *
     * Includes a timestamp component for easier chronological tracing
     *
     * @param string|int $hotelId      The hotel identifier
     * @param MessageType $messageType The type of message being sent
     *
     * @return string The generated message ID with timestamp
     */
    public static function generateWithTimestamp($hotelId, MessageType $messageType): string
    {
        // Use UUID v1 which includes a timestamp component
        $uuid = Uuid::uuid1()->toString();

        // Include current timestamp in format YmdHis
        $timestamp = date('YmdHis');

        return sprintf(
            self::TIMESTAMP_FORMAT,
            $hotelId,
            $messageType->value,
            $timestamp,
            $uuid
        );
    }

    /**
     * Generate a deterministic (idempotent) message ID based on payload
     *
     * This ensures that identical requests generate the same ID,
     * helping prevent duplicate processing
     *
     * @param string|int $hotelId      The hotel identifier
     * @param MessageType $messageType The type of message being sent
     * @param string $payload          The message payload used for deterministic generation
     *
     * @return string The deterministic message ID
     */
    public static function generateIdempotent($hotelId, MessageType $messageType, string $payload): string
    {
        // Create a deterministic hash based on inputs
        $hash = md5($hotelId . $messageType->value . $payload);

        // Format the first 8 characters of hash as a UUID-like string for consistency
        $deterministicId = substr($hash, 0, 8) . '-' .
            substr($hash, 8, 4) . '-' .
            substr($hash, 12, 4) . '-' .
            substr($hash, 16, 4) . '-' .
            substr($hash, 20);

        return sprintf(
            self::ID_FORMAT,
            $hotelId,
            $messageType->value,
            $deterministicId
        );
    }

    /**
     * Parse a message ID into its component parts
     *
     * @param string $messageId The message ID to parse
     *
     * @return array{hotel_id: string, message_type: string, uuid: string} The components of the message ID
     *
     * @throws InvalidArgumentException If the message ID format is invalid
     */
    public static function parseMessageId(string $messageId): array
    {
        // Validate the message ID format
        if (!preg_match(self::ID_PATTERN, $messageId, $matches)) {
            throw new InvalidArgumentException("Invalid message ID format: {$messageId}");
        }

        // Return the parsed components
        return [
            'hotel_id' => $matches[1],
            'message_type' => $matches[2],
            'uuid' => $matches[3]
        ];
    }

    /**
     * Check if a message ID is valid
     *
     * @param string $messageId The message ID to validate
     *
     * @return bool True if the message ID is valid, false otherwise
     */
    public static function isValid(string $messageId): bool
    {
        return preg_match(self::ID_PATTERN, $messageId) === 1;
    }

    /**
     * Extract the hotel ID from a message ID
     *
     * @param string $messageId The message ID
     *
     * @return string|null The hotel ID or null if invalid
     */
    public static function extractHotelId(string $messageId): ?string
    {
        try {
            $parts = self::parseMessageId($messageId);
            return $parts['hotel_id'];
        } catch (InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * Extract the message type from a message ID
     *
     * @param string $messageId The message ID
     *
     * @return string|null The message type or null if invalid
     */
    public static function extractMessageType(string $messageId): ?string
    {
        try {
            $parts = self::parseMessageId($messageId);
            return $parts['message_type'];
        } catch (InvalidArgumentException $e) {
            return null;
        }
    }
}
