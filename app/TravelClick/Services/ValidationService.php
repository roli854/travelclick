<?php

declare(strict_types=1);

namespace App\TravelClick\Services;

use App\TravelClick\Services\Contracts\ValidationServiceInterface;
use App\TravelClick\Services\Contracts\ConfigurationServiceInterface;
use App\TravelClick\DTOs\SoapRequestDto;
use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\CountType;
use App\TravelClick\Enums\ReservationType;
use App\TravelClick\Exceptions\ValidationException;
use App\TravelClick\Exceptions\InvalidConfigurationException;
use App\TravelClick\Support\ConfigurationValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use libXMLError;

/**
 * ValidationService
 *
 * Comprehensive validation service for TravelClick/HTNG 2011B operations.
 * Handles XML structure validation, business logic validation, and data sanitization.
 */
class ValidationService implements ValidationServiceInterface
{
    /**
     * The ConfigurationService instance
     */
    protected ConfigurationServiceInterface $configurationService;

    /**
     * The ConfigurationValidator instance
     */
    protected ConfigurationValidator $configurationValidator;

    /**
     * Schema files for HTNG 2011B validation
     *
     * @var array<string, string>
     */
    protected array $schemaFiles = [
        'inventory' => 'htng/OTA_HotelInvCountNotif.xsd',
        'rate' => 'htng/OTA_HotelRateAmountNotif.xsd',
        'reservation' => 'htng/OTA_HotelResNotif.xsd',
        'block' => 'htng/OTA_HotelInvBlockNotif.xsd',
    ];

    /**
     * Required namespaces for HTNG 2011B
     *
     * @var array<string, string>
     */
    protected array $requiredNamespaces = [
        'ota' => 'http://www.opentravel.org/OTA/2003/05',
        'wsa' => 'http://www.w3.org/2005/08/addressing',
        'wsse' => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
        'htng' => 'http://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService'
    ];

    /**
     * Constructor
     */
    public function __construct(
        ConfigurationServiceInterface $configurationService,
        ConfigurationValidator $configurationValidator
    ) {
        $this->configurationService = $configurationService;
        $this->configurationValidator = $configurationValidator;
    }

    /**
     * {@inheritDoc}
     */
    public function validateSoapMessage(
        SoapRequestDto|SoapResponseDto $message,
        MessageType $messageType
    ): array {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'message_type' => $messageType->value,
            'validated_at' => now()
        ];

        try {
            // Validate message structure
            $this->validateMessageStructure($message, $messageType, $results);

            // Validate headers
            if ($message instanceof SoapRequestDto && !empty($message->headers)) {
                $headerValidation = $this->validateSoapHeaders($message->headers, $message->hotelCode ?? '');
                $this->mergeValidationResults($results, $headerValidation);
            }

            // Validate XML if present
            if (!empty($message->body)) {
                $this->validateSoapBody($message->body, $messageType, $results);
            }

            Log::info('SOAP message validation completed', [
                'message_type' => $messageType->value,
                'valid' => $results['valid'],
                'error_count' => count($results['errors'])
            ]);
        } catch (\Exception $e) {
            $results['valid'] = false;
            $results['errors'][] = 'Validation failed: ' . $e->getMessage();

            Log::error('SOAP message validation error', [
                'message_type' => $messageType->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function validateXmlStructure(string $xml, string $schemaType): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'schema_type' => $schemaType,
            'validated_at' => now()
        ];

        // Enable libxml error handling
        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        try {
            // Load XML
            if (!$dom->loadXML($xml)) {
                $errors = libxml_get_errors();
                foreach ($errors as $error) {
                    $results['errors'][] = "XML Parse Error: {$error->message}";
                }
                $results['valid'] = false;
                return $results;
            }

            // Validate against schema if available
            $schemaPath = $this->getSchemaPath($schemaType);
            if ($schemaPath && file_exists($schemaPath)) {
                if (!$dom->schemaValidate($schemaPath)) {
                    $errors = libxml_get_errors();
                    foreach ($errors as $error) {
                        $results['errors'][] = "Schema Validation Error: {$error->message}";
                    }
                    $results['valid'] = false;
                }
            }

            // Validate required namespaces
            $this->validateNamespaces($dom, $results);

            // Validate HTNG specific elements
            $this->validateHtngElements($dom, $schemaType, $results);
        } catch (\Exception $e) {
            $results['valid'] = false;
            $results['errors'][] = 'XML validation failed: ' . $e->getMessage();
        } finally {
            libxml_clear_errors();
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function validateInventoryData(array $inventoryData, string $propertyId): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'property_id' => $propertyId,
            'validated_at' => now()
        ];

        // Get property configuration
        $propertyConfig = $this->configurationService->getPropertyConfig($propertyId);

        // Define validation rules
        $rules = [
            'hotel_code' => 'required|string|max:20',
            'inventories' => 'required|array|min:1',
            'inventories.*.room_type_code' => 'required|string|max:10',
            'inventories.*.start_date' => 'required|date',
            'inventories.*.end_date' => 'required|date|after_or_equal:inventories.*.start_date',
            'inventories.*.counts' => 'required|array|min:1',
            'inventories.*.counts.*.count_type' => 'required|integer',
            'inventories.*.counts.*.count' => 'required|integer|min:0',
        ];

        // Validate basic structure
        $validator = Validator::make($inventoryData, $rules);
        if ($validator->fails()) {
            $results['valid'] = false;
            $results['errors'] = array_merge($results['errors'], $validator->errors()->all());
        }

        // Validate each inventory item
        foreach ($inventoryData['inventories'] ?? [] as $index => $inventory) {
            $this->validateSingleInventoryItem($inventory, $index, $propertyConfig->toArray(), $results);
        }

        // Validate inventory method consistency
        $this->validateInventoryMethodConsistency($inventoryData, $results);

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function validateRateData(array $rateData, string $propertyId): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'property_id' => $propertyId,
            'validated_at' => now()
        ];

        // Get property configuration
        $propertyConfig = $this->configurationService->getPropertyConfig($propertyId);

        // Define validation rules
        $rules = [
            'hotel_code' => 'required|string|max:20',
            'rates' => 'required|array|min:1',
            'rates.*.rate_plan_code' => 'required|string|max:20',
            'rates.*.room_type_code' => 'required|string|max:10',
            'rates.*.start_date' => 'required|date',
            'rates.*.end_date' => 'required|date|after_or_equal:rates.*.start_date',
            'rates.*.base_amount' => 'required|numeric|min:0',
            'rates.*.currency_code' => 'required|string|size:3',
        ];

        // Validate basic structure
        $validator = Validator::make($rateData, $rules);
        if ($validator->fails()) {
            $results['valid'] = false;
            $results['errors'] = array_merge($results['errors'], $validator->errors()->all());
        }

        // Validate each rate item
        foreach ($rateData['rates'] ?? [] as $index => $rate) {
            $this->validateSingleRateItem($rate, $index, $propertyConfig->toArray(), $results);
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function validateReservationData(
        array $reservationData,
        ReservationType $reservationType,
        string $propertyId
    ): array {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'property_id' => $propertyId,
            'reservation_type' => $reservationType->value,
            'validated_at' => now()
        ];

        // Get property configuration
        $propertyConfig = $this->configurationService->getPropertyConfig($propertyId);

        // Define base validation rules
        $rules = $this->getReservationValidationRules($reservationType);

        // Validate basic structure
        $validator = Validator::make($reservationData, $rules);
        if ($validator->fails()) {
            $results['valid'] = false;
            $results['errors'] = array_merge($results['errors'], $validator->errors()->all());
        }

        // Validate guest information
        $this->validateGuestInformation($reservationData, $results);

        // Validate room stays
        $this->validateRoomStays($reservationData, $propertyConfig, $results);

        // Type-specific validations
        match ($reservationType) {
            ReservationType::TRAVEL_AGENCY => $this->validateTravelAgencyReservation($reservationData, $results),
            ReservationType::CORPORATE => $this->validateCorporateReservation($reservationData, $results),
            ReservationType::GROUP => $this->validateGroupReservation($reservationData, $results),
            ReservationType::PACKAGE => $this->validatePackageReservation($reservationData, $results),
            default => null,
        };

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function validateGroupBlockData(array $groupData, string $propertyId): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'property_id' => $propertyId,
            'validated_at' => now()
        ];

        // Define validation rules for group blocks
        $rules = [
            'hotel_code' => 'required|string|max:20',
            'block_code' => 'required|string|max:20',
            'block_name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'cutoff_date' => 'required|date|before_or_equal:start_date',
            'room_types' => 'required|array|min:1',
            'contact_info' => 'required|array',
        ];

        // Validate basic structure
        $validator = Validator::make($groupData, $rules);
        if ($validator->fails()) {
            $results['valid'] = false;
            $results['errors'] = array_merge($results['errors'], $validator->errors()->all());
        }

        // Validate room type allocations
        foreach ($groupData['room_types'] ?? [] as $index => $roomType) {
            $this->validateGroupRoomType($roomType, $index, $results);
        }

        // Validate contact information
        $this->validateGroupContactInfo($groupData['contact_info'] ?? [], $results);

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function validateInventoryCounts(array $inventoryCounts, string $inventoryMethod): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'method' => $inventoryMethod,
            'validated_at' => now()
        ];

        if ($inventoryMethod === 'not_calculated') {
            // For not calculated method, only CountType 2 (Available) is allowed
            if (count($inventoryCounts) !== 1) {
                $results['valid'] = false;
                $results['errors'][] = 'Not calculated method must have exactly one count type';
            }

            $firstCount = reset($inventoryCounts);
            if (($firstCount['count_type'] ?? null) !== CountType::AVAILABLE_ROOMS->value) {
                $results['valid'] = false;
                $results['errors'][] = 'Not calculated method must use CountType 2 (Available Rooms)';
            }
        } else {
            // For calculated method, validate count type combinations
            $countTypes = array_column($inventoryCounts, 'count_type');

            // CountType 4 (Definite Sold) is required
            if (!in_array(CountType::DEFINITE_SOLD->value, $countTypes)) {
                $results['valid'] = false;
                $results['errors'][] = 'Calculated method requires CountType 4 (Definite Sold)';
            }

            // CountType 5 (Tentative) must be 0 when present
            $tentativeCount = collect($inventoryCounts)
                ->firstWhere('count_type', CountType::TENTATIVE_SOLD->value);

            if ($tentativeCount && ($tentativeCount['count'] ?? 0) !== 0) {
                $results['warnings'][] = 'CountType 5 (Tentative) should typically be 0 in calculated method';
            }

            // Validate allowed count types
            $allowedTypes = [
                CountType::PHYSICAL_ROOMS->value,
                CountType::DEFINITE_SOLD->value,
                CountType::TENTATIVE_SOLD->value,
                CountType::OUT_OF_ORDER->value,
                CountType::OVERSELL_ROOMS->value,
            ];

            foreach ($inventoryCounts as $count) {
                if (!in_array($count['count_type'] ?? null, $allowedTypes)) {
                    $results['valid'] = false;
                    $results['errors'][] = "Invalid count type: {$count['count_type']}";
                }
            }
        }

        return $results;
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
            'remove_null_values' => true,
            'convert_empty_strings_to_null' => true,
            'normalize_dates' => true,
            'sanitize_html' => true,
        ];

        $rules = array_merge($defaultRules, $rules);

        // Apply sanitization recursively
        $sanitized = $this->applySanitizationRules($sanitized, $rules);

        return $sanitized;
    }

    /**
     * {@inheritDoc}
     */
    public function validateDateRange(string $startDate, string $endDate, array $constraints = []): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'validated_at' => now()
        ];

        try {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            // Basic range validation
            if ($start->isAfter($end)) {
                $results['valid'] = false;
                $results['errors'][] = 'Start date must be before or equal to end date';
            }

            // Apply constraints
            foreach ($constraints as $constraint => $value) {
                switch ($constraint) {
                    case 'max_days':
                        if ($start->diffInDays($end) > $value) {
                            $results['valid'] = false;
                            $results['errors'][] = "Date range exceeds maximum of {$value} days";
                        }
                        break;
                    case 'min_days':
                        if ($start->diffInDays($end) < $value) {
                            $results['valid'] = false;
                            $results['errors'][] = "Date range must be at least {$value} days";
                        }
                        break;
                    case 'future_only':
                        if ($value && $start->isPast()) {
                            $results['valid'] = false;
                            $results['errors'][] = 'Start date must be in the future';
                        }
                        break;
                    case 'max_advance_days':
                        if ($start->diffInDays(now()) > $value) {
                            $results['valid'] = false;
                            $results['errors'][] = "Start date cannot be more than {$value} days in advance";
                        }
                        break;
                }
            }
        } catch (\Exception $e) {
            $results['valid'] = false;
            $results['errors'][] = 'Invalid date format: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function validatePropertyRules(string $propertyId, array $data, string $operation): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'property_id' => $propertyId,
            'operation' => $operation,
            'validated_at' => now()
        ];

        try {
            // Get property-specific configuration
            $propertyConfig = $this->configurationService->getPropertyConfig($propertyId);

            // Validate based on operation type
            match ($operation) {
                'inventory' => $this->validatePropertyInventoryRules($data, $propertyConfig, $results),
                'rate' => $this->validatePropertyRateRules($data, $propertyConfig, $results),
                'reservation' => $this->validatePropertyReservationRules($data, $propertyConfig, $results),
                default => throw new ValidationException("Unknown operation type: {$operation}"),
            };
        } catch (\Exception $e) {
            $results['valid'] = false;
            $results['errors'][] = 'Property rule validation failed: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function validateRequiredFields(
        array $data,
        MessageType $messageType,
        array $optionalFields = []
    ): array {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'message_type' => $messageType->value,
            'validated_at' => now()
        ];

        $requiredFields = $this->getRequiredFieldsForMessageType($messageType);

        // Remove optional fields from required list
        $requiredFields = array_diff($requiredFields, $optionalFields);

        foreach ($requiredFields as $field) {
            if (!$this->hasNestedField($data, $field)) {
                $results['valid'] = false;
                $results['errors'][] = "Required field missing: {$field}";
            }
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function validateBusinessLogic(
        array $data,
        string $operationType,
        MessageType $messageType
    ): array {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'operation' => $operationType,
            'message_type' => $messageType->value,
            'validated_at' => now()
        ];

        // Apply business logic validations based on message type
        match ($messageType) {
            MessageType::INVENTORY => $this->validateInventoryBusinessLogic($data, $operationType, $results),
            MessageType::RATE => $this->validateRateBusinessLogic($data, $operationType, $results),
            MessageType::RESERVATION => $this->validateReservationBusinessLogic($data, $operationType, $results),
            MessageType::GROUP_BLOCK => $this->validateGroupBlockBusinessLogic($data, $operationType, $results),
            default => throw new ValidationException("Unsupported message type: {$messageType->value}"),
        };

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function validateSoapHeaders(array $headers, string $propertyId): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'property_id' => $propertyId,
            'validated_at' => now()
        ];

        // Required SOAP headers for HTNG 2011B
        $requiredHeaders = [
            'MessageID',
            'To',
            'ReplyTo',
            'Action',
            'Security'
        ];

        foreach ($requiredHeaders as $header) {
            if (!isset($headers[$header])) {
                $results['valid'] = false;
                $results['errors'][] = "Required SOAP header missing: {$header}";
            }
        }

        // Validate MessageID format
        if (isset($headers['MessageID'])) {
            if (!$this->isValidMessageId($headers['MessageID'])) {
                $results['valid'] = false;
                $results['errors'][] = 'Invalid MessageID format';
            }
        }

        // Validate To header (endpoint URL)
        if (isset($headers['To'])) {
            if (!filter_var($headers['To'], FILTER_VALIDATE_URL)) {
                $results['valid'] = false;
                $results['errors'][] = 'Invalid To header URL';
            }
        }

        // Validate Security header
        if (isset($headers['Security'])) {
            $this->validateSecurityHeader($headers['Security'], $results);
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function getValidationRules(MessageType $messageType, string $operation = 'create'): array
    {
        return match ($messageType) {
            MessageType::INVENTORY => $this->getInventoryValidationRules($operation),
            MessageType::RATES => $this->getRateValidationRules($operation),
            MessageType::RESERVATION => $this->getReservationValidationRules(ReservationType::TRANSIENT),
            MessageType::GROUP_BLOCK => $this->getGroupBlockValidationRules($operation),
            default => [],
        };
    }

    /**
     * {@inheritDoc}
     */
    public function allValidationsPassed(array $validationResults): bool
    {
        return $validationResults['valid'] ?? false;
    }

    /**
     * {@inheritDoc}
     */
    public function getValidationErrors(array $validationResults): array
    {
        return $validationResults['errors'] ?? [];
    }

    /**
     * Validate message structure
     */
    protected function validateMessageStructure(
        SoapRequestDto|SoapResponseDto $message,
        MessageType $messageType,
        array &$results
    ): void {
        // Validate message type compatibility
        if (!$this->isMessageTypeCompatible($message, $messageType)) {
            $results['valid'] = false;
            $results['errors'][] = "Message type mismatch: expected {$messageType->value}";
        }

        // Validate required properties
        $this->validateRequiredMessageProperties($message, $results);
    }

    /**
     * Validate SOAP body content
     */
    protected function validateSoapBody(string $body, MessageType $messageType, array &$results): void
    {
        // Parse XML body
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();

        if (!$dom->loadXML($body)) {
            $results['valid'] = false;
            $results['errors'][] = 'Invalid XML in SOAP body';
            return;
        }

        // Validate against message type schema
        $schemaType = $this->getSchemaTypeFromMessageType($messageType);
        $xmlValidation = $this->validateXmlStructure($body, $schemaType);

        $this->mergeValidationResults($results, $xmlValidation);
    }

    /**
     * Validate a single inventory item
     */
    protected function validateSingleInventoryItem(
        array $inventory,
        int $index,
        array $propertyConfig,
        array &$results
    ): void {
        // Validate room type exists in property
        $roomType = $inventory['room_type_code'] ?? null;
        if ($roomType && !$this->isValidRoomType($roomType, $propertyConfig)) {
            $results['warnings'][] = "Room type {$roomType} not found in property configuration (index {$index})";
        }

        // Validate date range
        if (isset($inventory['start_date'], $inventory['end_date'])) {
            $dateValidation = $this->validateDateRange(
                $inventory['start_date'],
                $inventory['end_date'],
                ['max_days' => 365]
            );

            if (!$dateValidation['valid']) {
                $results['valid'] = false;
                foreach ($dateValidation['errors'] as $error) {
                    $results['errors'][] = "Inventory item {$index}: {$error}";
                }
            }
        }

        // Validate inventory counts
        if (isset($inventory['counts'])) {
            $method = $this->determineInventoryMethod($inventory['counts']);
            $countValidation = $this->validateInventoryCounts($inventory['counts'], $method);

            if (!$countValidation['valid']) {
                $results['valid'] = false;
                foreach ($countValidation['errors'] as $error) {
                    $results['errors'][] = "Inventory item {$index}: {$error}";
                }
            }
        }
    }

    /**
     * Validate inventory method consistency
     */
    protected function validateInventoryMethodConsistency(array $inventoryData, array &$results): void
    {
        $methods = [];

        foreach ($inventoryData['inventories'] ?? [] as $inventory) {
            if (isset($inventory['counts'])) {
                $methods[] = $this->determineInventoryMethod($inventory['counts']);
            }
        }

        $uniqueMethods = array_unique($methods);
        if (count($uniqueMethods) > 1) {
            $results['valid'] = false;
            $results['errors'][] = 'All inventory items must use the same method (calculated or not calculated)';
        }
    }

    /**
     * Determine inventory method from counts
     */
    protected function determineInventoryMethod(array $counts): string
    {
        $countTypes = array_column($counts, 'count_type');

        if (count($counts) === 1 && in_array(CountType::AVAILABLE_ROOMS->value, $countTypes)) {
            return 'not_calculated';
        }

        return 'calculated';
    }

    /**
     * Validate a single rate item
     */
    protected function validateSingleRateItem(
        array $rate,
        int $index,
        array $propertyConfig,
        array &$results
    ): void {
        // Validate currency code
        if (isset($rate['currency_code'])) {
            if (!$this->isValidCurrencyCode($rate['currency_code'])) {
                $results['valid'] = false;
                $results['errors'][] = "Invalid currency code: {$rate['currency_code']} (index {$index})";
            }
        }

        // Validate rate plan
        $ratePlan = $rate['rate_plan_code'] ?? null;
        if ($ratePlan && !$this->isValidRatePlan($ratePlan, $propertyConfig)) {
            $results['warnings'][] = "Rate plan {$ratePlan} not found in property configuration (index {$index})";
        }

        // Validate guest amounts
        if (isset($rate['guest_amounts'])) {
            $this->validateGuestAmounts($rate['guest_amounts'], $index, $results);
        }
    }

    /**
     * Validate guest amounts
     */
    protected function validateGuestAmounts(array $guestAmounts, int $parentIndex, array &$results): void
    {
        // Ensure we have at least base rate for 1 adult
        $hasAdult1 = collect($guestAmounts)->contains('guest_count', 1);
        if (!$hasAdult1) {
            $results['valid'] = false;
            $results['errors'][] = "Missing rate for 1 adult (rate index {$parentIndex})";
        }

        // Validate amounts are positive
        foreach ($guestAmounts as $guestAmount) {
            if (($guestAmount['amount'] ?? 0) < 0) {
                $results['valid'] = false;
                $results['errors'][] = "Negative rate amount not allowed (rate index {$parentIndex})";
            }
        }
    }

    /**
     * Get reservation validation rules
     */
    protected function getReservationValidationRules(ReservationType $type): array
    {
        $baseRules = [
            'reservation_id' => 'required|string|max:50',
            'hotel_code' => 'required|string|max:20',
            'guest_info' => 'required|array',
            'guest_info.first_name' => 'required|string|max:50',
            'guest_info.last_name' => 'required|string|max:50',
            'room_stays' => 'required|array|min:1',
        ];

        return match ($type) {
            ReservationType::TRAVEL_AGENCY => array_merge($baseRules, [
                'travel_agency' => 'required|array',
                'travel_agency.iata_number' => 'required|string|max:10',
                'travel_agency.name' => 'required|string|max:100',
            ]),
            ReservationType::CORPORATE => array_merge($baseRules, [
                'corporate_info' => 'required|array',
                'corporate_info.company_code' => 'required|string|max:20',
                'corporate_info.company_name' => 'required|string|max:100',
            ]),
            ReservationType::GROUP => array_merge($baseRules, [
                'group_code' => 'required|string|max:20',
            ]),
            ReservationType::PACKAGE => array_merge($baseRules, [
                'package_code' => 'required|string|max:20',
            ]),
            default => $baseRules,
        };
    }

    /**
     * Get schema path for schema type
     */
    protected function getSchemaPath(string $schemaType): ?string
    {
        $schemaFile = $this->schemaFiles[$schemaType] ?? null;
        if (!$schemaFile) {
            return null;
        }

        return storage_path("schemas/{$schemaFile}");
    }

    /**
     * Validate XML namespaces
     */
    protected function validateNamespaces(DOMDocument $dom, array &$results): void
    {
        $xpath = new DOMXPath($dom);

        foreach ($this->requiredNamespaces as $prefix => $uri) {
            $elements = $xpath->query("//*[namespace-uri() = '{$uri}']");
            if ($elements->length === 0) {
                $results['warnings'][] = "Namespace {$prefix} ({$uri}) not found in XML";
            }
        }
    }

    /**
     * Validate HTNG specific elements
     */
    protected function validateHtngElements(DOMDocument $dom, string $schemaType, array &$results): void
    {
        $xpath = new DOMXPath($dom);

        // Register namespaces for XPath
        foreach ($this->requiredNamespaces as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        // Schema-specific validations
        match ($schemaType) {
            'inventory' => $this->validateInventoryXmlElements($xpath, $results),
            'rate' => $this->validateRateXmlElements($xpath, $results),
            'reservation' => $this->validateReservationXmlElements($xpath, $results),
            'block' => $this->validateBlockXmlElements($xpath, $results),
            default => null,
        };
    }

    /**
     * Apply sanitization rules recursively
     */
    protected function applySanitizationRules(array $data, array $rules): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->applySanitizationRules($value, $rules);
            } elseif (is_string($value)) {
                if ($rules['trim_strings'] ?? false) {
                    $value = trim($value);
                }

                if ($rules['sanitize_html'] ?? false) {
                    $value = strip_tags($value);
                }

                if ($rules['convert_empty_strings_to_null'] ?? false && $value === '') {
                    $value = null;
                }

                $data[$key] = $value;
            }
        }

        if ($rules['remove_null_values'] ?? false) {
            $data = array_filter($data, fn($value) => $value !== null);
        }

        return $data;
    }

    /**
     * Merge validation results
     */
    protected function mergeValidationResults(array &$target, array $source): void
    {
        if (!($source['valid'] ?? true)) {
            $target['valid'] = false;
        }

        $target['errors'] = array_merge($target['errors'], $source['errors'] ?? []);
        $target['warnings'] = array_merge($target['warnings'], $source['warnings'] ?? []);
    }

    /**
     * Check if nested field exists
     */
    protected function hasNestedField(array $data, string $field): bool
    {
        $keys = explode('.', $field);
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return false;
            }
            $current = $current[$key];
        }

        return true;
    }

    /** TODO
     * Additional validation methods would be implemented here:
     * - validateInventoryXmlElements()
     * - validateRateXmlElements()
     * - validateReservationXmlElements()
     * - validateBlockXmlElements()
     * - validateGuestInformation()
     * - validateRoomStays()
     * - validateTravelAgencyReservation()
     * - validateCorporateReservation()
     * - validateGroupReservation()
     * - validatePackageReservation()
     * - validateGroupRoomType()
     * - validateGroupContactInfo()
     * - validateInventoryBusinessLogic()
     * - validateRateBusinessLogic()
     * - validateReservationBusinessLogic()
     * - validateGroupBlockBusinessLogic()
     * - validateSecurityHeader()
     * - isValidMessageId()
     * - isMessageTypeCompatible()
     * - validateRequiredMessageProperties()
     * - getSchemaTypeFromMessageType()
     * - isValidRoomType()
     * - isValidCurrencyCode()
     * - isValidRatePlan()
     * - getRequiredFieldsForMessageType()
     * - getInventoryValidationRules()
     * - getRateValidationRules()
     * - getGroupBlockValidationRules()
     * - validatePropertyInventoryRules()
     * - validatePropertyRateRules()
     * - validatePropertyReservationRules()
     *
     * These methods would follow similar patterns and provide specific validation logic
     * for each aspect of the HTNG 2011B interface requirements.
     */
}
