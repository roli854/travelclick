<?php

namespace App\TravelClick\Support;

use App\TravelClick\Enums\MessageType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * TravelClick Helper
 *
 * Provides utility functions for the TravelClick integration.
 * This class contains common helper methods that are used across
 * different parts of the TravelClick integration.
 */
class TravelClickHelper
{
  /**
   * Validates a hotel code according to TravelClick requirements.
   *
   * Hotel codes must follow specific patterns defined in the config,
   * typically alphanumeric with specific length constraints.
   *
   * @param string $hotelCode The hotel code to validate
   * @return bool True if the hotel code is valid, false otherwise
   */
  public static function isValidHotelCode(string $hotelCode): bool
  {
    $config = Config::get('travelclick.validation.hotel_code', [
      'min_length' => 1,
      'max_length' => 10,
      'pattern' => '/^[A-Za-z0-9]+$/',
    ]);

    // Check length constraints
    $length = strlen($hotelCode);
    if ($length < $config['min_length'] || $length > $config['max_length']) {
      return false;
    }

    // Check pattern (alphanumeric by default)
    if (!preg_match($config['pattern'], $hotelCode)) {
      return false;
    }

    return true;
  }

  /**
   * Format a date to HTNG format (YYYY-MM-DD).
   *
   * @param Carbon|string $date The date to format
   * @return string The formatted date string
   */
  public static function formatDateForHtng(Carbon|string $date): string
  {
    // Convert to Carbon if it's a string
    if (is_string($date)) {
      $date = Carbon::parse($date);
    }

    // Format according to HTNG standard (YYYY-MM-DD)
    return $date->format('Y-m-d');
  }

  /**
   * Format a datetime to HTNG format (YYYY-MM-DDThh:mm:ss).
   *
   * @param Carbon|string $dateTime The datetime to format
   * @return string The formatted datetime string
   */
  public static function formatDateTimeForHtng(Carbon|string $dateTime): string
  {
    // Convert to Carbon if it's a string
    if (is_string($dateTime)) {
      $dateTime = Carbon::parse($dateTime);
    }

    // Format according to HTNG standard (YYYY-MM-DDThh:mm:ss)
    return $dateTime->format('Y-m-d\TH:i:s');
  }

  /**
   * Parse a date from HTNG format to a Carbon instance.
   *
   * @param string $htngDate The date in HTNG format (YYYY-MM-DD)
   * @return Carbon The parsed Carbon instance
   */
  public static function parseDateFromHtng(string $htngDate): Carbon
  {
    return Carbon::createFromFormat('Y-m-d', $htngDate);
  }

  /**
   * Parse a datetime from HTNG format to a Carbon instance.
   *
   * @param string $htngDateTime The datetime in HTNG format (YYYY-MM-DDThh:mm:ss)
   * @return Carbon The parsed Carbon instance
   */
  public static function parseDateTimeFromHtng(string $htngDateTime): Carbon
  {
    return Carbon::createFromFormat('Y-m-d\TH:i:s', $htngDateTime);
  }

  /**
   * Map a source of business to the corresponding TravelClick code.
   *
   * TravelClick expects specific codes for different booking sources,
   * this function maps our internal codes to their expected values.
   *
   * @param string $source The internal source of business
   * @return string|null The mapped TravelClick code, or null if not found
   */
  public static function mapSourceOfBusiness(string $source): ?string
  {
    // Source of business mappings are defined in config
    $mappings = Config::get('travelclick.source_of_business', [
      'SABRE' => 'SABRE',
      'WORLDSPAN' => 'WORLDSPAN',
      'AMADEUS' => 'AMADEUS',
      'GALILEO' => 'GALILEO',
      'TRAVELWEB' => 'TRAVELWEB',
      'TravelWeb Net Rates' => 'TRAVELWEBNETRATE',
      'WEB' => 'WEB',
      'WEB Group' => 'WEBGRP',
      'WEB Travel Agent' => 'WEBTA',
      'Call (3rd party)' => 'CALLCTR',
      'Call (property)' => 'CALLHOTEL',
      'PMS' => 'PMS',
      'IHOS' => 'IHOS',
    ]);

    return $mappings[$source] ?? null;
  }

  /**
   * Get all available source of business mappings.
   *
   * @return array Array of source of business mappings
   */
  public static function getAllSourceOfBusinessMappings(): array
  {
    return Config::get('travelclick.source_of_business', []);
  }

  /**
   * Generate WSSE headers for TravelClick SOAP authentication.
   *
   * WSSE (Web Services Security) is used by TravelClick for secure
   * SOAP message exchange. This implements WSSE Username Token Profile.
   *
   * @param string|null $username Optional username (defaults to config)
   * @param string|null $password Optional password (defaults to config)
   * @return array The WSSE headers array
   */
  public static function generateWsseHeaders(?string $username = null, ?string $password = null): array
  {
    // Use credentials from config if not provided
    $username = $username ?? Config::get('travelclick.credentials.username');
    $password = $password ?? Config::get('travelclick.credentials.password');

    if (empty($username) || empty($password)) {
      throw new RuntimeException('TravelClick credentials are not configured');
    }

    // Create nonce - a random value that helps prevent replay attacks
    $nonce = random_bytes(16);
    $nonceEncoded = base64_encode($nonce);

    // Create timestamp for the request
    $created = Carbon::now()->format('Y-m-d\TH:i:s\Z');

    // Create the password digest
    // The digest is: Base64(SHA1(nonce + created + password))
    $digest = base64_encode(sha1($nonce . $created . $password, true));

    // Return the security headers structure
    return [
      'wsse:Security' => [
        'wsse:UsernameToken' => [
          'wsse:Username' => $username,
          'wsse:Password' => [
            '_attributes' => [
              'Type' => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest'
            ],
            '_value' => $digest
          ],
          'wsse:Nonce' => [
            '_attributes' => [
              'EncodingType' => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary'
            ],
            '_value' => $nonceEncoded
          ],
          'wsu:Created' => $created
        ]
      ]
    ];
  }

  /**
   * Generate a timestamp for TravelClick requests.
   *
   * @return string The timestamp in HTNG format
   */
  public static function getRequestTimestamp(): string
  {
    return Carbon::now()->format('Y-m-d\TH:i:s');
  }

  /**
   * Generate a unique echo token for message tracing.
   *
   * Echo tokens are used to track messages through the system.
   * They should be unique for each message sent.
   *
   * @return string The generated echo token
   */
  public static function generateEchoToken(): string
  {
    return 'TC-' . Str::uuid()->toString();
  }

  /**
   * Get the active TravelClick endpoint based on environment.
   *
   * @return string The active endpoint URL
   */
  public static function getActiveEndpoint(): string
  {
    $environment = Config::get('app.env') === 'production' ? 'production' : 'test';

    return Config::get("travelclick.endpoints.{$environment}");
  }

  /**
   * Get the WSDL URL for SOAP operations.
   *
   * @return string The WSDL URL
   */
  public static function getWsdlUrl(): string
  {
    return Config::get('travelclick.endpoints.wsdl');
  }

  /**
   * Normalize a room type code to TravelClick format.
   *
   * @param string $roomTypeCode The room type code to normalize
   * @return string The normalized room type code
   */
  public static function normalizeRoomTypeCode(string $roomTypeCode): string
  {
    // Trim whitespace and convert to uppercase
    return trim(strtoupper($roomTypeCode));
  }

  /**
   * Normalize a rate plan code to TravelClick format.
   *
   * @param string $ratePlanCode The rate plan code to normalize
   * @return string The normalized rate plan code
   */
  public static function normalizeRatePlanCode(string $ratePlanCode): string
  {
    // Trim whitespace and convert to uppercase
    return trim(strtoupper($ratePlanCode));
  }

  /**
   * Convert a MessageType enum to its XML element name.
   *
   * @param MessageType $messageType The message type
   * @return string The XML element name
   */
  public static function getXmlElementName(MessageType $messageType): string
  {
    return $messageType->getOTAMessageName();
  }

  /**
   * Build the XML version and encoding declaration.
   *
   * @return string The XML declaration
   */
  public static function getXmlDeclaration(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>';
  }
}
