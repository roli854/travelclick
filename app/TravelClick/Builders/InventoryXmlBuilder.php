<?php

declare(strict_types=1);

namespace App\TravelClick\Builders;

use App\TravelClick\DTOs\InventoryData;
use App\TravelClick\DTOs\SoapHeaderDto;
use App\TravelClick\Enums\CountType;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Support\XmlNamespaces;
use InvalidArgumentException;
use Spatie\LaravelData\DataCollection;

/**
 * XML Builder for HTNG 2011B Inventory Messages (OTA_HotelInvCountNotifRQ)
 *
 * This builder handles the construction of inventory update messages for TravelClick.
 * It supports both "calculated" and "not-calculated" inventory methods as defined
 * in the TravelClick documentation.
 *
 * Key concepts:
 * - Not Calculated (CountType=2): Sets inventory directly with available room count
 * - Calculated (CountTypes 4,5,6,99): TravelClick calculates availability from sold counts
 * - Property-level vs Room-level: Can update entire property or specific room types
 *
 * Think of this as a translator that converts your inventory data into the exact
 * XML format that TravelClick expects, with all necessary validations and structure.
 */
class InventoryXmlBuilder extends XmlBuilder
{
    /**
     * Maximum number of inventory records per message
     * This helps prevent timeout issues with large batches
     */
    private const MAX_INVENTORY_RECORDS = 100;

    /**
     * Constructor
     */
    public function __construct(SoapHeaderDto $soapHeaders, bool $validateXml = true, bool $formatOutput = false)
    {
        parent::__construct(
            messageType: MessageType::INVENTORY,
            soapHeaders: $soapHeaders,
            validateXml: $validateXml,
            formatOutput: $formatOutput
        );
    }

    /**
     * Build multiple inventory records into a single XML message
     *
     * @param DataCollection<int, InventoryData> $inventoryCollection
     * @return string Complete XML message
     * @throws InvalidArgumentException If data validation fails
     */
    public function buildBatch(DataCollection $inventoryCollection): string
    {
        if ($inventoryCollection->count() === 0) {
            throw new InvalidArgumentException('Cannot build empty inventory batch');
        }

        if ($inventoryCollection->count() > self::MAX_INVENTORY_RECORDS) {
            throw new InvalidArgumentException(
                'Too many inventory records. Maximum allowed: ' . self::MAX_INVENTORY_RECORDS
            );
        }

        // Group inventory records by hotel code for validation
        $this->validateInventoryBatch($inventoryCollection);

        // Build message data with all inventory records
        $messageData = [
            'hotel_code' => $inventoryCollection->first()->hotelCode,
            'inventories' => $inventoryCollection->toArray(),
        ];

        return $this->build($messageData);
    }

    /**
     * Build a single inventory record into XML message
     *
     * @param InventoryData $inventoryData
     * @return string Complete XML message
     */
    public function buildSingle(InventoryData $inventoryData): string
    {
        return $this->buildBatch(new DataCollection(InventoryData::class, [$inventoryData]));
    }

    /**
     * Build the OTA message body containing inventory data
     *
     * @param array<string, mixed> $messageData
     * @return array<string, mixed>
     */
    protected function buildMessageBody(array $messageData): array
    {
        $inventories = [];

        /** @var InventoryData $inventoryData */
        foreach ($messageData['inventories'] as $inventoryData) {
            $inventories[] = $this->buildSingleInventoryElement($inventoryData);
        }

        return [
            $this->getOtaRootElement() => [
                '_attributes' => $this->getOtaMessageAttributes(),
                'Inventories' => [
                    '_attributes' => ['HotelCode' => $messageData['hotel_code']],
                    'Inventory' => count($inventories) === 1 ? $inventories[0] : $inventories,
                ],
            ],
        ];
    }

    /**
     * Build a single inventory element
     *
     * @param InventoryData $inventoryData
     * @return array<string, mixed>
     */
    private function buildSingleInventoryElement(InventoryData $inventoryData): array
    {
        $inventory = [];

        // Add unique ID if provided
        if ($inventoryData->uniqueId !== null) {
            $inventory['UniqueID'] = [
                '_attributes' => [
                    'Type' => '16',
                    'ID' => $inventoryData->uniqueId,
                ],
            ];
        }

        // Build status application control
        $inventory['StatusApplicationControl'] = $this->buildStatusApplicationControl(
            startDate: $inventoryData->startDate,
            endDate: $inventoryData->endDate,
            roomTypeCode: $inventoryData->roomTypeCode,
            allInvCode: $inventoryData->isPropertyLevel
        );

        // Build inventory counts
        $inventory['InvCounts'] = $this->buildInventoryCounts($inventoryData);

        return $inventory;
    }

    /**
     * Build inventory counts section
     *
     * @param InventoryData $inventoryData
     * @return array<string, mixed>
     */
    private function buildInventoryCounts(InventoryData $inventoryData): array
    {
        $invCounts = [];

        foreach ($inventoryData->counts as $countData) {
            $invCounts[] = [
                '_attributes' => $countData->toXmlAttributes(),
            ];
        }

        return [
            'InvCount' => count($invCounts) === 1 ? $invCounts[0] : $invCounts,
        ];
    }

    /**
     * Validate message data before building XML
     *
     * @param array<string, mixed> $messageData
     * @throws InvalidArgumentException If validation fails
     */
    protected function validateMessageData(array $messageData): void
    {
        // Validate hotel code
        if (!isset($messageData['hotel_code']) || empty($messageData['hotel_code'])) {
            throw new InvalidArgumentException('Hotel code is required');
        }

        $this->validateHotelCode($messageData['hotel_code']);

        // Validate inventories array
        if (!isset($messageData['inventories']) || !is_array($messageData['inventories'])) {
            throw new InvalidArgumentException('Inventories array is required');
        }

        if (empty($messageData['inventories'])) {
            throw new InvalidArgumentException('Cannot build message with empty inventories');
        }

        // Validate each inventory record
        foreach ($messageData['inventories'] as $index => $inventoryData) {
            if (!$inventoryData instanceof InventoryData) {
                throw new InvalidArgumentException("Invalid inventory data at index {$index}");
            }

            $this->validateSingleInventory($inventoryData);
        }
    }

    /**
     * Validate a single inventory record
     *
     * @param InventoryData $inventoryData
     * @throws InvalidArgumentException If validation fails
     */
    private function validateSingleInventory(InventoryData $inventoryData): void
    {
        // Validate business rules
        $businessErrors = $inventoryData->validateBusinessRules();
        if (!empty($businessErrors)) {
            throw new InvalidArgumentException(
                'Inventory validation failed: ' . implode(', ', $businessErrors)
            );
        }

        // Validate room type code if provided
        if ($inventoryData->roomTypeCode !== null) {
            $this->validateRoomTypeCode($inventoryData->roomTypeCode);
        }

        // Validate count types
        $this->validateCountTypes($inventoryData);

        // Validate date range
        $this->validateDateRange($inventoryData);
    }

    /**
     * Validate count types according to TravelClick rules
     *
     * @param InventoryData $inventoryData
     * @throws InvalidArgumentException If validation fails
     */
    private function validateCountTypes(InventoryData $inventoryData): void
    {
        $countTypes = $inventoryData->counts->collect()->pluck('countType')->toArray();

        // Check if any counts are present
        if (empty($countTypes)) {
            throw new InvalidArgumentException('At least one inventory count is required');
        }

        // Validate count values
        foreach ($inventoryData->counts as $countData) {
            if ($countData->count < 0) {
                throw new InvalidArgumentException(
                    "Count value cannot be negative for count type {$countData->countType->value}"
                );
            }
        }

        // Validate count type combinations
        $this->validateCountTypeCombinations($countTypes);

        // Special validation for calculated method
        if ($inventoryData->isCalculatedMethod()) {
            $this->validateCalculatedMethod($inventoryData);
        }

        // Special validation for direct method
        if ($inventoryData->isDirectMethod()) {
            $this->validateDirectMethod($countTypes);
        }
    }

    /**
     * Validate count type combinations
     *
     * @param array<CountType> $countTypes
     * @throws InvalidArgumentException If invalid combination
     */
    private function validateCountTypeCombinations(array $countTypes): void
    {
        // CountType 2 (AVAILABLE) cannot be combined with others
        if (in_array(CountType::AVAILABLE, $countTypes) && count($countTypes) > 1) {
            throw new InvalidArgumentException(
                'Available count type (2) cannot be combined with other count types'
            );
        }

        // Validate allowed count types from config
        $allowedCountTypes = config('travelclick.message_types.inventory.count_types', [1, 2, 4, 5, 6, 99]);

        foreach ($countTypes as $countType) {
            if (!in_array($countType->value, $allowedCountTypes)) {
                throw new InvalidArgumentException(
                    "Count type {$countType->value} is not allowed in configuration"
                );
            }
        }
    }

    /**
     * Validate calculated method requirements
     *
     * @param InventoryData $inventoryData
     * @throws InvalidArgumentException If validation fails
     */
    private function validateCalculatedMethod(InventoryData $inventoryData): void
    {
        $countTypes = $inventoryData->counts->collect()->pluck('countType')->toArray();

        // DEFINITE_SOLD is required for calculated method
        if (!in_array(CountType::DEFINITE_SOLD, $countTypes)) {
            throw new InvalidArgumentException(
                'Definite sold count type (4) is required for calculated method'
            );
        }

        // TENTATIVE_SOLD must be present (even if zero)
        if (!in_array(CountType::TENTATIVE_SOLD, $countTypes)) {
            throw new InvalidArgumentException(
                'Tentative sold count type (5) is required for calculated method'
            );
        }

        // Validate tentative sold logic
        $tentativeSoldCount = $inventoryData->getCountByType(CountType::TENTATIVE_SOLD);
        if ($tentativeSoldCount > 0) {
            throw new InvalidArgumentException(
                'Tentative sold count type (5) must be passed with value of zero in calculated method'
            );
        }
    }

    /**
     * Validate direct method (not-calculated) requirements
     *
     * @param array<CountType> $countTypes
     * @throws InvalidArgumentException If validation fails
     */
    private function validateDirectMethod(array $countTypes): void
    {
        // Only AVAILABLE count type should be present
        if (count($countTypes) !== 1 || !in_array(CountType::AVAILABLE, $countTypes)) {
            throw new InvalidArgumentException(
                'Direct method requires only available count type (2)'
            );
        }
    }

    /**
     * Validate date range
     *
     * @param InventoryData $inventoryData
     * @throws InvalidArgumentException If validation fails
     */
    private function validateDateRange(InventoryData $inventoryData): void
    {
        $startDate = \DateTime::createFromFormat('Y-m-d', $inventoryData->startDate);
        $endDate = \DateTime::createFromFormat('Y-m-d', $inventoryData->endDate);

        if (!$startDate || !$endDate) {
            throw new InvalidArgumentException('Invalid date format in inventory data');
        }

        // Check maximum date range (prevent very large batches)
        $maxDays = config('travelclick.message_types.inventory.max_days_per_request', 365);
        $daysDiff = $startDate->diff($endDate)->days;

        if ($daysDiff > $maxDays) {
            throw new InvalidArgumentException(
                "Date range too large. Maximum {$maxDays} days allowed."
            );
        }

        // Ensure dates are not in the past (with tolerance for current date)
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        if ($endDate < $today) {
            throw new InvalidArgumentException(
                'Inventory dates cannot be in the past'
            );
        }
    }

    /**
     * Validate inventory batch consistency
     *
     * @param DataCollection<int, InventoryData> $inventoryCollection
     * @throws InvalidArgumentException If validation fails
     */
    private function validateInventoryBatch(DataCollection $inventoryCollection): void
    {
        $hotelCodes = $inventoryCollection->collect()->pluck('hotelCode')->unique();

        // All records must be for the same hotel
        if ($hotelCodes->count() > 1) {
            throw new InvalidArgumentException(
                'All inventory records in a batch must be for the same hotel'
            );
        }

        // Check for duplicate room type/date combinations
        $combinations = [];
        foreach ($inventoryCollection as $inventory) {
            $key = sprintf(
                '%s|%s|%s|%s',
                $inventory->hotelCode,
                $inventory->roomTypeCode ?? 'PROPERTY',
                $inventory->startDate,
                $inventory->endDate
            );

            if (isset($combinations[$key])) {
                throw new InvalidArgumentException(
                    "Duplicate inventory record for room type '{$inventory->roomTypeCode}' and date range"
                );
            }

            $combinations[$key] = true;
        }
    }

    /**
     * Create a new builder instance for calculated inventory
     *
     * @param SoapHeaderDto $soapHeaders
     * @return self
     */
    public static function forCalculated(SoapHeaderDto $soapHeaders): self
    {
        return new self($soapHeaders, validateXml: true, formatOutput: false);
    }

    /**
     * Create a new builder instance for direct (not-calculated) inventory
     *
     * @param SoapHeaderDto $soapHeaders
     * @return self
     */
    public static function forDirect(SoapHeaderDto $soapHeaders): self
    {
        return new self($soapHeaders, validateXml: true, formatOutput: false);
    }

    /**
     * Create a new builder instance for property-level inventory
     *
     * @param SoapHeaderDto $soapHeaders
     * @return self
     */
    public static function forPropertyLevel(SoapHeaderDto $soapHeaders): self
    {
        return new self($soapHeaders, validateXml: true, formatOutput: false);
    }
}
