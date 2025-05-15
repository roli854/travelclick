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
            if (!empty($message->xmlBody)) {
                $this->validateSoapBody($message->xmlBody, $messageType, $results);
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
        $propertyConfig = $this->configurationService->getPropertyConfig((int)$propertyId);

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
        $propertyConfig = $this->configurationService->getPropertyConfig((int)$propertyId);

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
            if (($firstCount['count_type'] ?? null) !== CountType::AVAILABLE->value) {
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
                CountType::PHYSICAL->value,
                CountType::DEFINITE_SOLD->value,
                CountType::TENTATIVE_SOLD->value,
                CountType::OUT_OF_ORDER->value,
                CountType::OVERSELL->value,
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
            $propertyConfig = $this->configurationService->getPropertyConfig((int)$propertyId);

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
            MessageType::RATES => $this->validateRateBusinessLogic($data, $operationType, $results),
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

        if (count($counts) === 1 && in_array(CountType::AVAILABLE->value, $countTypes)) {
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


    /**
     * Validate inventory-specific XML elements
     */
    protected function validateInventoryXmlElements(DOMXPath $xpath, array &$results): void
    {
        // Check for required Inventories element
        $inventories = $xpath->query('//ota:Inventories');
        if ($inventories->length === 0) {
            $results['valid'] = false;
            $results['errors'][] = 'Missing required Inventories element';
            return;
        }

        // Validate HotelCode attribute
        $hotelCode = $inventories->item(0)->attributes['HotelCode']->value ?? null;
        if (empty($hotelCode)) {
            $results['valid'] = false;
            $results['errors'][] = 'Missing HotelCode attribute in Inventories element';
        }

        // Check for Inventory elements
        $inventoryItems = $xpath->query('//ota:Inventory');
        if ($inventoryItems->length === 0) {
            $results['valid'] = false;
            $results['errors'][] = 'At least one Inventory element is required';
        }

        // Validate each inventory item
        foreach ($inventoryItems as $index => $inventory) {
            $roomTypeCode = $xpath->query('.//ota:StatusApplicationControl/@InvTypeCode', $inventory);
            if ($roomTypeCode->length === 0) {
                $results['errors'][] = "Missing room type code in inventory item {$index}";
                $results['valid'] = false;
            }

            // Validate InvCounts
            $invCounts = $xpath->query('.//ota:InvCounts/ota:InvCount', $inventory);
            if ($invCounts->length === 0) {
                $results['errors'][] = "Missing inventory counts in inventory item {$index}";
                $results['valid'] = false;
            }

            // Validate count types
            foreach ($invCounts as $countIndex => $invCount) {
                $countType = $invCount->attributes['CountType']->value ?? null;
                $count = $invCount->attributes['Count']->value ?? 0;

                if (empty($countType)) {
                    $results['errors'][] = "Missing CountType in inventory item {$index}, count {$countIndex}";
                    $results['valid'] = false;
                }

                if (!in_array((int)$countType, [1, 2, 4, 5, 6, 99])) {
                    $results['errors'][] = "Invalid CountType {$countType} in inventory item {$index}";
                    $results['valid'] = false;
                }

                if ($count === '' || !is_numeric($count)) {
                    $results['errors'][] = "Invalid Count value in inventory item {$index}, count {$countIndex}";
                    $results['valid'] = false;
                }
            }
        }
    }

    /**
     * Validate rate-specific XML elements
     */
    protected function validateRateXmlElements(DOMXPath $xpath, array &$results): void
    {
        // Check for required RateAmountMessages element
        $rateMessages = $xpath->query('//ota:RateAmountMessages');
        if ($rateMessages->length === 0) {
            $results['valid'] = false;
            $results['errors'][] = 'Missing required RateAmountMessages element';
            return;
        }

        // Validate HotelCode attribute
        $hotelCode = $rateMessages->item(0)->attributes['HotelCode']->value ?? null;
        if (empty($hotelCode)) {
            $results['valid'] = false;
            $results['errors'][] = 'Missing HotelCode attribute in RateAmountMessages element';
        }

        // Check for RateAmountMessage elements
        $rateItems = $xpath->query('//ota:RateAmountMessage');
        if ($rateItems->length === 0) {
            $results['valid'] = false;
            $results['errors'][] = 'At least one RateAmountMessage element is required';
        }

        // Validate each rate item
        foreach ($rateItems as $index => $rateItem) {
            // Validate StatusApplicationControl
            $statusControl = $xpath->query('.//ota:StatusApplicationControl', $rateItem);
            if ($statusControl->length === 0) {
                $results['errors'][] = "Missing StatusApplicationControl in rate item {$index}";
                $results['valid'] = false;
                continue;
            }

            $ratePlanCode = $statusControl->item(0)->attributes['RatePlanCode'] ?? null;
            $invTypeCode = $statusControl->item(0)->attributes['InvTypeCode'] ?? null;

            if (empty($ratePlanCode)) {
                $results['errors'][] = "Missing RatePlanCode in rate item {$index}";
                $results['valid'] = false;
            }

            if (empty($invTypeCode)) {
                $results['errors'][] = "Missing InvTypeCode (room type) in rate item {$index}";
                $results['valid'] = false;
            }

            // Validate Rates element
            $rates = $xpath->query('.//ota:Rates/ota:Rate', $rateItem);
            if ($rates->length === 0) {
                $results['errors'][] = "Missing Rate elements in rate item {$index}";
                $results['valid'] = false;
            }

            // Validate BaseByGuestAmts
            foreach ($rates as $rateIndex => $rate) {
                $baseAmounts = $xpath->query('.//ota:BaseByGuestAmts/ota:BaseByGuestAmt', $rate);
                if ($baseAmounts->length === 0) {
                    $results['errors'][] = "Missing BaseByGuestAmt elements in rate {$index}.{$rateIndex}";
                    $results['valid'] = false;
                }

                // Check for required guest counts (1 and 2 adults minimum)
                $guestCounts = [];
                foreach ($baseAmounts as $baseAmount) {
                    $guestCounts[] = (int)$baseAmount->attributes['NumberOfGuests']->value ?? 0;
                }

                if (!in_array(1, $guestCounts)) {
                    $results['errors'][] = "Missing rate for 1 adult in rate {$index}.{$rateIndex}";
                    $results['valid'] = false;
                }

                if (!in_array(2, $guestCounts)) {
                    $results['errors'][] = "Missing rate for 2 adults in rate {$index}.{$rateIndex}";
                    $results['valid'] = false;
                }
            }
        }
    }

    /**
     * Validate reservation-specific XML elements
     */
    protected function validateReservationXmlElements(DOMXPath $xpath, array &$results): void
    {
        // Check for required HotelReservations element
        $reservations = $xpath->query('//ota:HotelReservations');
        if ($reservations->length === 0) {
            $results['valid'] = false;
            $results['errors'][] = 'Missing required HotelReservations element';
            return;
        }

        // Check for HotelReservation elements
        $reservationItems = $xpath->query('//ota:HotelReservation');
        if ($reservationItems->length === 0) {
            $results['valid'] = false;
            $results['errors'][] = 'At least one HotelReservation element is required';
        }

        // Validate each reservation
        foreach ($reservationItems as $index => $reservation) {
            // Validate UniqueID
            $uniqueIds = $xpath->query('.//ota:UniqueID[@Type="14"]', $reservation);
            if ($uniqueIds->length === 0) {
                $results['errors'][] = "Missing UniqueID with Type='14' in reservation {$index}";
                $results['valid'] = false;
            }

            // Validate RoomStays
            $roomStays = $xpath->query('.//ota:RoomStays/ota:RoomStay', $reservation);
            if ($roomStays->length === 0) {
                $results['errors'][] = "Missing RoomStay elements in reservation {$index}";
                $results['valid'] = false;
            }

            // Validate ResGuests
            $resGuests = $xpath->query('.//ota:ResGuests/ota:ResGuest', $reservation);
            if ($resGuests->length === 0) {
                $results['errors'][] = "Missing ResGuest elements in reservation {$index}";
                $results['valid'] = false;
            }

            // Validate guest profiles
            foreach ($resGuests as $guestIndex => $resGuest) {
                $profiles = $xpath->query('.//ota:Profiles/ota:ProfileInfo/ota:Profile', $resGuest);
                if ($profiles->length === 0) {
                    $results['errors'][] = "Missing Profile in ResGuest {$index}.{$guestIndex}";
                    $results['valid'] = false;
                    continue;
                }

                // Validate customer profile
                $customer = $xpath->query('.//ota:Customer/ota:PersonName', $profiles->item(0));
                if ($customer->length === 0) {
                    $results['errors'][] = "Missing Customer/PersonName in ResGuest {$index}.{$guestIndex}";
                    $results['valid'] = false;
                }
            }

            // Validate room stay details
            foreach ($roomStays as $stayIndex => $roomStay) {
                $roomTypes = $xpath->query('.//ota:RoomTypes/ota:RoomType', $roomStay);
                if ($roomTypes->length === 0) {
                    $results['errors'][] = "Missing RoomType in RoomStay {$index}.{$stayIndex}";
                    $results['valid'] = false;
                }

                $ratePlans = $xpath->query('.//ota:RatePlans/ota:RatePlan', $roomStay);
                if ($ratePlans->length === 0) {
                    $results['errors'][] = "Missing RatePlan in RoomStay {$index}.{$stayIndex}";
                    $results['valid'] = false;
                }

                $timeSpan = $xpath->query('.//ota:TimeSpan', $roomStay);
                if ($timeSpan->length === 0) {
                    $results['errors'][] = "Missing TimeSpan in RoomStay {$index}.{$stayIndex}";
                    $results['valid'] = false;
                }
            }
        }
    }

    /**
     * Validate block-specific XML elements
     */
    protected function validateBlockXmlElements(DOMXPath $xpath, array &$results): void
    {
        // Check for required InvBlocks element
        $invBlocks = $xpath->query('//ota:InvBlocks');
        if ($invBlocks->length === 0) {
            $results['valid'] = false;
            $results['errors'][] = 'Missing required InvBlocks element';
            return;
        }

        // Check for InvBlock elements
        $blockItems = $xpath->query('//ota:InvBlock');
        if ($blockItems->length === 0) {
            $results['valid'] = false;
            $results['errors'][] = 'At least one InvBlock element is required';
        }

        // Validate each block
        foreach ($blockItems as $index => $block) {
            // Validate required attributes
            $blockCode = $block->attributes['InvBlockCode']->value ?? null;
            $blockName = $block->attributes['InvBlockLongName']->value ?? null;
            $statusCode = $block->attributes['InvBlockStatusCode']->value ?? null;

            if (empty($blockCode)) {
                $results['errors'][] = "Missing InvBlockCode in block {$index}";
                $results['valid'] = false;
            }

            if (empty($blockName)) {
                $results['errors'][] = "Missing InvBlockLongName in block {$index}";
                $results['valid'] = false;
            }

            if (empty($statusCode)) {
                $results['errors'][] = "Missing InvBlockStatusCode in block {$index}";
                $results['valid'] = false;
            }

            // Validate HotelRef
            $hotelRef = $xpath->query('.//ota:HotelRef', $block);
            if ($hotelRef->length === 0) {
                $results['errors'][] = "Missing HotelRef in block {$index}";
                $results['valid'] = false;
            } else {
                $hotelCode = $hotelRef->item(0)->attributes['HotelCode']->value ?? null;
                if (empty($hotelCode)) {
                    $results['errors'][] = "Missing HotelCode in HotelRef for block {$index}";
                    $results['valid'] = false;
                }
            }

            // Validate InvBlockDates
            $blockDates = $xpath->query('.//ota:InvBlockDates', $block);
            if ($blockDates->length === 0) {
                $results['errors'][] = "Missing InvBlockDates in block {$index}";
                $results['valid'] = false;
            }

            // Validate RoomTypes
            $roomTypes = $xpath->query('.//ota:RoomTypes/ota:RoomType', $block);
            if ($roomTypes->length === 0) {
                $results['errors'][] = "Missing RoomType elements in block {$index}";
                $results['valid'] = false;
            }

            // Validate room type allocations
            foreach ($roomTypes as $roomIndex => $roomType) {
                $allocations = $xpath->query('.//ota:RoomTypeAllocations', $roomType);
                if ($allocations->length === 0) {
                    $results['errors'][] = "Missing RoomTypeAllocations in block {$index}, room type {$roomIndex}";
                    $results['valid'] = false;
                }

                // Check for required pickup statuses (1, 2, 3)
                $requiredStatuses = [1, 2, 3];
                foreach ($requiredStatuses as $status) {
                    $statusAllocation = $xpath->query(
                        ".//ota:RoomTypeAllocations[@RoomTypePickUpStatus='{$status}']",
                        $roomType
                    );
                    if ($statusAllocation->length === 0) {
                        $results['errors'][] = "Missing RoomTypePickUpStatus={$status} in block {$index}, room type {$roomIndex}";
                        $results['valid'] = false;
                    }
                }
            }

            // Validate Contacts
            $contacts = $xpath->query('.//ota:Contacts/ota:Contact', $block);
            if ($contacts->length === 0) {
                $results['warnings'][] = "No contact information in block {$index}";
            }
        }
    }

    /**
     * Validate guest information
     */
    protected function validateGuestInformation(array $reservationData, array &$results): void
    {
        $guestInfo = $reservationData['guest_info'] ?? [];

        // Required guest fields
        $requiredFields = ['first_name', 'last_name'];
        foreach ($requiredFields as $field) {
            if (empty($guestInfo[$field])) {
                $results['valid'] = false;
                $results['errors'][] = "Missing required guest field: {$field}";
            }
        }

        // Validate email format if provided
        if (!empty($guestInfo['email']) && !filter_var($guestInfo['email'], FILTER_VALIDATE_EMAIL)) {
            $results['valid'] = false;
            $results['errors'][] = 'Invalid email format in guest information';
        }

        // Validate phone number format if provided
        if (!empty($guestInfo['phone'])) {
            // Basic phone validation (more comprehensive could be added)
            if (!preg_match('/^[\+]?[\d\s\-\(\)]+$/', $guestInfo['phone'])) {
                $results['warnings'][] = 'Phone number format may not be valid';
            }
        }

        // Validate address if provided
        if (!empty($guestInfo['address'])) {
            $this->validateAddress($guestInfo['address'], $results);
        }
    }

    /**
     * Validate room stays
     */
    protected function validateRoomStays(array $reservationData, $propertyConfig, array &$results): void
    {
        $roomStays = $reservationData['room_stays'] ?? [];

        if (empty($roomStays)) {
            $results['valid'] = false;
            $results['errors'][] = 'At least one room stay is required';
            return;
        }

        foreach ($roomStays as $index => $roomStay) {
            // Validate required fields
            $requiredFields = ['room_type_code', 'rate_plan_code', 'arrival_date', 'departure_date'];
            foreach ($requiredFields as $field) {
                if (empty($roomStay[$field])) {
                    $results['valid'] = false;
                    $results['errors'][] = "Missing required field '{$field}' in room stay {$index}";
                }
            }

            // Validate dates
            if (!empty($roomStay['arrival_date']) && !empty($roomStay['departure_date'])) {
                $dateValidation = $this->validateDateRange(
                    $roomStay['arrival_date'],
                    $roomStay['departure_date']
                );

                if (!$dateValidation['valid']) {
                    $results['valid'] = false;
                    foreach ($dateValidation['errors'] as $error) {
                        $results['errors'][] = "Room stay {$index}: {$error}";
                    }
                }
            }

            // Validate guest counts
            $adults = $roomStay['adults'] ?? 1;
            $children = $roomStay['children'] ?? 0;

            if ($adults < 1) {
                $results['valid'] = false;
                $results['errors'][] = "Room stay {$index}: At least 1 adult is required";
            }

            if ($children < 0) {
                $results['valid'] = false;
                $results['errors'][] = "Room stay {$index}: Children count cannot be negative";
            }

            // Validate total rate if provided
            if (isset($roomStay['total_amount']) && $roomStay['total_amount'] < 0) {
                $results['valid'] = false;
                $results['errors'][] = "Room stay {$index}: Total amount cannot be negative";
            }
        }
    }

    /**
     * Validate travel agency reservation specific data
     */
    protected function validateTravelAgencyReservation(array $reservationData, array &$results): void
    {
        $travelAgency = $reservationData['travel_agency'] ?? [];

        if (empty($travelAgency)) {
            $results['valid'] = false;
            $results['errors'][] = 'Travel agency information is required for travel agency reservations';
            return;
        }

        // Required fields
        $requiredFields = ['iata_number', 'name'];
        foreach ($requiredFields as $field) {
            if (empty($travelAgency[$field])) {
                $results['valid'] = false;
                $results['errors'][] = "Missing required travel agency field: {$field}";
            }
        }

        // Validate IATA number format
        if (!empty($travelAgency['iata_number'])) {
            if (!preg_match('/^[0-9]{8}$/', $travelAgency['iata_number'])) {
                $results['warnings'][] = 'IATA number should be 8 digits';
            }
        }

        // Validate commission if provided
        if (isset($travelAgency['commission_rate'])) {
            $commissionRate = (float)$travelAgency['commission_rate'];
            if ($commissionRate < 0 || $commissionRate > 100) {
                $results['valid'] = false;
                $results['errors'][] = 'Travel agency commission rate must be between 0 and 100';
            }
        }
    }

    /**
     * Validate corporate reservation specific data
     */
    protected function validateCorporateReservation(array $reservationData, array &$results): void
    {
        $corporateInfo = $reservationData['corporate_info'] ?? [];

        if (empty($corporateInfo)) {
            $results['valid'] = false;
            $results['errors'][] = 'Corporate information is required for corporate reservations';
            return;
        }

        // Required fields
        $requiredFields = ['company_code', 'company_name'];
        foreach ($requiredFields as $field) {
            if (empty($corporateInfo[$field])) {
                $results['valid'] = false;
                $results['errors'][] = "Missing required corporate field: {$field}";
            }
        }

        // Validate company code format
        if (!empty($corporateInfo['company_code'])) {
            if (strlen($corporateInfo['company_code']) > 20) {
                $results['valid'] = false;
                $results['errors'][] = 'Company code cannot exceed 20 characters';
            }
        }

        // Validate employee information if provided
        if (!empty($corporateInfo['employee_info'])) {
            $this->validateEmployeeInfo($corporateInfo['employee_info'], $results);
        }
    }

    /**
     * Validate group reservation specific data
     */
    protected function validateGroupReservation(array $reservationData, array &$results): void
    {
        if (empty($reservationData['group_code'])) {
            $results['valid'] = false;
            $results['errors'][] = 'Group code is required for group reservations';
            return;
        }

        $groupCode = $reservationData['group_code'];
        if (strlen($groupCode) > 20) {
            $results['valid'] = false;
            $results['errors'][] = 'Group code cannot exceed 20 characters';
        }

        // Validate that group block exists (this would typically involve checking against the group block data)
        if (!empty($reservationData['validate_group_block']) && $reservationData['validate_group_block']) {
            // This is a placeholder for actual group block validation
            // In a real implementation, you'd check if the group block exists and has available inventory
            $results['warnings'][] = 'Group block validation should be performed separately';
        }
    }

    /**
     * Validate package reservation specific data
     */
    protected function validatePackageReservation(array $reservationData, array &$results): void
    {
        if (empty($reservationData['package_code'])) {
            $results['valid'] = false;
            $results['errors'][] = 'Package code is required for package reservations';
            return;
        }

        $packageCode = $reservationData['package_code'];
        if (strlen($packageCode) > 20) {
            $results['valid'] = false;
            $results['errors'][] = 'Package code cannot exceed 20 characters';
        }

        // Validate package components if provided
        if (!empty($reservationData['package_components'])) {
            foreach ($reservationData['package_components'] as $index => $component) {
                if (empty($component['component_code'])) {
                    $results['valid'] = false;
                    $results['errors'][] = "Missing component code in package component {$index}";
                }

                if (isset($component['amount']) && $component['amount'] < 0) {
                    $results['valid'] = false;
                    $results['errors'][] = "Package component {$index} amount cannot be negative";
                }
            }
        }
    }

    /**
     * Validate group room type
     */
    protected function validateGroupRoomType(array $roomType, int $index, array &$results): void
    {
        // Required fields
        $requiredFields = ['room_type_code', 'allocated_rooms', 'available_rooms', 'sold_rooms'];
        foreach ($requiredFields as $field) {
            if (!isset($roomType[$field])) {
                $results['valid'] = false;
                $results['errors'][] = "Missing required field '{$field}' in room type {$index}";
            }
        }

        // Validate room counts
        $allocated = $roomType['allocated_rooms'] ?? 0;
        $available = $roomType['available_rooms'] ?? 0;
        $sold = $roomType['sold_rooms'] ?? 0;

        if ($allocated < 0 || $available < 0 || $sold < 0) {
            $results['valid'] = false;
            $results['errors'][] = "Room counts cannot be negative in room type {$index}";
        }

        // Validate allocation logic
        if ($allocated > 0 && ($available + $sold) > $allocated) {
            $results['valid'] = false;
            $results['errors'][] = "Available + Sold rooms cannot exceed Allocated rooms in room type {$index}";
        }

        // Validate rate plans if provided
        if (!empty($roomType['rate_plans'])) {
            foreach ($roomType['rate_plans'] as $rpIndex => $ratePlan) {
                if (empty($ratePlan['rate_plan_code'])) {
                    $results['valid'] = false;
                    $results['errors'][] = "Missing rate plan code in room type {$index}, rate plan {$rpIndex}";
                }

                if (isset($ratePlan['base_amount']) && $ratePlan['base_amount'] < 0) {
                    $results['valid'] = false;
                    $results['errors'][] = "Rate amount cannot be negative in room type {$index}, rate plan {$rpIndex}";
                }
            }
        }
    }

    /**
     * Validate group contact information
     */
    protected function validateGroupContactInfo(array $contactInfo, array &$results): void
    {
        if (empty($contactInfo)) {
            $results['warnings'][] = 'Group contact information is recommended';
            return;
        }

        // Required fields for contact
        $requiredFields = ['contact_type', 'first_name', 'last_name'];
        foreach ($requiredFields as $field) {
            if (empty($contactInfo[$field])) {
                $results['valid'] = false;
                $results['errors'][] = "Missing required contact field: {$field}";
            }
        }

        // Validate contact type
        $validContactTypes = ['GroupOrganizer', 'GroupCompany', 'BillingContact'];
        if (!empty($contactInfo['contact_type']) && !in_array($contactInfo['contact_type'], $validContactTypes)) {
            $results['warnings'][] = "Unusual contact type: {$contactInfo['contact_type']}";
        }

        // Validate email if provided
        if (!empty($contactInfo['email']) && !filter_var($contactInfo['email'], FILTER_VALIDATE_EMAIL)) {
            $results['valid'] = false;
            $results['errors'][] = 'Invalid email format in group contact information';
        }

        // Validate company information if contact type is company-related
        if (in_array($contactInfo['contact_type'] ?? '', ['GroupCompany', 'BillingContact'])) {
            if (empty($contactInfo['company_name'])) {
                $results['warnings'][] = 'Company name is recommended for company-type contacts';
            }
        }
    }

    /**
     * Validate inventory business logic
     */
    protected function validateInventoryBusinessLogic(array $data, string $operationType, array &$results): void
    {
        // Validate operation type
        $validOperations = ['create', 'update', 'delete'];
        if (!in_array($operationType, $validOperations)) {
            $results['valid'] = false;
            $results['errors'][] = "Invalid operation type: {$operationType}";
            return;
        }

        foreach ($data['inventories'] ?? [] as $index => $inventory) {
            // Check for reasonable inventory counts
            foreach ($inventory['counts'] ?? [] as $count) {
                $countValue = $count['count'] ?? 0;
                $countType = $count['count_type'] ?? null;

                // Warn about extremely high counts
                if ($countValue > 10000) {
                    $results['warnings'][] = "Very high inventory count ({$countValue}) in item {$index}";
                }

                // Business logic: Sold rooms shouldn't exceed allocated rooms
                if ($countType === CountType::DEFINITE_SOLD->value) {
                    $physicalRooms = collect($inventory['counts'])
                        ->firstWhere('count_type', CountType::PHYSICAL->value)['count'] ?? 0;

                    if ($physicalRooms > 0 && $countValue > ($physicalRooms * 1.1)) {
                        $results['warnings'][] = "Sold rooms ({$countValue}) significantly exceed physical rooms ({$physicalRooms}) in item {$index}";
                    }
                }
            }

            // Validate date range business logic
            if (isset($inventory['start_date'], $inventory['end_date'])) {
                $start = Carbon::parse($inventory['start_date']);
                $end = Carbon::parse($inventory['end_date']);

                // Warn about very long date ranges
                if ($start->diffInDays($end) > 365) {
                    $results['warnings'][] = "Very long date range ({$start->diffInDays($end)} days) in item {$index}";
                }

                // Warn about past dates
                if ($start->isPast() && $operationType === 'create') {
                    $results['warnings'][] = "Setting inventory for past dates in item {$index}";
                }
            }
        }
    }

    /**
     * Validate rate business logic
     */
    protected function validateRateBusinessLogic(array $data, string $operationType, array &$results): void
    {
        foreach ($data['rates'] ?? [] as $index => $rate) {
            // Validate rate amounts
            $baseAmount = $rate['base_amount'] ?? 0;

            // Check for unreasonably low or high rates
            if ($baseAmount < 1 && $baseAmount > 0) {
                $results['warnings'][] = "Very low rate amount (${baseAmount}) in item {$index}";
            }

            if ($baseAmount > 100000) {
                $results['warnings'][] = "Very high rate amount (${baseAmount}) in item {$index}";
            }

            // Validate guest amounts business logic
            if (!empty($rate['guest_amounts'])) {
                $this->validateRateGuestAmountsLogic($rate['guest_amounts'], $index, $results);
            }

            // Validate seasonal/dynamic rate logic
            if (isset($rate['start_date'], $rate['end_date'])) {
                $start = Carbon::parse($rate['start_date']);
                $end = Carbon::parse($rate['end_date']);

                // Warn about very short rate periods
                if ($start->diffInDays($end) < 1) {
                    $results['warnings'][] = "Very short rate period in item {$index}";
                }

                // Warn about rate changes in the past
                if ($start->isPast() && $operationType === 'create') {
                    $results['warnings'][] = "Setting rates for past dates in item {$index}";
                }
            }

            // Validate derived rates if applicable
            if (!empty($rate['derived_rate_info'])) {
                $this->validateDerivedRateLogic($rate['derived_rate_info'], $index, $results);
            }
        }
    }

    /**
     * Validate reservation business logic
     */
    protected function validateReservationBusinessLogic(array $data, string $operationType, array &$results): void
    {
        // Validate reservation status
        $status = $data['status'] ?? 'confirmed';
        $validStatuses = ['confirmed', 'tentative', 'cancelled', 'no-show'];
        if (!in_array($status, $validStatuses)) {
            $results['warnings'][] = "Unusual reservation status: {$status}";
        }

        // Validate business rules for room stays
        foreach ($data['room_stays'] ?? [] as $index => $roomStay) {
            $arrival = Carbon::parse($roomStay['arrival_date'] ?? now());
            $departure = Carbon::parse($roomStay['departure_date'] ?? now()->addDay());

            // Check minimum stay requirements
            $lengthOfStay = $arrival->diffInDays($departure);
            if ($lengthOfStay < 1) {
                $results['valid'] = false;
                $results['errors'][] = "Room stay {$index}: Minimum stay is 1 night";
            }

            // Warn about very long stays
            if ($lengthOfStay > 365) {
                $results['warnings'][] = "Room stay {$index}: Very long stay ({$lengthOfStay} nights)";
            }

            // Validate guest capacity vs room type
            $totalGuests = ($roomStay['adults'] ?? 1) + ($roomStay['children'] ?? 0);
            if ($totalGuests > 10) {
                $results['warnings'][] = "Room stay {$index}: High guest count ({$totalGuests})";
            }

            // Business rule: Check arrival/departure days if restrictions apply
            $arrivalDay = $arrival->dayOfWeek;
            $departureDay = $departure->dayOfWeek;

            // This would typically check against property-specific rules
            // For now, we'll add a general warning about weekend arrivals
            if (in_array($arrivalDay, [Carbon::FRIDAY, Carbon::SATURDAY]) && $lengthOfStay < 2) {
                $results['warnings'][] = "Room stay {$index}: Weekend arrival with short stay";
            }
        }

        // Validate payment information if provided
        if (!empty($data['payment_info'])) {
            $this->validatePaymentBusinessLogic($data['payment_info'], $results);
        }

        // Validate special requests/services
        if (!empty($data['special_requests'])) {
            foreach ($data['special_requests'] as $request) {
                if (empty($request['request_type']) || empty($request['description'])) {
                    $results['warnings'][] = 'Special requests should include type and description';
                }
            }
        }
    }

    /**
     * Validate group block business logic
     */
    protected function validateGroupBlockBusinessLogic(array $data, string $operationType, array &$results): void
    {
        $startDate = Carbon::parse($data['start_date'] ?? now());
        $endDate = Carbon::parse($data['end_date'] ?? now()->addDays(7));
        $cutoffDate = Carbon::parse($data['cutoff_date'] ?? now()->subDays(7));

        // Validate cutoff date business logic
        if ($cutoffDate->isAfter($startDate)) {
            $results['valid'] = false;
            $results['errors'][] = 'Cutoff date must be before or equal to start date';
        }

        // Validate reasonable advance booking
        $advanceBooking = now()->diffInDays($startDate);
        if ($advanceBooking < 1 && $operationType === 'create') {
            $results['warnings'][] = 'Creating group block for immediate dates';
        }

        if ($advanceBooking > 730) {
            $results['warnings'][] = "Group block created very far in advance ({$advanceBooking} days)";
        }

        // Validate group size logic
        $totalAllocated = 0;
        foreach ($data['room_types'] ?? [] as $roomType) {
            $allocated = $roomType['allocated_rooms'] ?? 0;
            $totalAllocated += $allocated;
        }

        if ($totalAllocated < 5) {
            $results['warnings'][] = 'Small group allocation (less than 5 rooms)';
        }

        if ($totalAllocated > 1000) {
            $results['warnings'][] = "Very large group allocation ({$totalAllocated} rooms)";
        }

        // Validate rate plans in group
        foreach ($data['room_types'] ?? [] as $index => $roomType) {
            if (!empty($roomType['rate_plans'])) {
                foreach ($roomType['rate_plans'] as $rpIndex => $ratePlan) {
                    // Check for reasonable group rates
                    $baseAmount = $ratePlan['base_amount'] ?? 0;
                    if ($baseAmount > 0) {
                        // Group rates are typically lower than rack rates
                        // This would normally check against actual rate data
                        $this->validateGroupRateLogic($ratePlan, $index, $rpIndex, $results);
                    }
                }
            }
        }

        // Validate pickup patterns if historical data is available
        if ($operationType === 'update' && !empty($data['pickup_history'])) {
            $this->validateGroupPickupLogic($data['pickup_history'], $results);
        }
    }

    /**
     * Validate security header
     */
    protected function validateSecurityHeader(array $security, array &$results): void
    {
        // Check for UsernameToken
        if (empty($security['UsernameToken'])) {
            $results['valid'] = false;
            $results['errors'][] = 'Security header missing UsernameToken';
            return;
        }

        $usernameToken = $security['UsernameToken'];

        // Validate username
        if (empty($usernameToken['Username'])) {
            $results['valid'] = false;
            $results['errors'][] = 'Security header missing Username';
        }

        // Validate password
        if (empty($usernameToken['Password'])) {
            $results['valid'] = false;
            $results['errors'][] = 'Security header missing Password';
        }

        // Check password type if specified
        if (!empty($usernameToken['Password']['Type'])) {
            $validTypes = [
                'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText',
                'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest'
            ];

            if (!in_array($usernameToken['Password']['Type'], $validTypes)) {
                $results['warnings'][] = 'Unusual password type in security header';
            }
        }

        // Check for timestamp if present
        if (!empty($security['Timestamp'])) {
            $this->validateSecurityTimestamp($security['Timestamp'], $results);
        }
    }

    /**
     * Check if message ID is valid
     */
    protected function isValidMessageId(string $messageId): bool
    {
        // Message ID should be unique and follow a reasonable format
        // This is a basic validation - in production you might check against a more specific pattern
        if (strlen($messageId) < 1 || strlen($messageId) > 255) {
            return false;
        }

        // Check for basic format (alphanumeric, hyphens, underscores)
        return preg_match('/^[a-zA-Z0-9\-_]+$/', $messageId);
    }

    /**
     * Check if message type is compatible with DTO
     */
    protected function isMessageTypeCompatible(SoapRequestDto|SoapResponseDto $message, MessageType $messageType): bool
    {
        // Check if the message body contains elements compatible with the message type
        if (empty($message->xmlBody)) {
            return true; // Can't validate without body
        }

        $expectedElement = match ($messageType) {
            MessageType::INVENTORY => 'OTA_HotelInvCountNotifRQ',
            MessageType::RATES => 'OTA_HotelRateNotifRQ',
            MessageType::RESERVATION => 'OTA_HotelResNotifRQ',
            MessageType::RESTRICTIONS => 'OTA_HotelAvailNotifRQ',
            MessageType::GROUP_BLOCK => 'OTA_HotelInvBlockNotifRQ',
            MessageType::RESPONSE => ['OTA_HotelResNotifRS', 'OTA_HotelInvCountNotifRS'],
            default => null,
        };

        if ($expectedElement === null) {
            return true;
        }

        if (is_array($expectedElement)) {
            foreach ($expectedElement as $element) {
                if (strpos($message->xmlBody, $element) !== false) {
                    return true;
                }
            }
            return false;
        }

        return strpos($message->xmlBody, $expectedElement) !== false;
    }

    /**
     * Validate required message properties
     */
    protected function validateRequiredMessageProperties(SoapRequestDto|SoapResponseDto $message, array &$results): void
    {
        if ($message instanceof SoapRequestDto) {
            // Required properties for request
            if (empty($message->messageId)) {
                $results['warnings'][] = 'Message ID is recommended for tracking';
            }

            if (empty($message->hotelCode)) {
                $results['valid'] = false;
                $results['errors'][] = 'Hotel code is required';
            }
        }

        // Validate timestamp if present
        if (!empty($message->timestamp)) {
            try {
                $timestamp = Carbon::parse($message->timestamp);
                $now = Carbon::now();

                // Check if timestamp is too far in the past or future
                if ($timestamp->diffInMinutes($now) > 60) {
                    $results['warnings'][] = 'Message timestamp differs significantly from current time';
                }
            } catch (\Exception $e) {
                $results['valid'] = false;
                $results['errors'][] = 'Invalid timestamp format';
            }
        }
    }

    /**
     * Get schema type from message type
     */
    protected function getSchemaTypeFromMessageType(MessageType $messageType): string
    {
        return match ($messageType) {
            MessageType::INVENTORY => 'inventory',
            MessageType::RATES => 'rate',
            MessageType::RESERVATION => 'reservation',
            MessageType::RESTRICTIONS => 'restriction',
            MessageType::GROUP_BLOCK => 'block',
            default => 'unknown',
        };
    }

    /**
     * Check if room type is valid for property
     */
    protected function isValidRoomType(string $roomType, array $propertyConfig): bool
    {
        // Check against property configuration
        $roomTypes = $propertyConfig['room_types'] ?? [];

        foreach ($roomTypes as $configRoomType) {
            if (isset($configRoomType['code']) && $configRoomType['code'] === $roomType) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if currency code is valid
     */
    protected function isValidCurrencyCode(string $currencyCode): bool
    {
        // ISO 4217 currency codes are 3 characters
        if (strlen($currencyCode) !== 3) {
            return false;
        }

        // List of common currency codes
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
            'NZD',
            'MXN',
            'SGD',
            'HKD',
            'NOK',
            'TRY',
            'RUB',
            'INR',
            'BRL',
            'ZAR',
            'KRW',
            'DKK',
            'PLN',
            'TWD',
            'THB',
            'MYR',
            'CZK',
            'HUF',
            'ILS',
            'CLP',
            'PHP',
            'AED',
            'COP',
            'SAR',
            'QAR',
            'KWD',
            'BHD',
            'EGP',
            'JOD',
            'LBP',
            'MAD'
        ];

        return in_array(strtoupper($currencyCode), $validCurrencies);
    }

    /**
     * Check if rate plan is valid for property
     */
    protected function isValidRatePlan(string $ratePlan, array $propertyConfig): bool
    {
        // Check against property configuration
        $ratePlans = $propertyConfig['rate_plans'] ?? [];

        foreach ($ratePlans as $configRatePlan) {
            if (isset($configRatePlan['code']) && $configRatePlan['code'] === $ratePlan) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get required fields for message type
     */
    protected function getRequiredFieldsForMessageType(MessageType $messageType): array
    {
        return match ($messageType) {
            MessageType::INVENTORY => [
                'hotel_code',
                'inventories',
                'inventories.*.room_type_code',
                'inventories.*.counts',
                'inventories.*.counts.*.count_type',
                'inventories.*.counts.*.count'
            ],
            MessageType::RATES => [
                'hotel_code',
                'rates',
                'rates.*.rate_plan_code',
                'rates.*.room_type_code',
                'rates.*.base_amount',
                'rates.*.currency_code'
            ],
            MessageType::RESERVATION => [
                'hotel_code',
                'reservation_id',
                'guest_info.first_name',
                'guest_info.last_name',
                'room_stays',
                'room_stays.*.room_type_code',
                'room_stays.*.arrival_date',
                'room_stays.*.departure_date'
            ],
            MessageType::GROUP_BLOCK => [
                'hotel_code',
                'block_code',
                'block_name',
                'start_date',
                'end_date',
                'room_types'
            ],
            default => ['hotel_code'],
        };
    }

    /**
     * Get inventory validation rules
     */
    protected function getInventoryValidationRules(string $operation): array
    {
        $baseRules = [
            'hotel_code' => 'required|string|max:20',
            'inventories' => 'required|array|min:1',
            'inventories.*.room_type_code' => 'required|string|max:10',
            'inventories.*.start_date' => 'required|date',
            'inventories.*.end_date' => 'required|date|after_or_equal:inventories.*.start_date',
            'inventories.*.counts' => 'required|array|min:1',
            'inventories.*.counts.*.count_type' => 'required|integer|in:1,2,4,5,6,99',
            'inventories.*.counts.*.count' => 'required|integer|min:0'
        ];

        if ($operation === 'update') {
            $baseRules['message_id'] = 'required|string';
        }

        return $baseRules;
    }

    /**
     * Get rate validation rules
     */
    protected function getRateValidationRules(string $operation): array
    {
        $baseRules = [
            'hotel_code' => 'required|string|max:20',
            'rates' => 'required|array|min:1',
            'rates.*.rate_plan_code' => 'required|string|max:20',
            'rates.*.room_type_code' => 'required|string|max:10',
            'rates.*.start_date' => 'required|date',
            'rates.*.end_date' => 'required|date|after_or_equal:rates.*.start_date',
            'rates.*.base_amount' => 'required|numeric|min:0',
            'rates.*.currency_code' => 'required|string|size:3'
        ];

        if ($operation === 'update') {
            $baseRules['message_id'] = 'required|string';
        }

        return $baseRules;
    }

    /**
     * Get group block validation rules
     */
    protected function getGroupBlockValidationRules(string $operation): array
    {
        return [
            'hotel_code' => 'required|string|max:20',
            'block_code' => 'required|string|max:20',
            'block_name' => 'required|string|max:100',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'cutoff_date' => 'required|date|before_or_equal:start_date',
            'room_types' => 'required|array|min:1',
            'room_types.*.room_type_code' => 'required|string|max:10',
            'room_types.*.allocated_rooms' => 'required|integer|min:1',
            'room_types.*.available_rooms' => 'required|integer|min:0',
            'room_types.*.sold_rooms' => 'required|integer|min:0'
        ];
    }

    /**
     * Validate property inventory rules
     */
    protected function validatePropertyInventoryRules(array $data, $propertyConfig, array &$results): void
    {
        $config = $propertyConfig->toArray();

        // Check inventory limits
        if (!empty($config['inventory_limits'])) {
            foreach ($data['inventories'] ?? [] as $index => $inventory) {
                $roomType = $inventory['room_type_code'] ?? '';
                $roomTypeConfig = collect($config['room_types'] ?? [])
                    ->firstWhere('code', $roomType);

                if ($roomTypeConfig) {
                    $maxInventory = $roomTypeConfig['max_inventory'] ?? null;
                    if ($maxInventory !== null) {
                        foreach ($inventory['counts'] ?? [] as $count) {
                            if (($count['count'] ?? 0) > $maxInventory) {
                                $results['warnings'][] = "Inventory count exceeds maximum for room type {$roomType} in item {$index}";
                            }
                        }
                    }
                }
            }
        }

        // Check inventory method restrictions
        if (!empty($config['allowed_inventory_methods'])) {
            $allowedMethods = $config['allowed_inventory_methods'];
            foreach ($data['inventories'] ?? [] as $index => $inventory) {
                $method = $this->determineInventoryMethod($inventory['counts'] ?? []);
                if (!in_array($method, $allowedMethods)) {
                    $results['valid'] = false;
                    $results['errors'][] = "Inventory method '{$method}' not allowed for this property in item {$index}";
                }
            }
        }
    }

    /**
     * Validate property rate rules
     */
    protected function validatePropertyRateRules(array $data, $propertyConfig, array &$results): void
    {
        $config = $propertyConfig->toArray();

        // Check rate limits
        if (!empty($config['rate_limits'])) {
            $minRate = $config['rate_limits']['min_rate'] ?? 0;
            $maxRate = $config['rate_limits']['max_rate'] ?? null;

            foreach ($data['rates'] ?? [] as $index => $rate) {
                $baseAmount = $rate['base_amount'] ?? 0;

                if ($baseAmount < $minRate) {
                    $results['warnings'][] = "Rate below minimum ({$minRate}) in item {$index}";
                }

                if ($maxRate !== null && $baseAmount > $maxRate) {
                    $results['warnings'][] = "Rate exceeds maximum ({$maxRate}) in item {$index}";
                }
            }
        }

        // Check currency restrictions
        if (!empty($config['allowed_currencies'])) {
            $allowedCurrencies = $config['allowed_currencies'];
            foreach ($data['rates'] ?? [] as $index => $rate) {
                $currency = $rate['currency_code'] ?? '';
                if (!in_array($currency, $allowedCurrencies)) {
                    $results['valid'] = false;
                    $results['errors'][] = "Currency '{$currency}' not allowed for this property in item {$index}";
                }
            }
        }
    }

    /**
     * Validate property reservation rules
     */
    protected function validatePropertyReservationRules(array $data, $propertyConfig, array &$results): void
    {
        $config = $propertyConfig->toArray();

        // Check reservation limits
        if (!empty($config['reservation_limits'])) {
            $maxLengthOfStay = $config['reservation_limits']['max_length_of_stay'] ?? null;
            $minAdvanceBooking = $config['reservation_limits']['min_advance_booking'] ?? 0;

            foreach ($data['room_stays'] ?? [] as $index => $roomStay) {
                if (!empty($roomStay['arrival_date']) && !empty($roomStay['departure_date'])) {
                    $arrival = Carbon::parse($roomStay['arrival_date']);
                    $departure = Carbon::parse($roomStay['departure_date']);
                    $lengthOfStay = $arrival->diffInDays($departure);

                    // Check max length of stay
                    if ($maxLengthOfStay !== null && $lengthOfStay > $maxLengthOfStay) {
                        $results['valid'] = false;
                        $results['errors'][] = "Length of stay ({$lengthOfStay} nights) exceeds maximum ({$maxLengthOfStay}) in room stay {$index}";
                    }

                    // Check minimum advance booking
                    $daysInAdvance = now()->diffInDays($arrival);
                    if ($daysInAdvance < $minAdvanceBooking) {
                        $results['warnings'][] = "Reservation made with less than required advance booking in room stay {$index}";
                    }
                }
            }
        }

        // Check guest capacity restrictions
        if (!empty($config['guest_limits'])) {
            foreach ($data['room_stays'] ?? [] as $index => $roomStay) {
                $roomType = $roomStay['room_type_code'] ?? '';
                $roomTypeConfig = collect($config['room_types'] ?? [])
                    ->firstWhere('code', $roomType);

                if ($roomTypeConfig) {
                    $maxOccupancy = $roomTypeConfig['max_occupancy'] ?? null;
                    $totalGuests = ($roomStay['adults'] ?? 1) + ($roomStay['children'] ?? 0);

                    if ($maxOccupancy !== null && $totalGuests > $maxOccupancy) {
                        $results['valid'] = false;
                        $results['errors'][] = "Guest count ({$totalGuests}) exceeds room capacity ({$maxOccupancy}) in room stay {$index}";
                    }
                }
            }
        }
    }

    // Helper methods for complex validations

    /**
     * Validate address information
     */
    protected function validateAddress(array $address, array &$results): void
    {
        $requiredFields = ['address_line_1', 'city'];
        foreach ($requiredFields as $field) {
            if (empty($address[$field])) {
                $results['warnings'][] = "Missing recommended address field: {$field}";
            }
        }

        // Validate country code if provided
        if (!empty($address['country_code'])) {
            if (strlen($address['country_code']) !== 2) {
                $results['warnings'][] = 'Country code should be 2 characters (ISO 3166-1 alpha-2)';
            }
        }

        // Validate postal code if provided
        if (!empty($address['postal_code'])) {
            // Basic validation - this could be more sophisticated based on country
            if (strlen($address['postal_code']) > 20) {
                $results['warnings'][] = 'Postal code seems unusually long';
            }
        }
    }

    /**
     * Validate employee information
     */
    protected function validateEmployeeInfo(array $employeeInfo, array &$results): void
    {
        $recommendedFields = ['employee_id', 'job_title'];
        foreach ($recommendedFields as $field) {
            if (empty($employeeInfo[$field])) {
                $results['warnings'][] = "Missing recommended employee field: {$field}";
            }
        }

        // Validate employee ID format if provided
        if (!empty($employeeInfo['employee_id'])) {
            if (strlen($employeeInfo['employee_id']) > 50) {
                $results['warnings'][] = 'Employee ID seems unusually long';
            }
        }
    }

    /**
     * Validate security timestamp
     */
    protected function validateSecurityTimestamp(array $timestamp, array &$results): void
    {
        if (empty($timestamp['Created'])) {
            $results['warnings'][] = 'Security timestamp missing Created element';
            return;
        }

        try {
            $created = Carbon::parse($timestamp['Created']);
            $now = Carbon::now();

            // Check if timestamp is too old (more than 5 minutes)
            if ($created->diffInMinutes($now) > 5) {
                $results['warnings'][] = 'Security timestamp is more than 5 minutes old';
            }

            // Check if timestamp is in the future (clock skew)
            if ($created->isFuture()) {
                if ($created->diffInMinutes($now) > 5) {
                    $results['warnings'][] = 'Security timestamp is significantly in the future';
                }
            }
        } catch (\Exception $e) {
            $results['warnings'][] = 'Invalid security timestamp format';
        }

        // Validate Expires if present
        if (!empty($timestamp['Expires'])) {
            try {
                $expires = Carbon::parse($timestamp['Expires']);
                $created = Carbon::parse($timestamp['Created']);

                if ($expires->isBefore($created)) {
                    $results['valid'] = false;
                    $results['errors'][] = 'Security timestamp expires before it was created';
                }

                if ($expires->isPast()) {
                    $results['valid'] = false;
                    $results['errors'][] = 'Security timestamp has expired';
                }
            } catch (\Exception $e) {
                $results['warnings'][] = 'Invalid security timestamp expires format';
            }
        }
    }

    /**
     * Validate rate guest amounts logic
     */
    protected function validateRateGuestAmountsLogic(array $guestAmounts, int $rateIndex, array &$results): void
    {
        // Sort guest amounts by guest count
        usort($guestAmounts, fn($a, $b) => ($a['guest_count'] ?? 0) - ($b['guest_count'] ?? 0));

        // Check that rates generally increase with guest count
        $lastAmount = 0;
        foreach ($guestAmounts as $guestAmount) {
            $currentAmount = $guestAmount['amount'] ?? 0;
            $guestCount = $guestAmount['guest_count'] ?? 0;

            // Rates should generally not decrease as guest count increases
            if ($currentAmount < $lastAmount && abs($currentAmount - $lastAmount) > 1) {
                $results['warnings'][] = "Rate decreases with guest count in rate {$rateIndex} for {$guestCount} guests";
            }

            $lastAmount = $currentAmount;
        }

        // Check for reasonable single supplement (1 guest vs 2 guest rates)
        $rate1 = collect($guestAmounts)->firstWhere('guest_count', 1)['amount'] ?? 0;
        $rate2 = collect($guestAmounts)->firstWhere('guest_count', 2)['amount'] ?? 0;

        if ($rate1 > 0 && $rate2 > 0 && $rate1 >= $rate2) {
            $results['warnings'][] = "Single rate is not less than double rate in rate {$rateIndex}";
        }
    }

    /**
     * Validate derived rate logic
     */
    protected function validateDerivedRateLogic(array $derivedRateInfo, int $rateIndex, array &$results): void
    {
        $requiredFields = ['base_rate_plan', 'derivation_type', 'derivation_value'];
        foreach ($requiredFields as $field) {
            if (empty($derivedRateInfo[$field])) {
                $results['valid'] = false;
                $results['errors'][] = "Missing required derived rate field '{$field}' in rate {$rateIndex}";
                return;
            }
        }

        $derivationType = $derivedRateInfo['derivation_type'];
        $derivationValue = $derivedRateInfo['derivation_value'];

        // Validate derivation type
        $validTypes = ['percentage', 'amount', 'flat'];
        if (!in_array($derivationType, $validTypes)) {
            $results['valid'] = false;
            $results['errors'][] = "Invalid derivation type '{$derivationType}' in rate {$rateIndex}";
        }

        // Validate derivation value based on type
        switch ($derivationType) {
            case 'percentage':
                if ($derivationValue < -100 || $derivationValue > 100) {
                    $results['warnings'][] = "Unusual percentage derivation ({$derivationValue}%) in rate {$rateIndex}";
                }
                break;
            case 'amount':
                if ($derivationValue < -10000 || $derivationValue > 10000) {
                    $results['warnings'][] = "Unusual amount derivation ({$derivationValue}) in rate {$rateIndex}";
                }
                break;
            case 'flat':
                if ($derivationValue <= 0) {
                    $results['warnings'][] = "Flat rate should be positive in rate {$rateIndex}";
                }
                break;
        }
    }

    /**
     * Validate payment business logic
     */
    protected function validatePaymentBusinessLogic(array $paymentInfo, array &$results): void
    {
        // Validate payment method
        $paymentMethod = $paymentInfo['payment_method'] ?? '';
        $validMethods = ['credit_card', 'debit_card', 'bank_transfer', 'cash', 'check', 'voucher'];

        if (!in_array($paymentMethod, $validMethods)) {
            $results['warnings'][] = "Unusual payment method: {$paymentMethod}";
        }

        // Validate credit card info if present
        if ($paymentMethod === 'credit_card' && !empty($paymentInfo['credit_card'])) {
            $ccInfo = $paymentInfo['credit_card'];

            // Validate card type
            if (empty($ccInfo['card_type'])) {
                $results['warnings'][] = 'Credit card type not specified';
            }

            // Validate expiry date format
            if (!empty($ccInfo['expiry_date'])) {
                if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $ccInfo['expiry_date'])) {
                    $results['warnings'][] = 'Credit card expiry date format should be MM/YY';
                }
            }

            // Check if card number is masked (for security)
            if (!empty($ccInfo['card_number'])) {
                if (!preg_match('/\*+/', $ccInfo['card_number'])) {
                    $results['warnings'][] = 'Credit card number should be masked in transmission';
                }
            }
        }

        // Validate payment amount if provided
        if (isset($paymentInfo['amount'])) {
            $amount = $paymentInfo['amount'];
            if ($amount < 0) {
                $results['valid'] = false;
                $results['errors'][] = 'Payment amount cannot be negative';
            }

            if ($amount > 1000000) {
                $results['warnings'][] = 'Very large payment amount';
            }
        }

        // Validate deposit/guarantee logic
        if (!empty($paymentInfo['is_deposit']) || !empty($paymentInfo['is_guarantee'])) {
            if (empty($paymentInfo['amount']) || $paymentInfo['amount'] <= 0) {
                $results['warnings'][] = 'Deposit/guarantee should have a positive amount';
            }
        }
    }

    /**
     * Validate group rate logic
     */
    protected function validateGroupRateLogic(array $ratePlan, int $roomTypeIndex, int $ratePlanIndex, array &$results): void
    {
        $baseAmount = $ratePlan['base_amount'] ?? 0;

        // Group rates should be reasonable
        if ($baseAmount <= 0) {
            $results['valid'] = false;
            $results['errors'][] = "Group rate must be positive in room type {$roomTypeIndex}, rate plan {$ratePlanIndex}";
        }

        // Check for group discount indicator
        if (!empty($ratePlan['is_group_rate']) && empty($ratePlan['group_discount_info'])) {
            $results['warnings'][] = "Group rate lacks discount information in room type {$roomTypeIndex}, rate plan {$ratePlanIndex}";
        }

        // Validate guest amounts in group rates
        if (!empty($ratePlan['guest_amounts'])) {
            $this->validateRateGuestAmountsLogic(
                $ratePlan['guest_amounts'],
                "{$roomTypeIndex}.{$ratePlanIndex}",
                $results
            );
        }

        // Check for reasonable group pricing
        if (!empty($ratePlan['group_discount_info'])) {
            $discountInfo = $ratePlan['group_discount_info'];
            if (!empty($discountInfo['discount_percentage'])) {
                $discount = $discountInfo['discount_percentage'];
                if ($discount < 0 || $discount > 50) {
                    $results['warnings'][] = "Unusual group discount percentage ({$discount}%) in room type {$roomTypeIndex}";
                }
            }
        }
    }

    /**
     * Validate group pickup logic
     */
    protected function validateGroupPickupLogic(array $pickupHistory, array &$results): void
    {
        // Analyze pickup patterns
        $totalAllocated = $pickupHistory['total_allocated'] ?? 0;
        $totalPickedUp = $pickupHistory['total_picked_up'] ?? 0;
        $pickupPercentage = $totalAllocated > 0 ? ($totalPickedUp / $totalAllocated) * 100 : 0;

        // Warn about unusual pickup patterns
        if ($pickupPercentage < 30) {
            $results['warnings'][] = "Low pickup rate: {$pickupPercentage}% of allocated rooms";
        }

        if ($pickupPercentage > 100) {
            $results['warnings'][] = "Pickup rate exceeds 100%: {$pickupPercentage}%";
        }

        // Check pickup trends if daily data is available
        if (!empty($pickupHistory['daily_pickup'])) {
            $dailyPickup = $pickupHistory['daily_pickup'];

            // Warn about unusual pickup spikes
            $avgPickup = array_sum($dailyPickup) / count($dailyPickup);
            foreach ($dailyPickup as $day => $pickup) {
                if ($pickup > ($avgPickup * 3)) {
                    $results['warnings'][] = "Unusual pickup spike on day {$day}: {$pickup} rooms";
                }
            }
        }

        // Check wash factor if available
        if (!empty($pickupHistory['wash_factor'])) {
            $washFactor = $pickupHistory['wash_factor'];
            if ($washFactor < 0.7 || $washFactor > 1.3) {
                $results['warnings'][] = "Unusual wash factor: {$washFactor}";
            }
        }
    }
}
