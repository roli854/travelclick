<?php

declare(strict_types=1);

namespace App\TravelClick\Support;

use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\ValidationErrorType;
use App\TravelClick\Exceptions\ValidationException;
use DOMDocument;
use LibXMLError;

/**
 * Advanced XML validator with support for HTNG 2011B schema validation
 *
 * This class provides both basic XML structure validation and XSD schema validation
 * for TravelClick HTNG 2011B messages.
 */
class XmlValidator
{
    /**
     * Validate XML string for basic structure and well-formedness
     *
     * @param string $xml The XML string to validate
     * @return bool True if XML is valid
     * @throws ValidationException If XML is invalid with details
     */
    public static function validateXmlStructure(string $xml): bool
    {
        if (empty(trim($xml))) {
            throw new ValidationException(
                'XML content is empty',
                ValidationErrorType::XML_STRUCTURE->value
            );
        }

        // Enable user error handling
        $useInternalErrors = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader(true);

        try {
            $dom = new DOMDocument();
            $dom->validateOnParse = true;

            // Load XML with error checking
            $result = $dom->loadXML($xml, LIBXML_DTDLOAD | LIBXML_DTDVALID);

            if (!$result) {
                $errors = libxml_get_errors();
                $errorMessage = self::formatLibXmlErrors($errors);
                throw new ValidationException(
                    "Invalid XML structure: {$errorMessage}",
                    ValidationErrorType::XML_STRUCTURE->value
                );
            }

            return true;
        } finally {
            // Restore original error handling
            libxml_use_internal_errors($useInternalErrors);
            libxml_disable_entity_loader($disableEntities);
            libxml_clear_errors();
        }
    }

    /**
     * Validate XML against HTNG 2011B XSD schema
     *
     * @param string $xml The XML string to validate
     * @param MessageType $messageType The message type to validate against
     * @return bool True if XML is valid against schema
     * @throws ValidationException If validation fails with details
     */
    public static function validateAgainstSchema(string $xml, MessageType $messageType): bool
    {
        // First validate basic XML structure
        self::validateXmlStructure($xml);

        // Check if schema is available
        if (!XsdSchemas::hasSchema($messageType)) {
            throw new ValidationException(
                "XSD schema not available for message type: {$messageType->value}",
                ValidationErrorType::XML_SCHEMA->value
            );
        }

        // Enable user error handling
        $useInternalErrors = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument();
            $dom->loadXML($xml);

            // Load and validate against schema
            $schemaPath = XsdSchemas::getSchemaPath($messageType);
            $isValid = $dom->schemaValidate($schemaPath);

            if (!$isValid) {
                $errors = libxml_get_errors();
                $errorMessage = self::formatLibXmlErrors($errors);
                throw new ValidationException(
                    "XSD validation failed: {$errorMessage}",
                    ValidationErrorType::XML_SCHEMA->value
                );
            }

            return true;
        } finally {
            // Restore original error handling
            libxml_use_internal_errors($useInternalErrors);
            libxml_clear_errors();
        }
    }

    /**
     * Validate XML with automatic message type detection
     *
     * @param string $xml The XML string to validate
     * @return bool True if XML is valid
     * @throws ValidationException If validation fails
     */
    public static function validate(string $xml): bool
    {
        // First validate structure
        self::validateXmlStructure($xml);

        // Try to detect message type from XML
        $messageType = self::detectMessageType($xml);

        if ($messageType === null) {
            // If we can't detect the message type, just validate structure
            return true;
        }

        // Validate against schema if available
        if (XsdSchemas::hasSchema($messageType)) {
            return self::validateAgainstSchema($xml, $messageType);
        }

        return true;
    }

    /**
     * Detect message type from XML content
     *
     * @param string $xml The XML string to analyze
     * @return MessageType|null The detected message type, or null if unknown
     */
    private static function detectMessageType(string $xml): ?MessageType
    {
        // Map of root element names to message types
        $rootElementMap = [
            'OTA_HotelInvCountNotifRQ' => MessageType::INVENTORY,
            'OTA_HotelRateNotifRQ' => MessageType::RATES,
            'OTA_HotelResNotifRQ' => MessageType::RESERVATION,
            'OTA_HotelInvBlockNotifRQ' => MessageType::GROUP_BLOCK,
        ];

        try {
            $dom = new DOMDocument();
            $dom->loadXML($xml);
            $rootElementName = $dom->documentElement->nodeName;

            return $rootElementMap[$rootElementName] ?? null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Format LibXML errors into a readable string
     *
     * @param array<LibXMLError> $errors Array of LibXML errors
     * @return string Formatted error message
     */
    private static function formatLibXmlErrors(array $errors): string
    {
        $errorMessages = [];

        foreach ($errors as $error) {
            $level = match ($error->level) {
                LIBXML_ERR_WARNING => 'Warning',
                LIBXML_ERR_ERROR => 'Error',
                LIBXML_ERR_FATAL => 'Fatal Error',
                default => 'Unknown'
            };

            $message = trim($error->message);
            $line = $error->line;
            $column = $error->column;

            $errorMessages[] = "[{$level}] {$message} (Line: {$line}, Column: {$column})";
        }

        return implode('; ', $errorMessages);
    }

    /**
     * Get validation statistics for all available schemas
     *
     * @return array{schemas: array, stats: array}
     */
    public static function getValidationInfo(): array
    {
        $availableTypes = XsdSchemas::getAvailableMessageTypes();
        $stats = XsdSchemas::getSchemaStats();

        $schemaInfo = [];
        foreach (MessageType::cases() as $messageType) {
            $schemaInfo[] = [
                'type' => $messageType->value,
                'available' => XsdSchemas::hasSchema($messageType),
                'path' => XsdSchemas::hasSchema($messageType)
                    ? XsdSchemas::getSchemaPath($messageType)
                    : null
            ];
        }

        return [
            'schemas' => $schemaInfo,
            'stats' => $stats
        ];
    }

    /**
     * Validate XSD schema file itself
     *
     * @param string $xsdPath Path to XSD file
     * @return bool True if XSD is valid
     * @throws ValidationException If XSD is invalid
     */
    public static function validateXsdSchema(string $xsdPath): bool
    {
        if (!file_exists($xsdPath)) {
            throw new ValidationException(
                "XSD file not found: {$xsdPath}",
                ValidationErrorType::XML_SCHEMA->value
            );
        }

        $useInternalErrors = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument();
            $result = $dom->load($xsdPath);

            if (!$result) {
                $errors = libxml_get_errors();
                $errorMessage = self::formatLibXmlErrors($errors);
                throw new ValidationException(
                    "Invalid XSD schema: {$errorMessage}",
                    ValidationErrorType::XML_SCHEMA->value
                );
            }

            return true;
        } finally {
            libxml_use_internal_errors($useInternalErrors);
            libxml_clear_errors();
        }
    }
}
