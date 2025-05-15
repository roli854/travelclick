<?php

declare(strict_types=1);

namespace App\TravelClick\Support;

use App\TravelClick\Enums\MessageType;
use InvalidArgumentException;

/**
 * Registry for mapping HTNG 2011B message types to their corresponding XSD schema files.
 *
 * This class provides a centralized way to locate and load XSD schemas for validation
 * of different message types in the TravelClick integration.
 */
class XsdSchemas
{
  /**
   * Path to the directory containing XSD schema files
   */
  private const SCHEMA_PATH = 'schemas/htng/';

  /**
   * Mapping of message types to their XSD schema filenames
   */
  private const SCHEMA_MAP = [
    MessageType::INVENTORY => 'OTA_HotelInvCountNotifRQ.xsd',
    MessageType::RATES => 'OTA_HotelRateNotifRQ.xsd',
    MessageType::RESERVATION => 'OTA_HotelResNotifRQ.xsd',
    MessageType::GROUP_BLOCK => 'OTA_HotelInvBlockNotifRQ.xsd',
  ];

  /**
   * Cache for loaded schema contents
   */
  private static array $schemaCache = [];

  /**
   * Get the file path for a specific message type's XSD schema
   *
   * @param MessageType $messageType The message type to get schema for
   * @return string Full path to the XSD file
   * @throws InvalidArgumentException If schema not found for message type
   */
  public static function getSchemaPath(MessageType $messageType): string
  {
    if (!isset(self::SCHEMA_MAP[$messageType])) {
      throw new InvalidArgumentException(
        "No XSD schema defined for message type: {$messageType->value}"
      );
    }

    return storage_path(self::SCHEMA_PATH . self::SCHEMA_MAP[$messageType]);
  }

  /**
   * Get the content of an XSD schema for a specific message type
   *
   * @param MessageType $messageType The message type to get schema content for
   * @return string The XSD schema content
   * @throws InvalidArgumentException If schema file not found or not readable
   */
  public static function getSchemaContent(MessageType $messageType): string
  {
    $cacheKey = $messageType->value;

    // Return cached content if available
    if (isset(self::$schemaCache[$cacheKey])) {
      return self::$schemaCache[$cacheKey];
    }

    $schemaPath = self::getSchemaPath($messageType);

    if (!file_exists($schemaPath)) {
      throw new InvalidArgumentException(
        "XSD schema file not found: {$schemaPath}"
      );
    }

    if (!is_readable($schemaPath)) {
      throw new InvalidArgumentException(
        "XSD schema file not readable: {$schemaPath}"
      );
    }

    $content = file_get_contents($schemaPath);

    if ($content === false) {
      throw new InvalidArgumentException(
        "Failed to read XSD schema file: {$schemaPath}"
      );
    }

    // Cache the content
    self::$schemaCache[$cacheKey] = $content;

    return $content;
  }

  /**
   * Check if a schema exists for a specific message type
   *
   * @param MessageType $messageType The message type to check
   * @return bool True if schema exists and is readable
   */
  public static function hasSchema(MessageType $messageType): bool
  {
    try {
      $schemaPath = self::getSchemaPath($messageType);
      return file_exists($schemaPath) && is_readable($schemaPath);
    } catch (InvalidArgumentException) {
      return false;
    }
  }

  /**
   * Get all available message types that have corresponding XSD schemas
   *
   * @return array<MessageType> Array of message types with available schemas
   */
  public static function getAvailableMessageTypes(): array
  {
    $available = [];

    foreach (array_keys(self::SCHEMA_MAP) as $messageType) {
      if (self::hasSchema(MessageType::from($messageType))) {
        $available[] = $messageType;
      }
    }

    return $available;
  }

  /**
   * Clear the schema cache
   *
   * @return void
   */
  public static function clearCache(): void
  {
    self::$schemaCache = [];
  }

  /**
   * Validate that all required schemas are present
   *
   * @return array<string> Array of missing schema files
   */
  public static function validateSchemaAvailability(): array
  {
    $missing = [];

    foreach (self::SCHEMA_MAP as $messageType => $filename) {
      if (!self::hasSchema(MessageType::from($messageType))) {
        $missing[] = $filename;
      }
    }

    return $missing;
  }

  /**
   * Get schema validation statistics
   *
   * @return array{total: int, available: int, missing: int, percent_available: float}
   */
  public static function getSchemaStats(): array
  {
    $total = count(self::SCHEMA_MAP);
    $available = count(self::getAvailableMessageTypes());
    $missing = $total - $available;
    $percentAvailable = $total > 0 ? round(($available / $total) * 100, 2) : 0;

    return [
      'total' => $total,
      'available' => $available,
      'missing' => $missing,
      'percent_available' => $percentAvailable,
    ];
  }
}
