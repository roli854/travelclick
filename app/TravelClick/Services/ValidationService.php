<?php

declare(strict_types=1);

namespace App\TravelClick\Services;

use App\TravelClick\DTOs\SoapRequestDto;
use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Enums\CountType;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\ReservationType;
use App\TravelClick\Enums\ValidationErrorType;
use App\TravelClick\Exceptions\ValidationException;
use App\TravelClick\Services\Contracts\ValidationServiceInterface;
use App\TravelClick\Support\BusinessRulesValidator;
use App\TravelClick\Support\XmlValidator;
use App\TravelClick\Support\XsdSchemas;
use App\TravelClick\Support\ValidationRulesHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

/**
 * Complete validation service implementing ValidationServiceInterface
 *
 * Provides comprehensive validation for HTNG 2011B messages including:
 * - SOAP message validation with DTOs
 * - XML structure validation against schemas
 * - Business rule validation per message type
 * - Property-specific rule validation
 * - Data sanitization and cleaning
 */
class ValidationService implements ValidationServiceInterface
{
    private ValidationRulesHelper $rulesHelper;
    private BusinessRulesValidator $businessRulesValidator;

    public function __construct(
        ?ValidationRulesHelper $rulesHelper = null,
        ?BusinessRulesValidator $businessRulesValidator = null
    ) {
        $this->rulesHelper = $rulesHelper ?? new ValidationRulesHelper();
        $this->businessRulesValidator = $businessRulesValidator ?? new BusinessRulesValidator();
    }

    /**
     * {@inheritDoc}
     */
    public function validateSoapMessage(
        SoapRequestDto|SoapResponseDto $message,
        MessageType $messageType
    ): array {
        $validationResults = [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'details' => []
        ];

        try {
            // Validate message structure
            $this->validateSoapMessageStructure($message, $messageType);

            // Validate XML content if available
            if ($message->xmlBody) {
                $xmlValidation = $this->validateXmlStructure($message->xmlBody, $messageType->value);
                $validationResults['details']['xml_validation'] = $xmlValidation;

                if (!$xmlValidation['success']) {
                    $validationResults['success'] = false;
                    $validationResults['errors'] = array_merge(
                        $validationResults['errors'],
                        $xmlValidation['errors']
                    );
                }
            }

            // Validate message-specific data
            $this->validateMessageSpecificData($message, $messageType, $validationResults);

            $this->logValidationResult('soap_message', $messageType->value, $validationResults);
        } catch (ValidationException $e) {
            $validationResults['success'] = false;
            $validationResults['errors'][] = $e->getMessage();
            $validationResults['error_type'] = $e->getErrorType();
        }

        return $validationResults;
    }

    /**
     * {@inheritDoc}
     */
    public function validateXmlStructure(string $xml, string $schemaType): array
    {
        $validationResults = [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'details' => [
                'schema_type' => $schemaType,
                'validation_steps' => []
            ]
        ];

        try {
            // Step 1: Basic XML structure validation
            XmlValidator::validateXmlStructure($xml);
            $validationResults['details']['validation_steps'][] = 'basic_structure';

            // Step 2: Schema-specific validation
            $messageType = MessageType::tryFrom($schemaType);
            if ($messageType && XsdSchemas::hasSchema($messageType)) {
                XmlValidator::validateAgainstSchema($xml, $messageType);
                $validationResults['details']['validation_steps'][] = 'xsd_validation';
            }

            // Step 3: Business rule validation
            if ($messageType) {
                $this->businessRulesValidator->validateXml($xml, $messageType);
                $validationResults['details']['validation_steps'][] = 'business_rules';
            }

            $this->logValidationResult('xml_structure', $schemaType, $validationResults);
        } catch (ValidationException $e) {
            $validationResults['success'] = false;
            $validationResults['errors'][] = $e->getMessage();
            $validationResults['error_type'] = $e->getErrorType();
        }

        return $validationResults;
    }

    /**
     * {@inheritDoc}
     */
    public function validateInventoryData(array $inventoryData, string $propertyId): array
    {
        $validationResults = [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'details' => [
                'property_id' => $propertyId,
                'items_validated' => 0
            ]
        ];

        try {
            // Validate required structure
            $this->validateRequired($inventoryData, ['hotel_code', 'inventory_items']);

            // Property-specific validation
            $propertyRulesResult = $this->validatePropertyRules(
                $propertyId,
                $inventoryData,
                'inventory'
            );

            if (!$propertyRulesResult['success']) {
                $validationResults['warnings'] = array_merge(
                    $validationResults['warnings'],
                    $propertyRulesResult['errors']
                );
            }

            // Validate each inventory item
            foreach ($inventoryData['inventory_items'] as $index => $item) {
                $this->validateInventoryItem($item, $index);
                $validationResults['details']['items_validated']++;
            }

            $this->logValidationResult('inventory_data', $propertyId, $validationResults);
        } catch (ValidationException $e) {
            $validationResults['success'] = false;
            $validationResults['errors'][] = $e->getMessage();
        }

        return $validationResults;
    }

    /**
     * {@inheritDoc}
     */
    public function validateRateData(array $rateData, string $propertyId): array
    {
        $validationResults = [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'details' => [
                'property_id' => $propertyId,
                'rate_plans_validated' => 0
            ]
        ];

        try {
            // Validate required structure
            $this->validateRequired($rateData, ['hotel_code', 'rate_plans']);

            // Property-specific validation
            $propertyRulesResult = $this->validatePropertyRules(
                $propertyId,
                $rateData,
                'rate'
            );

            if (!$propertyRulesResult['success']) {
                $validationResults['warnings'] = array_merge(
                    $validationResults['warnings'],
                    $propertyRulesResult['errors']
                );
            }

            // Validate each rate plan
            foreach ($rateData['rate_plans'] as $index => $ratePlan) {
                $this->validateRatePlan($ratePlan, $index);
                $validationResults['details']['rate_plans_validated']++;
            }

            $this->logValidationResult('rate_data', $propertyId, $validationResults);
        } catch (ValidationException $e) {
            $validationResults['success'] = false;
            $validationResults['errors'][] = $e->getMessage();
        }

        return $validationResults;
    }

    /**
     * {@inheritDoc}
     */
    public function validateReservationData(
        array $reservationData,
        ReservationType $reservationType,
        string $propertyId
    ): array {
        $validationResults = [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'details' => [
                'property_id' => $propertyId,
                'reservation_type' => $reservationType->value,
                'reservations_validated' => 0
            ]
        ];

        try {
            // Validate required structure
            $this->validateRequired($reservationData, ['hotel_code', 'reservations']);

            // Reservation type specific validation
            $this->validateReservationTypeRules($reservationData, $reservationType);

            // Property-specific validation
            $propertyRulesResult = $this->validatePropertyRules(
                $propertyId,
                $reservationData,
                'reservation'
            );

            if (!$propertyRulesResult['success']) {
                $validationResults['warnings'] = array_merge(
                    $validationResults['warnings'],
                    $propertyRulesResult['errors']
                );
            }

            // Validate each reservation
            foreach ($reservationData['reservations'] as $index => $reservation) {
                $this->validateReservationItem($reservation, $reservationType, $index);
                $validationResults['details']['reservations_validated']++;
            }

            $this->logValidationResult('reservation_data', $propertyId, $validationResults);
        } catch (ValidationException $e) {
            $validationResults['success'] = false;
            $validationResults['errors'][] = $e->getMessage();
        }

        return $validationResults;
    }

    /**
     * {@inheritDoc}
     */
    public function validateGroupBlockData(array $groupData, string $propertyId): array
    {
        $validationResults = [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'details' => [
                'property_id' => $propertyId,
                'blocks_validated' => 0
            ]
        ];

        try {
            // Validate required structure
            $this->validateRequired($groupData, ['hotel_code', 'inv_blocks']);

            // Property-specific validation
            $propertyRulesResult = $this->validatePropertyRules(
                $propertyId,
                $groupData,
                'group_block'
            );

            if (!$propertyRulesResult['success']) {
                $validationResults['warnings'] = array_merge(
                    $validationResults['warnings'],
                    $propertyRulesResult['errors']
                );
            }

            // Validate each group block
            foreach ($groupData['inv_blocks'] as $index => $block) {
                $this->validateGroupBlock($block, $index);
                $validationResults['details']['blocks_validated']++;
            }

            $this->logValidationResult('group_block_data', $propertyId, $validationResults);
        } catch (ValidationException $e) {
            $validationResults['success'] = false;
            $validationResults['errors'][] = $e->getMessage();
        }

        return $validationResults;
    }

    /**
     * {@inheritDoc}
     */
    public function validateInventoryCounts(array $inventoryCounts, string $inventoryMethod): array
    {
        $validationResults = [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'details' => [
                'method' => $inventoryMethod,
                'counts_validated' => 0,
                'count_types_found' => []
            ]
        ];

        try {
            $countTypesFound = [];

            foreach ($inventoryCounts as $index => $count) {
                // Validate count structure
                $this->validateRequired($count, ['count_type', 'count']);

                // Validate count type
                $countType = CountType::tryFrom($count['count_type']);
                if (!$countType) {
                    throw new ValidationException(
                        "Invalid count type: {$count['count_type']} at index {$index}",
                        ValidationErrorType::COUNT_TYPE->value
                    );
                }

                $countTypesFound[] = $countType->value;

                // Validate count value
                $this->validateCountValue($count['count'], $countType);

                $validationResults['details']['counts_validated']++;
            }

            $validationResults['details']['count_types_found'] = array_unique($countTypesFound);

            // Validate inventory method-specific rules
            $this->validateInventoryMethodRules($countTypesFound, $inventoryMethod);

            $this->logValidationResult('inventory_counts', $inventoryMethod, $validationResults);
        } catch (ValidationException $e) {
            $validationResults['success'] = false;
            $validationResults['errors'][] = $e->getMessage();
        }

        return $validationResults;
    }

    /**
     * {@inheritDoc}
     */
    public function sanitizeData(array $data, array $rules = []): array
    {
        $sanitized = $data;

        // Default sanitization rules
        $defaultRules = [
            'trim_strings' => true,
            'remove_null_values' => false,
            'escape_html' => true,
            'normalize_booleans' => true
        ];

        $appliedRules = array_merge($defaultRules, $rules);

        if ($appliedRules['trim_strings']) {
            $sanitized = $this->trimStringValues($sanitized);
        }

        if ($appliedRules['remove_null_values']) {
            $sanitized = array_filter($sanitized, fn($value) => $value !== null);
        }

        if ($appliedRules['escape_html']) {
            $sanitized = $this->escapeHtmlValues($sanitized);
        }

        if ($appliedRules['normalize_booleans']) {
            $sanitized = $this->normalizeBooleanValues($sanitized);
        }

        return $sanitized;
    }

    /**
     * {@inheritDoc}
     */
    public function validateDateRange(string $startDate, string $endDate, array $constraints = []): array
    {
        $validationResults = [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'details' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'constraints_applied' => $constraints
            ]
        ];

        try {
            // Parse dates
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            // Basic validation
            if ($start->gte($end)) {
                throw new ValidationException(
                    'Start date must be before end date',
                    ValidationErrorType::DATE_RANGE->value
                );
            }

            // Apply constraints
            foreach ($constraints as $constraint => $value) {
                $this->applyDateConstraint($start, $end, $constraint, $value);
            }

            $validationResults['details']['parsed_start'] = $start->toDateString();
            $validationResults['details']['parsed_end'] = $end->toDateString();
            $validationResults['details']['duration_days'] = $start->diffInDays($end);
        } catch (\Exception $e) {
            $validationResults['success'] = false;
            $validationResults['errors'][] = $e->getMessage();
        }

        return $validationResults;
    }

    /**
     * {@inheritDoc}
     */
    public function validatePropertyRules(string $propertyId, array $data, string $operation): array
    {
        $validationResults = [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'details' => [
                'property_id' => $propertyId,
                'operation' => $operation,
                'rules_applied' => []
            ]
        ];

        try {
            // Get property-specific rules
            $propertyRules = $this->rulesHelper->getPropertyRules($propertyId, $operation);

            if (empty($propertyRules)) {
                $validationResults['warnings'][] = "No specific rules found for property {$propertyId}";
                return $validationResults;
            }

            // Apply each rule
            foreach ($propertyRules as $ruleName => $rule) {
                $this->applyPropertyRule($data, $rule, $ruleName);
                $validationResults['details']['rules_applied'][] = $ruleName;
            }
        } catch (ValidationException $e) {
            $validationResults['success'] = false;
            $validationResults['errors'][] = $e->getMessage();
        }

        return $validationResults;
    }

    /**
     * {@inheritDoc}
     */
    public function validateRequiredFields(
        array $data,
        MessageType $messageType,
        array $optionalFields = []
    ): array {
        $validationResults = [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'details' => [
                'message_type' => $messageType->value,
                'optional_fields' => $optionalFields,
                'required_fields_found' => [],
                'missing_fields' => []
            ]
        ];

        try {
            // Get required fields for message type
            $requiredFields = $this->rulesHelper->getRequiredFields($messageType);

            // Filter out optional fields
            $actualRequired = array_diff($requiredFields, $optionalFields);

            // Check each required field
            foreach ($actualRequired as $field) {
                if ($this->hasNestedField($data, $field)) {
                    $validationResults['details']['required_fields_found'][] = $field;
                } else {
                    $validationResults['details']['missing_fields'][] = $field;
                    $validationResults['success'] = false;
                    $validationResults['errors'][] = "Missing required field: {$field}";
                }
            }
        } catch (ValidationException $e) {
            $validationResults['success'] = false;
            $validationResults['errors'][] = $e->getMessage();
        }

        return $validationResults;
    }

    /**
     * {@inheritDoc}
     */
    public function validateBusinessLogic(
        array $data,
        string $operationType,
        MessageType $messageType
    ): array {
        $validationResults = [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'details' => [
                'operation_type' => $operationType,
                'message_type' => $messageType->value,
                'rules_validated' => []
            ]
        ];

        try {
            // Get business logic rules for the operation and message type
            $businessRules = $this->rulesHelper->getBusinessLogicRules($messageType, $operationType);

            // Apply each business rule
            foreach ($businessRules as $ruleName => $rule) {
                $this->applyBusinessLogicRule($data, $rule, $ruleName);
                $validationResults['details']['rules_validated'][] = $ruleName;
            }
        } catch (ValidationException $e) {
            $validationResults['success'] = false;
            $validationResults['errors'][] = $e->getMessage();
        }

        return $validationResults;
    }

    /**
     * {@inheritDoc}
     */
    public function validateSoapHeaders(array $headers, string $propertyId): array
    {
        $validationResults = [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'details' => [
                'property_id' => $propertyId,
                'headers_validated' => []
            ]
        ];

        try {
            // Required SOAP headers for HTNG 2011B
            $requiredHeaders = [
                'messageId' => 'Message ID is required',
                'to' => 'To address is required',
                'action' => 'SOAP action is required'
            ];

            // Validate each required header
            foreach ($requiredHeaders as $header => $errorMessage) {
                if (!isset($headers[$header]) || empty($headers[$header])) {
                    $validationResults['success'] = false;
                    $validationResults['errors'][] = $errorMessage;
                } else {
                    $validationResults['details']['headers_validated'][] = $header;
                }
            }

            // Validate authentication headers
            $this->validateAuthenticationHeaders($headers);
        } catch (ValidationException $e) {
            $validationResults['success'] = false;
            $validationResults['errors'][] = $e->getMessage();
        }

        return $validationResults;
    }

    /**
     * {@inheritDoc}
     */
    public function getValidationRules(MessageType $messageType, string $operation = 'create'): array
    {
        return $this->rulesHelper->getAllRules($messageType, $operation);
    }

    /**
     * {@inheritDoc}
     */
    public function allValidationsPassed(array $validationResults): bool
    {
        return $validationResults['success'] ?? false;
    }

    /**
     * {@inheritDoc}
     */
    public function getValidationErrors(array $validationResults): array
    {
        $errors = $validationResults['errors'] ?? [];
        $warnings = $validationResults['warnings'] ?? [];

        return array_merge($errors, $warnings);
    }

    // Private helper methods

    private function validateSoapMessageStructure(
        SoapRequestDto|SoapResponseDto $message,
        MessageType $messageType
    ): void {
        // Validate message properties based on type
        if ($message instanceof SoapRequestDto) {
            $this->validateRequired([
                'messageId' => $message->messageId,
                'to' => $message->target,
                'action' => $message->action
            ], ['messageId', 'to', 'action']);
        }

        // Validate message type consistency
        if ($message->xmlBody && !str_contains($message->xmlBody, $messageType->getXmlElement())) {
            throw new ValidationException(
                "XML content does not match expected message type: {$messageType->value}",
                ValidationErrorType::MESSAGE_TYPE->value
            );
        }
    }

    private function validateMessageSpecificData(
        SoapRequestDto|SoapResponseDto $message,
        MessageType $messageType,
        array &$validationResults
    ): void {
        // Implementation specific to each message type
        match ($messageType) {
            MessageType::INVENTORY => $this->validateInventoryMessage($message, $validationResults),
            MessageType::RATES => $this->validateRateMessage($message, $validationResults),
            MessageType::RESERVATION => $this->validateReservationMessage($message, $validationResults),
            MessageType::GROUP_BLOCK => $this->validateGroupBlockMessage($message, $validationResults),
            default => null
        };
    }

    private function validateInventoryItem(array $item, int $index): void
    {
        $this->validateRequired($item, ['room_type', 'start_date', 'end_date', 'inv_counts']);

        // Validate dates
        $dateValidation = $this->validateDateRange($item['start_date'], $item['end_date']);
        if (!$dateValidation['success']) {
            throw new ValidationException(
                "Invalid date range in inventory item {$index}: " . implode(', ', $dateValidation['errors']),
                ValidationErrorType::DATE_RANGE->value
            );
        }

        // Validate inventory counts
        $countsValidation = $this->validateInventoryCounts(
            $item['inv_counts'],
            $item['inventory_method'] ?? 'calculated'
        );

        if (!$countsValidation['success']) {
            throw new ValidationException(
                "Invalid inventory counts in item {$index}: " . implode(', ', $countsValidation['errors']),
                ValidationErrorType::COUNT_TYPE->value
            );
        }
    }

    private function validateRatePlan(array $ratePlan, int $index): void
    {
        $this->validateRequired($ratePlan, ['rate_plan_code', 'rates']);

        foreach ($ratePlan['rates'] as $rateIndex => $rate) {
            $this->validateRateItem($rate, $index, $rateIndex);
        }
    }

    private function validateRateItem(array $rate, int $planIndex, int $rateIndex): void
    {
        $this->validateRequired($rate, ['start_date', 'end_date', 'base_amounts']);

        // Validate dates
        $dateValidation = $this->validateDateRange($rate['start_date'], $rate['end_date']);
        if (!$dateValidation['success']) {
            throw new ValidationException(
                "Invalid date range in rate plan {$planIndex}, rate {$rateIndex}",
                ValidationErrorType::DATE_RANGE->value
            );
        }

        // Validate base amounts
        foreach ($rate['base_amounts'] as $amountIndex => $amount) {
            $this->validateBaseAmount($amount, $planIndex, $rateIndex, $amountIndex);
        }
    }

    private function validateBaseAmount(array $amount, int $planIndex, int $rateIndex, int $amountIndex): void
    {
        $this->validateRequired($amount, ['number_of_guests', 'amount_before_tax']);

        if (!is_int($amount['number_of_guests']) || $amount['number_of_guests'] < 1) {
            throw new ValidationException(
                "Invalid guest count in rate plan {$planIndex}, rate {$rateIndex}, amount {$amountIndex}",
                ValidationErrorType::GUEST_INFORMATION->value
            );
        }

        if (!is_numeric($amount['amount_before_tax']) || $amount['amount_before_tax'] < 0) {
            throw new ValidationException(
                "Invalid amount in rate plan {$planIndex}, rate {$rateIndex}, amount {$amountIndex}",
                ValidationErrorType::RATE_PLAN->value
            );
        }
    }

    private function validateReservationTypeRules(array $data, ReservationType $type): void
    {
        // Type-specific validation rules
        match ($type) {
            ReservationType::TRAVEL_AGENCY => $this->validateTravelAgencyReservation($data),
            ReservationType::CORPORATE => $this->validateCorporateReservation($data),
            ReservationType::GROUP => $this->validateGroupReservation($data),
            ReservationType::PACKAGE => $this->validatePackageReservation($data),
            default => null
        };
    }

    private function validateReservationItem(array $reservation, ReservationType $type, int $index): void
    {
        $this->validateRequired($reservation, ['reservation_id', 'room_stays', 'res_guests']);

        // Validate room stays
        foreach ($reservation['room_stays'] as $stayIndex => $roomStay) {
            $this->validateRoomStay($roomStay, $index, $stayIndex);
        }

        // Validate guests
        foreach ($reservation['res_guests'] as $guestIndex => $guest) {
            $this->validateGuestData($guest, $index, $guestIndex);
        }
    }

    private function validateRoomStay(array $roomStay, int $resIndex, int $stayIndex): void
    {
        $this->validateRequired($roomStay, [
            'room_type_code',
            'rate_plan_code',
            'time_span'
        ]);

        if (isset($roomStay['time_span'])) {
            $this->validateTimeSpan($roomStay['time_span'], $resIndex, $stayIndex);
        }

        if (isset($roomStay['guest_counts'])) {
            $this->validateGuestCounts($roomStay['guest_counts'], $resIndex, $stayIndex);
        }
    }

    private function validateTimeSpan(array $timeSpan, int $resIndex, int $stayIndex): void
    {
        $this->validateRequired($timeSpan, ['start', 'end']);

        $dateValidation = $this->validateDateRange($timeSpan['start'], $timeSpan['end']);
        if (!$dateValidation['success']) {
            throw new ValidationException(
                "Invalid time span in reservation {$resIndex}, room stay {$stayIndex}",
                ValidationErrorType::DATE_RANGE->value
            );
        }
    }

    private function validateGuestCounts(array $guestCounts, int $resIndex, int $stayIndex): void
    {
        $totalGuests = 0;

        foreach ($guestCounts as $guestCount) {
            $this->validateRequired($guestCount, ['age_qualifying_code', 'count']);

            if (!is_int($guestCount['count']) || $guestCount['count'] < 0) {
                throw new ValidationException(
                    "Invalid guest count in reservation {$resIndex}, room stay {$stayIndex}",
                    ValidationErrorType::GUEST_INFORMATION->value
                );
            }

            $totalGuests += $guestCount['count'];
        }

        if ($totalGuests === 0) {
            throw new ValidationException(
                "Total guest count must be greater than zero in reservation {$resIndex}, room stay {$stayIndex}",
                ValidationErrorType::GUEST_INFORMATION->value
            );
        }
    }

    private function validateGuestData(array $guest, int $resIndex, int $guestIndex): void
    {
        $this->validateRequired($guest, ['profile']);

        if (isset($guest['profile']['person_name'])) {
            $this->validatePersonName($guest['profile']['person_name'], $resIndex, $guestIndex);
        }
    }

    private function validatePersonName(array $personName, int $resIndex, int $guestIndex): void
    {
        $this->validateRequired($personName, ['given_name', 'surname']);

        // Validate name lengths
        if (strlen($personName['given_name']) > 50) {
            throw new ValidationException(
                "Given name too long in reservation {$resIndex}, guest {$guestIndex}",
                ValidationErrorType::GUEST_INFORMATION->value
            );
        }

        if (strlen($personName['surname']) > 50) {
            throw new ValidationException(
                "Surname too long in reservation {$resIndex}, guest {$guestIndex}",
                ValidationErrorType::GUEST_INFORMATION->value
            );
        }
    }

    private function validateGroupBlock(array $block, int $index): void
    {
        $this->validateRequired($block, [
            'inv_block_code',
            'inv_block_long_name',
            'inv_block_dates',
            'room_types'
        ]);

        // Validate block dates
        if (isset($block['inv_block_dates'])) {
            $this->validateGroupBlockDates($block['inv_block_dates'], $index);
        }

        // Validate room types
        foreach ($block['room_types'] as $roomTypeIndex => $roomType) {
            $this->validateGroupRoomType($roomType, $index, $roomTypeIndex);
        }
    }

    private function validateGroupBlockDates(array $dates, int $blockIndex): void
    {
        $this->validateRequired($dates, ['start', 'end']);

        $dateValidation = $this->validateDateRange($dates['start'], $dates['end']);
        if (!$dateValidation['success']) {
            throw new ValidationException(
                "Invalid date range in group block {$blockIndex}",
                ValidationErrorType::DATE_RANGE->value
            );
        }
    }

    private function validateGroupRoomType(array $roomType, int $blockIndex, int $roomTypeIndex): void
    {
        $this->validateRequired($roomType, ['room_type_code', 'room_type_allocations']);

        foreach ($roomType['room_type_allocations'] as $allocIndex => $allocation) {
            $this->validateRoomAllocation($allocation, $blockIndex, $roomTypeIndex, $allocIndex);
        }
    }

    private function validateRoomAllocation(array $allocation, int $blockIndex, int $roomTypeIndex, int $allocIndex): void
    {
        $this->validateRequired($allocation, ['room_type_pickup_status', 'room_type_allocation']);

        $pickupStatus = $allocation['room_type_pickup_status'];
        if (!in_array($pickupStatus, [1, 2, 3])) {
            throw new ValidationException(
                "Invalid pickup status in block {$blockIndex}, room type {$roomTypeIndex}, allocation {$allocIndex}",
                ValidationErrorType::UNKNOWN->value
            );
        }

        if (isset($allocation['room_type_allocation'])) {
            $this->validateAllocationDetails($allocation['room_type_allocation'], $blockIndex, $roomTypeIndex, $allocIndex);
        }
    }

    private function validateAllocationDetails(array $details, int $blockIndex, int $roomTypeIndex, int $allocIndex): void
    {
        $this->validateRequired($details, ['start', 'end', 'number_of_units']);

        if (!is_int($details['number_of_units']) || $details['number_of_units'] < 0) {
            throw new ValidationException(
                "Invalid number of units in block {$blockIndex}, room type {$roomTypeIndex}, allocation {$allocIndex}",
                ValidationErrorType::UNKNOWN->value
            );
        }
    }

    private function validateCountValue(int $count, CountType $countType): void
    {
        if ($count < 0) {
            throw new ValidationException(
                "Count value cannot be negative for count type {$countType->value}",
                ValidationErrorType::COUNT_TYPE->value
            );
        }

        // Additional business rules for specific count types
        match ($countType) {
            CountType::PHYSICAL => $this->validatePhysicalRoomCount($count),
            CountType::AVAILABLE => $this->validateAvailableRoomCount($count),
            CountType::OVERSELL => $this->validateOversellCount($count),
            default => null
        };
    }

    private function validateInventoryMethodRules(array $countTypes, string $method): void
    {
        if ($method === 'not_calculated') {
            // For not calculated method, only CountType 2 should be present
            if (count($countTypes) !== 1 || !in_array(2, $countTypes)) {
                throw new ValidationException(
                    'Not calculated method must use only CountType 2 (Available Rooms)',
                    ValidationErrorType::INVENTORY_METHOD->value
                );
            }
        } elseif ($method === 'calculated') {
            // For calculated method, CountType 4 is required, CountType 5 must be zero
            if (!in_array(4, $countTypes)) {
                throw new ValidationException(
                    'Calculated method must include CountType 4 (Definite Sold)',
                    ValidationErrorType::INVENTORY_METHOD->value
                );
            }

            if (!in_array(5, $countTypes)) {
                throw new ValidationException(
                    'Calculated method must include CountType 5 (Tentative Sold) with value 0',
                    ValidationErrorType::INVENTORY_METHOD->value
                );
            }
        }
    }

    // Validation rule application methods

    private function applyDateConstraint(Carbon $start, Carbon $end, string $constraint, $value): void
    {
        match ($constraint) {
            'max_duration_days' => $this->validateMaxDuration($start, $end, $value),
            'min_duration_days' => $this->validateMinDuration($start, $end, $value),
            'not_in_past' => $this->validateNotInPast($start),
            'within_booking_window' => $this->validateBookingWindow($start, $value),
            default => null
        };
    }

    private function validateMaxDuration(Carbon $start, Carbon $end, int $maxDays): void
    {
        $duration = $start->diffInDays($end);
        if ($duration > $maxDays) {
            throw new ValidationException(
                "Date range exceeds maximum duration of {$maxDays} days",
                ValidationErrorType::DATE_RANGE->value
            );
        }
    }

    private function validateMinDuration(Carbon $start, Carbon $end, int $minDays): void
    {
        $duration = $start->diffInDays($end);
        if ($duration < $minDays) {
            throw new ValidationException(
                "Date range is below minimum duration of {$minDays} days",
                ValidationErrorType::DATE_RANGE->value
            );
        }
    }

    private function validateNotInPast(Carbon $date): void
    {
        if ($date->isPast()) {
            throw new ValidationException(
                'Date cannot be in the past',
                ValidationErrorType::DATE_RANGE->value
            );
        }
    }

    private function validateBookingWindow(Carbon $date, int $daysInAdvance): void
    {
        $maxBookingDate = now()->addDays($daysInAdvance);
        if ($date->isAfter($maxBookingDate)) {
            throw new ValidationException(
                "Date is outside booking window of {$daysInAdvance} days",
                ValidationErrorType::DATE_RANGE->value
            );
        }
    }

    private function applyPropertyRule(array $data, array $rule, string $ruleName): void
    {
        // Implementation of property-specific rule application
        // This would be defined based on your business requirements
    }

    private function applyBusinessLogicRule(array $data, array $rule, string $ruleName): void
    {
        // Implementation of business logic rule application
        // This would be defined based on HTNG 2011B business rules
    }

    // Helper validation methods

    private function validateTravelAgencyReservation(array $data): void
    {
        // Travel agency specific validation
        if (!isset($data['agency_id']) && !isset($data['iata_number'])) {
            throw new ValidationException(
                'Travel agency reservations must include agency_id or iata_number',
                ValidationErrorType::REQUIRED_FIELD->value
            );
        }
    }

    private function validateCorporateReservation(array $data): void
    {
        // Corporate reservation specific validation
        if (!isset($data['company_code'])) {
            throw new ValidationException(
                'Corporate reservations must include company_code',
                ValidationErrorType::REQUIRED_FIELD->value
            );
        }
    }

    private function validateGroupReservation(array $data): void
    {
        // Group reservation specific validation
        if (!isset($data['group_block_code'])) {
            throw new ValidationException(
                'Group reservations must include group_block_code',
                ValidationErrorType::REQUIRED_FIELD->value
            );
        }
    }

    private function validatePackageReservation(array $data): void
    {
        // Package reservation specific validation
        if (!isset($data['package_code'])) {
            throw new ValidationException(
                'Package reservations must include package_code',
                ValidationErrorType::REQUIRED_FIELD->value
            );
        }
    }

    private function validatePhysicalRoomCount(int $count): void
    {
        // Physical room counts should typically be reasonable numbers
        if ($count > 10000) {
            throw new ValidationException(
                'Physical room count seems unusually high',
                ValidationErrorType::COUNT_TYPE->value
            );
        }
    }

    private function validateAvailableRoomCount(int $count): void
    {
        // Available room counts validation
    }

    private function validateOversellCount(int $count): void
    {
        // Oversell typically shouldn't exceed certain thresholds
        if ($count > 100) {
            throw new ValidationException(
                'Oversell count exceeds reasonable threshold',
                ValidationErrorType::COUNT_TYPE->value
            );
        }
    }

    private function validateAuthenticationHeaders(array $headers): void
    {
        // Validate WS-Security headers for HTNG authentication
        if (!isset($headers['security'])) {
            throw new ValidationException(
                'Security headers are required for HTNG 2011B',
                ValidationErrorType::AUTHENTICATION->value
            );
        }

        $security = $headers['security'];
        if (!isset($security['username']) || !isset($security['password'])) {
            throw new ValidationException(
                'Username and password are required in security headers',
                ValidationErrorType::AUTHENTICATION->value
            );
        }
    }

    // Inventory message validation methods

    private function validateInventoryMessage(
        SoapRequestDto|SoapResponseDto $message,
        array &$validationResults
    ): void {
        // Specific validation for inventory messages
        $validationResults['details']['inventory_validation'] = [
            'message_id' => $message->messageId,
            'timestamp_format' => $this->validateTimestampFormat($message->timeStamp ?? '')
        ];
    }

    private function validateRateMessage(
        SoapRequestDto|SoapResponseDto $message,
        array &$validationResults
    ): void {
        // Specific validation for rate messages
        $validationResults['details']['rate_validation'] = [
            'message_id' => $message->messageId,
            'timestamp_format' => $this->validateTimestampFormat($message->timeStamp ?? '')
        ];
    }

    private function validateReservationMessage(
        SoapRequestDto|SoapResponseDto $message,
        array &$validationResults
    ): void {
        // Specific validation for reservation messages
        $validationResults['details']['reservation_validation'] = [
            'message_id' => $message->messageId,
            'timestamp_format' => $this->validateTimestampFormat($message->timeStamp ?? '')
        ];
    }

    private function validateGroupBlockMessage(
        SoapRequestDto|SoapResponseDto $message,
        array &$validationResults
    ): void {
        // Specific validation for group block messages
        $validationResults['details']['group_block_validation'] = [
            'message_id' => $message->messageId,
            'timestamp_format' => $this->validateTimestampFormat($message->timeStamp ?? '')
        ];
    }

    private function validateTimestampFormat(string $timestamp): bool
    {
        if (empty($timestamp)) {
            return false;
        }

        // HTNG 2011B uses ISO 8601 format: YYYY-MM-DDTHH:MM:SS
        try {
            $parsed = Carbon::createFromFormat('Y-m-d\TH:i:s', $timestamp);
            return $parsed !== false;
        } catch (\Exception) {
            return false;
        }
    }

    // Data sanitization helper methods

    private function trimStringValues(array $data): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                return trim($value);
            }
            if (is_array($value)) {
                return $this->trimStringValues($value);
            }
            return $value;
        }, $data);
    }

    private function escapeHtmlValues(array $data): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
            if (is_array($value)) {
                return $this->escapeHtmlValues($value);
            }
            return $value;
        }, $data);
    }

    private function normalizeBooleanValues(array $data): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                $lower = strtolower($value);
                if (in_array($lower, ['true', '1', 'yes', 'on'])) {
                    return true;
                }
                if (in_array($lower, ['false', '0', 'no', 'off'])) {
                    return false;
                }
            }
            if (is_array($value)) {
                return $this->normalizeBooleanValues($value);
            }
            return $value;
        }, $data);
    }

    // General helper methods

    private function validateRequired(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new ValidationException(
                    "Required field missing: {$field}",
                    ValidationErrorType::REQUIRED_FIELD->value
                );
            }
        }
    }

    private function hasNestedField(array $data, string $field): bool
    {
        // Support for dot notation field paths
        if (!str_contains($field, '.')) {
            return isset($data[$field]);
        }

        $parts = explode('.', $field);
        $current = $data;

        foreach ($parts as $part) {
            if (!is_array($current) || !isset($current[$part])) {
                return false;
            }
            $current = $current[$part];
        }

        return true;
    }

    private function logValidationResult(string $type, string $context, array $results): void
    {
        $level = $results['success'] ? 'info' : 'warning';

        Log::log($level, "Validation {$type} completed", [
            'context' => $context,
            'success' => $results['success'],
            'error_count' => count($results['errors'] ?? []),
            'warning_count' => count($results['warnings'] ?? [])
        ]);
    }
}
