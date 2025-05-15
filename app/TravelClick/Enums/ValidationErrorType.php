<?php

declare(strict_types=1);

namespace App\TravelClick\Enums;

/**
 * ValidationErrorType Enum
 *
 * Defines the types of validation errors that can occur in TravelClick operations.
 * Each error type provides context for logging, debugging, and error handling.
 */
enum ValidationErrorType: string
{
    case XML_STRUCTURE = 'xml_structure';
    case XML_SCHEMA = 'xml_schema';
    case XML_NAMESPACE = 'xml_namespace';
    case BUSINESS_LOGIC = 'business_logic';
    case REQUIRED_FIELD = 'required_field';
    case DATA_TYPE = 'data_type';
    case DATE_RANGE = 'date_range';
    case INVENTORY_METHOD = 'inventory_method';
    case COUNT_TYPE = 'count_type';
    case CURRENCY_CODE = 'currency_code';
    case PROPERTY_RULES = 'property_rules';
    case SOAP_HEADERS = 'soap_headers';
    case MESSAGE_TYPE = 'message_type';
    case GUEST_INFORMATION = 'guest_information';
    case ROOM_STAYS = 'room_stays';
    case RATE_PLAN = 'rate_plan';
    case ROOM_TYPE = 'room_type';
    case GROUP_BLOCK = 'group_block';
    case RESERVATION_TYPE = 'reservation_type';
    case PACKAGE_CODE = 'package_code';
    case TRAVEL_AGENCY = 'travel_agency';
    case CORPORATE = 'corporate';
    case AUTHENTICATION = 'authentication';
    case SANITIZATION = 'sanitization';
    case CONFIGURATION = 'configuration';
    case UNKNOWN = 'unknown';

    /**
     * Get human-readable description of the error type
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::XML_STRUCTURE => 'XML structure or format error',
            self::XML_SCHEMA => 'XML schema validation error',
            self::XML_NAMESPACE => 'XML namespace validation error',
            self::BUSINESS_LOGIC => 'Business logic validation error',
            self::REQUIRED_FIELD => 'Required field missing or empty',
            self::DATA_TYPE => 'Invalid data type or format',
            self::DATE_RANGE => 'Invalid date range or format',
            self::INVENTORY_METHOD => 'Invalid inventory method or calculation',
            self::COUNT_TYPE => 'Invalid inventory count type',
            self::CURRENCY_CODE => 'Invalid currency code',
            self::PROPERTY_RULES => 'Property-specific rule violation',
            self::SOAP_HEADERS => 'SOAP header validation error',
            self::MESSAGE_TYPE => 'Message type mismatch or invalid',
            self::GUEST_INFORMATION => 'Guest information validation error',
            self::ROOM_STAYS => 'Room stay information validation error',
            self::RATE_PLAN => 'Rate plan validation error',
            self::ROOM_TYPE => 'Room type validation error',
            self::GROUP_BLOCK => 'Group block validation error',
            self::RESERVATION_TYPE => 'Reservation type validation error',
            self::PACKAGE_CODE => 'Package code validation error',
            self::TRAVEL_AGENCY => 'Travel agency information validation error',
            self::CORPORATE => 'Corporate information validation error',
            self::AUTHENTICATION => 'Authentication validation error',
            self::SANITIZATION => 'Data sanitization error',
            self::CONFIGURATION => 'Configuration validation error',
            self::UNKNOWN => 'Unknown validation error',
        };
    }

    /**
     * Get severity level of the error type
     */
    public function getSeverity(): string
    {
        return match ($this) {
            self::XML_STRUCTURE,
            self::XML_SCHEMA,
            self::REQUIRED_FIELD,
            self::AUTHENTICATION => 'critical',

            self::BUSINESS_LOGIC,
            self::COUNT_TYPE,
            self::INVENTORY_METHOD,
            self::MESSAGE_TYPE => 'high',

            self::DATA_TYPE,
            self::DATE_RANGE,
            self::PROPERTY_RULES,
            self::SOAP_HEADERS => 'medium',

            self::XML_NAMESPACE,
            self::SANITIZATION,
            self::CONFIGURATION => 'low',

            default => 'unknown',
        };
    }

    /**
     * Check if this error type is critical
     */
    public function isCritical(): bool
    {
        return $this->getSeverity() === 'critical';
    }

    /**
     * Check if this error type should block processing
     */
    public function shouldBlockProcessing(): bool
    {
        return in_array($this->getSeverity(), ['critical', 'high']);
    }

    /**
     * Get all error types by severity
     *
     * @param string $severity
     * @return array<self>
     */
    public static function getBySeverity(string $severity): array
    {
        return array_filter(
            self::cases(),
            fn(self $case) => $case->getSeverity() === $severity
        );
    }

    /**
     * Get error type from context string
     */
    public static function fromContext(string $context): self
    {
        return match ($context) {
            'xml_validation', 'xml_structure' => self::XML_STRUCTURE,
            'schema_validation' => self::XML_SCHEMA,
            'inventory' => self::COUNT_TYPE,
            'rate' => self::RATE_PLAN,
            'reservation' => self::RESERVATION_TYPE,
            'group_block' => self::GROUP_BLOCK,
            'soap_headers' => self::SOAP_HEADERS,
            'business_logic' => self::BUSINESS_LOGIC,
            'required_fields' => self::REQUIRED_FIELD,
            'date_range' => self::DATE_RANGE,
            'property_rules' => self::PROPERTY_RULES,
            'sanitization' => self::SANITIZATION,
            default => self::UNKNOWN,
        };
    }

    /**
     * Convert to array for API responses
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->value,
            'description' => $this->getDescription(),
            'severity' => $this->getSeverity(),
            'critical' => $this->isCritical(),
            'blocks_processing' => $this->shouldBlockProcessing(),
        ];
    }
}
