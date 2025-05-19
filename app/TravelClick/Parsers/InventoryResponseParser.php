<?php

namespace App\TravelClick\Parsers;

use App\TravelClick\DTOs\InventoryResponseDto;
use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Enums\CountType;
use Carbon\Carbon;
use SimpleXMLElement;
use Throwable;

/**
 * Parser for TravelClick Inventory Response Messages
 *
 * This class extends the base SOAP parser to handle the specific structure
 * of inventory response messages (OTA_HotelInvCountNotifRS).
 * It extracts inventory counts, date ranges, and room type information.
 */
class InventoryResponseParser extends SoapResponseParser
{
    /**
     * Parse a SOAP response into a structured InventoryResponseDto
     *
     * @param string $messageId The unique message identifier for tracking
     * @param string $rawResponse The raw XML response from TravelClick
     * @param ?float $durationMs The time taken to receive the response in milliseconds
     * @param array $headers Optional SOAP headers from the response
     * @return InventoryResponseDto The parsed inventory response data
     */
    public function parse(
        string $messageId,
        string $rawResponse,
        ?float $durationMs = null,
        array $headers = []
    ): SoapResponseDto {
        // First use the parent parser to check for SOAP faults and basic validation
        $baseResponse = parent::parse($messageId, $rawResponse, $durationMs, $headers);

        // If the base response indicated failure, convert it to our specific type
        if (!$baseResponse->isSuccess) {
            return InventoryResponseDto::failure(
                messageId: $messageId,
                rawResponse: $rawResponse,
                errorMessage: $baseResponse->errorMessage ?? 'Unknown error',
                errorCode: $baseResponse->errorCode,
                warnings: $baseResponse->warnings,
                durationMs: $baseResponse->durationMs
            );
        }

        try {
            // Parse the XML
            $xml = $this->parseXml($rawResponse);

            // Extract the body content (removes SOAP envelope)
            $bodyContent = $this->extractBodyContent($xml);

            if (!$bodyContent) {
                return InventoryResponseDto::failure(
                    messageId: $messageId,
                    rawResponse: $rawResponse,
                    errorMessage: 'Invalid response structure: missing body content',
                    errorCode: 'INVALID_STRUCTURE',
                    durationMs: $durationMs
                );
            }

            // Check if we have an OTA_HotelInvCountNotifRS element
            $responseNode = $this->findInventoryResponseNode($bodyContent);

            if (!$responseNode) {
                return InventoryResponseDto::failure(
                    messageId: $messageId,
                    rawResponse: $rawResponse,
                    errorMessage: 'Missing OTA_HotelInvCountNotifRS element in response',
                    errorCode: 'INVALID_STRUCTURE',
                    durationMs: $durationMs
                );
            }

            // Extract hotel code from the response
            $hotelCode = $this->extractHotelCode($responseNode);

            // Extract success messages and warnings from the response
            $warnings = $this->extractWarnings($xml);

            // Extract inventory data
            $inventoryData = $this->extractInventoryData($responseNode);

            // Return successful response - note the corrected parameter order to match the parent method
            return InventoryResponseDto::success(
                messageId: $messageId,
                rawResponse: $rawResponse,
                echoToken: $baseResponse->echoToken,
                headers: $headers,
                durationMs: $durationMs,
                processedCounts: $inventoryData['processedCounts'] ?? [],
                hotelCode: $hotelCode,
                roomTypes: $inventoryData['roomTypes'] ?? [],
                startDate: $inventoryData['startDate'] ?? null,
                endDate: $inventoryData['endDate'] ?? null,
                warnings: $warnings
            );
        } catch (Throwable $e) {
            // Handle any exceptions during the parsing process
            return InventoryResponseDto::failure(
                messageId: $messageId,
                rawResponse: $rawResponse,
                errorMessage: "Failed to parse inventory response: {$e->getMessage()}",
                errorCode: 'INVENTORY_PARSING_ERROR',
                durationMs: $durationMs
            );
        }
    }

    /**
     * Find the inventory response node in the SOAP body
     *
     * @param SimpleXMLElement $bodyContent The SOAP body content
     * @return SimpleXMLElement|null The inventory response node if found
     */
    protected function findInventoryResponseNode(SimpleXMLElement $bodyContent): ?SimpleXMLElement
    {
        // The response node could be either directly in the body or nested
        // Try the direct approach first
        if ($bodyContent->getName() === 'OTA_HotelInvCountNotifRS') {
            return $bodyContent;
        }

        // Try to find it by xpath
        $nodes = $bodyContent->xpath('//OTA_HotelInvCountNotifRS');
        return !empty($nodes) ? $nodes[0] : null;
    }

    /**
     * Extract hotel code from the response
     *
     * @param SimpleXMLElement $responseNode The inventory response node
     * @return string|null The hotel code if found
     */
    protected function extractHotelCode(SimpleXMLElement $responseNode): ?string
    {
        // First try the inventories element
        $inventoriesNodes = $responseNode->xpath('.//Inventories');
        if (!empty($inventoriesNodes)) {
            $attributes = $inventoriesNodes[0]->attributes();
            if (isset($attributes['HotelCode'])) {
                return (string)$attributes['HotelCode'];
            }
        }

        // If not found there, try the Success element
        $successNodes = $responseNode->xpath('.//Success');
        if (!empty($successNodes)) {
            $attributes = $successNodes[0]->attributes();
            if (isset($attributes['HotelCode'])) {
                return (string)$attributes['HotelCode'];
            }
        }

        return null;
    }

    /**
     * Extract inventory data from the response including processed counts,
     * room types, and date ranges
     *
     * @param SimpleXMLElement $responseNode The inventory response node
     * @return array The extracted inventory data
     */
    protected function extractInventoryData(SimpleXMLElement $responseNode): array
    {
        $result = [
            'processedCounts' => [],
            'roomTypes' => [],
            'startDate' => null,
            'endDate' => null,
        ];

        // Find all Inventory elements
        $inventoryNodes = $responseNode->xpath('.//Inventory');
        if (empty($inventoryNodes)) {
            return $result;
        }

        foreach ($inventoryNodes as $inventoryNode) {
            // Extract date ranges and room type
            $statusNode = $inventoryNode->xpath('./StatusApplicationControl');
            if (empty($statusNode)) {
                continue;
            }

            $statusAttributes = $statusNode[0]->attributes();

            // Extract room type
            $roomType = null;
            if (isset($statusAttributes['InvTypeCode'])) {
                $roomType = (string)$statusAttributes['InvTypeCode'];
                if (!in_array($roomType, $result['roomTypes'])) {
                    $result['roomTypes'][] = $roomType;
                }
            }

            // Check for property-level indicator
            $isPropertyLevel = false;
            if (isset($statusAttributes['AllInvCode']) && (string)$statusAttributes['AllInvCode'] === 'true') {
                $isPropertyLevel = true;
                $roomType = 'property'; // Use 'property' for property-level counts
            }

            // Extract date range
            $startDate = isset($statusAttributes['Start'])
                ? $this->parseDate((string)$statusAttributes['Start'])
                : null;

            $endDate = isset($statusAttributes['End'])
                ? $this->parseDate((string)$statusAttributes['End'])
                : null;

            // Update global date range
            if ($startDate && (!$result['startDate'] || $startDate < $result['startDate'])) {
                $result['startDate'] = $startDate;
            }

            if ($endDate && (!$result['endDate'] || $endDate > $result['endDate'])) {
                $result['endDate'] = $endDate;
            }

            // Extract counts
            $countNodes = $inventoryNode->xpath('./InvCounts/InvCount');
            if (empty($countNodes)) {
                continue;
            }

            $counts = [];
            foreach ($countNodes as $countNode) {
                $countAttributes = $countNode->attributes();

                if (isset($countAttributes['CountType']) && isset($countAttributes['Count'])) {
                    $countType = (int)$countAttributes['CountType'];
                    $count = (int)$countAttributes['Count'];

                    // Validate the count type against our enum
                    if ($this->isValidCountType($countType)) {
                        $counts[$countType] = $count;
                    }
                }
            }

            // Store the counts for this room type
            if ($roomType) {
                $result['processedCounts'][$roomType] = $counts;
            }
        }

        return $result;
    }

    /**
     * Check if a count type is valid according to our enum
     *
     * @param int $countType The count type to validate
     * @return bool True if the count type is valid
     */
    protected function isValidCountType(int $countType): bool
    {
        return in_array($countType, array_column(CountType::cases(), 'value'));
    }

    /**
     * Parse a date string into a Carbon instance
     *
     * @param string $dateString The date string to parse
     * @return Carbon|null The parsed date or null if invalid
     */
    protected function parseDate(string $dateString): ?Carbon
    {
        try {
            return Carbon::parse($dateString);
        } catch (Throwable $e) {
            return null;
        }
    }
}
