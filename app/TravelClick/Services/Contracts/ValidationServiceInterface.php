<?php

declare(strict_types=1);

namespace App\TravelClick\Services\Contracts;

use App\TravelClick\DTOs\SoapRequestDto;
use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\CountType;
use App\TravelClick\Enums\ReservationType;

/**
 * ValidationService Interface
 *
 * Provides comprehensive validation for all TravelClick/HTNG 2011B operations.
 * This service ensures data integrity, business logic compliance, and XML structure validation.
 */
interface ValidationServiceInterface
{
    /**
     * Validate complete SOAP message (request or response)
     *
     * @param SoapRequestDto|SoapResponseDto $message The SOAP message to validate
     * @param MessageType $messageType The expected message type
     * @return array<string, mixed> Validation results
     * @throws \App\TravelClick\Exceptions\ValidationException
     */
    public function validateSoapMessage(
        SoapRequestDto|SoapResponseDto $message,
        MessageType $messageType
    ): array;

    /**
     * Validate XML structure against HTNG 2011B schema
     *
     * @param string $xml The XML to validate
     * @param string $schemaType The schema type (inventory, rate, reservation, etc.)
     * @return array<string, mixed> Validation results with details
     * @throws \App\TravelClick\Exceptions\ValidationException
     */
    public function validateXmlStructure(string $xml, string $schemaType): array;

    /**
     * Validate inventory data according to business rules
     *
     * @param array<string, mixed> $inventoryData The inventory data to validate
     * @param string $propertyId The property ID for context
     * @return array<string, mixed> Validation results
     * @throws \App\TravelClick\Exceptions\ValidationException
     */
    public function validateInventoryData(array $inventoryData, string $propertyId): array;

    /**
     * Validate rate data according to business rules
     *
     * @param array<string, mixed> $rateData The rate data to validate
     * @param string $propertyId The property ID for context
     * @return array<string, mixed> Validation results
     * @throws \App\TravelClick\Exceptions\ValidationException
     */
    public function validateRateData(array $rateData, string $propertyId): array;

    /**
     * Validate reservation data according to business rules
     *
     * @param array<string, mixed> $reservationData The reservation data to validate
     * @param ReservationType $reservationType The type of reservation
     * @param string $propertyId The property ID for context
     * @return array<string, mixed> Validation results
     * @throws \App\TravelClick\Exceptions\ValidationException
     */
    public function validateReservationData(
        array $reservationData,
        ReservationType $reservationType,
        string $propertyId
    ): array;

    /**
     * Validate group block data according to business rules
     *
     * @param array<string, mixed> $groupData The group block data to validate
     * @param string $propertyId The property ID for context
     * @return array<string, mixed> Validation results
     * @throws \App\TravelClick\Exceptions\ValidationException
     */
    public function validateGroupBlockData(array $groupData, string $propertyId): array;

    /**
     * Validate inventory count types and values
     *
     * @param array<string, mixed> $inventoryCounts Array of inventory counts with CountType
     * @param string $inventoryMethod Either 'calculated' or 'not_calculated'
     * @return array<string, mixed> Validation results
     * @throws \App\TravelClick\Exceptions\ValidationException
     */
    public function validateInventoryCounts(array $inventoryCounts, string $inventoryMethod): array;

    /**
     * Sanitize and clean data for safe processing
     *
     * @param array<string, mixed> $data Raw data to sanitize
     * @param array<string> $rules Sanitization rules to apply
     * @return array<string, mixed> Sanitized data
     */
    public function sanitizeData(array $data, array $rules = []): array;

    /**
     * Validate date ranges and formats
     *
     * @param string $startDate Start date string
     * @param string $endDate End date string
     * @param array<string, mixed> $constraints Additional date constraints
     * @return array<string, mixed> Validation results
     * @throws \App\TravelClick\Exceptions\ValidationException
     */
    public function validateDateRange(string $startDate, string $endDate, array $constraints = []): array;

    /**
     * Validate property-specific business rules
     *
     * @param string $propertyId The property ID
     * @param array<string, mixed> $data Data to validate against property rules
     * @param string $operation Operation type (inventory, rate, reservation)
     * @return array<string, mixed> Validation results
     * @throws \App\TravelClick\Exceptions\ValidationException
     */
    public function validatePropertyRules(string $propertyId, array $data, string $operation): array;

    /**
     * Validate required fields based on message type
     *
     * @param array<string, mixed> $data Data to validate
     * @param MessageType $messageType The message type
     * @param array<string> $optionalFields Optional fields that can be omitted
     * @return array<string, mixed> Validation results
     * @throws \App\TravelClick\Exceptions\ValidationException
     */
    public function validateRequiredFields(
        array $data,
        MessageType $messageType,
        array $optionalFields = []
    ): array;

    /**
     * Validate business logic for specific HTNG operations
     *
     * @param array<string, mixed> $data The data to validate
     * @param string $operationType The operation type (create, modify, cancel)
     * @param MessageType $messageType The message type
     * @return array<string, mixed> Validation results
     * @throws \App\TravelClick\Exceptions\ValidationException
     */
    public function validateBusinessLogic(
        array $data,
        string $operationType,
        MessageType $messageType
    ): array;

    /**
     * Validate SOAP headers and authentication data
     *
     * @param array<string, mixed> $headers SOAP headers to validate
     * @param string $propertyId The property ID for context
     * @return array<string, mixed> Validation results
     * @throws \App\TravelClick\Exceptions\ValidationException
     */
    public function validateSoapHeaders(array $headers, string $propertyId): array;

    /**
     * Get validation rules for a specific message type
     *
     * @param MessageType $messageType The message type
     * @param string $operation The operation (create, modify, cancel)
     * @return array<string, mixed> Array of validation rules
     */
    public function getValidationRules(MessageType $messageType, string $operation = 'create'): array;

    /**
     * Check if data passes all validations
     *
     * @param array<string, mixed> $validationResults Results from validation methods
     * @return bool True if all validations pass
     */
    public function allValidationsPassed(array $validationResults): bool;

    /**
     * Get formatted validation errors
     *
     * @param array<string, mixed> $validationResults Results from validation methods
     * @return array<string> Array of formatted error messages
     */
    public function getValidationErrors(array $validationResults): array;
}
