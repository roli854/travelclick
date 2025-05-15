<?php

declare(strict_types=1);

namespace App\TravelClick\Support;

use App\TravelClick\Enums\CountType;
use App\TravelClick\Enums\ReservationType;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * ValidationRulesHelper
 *
 * Centralized helper class for common validation logic specific to HTNG 2011B.
 * Provides utility methods for parsing and validating dates, codes, ranges, and business rules.
 *
 * This class complements BusinessRulesValidator by providing reusable validation methods
 * that can be used across different parts of the TravelClick integration.
 */
class ValidationRulesHelper
{
    /**
     * HTNG date format for XML messages
     */
    public const HTNG_DATE_FORMAT = 'Y-m-d';
    public const HTNG_DATETIME_FORMAT = 'Y-m-d\TH:i:s';
    public const HTNG_DATETIME_WITH_TZ_FORMAT = 'Y-m-d\TH:i:s.u\Z';

    /**
     * Maximum allowed date range for most operations (in days)
     */
    public const MAX_DATE_RANGE_DAYS = 365;

    /**
     * Maximum allowed future booking date (in years)
     */
    public const MAX_FUTURE_BOOKING_YEARS = 2;

    /**
     * Hotel code pattern (typically 6 digits)
     */
    public const HOTEL_CODE_PATTERN = '/^\d{6}$/';

    /**
     * Room type code pattern (3-10 alphanumeric characters)
     */
    public const ROOM_TYPE_CODE_PATTERN = '/^[A-Z0-9]{3,10}$/';

    /**
     * Rate plan code pattern (3-20 alphanumeric characters with possible hyphens)
     */
    public const RATE_PLAN_CODE_PATTERN = '/^[A-Z0-9\-]{3,20}$/';

    /**
     * Validate and parse HTNG date string
     *
     * @param string $dateString Date string to validate
     * @param bool $allowNull Whether null/empty values are allowed
     * @return array{valid: bool, date: ?Carbon, error: ?string}
     */
    public static function validateAndParseHtngDate(string $dateString, bool $allowNull = false): array
    {
        // Handle empty string
        if (empty($dateString)) {
            return [
                'valid' => $allowNull,
                'date' => null,
                'error' => $allowNull ? null : 'Date is required'
            ];
        }

        try {
            // Try to parse with different HTNG formats
            $formats = [
                self::HTNG_DATE_FORMAT,
                self::HTNG_DATETIME_FORMAT,
                self::HTNG_DATETIME_WITH_TZ_FORMAT
            ];

            $parsedDate = null;
            foreach ($formats as $format) {
                try {
                    $parsedDate = Carbon::createFromFormat($format, $dateString);
                    if ($parsedDate !== false) {
                        break;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!$parsedDate) {
                // Final attempt with natural parsing
                $parsedDate = Carbon::parse($dateString);
            }

            return [
                'valid' => true,
                'date' => $parsedDate,
                'error' => null
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'date' => null,
                'error' => "Invalid date format: {$e->getMessage()}"
            ];
        }
    }

    /**
     * Validate date range
     *
     * @param string $startDate Start date string
     * @param string $endDate End date string
     * @param array $options Validation options
     * @return array{valid: bool, start: ?Carbon, end: ?Carbon, errors: array}
     */
    public static function validateDateRange(
        string $startDate,
        string $endDate,
        array $options = []
    ): array {
        $maxDays = $options['max_days'] ?? self::MAX_DATE_RANGE_DAYS;
        $allowPastDates = $options['allow_past_dates'] ?? true;
        $maxFutureYears = $options['max_future_years'] ?? self::MAX_FUTURE_BOOKING_YEARS;

        $result = [
            'valid' => true,
            'start' => null,
            'end' => null,
            'errors' => []
        ];

        // Parse start date
        $startResult = self::validateAndParseHtngDate($startDate);
        if (!$startResult['valid']) {
            $result['valid'] = false;
            $result['errors'][] = "Start date error: {$startResult['error']}";
            return $result;
        }

        // Parse end date
        $endResult = self::validateAndParseHtngDate($endDate);
        if (!$endResult['valid']) {
            $result['valid'] = false;
            $result['errors'][] = "End date error: {$endResult['error']}";
            return $result;
        }

        $result['start'] = $startResult['date'];
        $result['end'] = $endResult['date'];

        // Validate date range logic
        if ($result['start']->isAfter($result['end'])) {
            $result['valid'] = false;
            $result['errors'][] = 'Start date must be before or equal to end date';
        }

        // Check maximum range
        if ($result['start']->diffInDays($result['end']) > $maxDays) {
            $result['valid'] = false;
            $result['errors'][] = "Date range exceeds maximum allowed ({$maxDays} days)";
        }

        // Check past dates
        if (!$allowPastDates && $result['start']->isPast()) {
            $result['valid'] = false;
            $result['errors'][] = 'Start date cannot be in the past';
        }

        // Check future limit
        $maxFutureDate = now()->addYears($maxFutureYears);
        if ($result['end']->isAfter($maxFutureDate)) {
            $result['valid'] = false;
            $result['errors'][] = "End date exceeds maximum future limit ({$maxFutureYears} years)";
        }

        return $result;
    }

    /**
     * Validate hotel code format
     *
     * @param string $hotelCode Hotel code to validate
     * @return array{valid: bool, error: ?string}
     */
    public static function validateHotelCode(string $hotelCode): array
    {
        if (empty($hotelCode)) {
            return ['valid' => false, 'error' => 'Hotel code is required'];
        }

        if (!preg_match(self::HOTEL_CODE_PATTERN, $hotelCode)) {
            return [
                'valid' => false,
                'error' => 'Hotel code must be exactly 6 digits'
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate room type code format
     *
     * @param string $roomTypeCode Room type code to validate
     * @return array{valid: bool, error: ?string}
     */
    public static function validateRoomTypeCode(string $roomTypeCode): array
    {
        if (empty($roomTypeCode)) {
            return ['valid' => false, 'error' => 'Room type code is required'];
        }

        // Convert to uppercase for validation
        $roomTypeCode = strtoupper($roomTypeCode);

        if (!preg_match(self::ROOM_TYPE_CODE_PATTERN, $roomTypeCode)) {
            return [
                'valid' => false,
                'error' => 'Room type code must be 3-10 alphanumeric characters'
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate rate plan code format
     *
     * @param string $ratePlanCode Rate plan code to validate
     * @return array{valid: bool, error: ?string}
     */
    public static function validateRatePlanCode(string $ratePlanCode): array
    {
        if (empty($ratePlanCode)) {
            return ['valid' => false, 'error' => 'Rate plan code is required'];
        }

        // Convert to uppercase for validation
        $ratePlanCode = strtoupper($ratePlanCode);

        if (!preg_match(self::RATE_PLAN_CODE_PATTERN, $ratePlanCode)) {
            return [
                'valid' => false,
                'error' => 'Rate plan code must be 3-20 alphanumeric characters (hyphens allowed)'
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate currency code (ISO 4217)
     *
     * @param string $currencyCode Currency code to validate
     * @return array{valid: bool, error: ?string}
     */
    public static function validateCurrencyCode(string $currencyCode): array
    {
        if (empty($currencyCode)) {
            return ['valid' => false, 'error' => 'Currency code is required'];
        }

        // List of common hotel industry currencies
        $validCurrencies = [
            'USD',
            'EUR',
            'GBP',
            'JPY',
            'AUD',
            'CAD',
            'CHF',
            'CNY',
            'SEK',
            'NOK',
            'DKK',
            'PLN',
            'CZK',
            'HUF',
            'BGN',
            'RON',
            'HRK',
            'BRL',
            'MXN',
            'ARS',
            'CLP',
            'COP',
            'PEN',
            'UYU',
            'THB',
            'SGD',
            'MYR',
            'IDR',
            'PHP',
            'VND',
            'KRW',
            'TWD',
            'HKD',
            'INR',
            'PKR',
            'LKR',
            'BDT',
            'NPR',
            'AED',
            'SAR',
            'OMR',
            'QAR',
            'KWD',
            'BHD',
            'ILS',
            'TRY',
            'RUB',
            'UAH',
            'KZT',
            'UZS',
            'GEL',
            'AMD',
            'AZN',
            'EGP',
            'MAD',
            'TND',
            'DZD',
            'ZAR',
            'NGN',
            'KES',
            'GHS',
            'XOF',
            'XAF'
        ];

        $currencyCode = strtoupper($currencyCode);

        if (!in_array($currencyCode, $validCurrencies)) {
            return [
                'valid' => false,
                'error' => "Currency code '{$currencyCode}' is not supported"
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate numeric range
     *
     * @param int|float $value Value to validate
     * @param int|float $min Minimum allowed value
     * @param int|float $max Maximum allowed value
     * @param string $fieldName Field name for error messages
     * @return array{valid: bool, error: ?string}
     */
    public static function validateNumericRange(
        int|float $value,
        int|float $min,
        int|float $max,
        string $fieldName = 'Value'
    ): array {
        if ($value < $min) {
            return [
                'valid' => false,
                'error' => "{$fieldName} must be at least {$min}"
            ];
        }

        if ($value > $max) {
            return [
                'valid' => false,
                'error' => "{$fieldName} must not exceed {$max}"
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate count type and count value combination
     *
     * @param CountType $countType Count type enum
     * @param int $count Count value
     * @return array{valid: bool, error: ?string}
     */
    public static function validateCountTypeAndValue(CountType $countType, int $count): array
    {
        // General count validation
        if ($count < 0) {
            return [
                'valid' => false,
                'error' => 'Count value cannot be negative'
            ];
        }

        // Special rules for specific count types
        switch ($countType) {
            case CountType::TENTATIVE_SOLD:
                // In calculated method, tentative should typically be 0
                if ($count > 0) {
                    return [
                        'valid' => true,
                        'error' => 'Tentative sold count should typically be 0 in calculated method (include in Definite Sold instead)'
                    ];
                }
                break;

            case CountType::OVERSELL:
                // Large oversell values should be flagged
                if ($count > 20) {
                    return [
                        'valid' => true,
                        'error' => "Oversell count ({$count}) is unusually high - verify this is correct"
                    ];
                }
                break;

            case CountType::PHYSICAL:
                // Physical rooms should have reasonable limits
                if ($count > 10000) {
                    return [
                        'valid' => false,
                        'error' => "Physical room count ({$count}) exceeds reasonable limit"
                    ];
                }
                break;
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate guest count and occupancy rules
     *
     * @param int $adults Number of adults
     * @param int $children Number of children
     * @param int $infants Number of infants
     * @param array $roomTypeRules Room type specific rules
     * @return array{valid: bool, errors: array}
     */
    public static function validateOccupancy(
        int $adults,
        int $children = 0,
        int $infants = 0,
        array $roomTypeRules = []
    ): array {
        $result = [
            'valid' => true,
            'errors' => []
        ];

        // Basic validations
        if ($adults < 1) {
            $result['valid'] = false;
            $result['errors'][] = 'At least one adult is required';
        }

        if ($adults > 10) {
            $result['valid'] = false;
            $result['errors'][] = 'Maximum 10 adults allowed';
        }

        if ($children < 0 || $children > 10) {
            $result['valid'] = false;
            $result['errors'][] = 'Children count must be between 0 and 10';
        }

        if ($infants < 0 || $infants > 5) {
            $result['valid'] = false;
            $result['errors'][] = 'Infants count must be between 0 and 5';
        }

        // Total occupancy check
        $totalOccupancy = $adults + $children;
        if ($totalOccupancy > 15) {
            $result['valid'] = false;
            $result['errors'][] = 'Total occupancy exceeds maximum (15)';
        }

        // Room type specific validations
        if (!empty($roomTypeRules)) {
            $minAdults = $roomTypeRules['min_adults'] ?? 1;
            $maxAdults = $roomTypeRules['max_adults'] ?? 10;
            $maxChildren = $roomTypeRules['max_children'] ?? 10;
            $maxOccupancy = $roomTypeRules['max_occupancy'] ?? 15;

            if ($adults < $minAdults) {
                $result['valid'] = false;
                $result['errors'][] = "Minimum {$minAdults} adults required for this room type";
            }

            if ($adults > $maxAdults) {
                $result['valid'] = false;
                $result['errors'][] = "Maximum {$maxAdults} adults allowed for this room type";
            }

            if ($children > $maxChildren) {
                $result['valid'] = false;
                $result['errors'][] = "Maximum {$maxChildren} children allowed for this room type";
            }

            if ($totalOccupancy > $maxOccupancy) {
                $result['valid'] = false;
                $result['errors'][] = "Total occupancy exceeds room type maximum ({$maxOccupancy})";
            }
        }

        return $result;
    }

    /**
     * Validate rate amounts and guest pricing logic
     *
     * @param array $guestAmounts Array of guest amounts
     * @return array{valid: bool, errors: array}
     */
    public static function validateRateAmounts(array $guestAmounts): array
    {
        $result = [
            'valid' => true,
            'errors' => []
        ];

        $rates = [];

        foreach ($guestAmounts as $guestAmount) {
            $guestCount = $guestAmount['guests'] ?? $guestAmount['guest_count'] ?? 0;
            $amount = $guestAmount['amount'] ?? 0;

            // Validate amount
            if ($amount <= 0) {
                $result['valid'] = false;
                $result['errors'][] = "Rate amount for {$guestCount} guest(s) must be greater than 0";
                continue;
            }

            if ($amount > 100000) {
                $result['valid'] = false;
                $result['errors'][] = "Rate amount for {$guestCount} guest(s) exceeds reasonable limit (100,000)";
                continue;
            }

            $rates[$guestCount] = $amount;
        }

        // HTNG 2011B requires rates for 1 and 2 guests
        if (!isset($rates[1])) {
            $result['valid'] = false;
            $result['errors'][] = 'Rate for 1 guest is required (HTNG 2011B specification)';
        }

        if (!isset($rates[2])) {
            $result['valid'] = false;
            $result['errors'][] = 'Rate for 2 guests is required (HTNG 2011B specification)';
        }

        // Validate rate progression logic
        if (isset($rates[1]) && isset($rates[2])) {
            if ($rates[2] < $rates[1]) {
                $result['errors'][] = 'Rate for 2 guests is less than rate for 1 guest - verify this is correct';
            }

            // Check for reasonable increase
            $increaseRatio = $rates[2] / $rates[1];
            if ($increaseRatio > 2.5) {
                $result['errors'][] = 'Rate increase from 1 to 2 guests is unusually large (>150%) - verify this is correct';
            }
        }

        return $result;
    }

    /**
     * Validate message ID format (UUID)
     *
     * @param string $messageId Message ID to validate
     * @return array{valid: bool, error: ?string}
     */
    public static function validateMessageId(string $messageId): array
    {
        if (empty($messageId)) {
            return ['valid' => false, 'error' => 'Message ID is required'];
        }

        // Check if it's a valid UUID format
        if (!Str::isUuid($messageId)) {
            return [
                'valid' => false,
                'error' => 'Message ID must be a valid UUID format'
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate email address format
     *
     * @param string $email Email address to validate
     * @param bool $required Whether email is required
     * @return array{valid: bool, error: ?string}
     */
    public static function validateEmail(string $email, bool $required = true): array
    {
        if (empty($email)) {
            return [
                'valid' => !$required,
                'error' => $required ? 'Email address is required' : null
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'error' => 'Invalid email address format'
            ];
        }

        // Additional check for reasonable email length
        if (strlen($email) > 254) {
            return [
                'valid' => false,
                'error' => 'Email address exceeds maximum length (254 characters)'
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate phone number format
     *
     * @param string $phone Phone number to validate
     * @param bool $required Whether phone is required
     * @return array{valid: bool, error: ?string}
     */
    public static function validatePhone(string $phone, bool $required = true): array
    {
        if (empty($phone)) {
            return [
                'valid' => !$required,
                'error' => $required ? 'Phone number is required' : null
            ];
        }

        // Remove common formatting characters
        $cleanPhone = preg_replace('/[\s\-\(\)\+\.]/', '', $phone);

        // Check for reasonable phone number length (7-15 digits)
        if (!preg_match('/^\d{7,15}$/', $cleanPhone)) {
            return [
                'valid' => false,
                'error' => 'Phone number must contain 7-15 digits'
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate batch size for processing
     *
     * @param int $batchSize Batch size to validate
     * @param int $maxBatchSize Maximum allowed batch size
     * @return array{valid: bool, error: ?string}
     */
    public static function validateBatchSize(int $batchSize, int $maxBatchSize = 1000): array
    {
        if ($batchSize <= 0) {
            return [
                'valid' => false,
                'error' => 'Batch size must be greater than 0'
            ];
        }

        if ($batchSize > $maxBatchSize) {
            return [
                'valid' => false,
                'error' => "Batch size exceeds maximum allowed ({$maxBatchSize})"
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Format date for HTNG XML
     *
     * @param CarbonInterface $date Date to format
     * @param bool $includeTime Whether to include time
     * @return string Formatted date string
     */
    public static function formatDateForHtng(CarbonInterface $date, bool $includeTime = false): string
    {
        return $includeTime
            ? $date->format(self::HTNG_DATETIME_FORMAT)
            : $date->format(self::HTNG_DATE_FORMAT);
    }

    /**
     * Sanitize text for XML content
     *
     * @param string $text Text to sanitize
     * @param int $maxLength Maximum allowed length
     * @return string Sanitized text
     */
    public static function sanitizeForXml(string $text, int $maxLength = 255): string
    {
        // Remove or replace problematic characters
        $text = trim($text);

        // Replace XML entities
        $text = htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        // Remove control characters except tab, newline, and carriage return
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Truncate if necessary
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength - 3) . '...';
        }

        return $text;
    }

    /**
     * Create standardized validation result array
     *
     * @param bool $valid Whether validation passed
     * @param string|array|null $errors Error message(s)
     * @param string|array|null $warnings Warning message(s)
     * @return array{valid: bool, errors: array, warnings: array}
     */
    public static function createValidationResult(
        bool $valid,
        string|array|null $errors = null,
        string|array|null $warnings = null
    ): array {
        return [
            'valid' => $valid,
            'errors' => is_array($errors) ? $errors : ($errors ? [$errors] : []),
            'warnings' => is_array($warnings) ? $warnings : ($warnings ? [$warnings] : [])
        ];
    }
}
