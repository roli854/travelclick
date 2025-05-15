<?php

namespace App\TravelClick\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Validates currency codes according to ISO 4217 standard
 * and TravelClick supported currencies
 *
 * @package App\TravelClick\Rules
 */
class ValidCurrencyCode implements Rule
{
    /**
     * ISO 4217 currency codes supported by TravelClick
     * Based on major international currencies commonly used in hospitality
     */
    private const SUPPORTED_CURRENCIES = [
        // Major International Currencies
        'USD', // US Dollar
        'EUR', // Euro
        'GBP', // British Pound Sterling
        'JPY', // Japanese Yen
        'CHF', // Swiss Franc
        'CAD', // Canadian Dollar
        'AUD', // Australian Dollar
        'NZD', // New Zealand Dollar
        'HKD', // Hong Kong Dollar
        'SGD', // Singapore Dollar

        // Popular Tourism Currencies
        'MXN', // Mexican Peso
        'BRL', // Brazilian Real
        'ARS', // Argentine Peso
        'CLP', // Chilean Peso
        'COP', // Colombian Peso
        'PEN', // Peruvian Sol
        'UYU', // Uruguayan Peso

        // European Currencies (Non-Euro)
        'NOK', // Norwegian Krone
        'SEK', // Swedish Krona
        'DKK', // Danish Krone
        'PLN', // Polish Zloty
        'CZK', // Czech Koruna
        'HUF', // Hungarian Forint
        'RON', // Romanian Leu
        'BGN', // Bulgarian Lev
        'HRK', // Croatian Kuna

        // Asian Currencies
        'CNY', // Chinese Yuan
        'KRW', // South Korean Won
        'INR', // Indian Rupee
        'THB', // Thai Baht
        'MYR', // Malaysian Ringgit
        'IDR', // Indonesian Rupiah
        'PHP', // Philippine Peso
        'VND', // Vietnamese Dong

        // Middle East & Africa
        'AED', // UAE Dirham
        'SAR', // Saudi Riyal
        'QAR', // Qatari Riyal
        'EGP', // Egyptian Pound
        'ZAR', // South African Rand
        'MAD', // Moroccan Dirham
        'TND', // Tunisian Dinar

        // Others
        'ILS', // Israeli Shekel
        'TRY', // Turkish Lira
        'RUB', // Russian Ruble
        'UAH', // Ukrainian Hryvnia
    ];

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        // Check if value is a string
        if (!is_string($value)) {
            return false;
        }

        // Convert to uppercase for comparison
        $currency = strtoupper(trim($value));

        // Validate format (exactly 3 alphabetic characters)
        if (!$this->isValidFormat($currency)) {
            return false;
        }

        // Check if currency is in supported list
        return in_array($currency, self::SUPPORTED_CURRENCIES, true);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'The :attribute must be a valid ISO 4217 currency code supported by TravelClick.';
    }

    /**
     * Validate the currency code format
     *
     * @param string $currency
     * @return bool
     */
    private function isValidFormat(string $currency): bool
    {
        // Must be exactly 3 characters and all alphabetic
        return strlen($currency) === 3 && ctype_alpha($currency);
    }

    /**
     * Get all supported currency codes
     *
     * @return array
     */
    public static function getSupportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    /**
     * Check if a specific currency is supported
     *
     * @param string $currency
     * @return bool
     */
    public static function isSupported(string $currency): bool
    {
        return in_array(strtoupper(trim($currency)), self::SUPPORTED_CURRENCIES, true);
    }

    /**
     * Get currency information for debugging/logging
     *
     * @param string $currency
     * @return array
     */
    public static function getCurrencyInfo(string $currency): array
    {
        $currency = strtoupper(trim($currency));

        return [
            'code' => $currency,
            'is_valid_format' => strlen($currency) === 3 && ctype_alpha($currency),
            'is_supported' => self::isSupported($currency),
            'supported_count' => count(self::SUPPORTED_CURRENCIES)
        ];
    }
}
