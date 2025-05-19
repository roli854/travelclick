<?php

namespace App\TravelClick\Parsers;

use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Enums\RateOperationType;
use Carbon\Carbon;
use SimpleXMLElement;

/**
 * Parser specialized in handling and interpreting TravelClick rate responses.
 *
 * This parser extends the base SoapResponseParser to provide rate-specific
 * parsing functionality, extracting detailed information about rate plans,
 * room types, pricing, and linked rates.
 *
 * It handles all rate operation types defined in the RateOperationType enum:
 * - Rate Update (mandatory)
 * - Rate Creation (optional)
 * - Inactive Rate (optional)
 * - Remove Room Types (optional)
 * - Full Sync (special operation)
 * - Delta Update (recommended for routine updates)
 */
class RateResponseParser extends SoapResponseParser
{
    /**
     * OTA namespace for rates XML elements
     */
    protected const OTA_RATE_NS = 'http://www.opentravel.org/OTA/2003/05';

    /**
     * Parse a SOAP response related to rate operations
     *
     * This method extends the base parser functionality to extract rate-specific
     * information from the response.
     *
     * @param string $messageId The unique message identifier for tracking
     * @param string $rawResponse The raw XML response from TravelClick
     * @param ?float $durationMs The time taken to receive the response in milliseconds
     * @param array $headers Optional SOAP headers from the response
     * @return SoapResponseDto The parsed response data enriched with rate information
     */
    public function parse(
        string $messageId,
        string $rawResponse,
        ?float $durationMs = null,
        array $headers = []
    ): SoapResponseDto {
        // Call the parent parser for base functionality
        $responseDto = parent::parse(
            messageId: $messageId,
            rawResponse: $rawResponse,
            durationMs: $durationMs,
            headers: $headers
        );

        // If the response already indicates failure, return it as is
        if (!$responseDto->isSuccess) {
            return $responseDto;
        }

        try {
            // For successful responses, extract the XML and parse rate-specific data
            $xml = $this->parseXml($rawResponse);
            $bodyContent = $this->extractBodyContent($xml);

            if ($bodyContent) {
                // Determine operation type and add rate-specific metadata
                $metadata = $this->extractRateMetadata($bodyContent);

                // Create a new successful response with the additional metadata
                return new SoapResponseDto(
                    messageId: $responseDto->messageId,
                    isSuccess: true,
                    rawResponse: $responseDto->rawResponse,
                    echoToken: $responseDto->echoToken,
                    headers: $responseDto->headers,
                    durationMs: $responseDto->durationMs,
                    timestamp: $responseDto->timestamp,
                    warnings: $responseDto->warnings,
                );
            }
        } catch (\Throwable $e) {
            // If rate-specific parsing fails, log it but don't change the success status
            // since the base SOAP parsing was successful
            $warnings = $responseDto->warnings ?? [];
            $warnings[] = "Rate parsing warning: {$e->getMessage()}";

            return new SoapResponseDto(
                messageId: $responseDto->messageId,
                isSuccess: $responseDto->isSuccess,
                rawResponse: $responseDto->rawResponse,
                echoToken: $responseDto->echoToken,
                headers: $responseDto->headers,
                durationMs: $responseDto->durationMs,
                timestamp: $responseDto->timestamp,
                warnings: $warnings,
            );
        }

        // If we couldn't extract specific rate data but the SOAP call was successful,
        // return the original successful response
        return $responseDto;
    }

    /**
     * Extract rate metadata from the response body
     *
     * @param SimpleXMLElement $bodyContent The SOAP body content
     * @return array Rate metadata including operation type and affected rates
     */
    protected function extractRateMetadata(SimpleXMLElement $bodyContent): array
    {
        $metadata = [
            'operation_type' => $this->determineOperationType($bodyContent),
            'rate_plans' => [],
            'has_linked_rates' => $this->hasLinkedRates($bodyContent),
            'success_code' => $this->extractSuccessCode($bodyContent),
            'affected_rates_count' => 0,
        ];

        // Extract rate plan information based on response type
        $ratePlans = $this->extractRatePlans($bodyContent);
        if (!empty($ratePlans)) {
            $metadata['rate_plans'] = $ratePlans;
            $metadata['affected_rates_count'] = count($ratePlans);
        }

        // If the response contains linked rates, extract that information
        if ($metadata['has_linked_rates']) {
            $metadata['linked_rates'] = $this->extractLinkedRatesInfo($bodyContent);
        }

        return $metadata;
    }

    /**
     * Determine the type of rate operation from the response
     *
     * @param SimpleXMLElement $bodyContent The SOAP body content
     * @return RateOperationType The type of operation performed
     */
    protected function determineOperationType(SimpleXMLElement $bodyContent): RateOperationType
    {
        // Default to rate update if we can't determine
        $operationType = RateOperationType::RATE_UPDATE;

        // Check for specific elements or attributes that indicate operation type
        $responseNameString = $bodyContent->getName();

        if (str_contains($responseNameString, 'RateNotifRS')) {
            // Look for attributes or elements that indicate the operation type
            $success = $bodyContent->xpath('.//Success');
            $warning = $bodyContent->xpath('.//Warning');

            // Check for new rate creation responses
            if (!empty($success) && $bodyContent->xpath('.//*[@RatePlanCode]')) {
                $attributes = $bodyContent->xpath('.//*[@RatePlanNotifType]');
                if (!empty($attributes)) {
                    $notifType = (string)$attributes[0]['RatePlanNotifType'];
                    if ($notifType === 'New') {
                        return RateOperationType::RATE_CREATION;
                    } elseif ($notifType === 'Overlay') {
                        return RateOperationType::FULL_SYNC;
                    } elseif ($notifType === 'Remove') {
                        return RateOperationType::REMOVE_ROOM_TYPES;
                    } elseif ($notifType === 'Delta') {
                        return RateOperationType::DELTA_UPDATE;
                    }
                }
            }

            // Check for inactive rates
            $inactiveStatus = $bodyContent->xpath('.//*[@InvCode="Inactive"]');
            if (!empty($inactiveStatus)) {
                return RateOperationType::INACTIVE_RATE;
            }
        }

        return $operationType;
    }

    /**
     * Check if the response contains information about linked rates
     *
     * @param SimpleXMLElement $bodyContent The SOAP body content
     * @return bool True if linked rates are included
     */
    protected function hasLinkedRates(SimpleXMLElement $bodyContent): bool
    {
        // Look for indicators of linked rates in the response
        $linkedRateIndicators = $bodyContent->xpath('.//*[@DerivedRateFlag="true"]');

        if (!empty($linkedRateIndicators)) {
            return true;
        }

        // Also check for base rate references
        $baseRateRefs = $bodyContent->xpath('.//*[@BaseRatePlanCode]');

        return !empty($baseRateRefs);
    }

    /**
     * Extract information about linked rates
     *
     * @param SimpleXMLElement $bodyContent The SOAP body content
     * @return array Information about linked rates
     */
    protected function extractLinkedRatesInfo(SimpleXMLElement $bodyContent): array
    {
        $linkedRatesInfo = [];

        // Find elements with BaseRatePlanCode attribute
        $linkedElements = $bodyContent->xpath('.//*[@BaseRatePlanCode]');

        foreach ($linkedElements as $element) {
            $attributes = $element->attributes();

            if (isset($attributes['RatePlanCode']) && isset($attributes['BaseRatePlanCode'])) {
                $childRate = (string)$attributes['RatePlanCode'];
                $masterRate = (string)$attributes['BaseRatePlanCode'];

                // Extract rate derivation information (percentage or fixed amount)
                $derivationType = 'unknown';
                $derivationValue = null;

                if (isset($attributes['RateDerivationPercent'])) {
                    $derivationType = 'percentage';
                    $derivationValue = (float)$attributes['RateDerivationPercent'];
                } elseif (isset($attributes['RateDerivationAmount'])) {
                    $derivationType = 'amount';
                    $derivationValue = (float)$attributes['RateDerivationAmount'];
                }

                $linkedRatesInfo[] = [
                    'child_rate_code' => $childRate,
                    'master_rate_code' => $masterRate,
                    'derivation_type' => $derivationType,
                    'derivation_value' => $derivationValue,
                ];
            }
        }

        return $linkedRatesInfo;
    }

    /**
     * Extract the success code from the response
     *
     * @param SimpleXMLElement $bodyContent The SOAP body content
     * @return string|null The success code if present
     */
    protected function extractSuccessCode(SimpleXMLElement $bodyContent): ?string
    {
        $successElements = $bodyContent->xpath('.//Success');

        if (!empty($successElements)) {
            $attributes = $successElements[0]->attributes();

            if (isset($attributes['Code'])) {
                return (string)$attributes['Code'];
            }
        }

        return null;
    }

    /**
     * Extract rate plan information from the response
     *
     * @param SimpleXMLElement $bodyContent The SOAP body content
     * @return array Information about the rate plans in the response
     */
    protected function extractRatePlans(SimpleXMLElement $bodyContent): array
    {
        $ratePlans = [];

        // Look for rate plan elements
        $ratePlanElements = $bodyContent->xpath('.//*[local-name()="RatePlan"]');

        foreach ($ratePlanElements as $ratePlan) {
            $attributes = $ratePlan->attributes();

            if (isset($attributes['RatePlanCode'])) {
                $ratePlanCode = (string)$attributes['RatePlanCode'];

                $ratePlanInfo = [
                    'rate_plan_code' => $ratePlanCode,
                    'currency_code' => isset($attributes['CurrencyCode']) ? (string)$attributes['CurrencyCode'] : null,
                    'start_date' => null,
                    'end_date' => null,
                    'room_types' => [],
                ];

                // Extract rate plan dates
                $dateRange = $ratePlan->xpath('.//*[local-name()="DateRange"]');
                if (!empty($dateRange)) {
                    $dateAttributes = $dateRange[0]->attributes();

                    if (isset($dateAttributes['Start'])) {
                        try {
                            $ratePlanInfo['start_date'] = Carbon::parse((string)$dateAttributes['Start'])->toDateString();
                        } catch (\Throwable $e) {
                            // Invalid date format, leave as null
                        }
                    }

                    if (isset($dateAttributes['End'])) {
                        try {
                            $ratePlanInfo['end_date'] = Carbon::parse((string)$dateAttributes['End'])->toDateString();
                        } catch (\Throwable $e) {
                            // Invalid date format, leave as null
                        }
                    }
                }

                // Extract room type information
                $roomTypeElements = $ratePlan->xpath('.//*[local-name()="RoomType"]');
                foreach ($roomTypeElements as $roomType) {
                    $roomAttributes = $roomType->attributes();

                    if (isset($roomAttributes['RoomTypeCode'])) {
                        $roomTypeCode = (string)$roomAttributes['RoomTypeCode'];

                        // Extract rate information for this room type
                        $rates = $this->extractRoomRates($roomType);

                        $ratePlanInfo['room_types'][] = [
                            'room_type_code' => $roomTypeCode,
                            'rates' => $rates,
                        ];
                    }
                }

                $ratePlans[] = $ratePlanInfo;
            }
        }

        return $ratePlans;
    }

    /**
     * Extract rate information for a specific room type
     *
     * @param SimpleXMLElement $roomTypeElement The room type XML element
     * @return array Rate information for different occupancies and dates
     */
    protected function extractRoomRates(SimpleXMLElement $roomTypeElement): array
    {
        $rates = [];

        // Extract rate elements for this room type
        $rateElements = $roomTypeElement->xpath('.//*[local-name()="Rate"]');

        foreach ($rateElements as $rate) {
            $baseByGuestAmts = $rate->xpath('.//*[local-name()="BaseByGuestAmt"]');
            $additionalGuestAmts = $rate->xpath('.//*[local-name()="AdditionalGuestAmount"]');

            $rateInfo = [
                'first_adult_rate' => null,
                'second_adult_rate' => null,
                'additional_adult_rate' => null,
                'child_rate' => null,
            ];

            // Extract base rates for first and second guest
            foreach ($baseByGuestAmts as $baseByGuestAmt) {
                $attributes = $baseByGuestAmt->attributes();

                if (isset($attributes['NumberOfGuests']) && isset($attributes['AmountBeforeTax'])) {
                    $numberOfGuests = (int)$attributes['NumberOfGuests'];
                    $amount = (float)$attributes['AmountBeforeTax'];

                    if ($numberOfGuests === 1) {
                        $rateInfo['first_adult_rate'] = $amount;
                    } elseif ($numberOfGuests === 2) {
                        $rateInfo['second_adult_rate'] = $amount;
                    }
                }
            }

            // Extract additional guest rates
            foreach ($additionalGuestAmts as $additionalGuestAmt) {
                $attributes = $additionalGuestAmt->attributes();

                if (isset($attributes['AgeQualifyingCode']) && isset($attributes['Amount'])) {
                    $ageCode = (string)$attributes['AgeQualifyingCode'];
                    $amount = (float)$attributes['Amount'];

                    // Typically, 10 = adult, 8 = child
                    if ($ageCode === '10') {
                        $rateInfo['additional_adult_rate'] = $amount;
                    } elseif ($ageCode === '8') {
                        $rateInfo['child_rate'] = $amount;
                    }
                }
            }

            $rates[] = $rateInfo;
        }

        return $rates;
    }
}
