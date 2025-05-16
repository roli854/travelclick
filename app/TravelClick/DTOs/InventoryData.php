<?php

declare(strict_types=1);

namespace App\TravelClick\DTOs;

use App\TravelClick\Enums\CountType;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/**
 * Data Transfer Object for TravelClick Inventory Messages
 *
 * This class represents a single inventory item that will be sent to TravelClick.
 * It handles all validation and transformation logic for inventory data, ensuring
 * that the data is properly formatted before building the XML message.
 *
 * Think of this as a smart container that knows exactly what inventory data
 * should look like and can validate itself before sending it anywhere.
 */
class InventoryData extends Data
{
    /**
     * @param string $hotelCode The property identifier in TravelClick
     * @param string $startDate Start date for inventory period (YYYY-MM-DD)
     * @param string $endDate End date for inventory period (YYYY-MM-DD)
     * @param string|null $roomTypeCode Room type code (required unless property-level)
     * @param bool $isPropertyLevel Whether this is property-level inventory (AllInvCode=true)
     * @param DataCollection<int, InventoryCountData> $counts Collection of inventory counts
     * @param string|null $uniqueId Optional unique identifier for the inventory record
     */
    public function __construct(
        #[Required, StringType, Min(1), Max(10)]
        #[MapInputName('hotel_code')]
        #[MapOutputName('hotel_code')]
        public readonly string $hotelCode,

        #[Required, DateFormat('Y-m-d')]
        #[MapInputName('start_date')]
        #[MapOutputName('start_date')]
        public readonly string $startDate,

        #[Required, DateFormat('Y-m-d')]
        #[MapInputName('end_date')]
        #[MapOutputName('end_date')]
        public readonly string $endDate,

        #[StringType, Min(1), Max(20)]
        #[MapInputName('room_type_code')]
        #[MapOutputName('room_type_code')]
        public readonly ?string $roomTypeCode = null,

        #[MapInputName('is_property_level')]
        #[MapOutputName('is_property_level')]
        public readonly bool $isPropertyLevel = false,

        /** @var DataCollection<int, InventoryCountData> */
        public readonly DataCollection $counts,

        #[StringType]
        #[MapInputName('unique_id')]
        #[MapOutputName('unique_id')]
        public readonly ?string $uniqueId = null,
    ) {}

    /**
     * Create inventory data from Centrium database models
     *
     * @param array<string, mixed> $inventoryRecord
     * @return self
     */
    public static function fromCentrium(array $inventoryRecord): self
    {
        // Convert Centrium inventory counts to TravelClick format
        $counts = [];

        // Map StandardAllocation to PHYSICAL count type
        if (isset($inventoryRecord['StandardAllocation']) && $inventoryRecord['StandardAllocation'] > 0) {
            $counts[] = InventoryCountData::from([
                'count_type' => CountType::PHYSICAL,
                'count' => $inventoryRecord['StandardAllocation'],
            ]);
        }

        // Map Rooms to AVAILABLE count type
        if (isset($inventoryRecord['Rooms'])) {
            $counts[] = InventoryCountData::from([
                'count_type' => CountType::AVAILABLE,
                'count' => $inventoryRecord['Rooms'],
            ]);
        }

        // Map Overbook to OVERSELL count type
        if (isset($inventoryRecord['Overbook']) && $inventoryRecord['Overbook'] > 0) {
            $counts[] = InventoryCountData::from([
                'count_type' => CountType::OVERSELL,
                'count' => $inventoryRecord['Overbook'],
            ]);
        }

        return new self(
            hotelCode: (string) $inventoryRecord['PropertyID'],
            startDate: Carbon::parse($inventoryRecord['InventoryDate'])->format('Y-m-d'),
            endDate: Carbon::parse($inventoryRecord['InventoryDate'])->format('Y-m-d'),
            roomTypeCode: $inventoryRecord['RoomTypeCode'] ?? null,
            isPropertyLevel: !isset($inventoryRecord['RoomTypeCode']),
            counts: new DataCollection(InventoryCountData::class, $counts),
            uniqueId: isset($inventoryRecord['InventoryID']) ? "INV_{$inventoryRecord['InventoryID']}" : null,
        );
    }

    /**
     * Create calculated inventory data (using count types 4, 5, 6, 99)
     *
     * @param string $hotelCode
     * @param string $startDate
     * @param string $endDate
     * @param string|null $roomTypeCode
     * @param int $definiteSold
     * @param int $tentativeSold
     * @param int $outOfOrder
     * @param int $oversell
     * @param int|null $physical
     * @return self
     */
    public static function createCalculated(
        string $hotelCode,
        string $startDate,
        string $endDate,
        ?string $roomTypeCode = null,
        int $definiteSold = 0,
        int $tentativeSold = 0,
        int $outOfOrder = 0,
        int $oversell = 0,
        ?int $physical = null
    ): self {
        $counts = [];

        // Add physical count if provided
        if ($physical !== null && $physical > 0) {
            $counts[] = InventoryCountData::from([
                'count_type' => CountType::PHYSICAL,
                'count' => $physical,
            ]);
        }

        // Add definite sold (required for calculated method)
        $counts[] = InventoryCountData::from([
            'count_type' => CountType::DEFINITE_SOLD,
            'count' => $definiteSold,
        ]);

        // Add tentative sold (always required, even if zero)
        $counts[] = InventoryCountData::from([
            'count_type' => CountType::TENTATIVE_SOLD,
            'count' => $tentativeSold,
        ]);

        // Add out of order if provided
        if ($outOfOrder > 0) {
            $counts[] = InventoryCountData::from([
                'count_type' => CountType::OUT_OF_ORDER,
                'count' => $outOfOrder,
            ]);
        }

        // Add oversell if provided
        if ($oversell > 0) {
            $counts[] = InventoryCountData::from([
                'count_type' => CountType::OVERSELL,
                'count' => $oversell,
            ]);
        }

        return new self(
            hotelCode: $hotelCode,
            startDate: $startDate,
            endDate: $endDate,
            roomTypeCode: $roomTypeCode,
            isPropertyLevel: $roomTypeCode === null,
            counts: new DataCollection(InventoryCountData::class, $counts),
        );
    }

    /**
     * Create available count inventory data (using count type 2)
     *
     * @param string $hotelCode
     * @param string $startDate
     * @param string $endDate
     * @param string|null $roomTypeCode
     * @param int $availableCount
     * @return self
     */
    public static function createAvailable(
        string $hotelCode,
        string $startDate,
        string $endDate,
        ?string $roomTypeCode = null,
        int $availableCount = 0
    ): self {
        $counts = [
            InventoryCountData::from([
                'count_type' => CountType::AVAILABLE,
                'count' => $availableCount,
            ])
        ];

        return new self(
            hotelCode: $hotelCode,
            startDate: $startDate,
            endDate: $endDate,
            roomTypeCode: $roomTypeCode,
            isPropertyLevel: $roomTypeCode === null,
            counts: new DataCollection(InventoryCountData::class, $counts),
        );
    }

    /**
     * Validate inventory data business rules
     *
     * @return array<string> Array of validation errors, empty if valid
     */
    public function validateBusinessRules(): array
    {
        $errors = [];

        // Check date logic
        if (Carbon::parse($this->startDate)->isAfter(Carbon::parse($this->endDate))) {
            $errors[] = 'Start date must be before or equal to end date';
        }

        // Validate count types combinations
        $countTypes = $this->counts->collect()->pluck('countType')->toArray();

        // Check for invalid combinations
        if (in_array(CountType::AVAILABLE, $countTypes) && count($countTypes) > 1) {
            $errors[] = 'Available count type (2) cannot be combined with other count types';
        }

        // Check for required room type code unless property level
        if (!$this->isPropertyLevel && empty($this->roomTypeCode)) {
            $errors[] = 'Room type code is required for room-level inventory';
        }

        // Check for property level constraints
        if ($this->isPropertyLevel && !empty($this->roomTypeCode)) {
            $errors[] = 'Room type code should not be provided for property-level inventory';
        }

        // Validate calculated method requirements
        if (in_array(CountType::DEFINITE_SOLD, $countTypes)) {
            // If using calculated method, TENTATIVE_SOLD must be present
            if (!in_array(CountType::TENTATIVE_SOLD, $countTypes)) {
                $errors[] = 'Tentative sold count type (5) is required when using calculated method';
            }
        }

        return $errors;
    }

    /**
     * Check if this uses the calculated method
     *
     * @return bool
     */
    public function isCalculatedMethod(): bool
    {
        $countTypes = $this->counts->collect()->pluck('countType')->toArray();
        return in_array(CountType::DEFINITE_SOLD, $countTypes);
    }

    /**
     * Check if this uses the not-calculated method (direct count)
     *
     * @return bool
     */
    public function isDirectMethod(): bool
    {
        $countTypes = $this->counts->collect()->pluck('countType')->toArray();
        return in_array(CountType::AVAILABLE, $countTypes) && count($countTypes) === 1;
    }

    /**
     * Get the total inventory count for display purposes
     *
     * @return int
     */
    public function getTotalCount(): int
    {
        return $this->counts->collect()->sum('count');
    }

    /**
     * Get count by specific count type
     *
     * @param CountType $countType
     * @return int
     */
    public function getCountByType(CountType $countType): int
    {
        $count = $this->counts->collect()
            ->where('countType', $countType)
            ->first();

        return $count?->count ?? 0;
    }
}

/**
 * Data Transfer Object for individual inventory counts
 *
 * Represents a single count entry within an inventory record.
 * Each count has a type (from CountType enum) and a numeric value.
 */
class InventoryCountData extends Data
{
    /**
     * @param CountType $countType The type of inventory count
     * @param int $count The numeric count value
     */
    public function __construct(
        #[Required]
        #[MapInputName('count_type')]
        #[MapOutputName('count_type')]
        public readonly CountType $countType,

        #[Required, IntegerType, Min(0)]
        public readonly int $count,
    ) {}

    /**
     * Convert to TravelClick XML format
     *
     * @return array<string, mixed>
     */
    public function toXmlAttributes(): array
    {
        return [
            'CountType' => $this->countType->value,
            'Count' => (string) $this->count,
        ];
    }
}
