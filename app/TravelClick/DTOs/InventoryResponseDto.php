<?php

namespace App\TravelClick\DTOs;

use App\TravelClick\Enums\CountType;
use Carbon\Carbon;

/**
 * Data Transfer Object for TravelClick Inventory Response
 *
 * This DTO extends the base SOAP response to include inventory-specific
 * information such as processed counts, room types, and date ranges.
 */
class InventoryResponseDto extends SoapResponseDto
{
    /**
     * Collection of inventory counts processed
     * Organized by room type and count type
     */
    private array $processedCounts = [];

    /**
     * Hotel code from the response
     */
    private ?string $hotelCode = null;

    /**
     * List of room types processed in the response
     */
    private array $roomTypes = [];

    /**
     * Start date for inventory update
     */
    private ?Carbon $startDate = null;

    /**
     * End date for inventory update
     */
    private ?Carbon $endDate = null;

    /**
     * Constructor for inventory response DTO
     */
    public function __construct(
        string $messageId,
        bool $isSuccess,
        string $rawResponse,
        ?array $processedCounts = null,
        ?string $hotelCode = null,
        ?array $roomTypes = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?string $errorMessage = null,
        ?string $errorCode = null,
        ?array $warnings = null,
        ?Carbon $timestamp = null,
        ?string $echoToken = null,
        ?array $headers = null,
        ?float $durationMs = null
    ) {
        parent::__construct(
            messageId: $messageId,
            isSuccess: $isSuccess,
            rawResponse: $rawResponse,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
            warnings: $warnings,
            timestamp: $timestamp,
            echoToken: $echoToken,
            headers: $headers,
            durationMs: $durationMs
        );

        $this->processedCounts = $processedCounts ?? [];
        $this->hotelCode = $hotelCode;
        $this->roomTypes = $roomTypes ?? [];
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Create a successful inventory response DTO
     *
     * @param string $messageId The original message ID for tracking
     * @param string $rawResponse The raw XML response
     * @param array $processedCounts The inventory counts that were processed
     * @param string|null $hotelCode The hotel code in the response
     * @param array $roomTypes The room types processed in the response
     * @param Carbon|null $startDate The start date for the inventory update
     * @param Carbon|null $endDate The end date for the inventory update
     * @param string|null $echoToken The echo token from the response
     * @param array|null $headers Any SOAP headers in the response
     * @param float|null $durationMs The duration of the request in milliseconds
     * @param array|null $warnings Any warnings in the response
     * @return static
     */
    public static function success(
        string $messageId,
        string $rawResponse,
        ?string $echoToken = null,
        ?array $headers = null,
        ?float $durationMs = null,
        array $processedCounts = [],
        ?string $hotelCode = null,
        array $roomTypes = [],
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?array $warnings = null
    ): self {
        return new self(
            messageId: $messageId,
            isSuccess: true,
            rawResponse: $rawResponse,
            processedCounts: $processedCounts,
            hotelCode: $hotelCode,
            roomTypes: $roomTypes,
            startDate: $startDate,
            endDate: $endDate,
            echoToken: $echoToken,
            headers: $headers,
            durationMs: $durationMs,
            warnings: $warnings,
            timestamp: Carbon::now()
        );
    }

    /**
     * Create a failed inventory response DTO
     *
     * @param string $messageId The original message ID for tracking
     * @param string $rawResponse The raw XML response
     * @param string $errorMessage The error message
     * @param string|null $errorCode The error code
     * @param array|null $warnings Any warnings in the response
     * @param float|null $durationMs The duration of the request in milliseconds
     * @return static
     */
    public static function failure(
        string $messageId,
        string $rawResponse,
        string $errorMessage,
        ?string $errorCode = null,
        ?array $warnings = null,
        ?float $durationMs = null
    ): self {
        return new self(
            messageId: $messageId,
            isSuccess: false,
            rawResponse: $rawResponse,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
            warnings: $warnings,
            durationMs: $durationMs,
            timestamp: Carbon::now()
        );
    }

    /**
     * Get the processed counts for a specific count type
     *
     * @param CountType $countType The count type to retrieve
     * @param string|null $roomType Optional room type filter
     * @return int|null The count value or null if not present
     */
    public function getCountValue(CountType $countType, ?string $roomType = null): ?int
    {
        if (empty($this->processedCounts)) {
            return null;
        }

        if ($roomType !== null) {
            return $this->processedCounts[$roomType][$countType->value] ?? null;
        }

        // If multiple room types, try to find the first matching count type
        foreach ($this->processedCounts as $roomData) {
            if (isset($roomData[$countType->value])) {
                return $roomData[$countType->value];
            }
        }

        return null;
    }

    /**
     * Get all processed room types
     *
     * @return array The room types processed in this response
     */
    public function getRoomTypes(): array
    {
        return $this->roomTypes;
    }

    /**
     * Get the date range for this inventory update
     *
     * @return array{start: ?Carbon, end: ?Carbon} The date range
     */
    public function getDateRange(): array
    {
        return [
            'start' => $this->startDate,
            'end' => $this->endDate,
        ];
    }

    /**
     * Get all processed counts
     *
     * @return array The processed counts data
     */
    public function getProcessedCounts(): array
    {
        return $this->processedCounts;
    }

    /**
     * Check if a specific room type was processed
     *
     * @param string $roomType The room type code to check
     * @return bool True if the room type was processed
     */
    public function hasRoomType(string $roomType): bool
    {
        return in_array($roomType, $this->roomTypes);
    }

    /**
     * Get the hotel code
     *
     * @return string|null The hotel code
     */
    public function getHotelCode(): ?string
    {
        return $this->hotelCode;
    }

    /**
     * Convert DTO to array for logging purposes
     * Extends parent method to include inventory-specific data
     */
    public function toArray(): array
    {
        $baseArray = parent::toArray();

        return array_merge($baseArray, [
            'hotel_code' => $this->hotelCode,
            'room_types_count' => count($this->roomTypes),
            'has_processed_counts' => !empty($this->processedCounts),
            'date_range' => [
                'start' => $this->startDate?->toDateString(),
                'end' => $this->endDate?->toDateString(),
            ],
        ]);
    }
}
