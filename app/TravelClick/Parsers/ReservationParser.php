<?php

namespace App\TravelClick\Parsers;

use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\DTOs\ReservationResponseDto;
use App\TravelClick\Enums\ReservationType;
use Carbon\Carbon;
use Exception;
use SimpleXMLElement;
use Throwable;

/**
 * Parser for TravelClick reservation responses
 *
 * Extends the base SoapResponseParser to handle specific parsing logic for
 * reservation-related messages. Extracts reservation details like confirmation numbers,
 * guest profiles, room information, and payment details.
 */
class ReservationParser extends SoapResponseParser
{
    /**
     * Additional XML namespaces used in reservation responses
     */
    protected const RESERVATION_NAMESPACES = [
        'res' => 'http://www.opentravel.org/OTA/2003/05/ReservationRQ',
    ];

    /**
     * Parse a reservation response into a structured format
     *
     * This method extends the base parse method to extract detailed reservation
     * information after the general SOAP response is processed.
     *
     * @param string $messageId The unique message identifier for tracking
     * @param string $rawResponse The raw XML response from TravelClick
     * @param ?float $durationMs The time taken to receive the response in milliseconds
     * @param array $headers Optional SOAP headers from the response
     * @return ReservationResponseDto The parsed response data with reservation details
     */
    public function parse(
        string $messageId,
        string $rawResponse,
        ?float $durationMs = null,
        array $headers = []
    ): ReservationResponseDto {
        // First, use the parent class to parse the general SOAP response
        $baseResponse = parent::parse($messageId, $rawResponse, $durationMs, $headers);

        // If the base parsing failed, return the failure response wrapped in our DTO
        if (!$baseResponse->isSuccess) {
            return ReservationResponseDto::fromSoapResponse($baseResponse);
        }

        try {
            // Extract the body content
            $xml = $this->parseXml($rawResponse);
            $bodyContent = $this->extractBodyContent($xml);

            if (!$bodyContent) {
                throw new Exception('Unable to extract reservation data from response body');
            }

            // Register additional namespaces for reservation-specific parsing
            foreach (self::RESERVATION_NAMESPACES as $prefix => $uri) {
                $bodyContent->registerXPathNamespace($prefix, $uri);
            }

            // Extract reservation details
            $reservationData = $this->parseReservationResponse($bodyContent);

            // Create a reservation response with the data
            return ReservationResponseDto::fromSoapResponse($baseResponse, $reservationData);
        } catch (Throwable $e) {
            // If parsing the reservation details fails, return a failure response
            return ReservationResponseDto::fromSoapResponse(
                SoapResponseDto::failure(
                    messageId: $messageId,
                    rawResponse: $rawResponse,
                    errorMessage: "Failed to parse reservation details: {$e->getMessage()}",
                    errorCode: 'RESERVATION_PARSE_ERROR',
                    durationMs: $durationMs
                )
            );
        }
    }

    /**
     * Parse the reservation-specific parts of the response
     *
     * @param SimpleXMLElement $xml The body content of the SOAP response
     * @return array The extracted reservation data
     */
    protected function parseReservationResponse(SimpleXMLElement $xml): array
    {
        // Detect the type of reservation response
        $reservationType = $this->extractReservationType($xml);

        // Extract common reservation data
        $reservationData = $this->extractReservationData($xml);
        $reservationData['type'] = $reservationType->value;

        // Add confirmation number
        $confirmationNumber = $this->extractConfirmationNumber($xml);
        if ($confirmationNumber) {
            $reservationData['confirmation_number'] = $confirmationNumber;
        }

        // Extract guest profile information
        $guestProfile = $this->extractGuestProfile($xml);
        if ($guestProfile) {
            $reservationData['guest'] = $guestProfile;
        }

        // Extract room information
        $roomInfo = $this->extractRoomInformation($xml);
        if ($roomInfo) {
            $reservationData['room'] = $roomInfo;
        }

        // Extract payment information
        $paymentInfo = $this->extractPaymentInformation($xml);
        if ($paymentInfo) {
            $reservationData['payment'] = $paymentInfo;
        }

        // Add type-specific information
        $this->addTypeSpecificData($xml, $reservationType, $reservationData);

        return $reservationData;
    }

    /**
     * Determine the reservation type from the response
     *
     * @param SimpleXMLElement $xml The parsed XML response
     * @return ReservationType The detected reservation type
     */
    protected function extractReservationType(SimpleXMLElement $xml): ReservationType
    {
        // Default to transient reservation
        $type = ReservationType::TRANSIENT;

        // Check for company profile (corporate reservation)
        $companyProfile = $xml->xpath('.//ota:Profile[@ProfileType="Company"]');
        if (!empty($companyProfile)) {
            return ReservationType::CORPORATE;
        }

        // Check for travel agency profile
        $travelAgencyProfile = $xml->xpath('.//ota:Profile[@ProfileType="TravelAgent"]');
        if (!empty($travelAgencyProfile)) {
            return ReservationType::TRAVEL_AGENCY;
        }

        // Check for group reservation
        $groupBlocks = $xml->xpath('.//ota:ResGlobalInfo//ota:InvBlockCode');
        if (!empty($groupBlocks)) {
            return ReservationType::GROUP;
        }

        // Check for package reservation
        $packageCode = $xml->xpath('.//ota:RoomStay//ota:RatePlanCode[@RatePlanType="Package"]');
        if (!empty($packageCode)) {
            return ReservationType::PACKAGE;
        }

        // Check for alternate payment
        $alternatePayment = $xml->xpath('.//ota:ResGlobalInfo//ota:Comments[contains(., "Alternate Provider")]');
        if (!empty($alternatePayment)) {
            return ReservationType::ALTERNATE_PAYMENT;
        }

        // Default is transient
        return $type;
    }

    /**
     * Extract basic reservation data common to all types
     *
     * @param SimpleXMLElement $xml The parsed XML response
     * @return array Common reservation data
     */
    protected function extractReservationData(SimpleXMLElement $xml): array
    {
        $data = [];

        // Extract reservation ID
        $reservationId = $xml->xpath('.//ota:UniqueID[@Type="14" or @Type="Reservation"]');
        if (!empty($reservationId)) {
            $data['reservation_id'] = (string)$reservationId[0]->attributes()['ID'];
        }

        // Extract creation timestamp
        $createDateTime = $xml->xpath('.//ota:ResGlobalInfo//ota:CreateDateTime');
        if (!empty($createDateTime)) {
            try {
                $data['created_at'] = Carbon::parse((string)$createDateTime[0])->toIso8601String();
            } catch (Throwable $e) {
                // Ignore parse errors for dates
            }
        }

        // Extract status
        $status = $xml->xpath('.//*[@ResStatus]');
        if (!empty($status)) {
            $data['status'] = (string)$status[0]->attributes()['ResStatus'];
        }

        // Extract reservation dates
        $stayDates = $this->extractStayDates($xml);
        if ($stayDates) {
            $data = array_merge($data, $stayDates);
        }

        return $data;
    }

    /**
     * Extract the confirmation number from the response
     *
     * @param SimpleXMLElement $xml The parsed XML response
     * @return string|null The confirmation number if available
     */
    protected function extractConfirmationNumber(SimpleXMLElement $xml): ?string
    {
        // Check for ResID_Value in the response body
        $resIdValue = $xml->xpath('.//ota:UniqueID[@Type="14" or @Type="Reservation"]');
        if (!empty($resIdValue)) {
            return (string)$resIdValue[0]->attributes()['ID'];
        }

        // Check for PMS reservation number
        $pmsResId = $xml->xpath('.//ota:HotelReservationID');
        if (!empty($pmsResId)) {
            return (string)$pmsResId[0]->attributes()['ResID_Value'];
        }

        return null;
    }

    /**
     * Extract guest profile information
     *
     * @param SimpleXMLElement $xml The parsed XML response
     * @return array|null Guest profile data
     */
    protected function extractGuestProfile(SimpleXMLElement $xml): ?array
    {
        $guestProfiles = $xml->xpath('.//ota:Profile[@ProfileType="Customer"]');
        if (empty($guestProfiles)) {
            return null;
        }

        $profile = $guestProfiles[0];
        $guest = [];

        // Extract guest name
        $nameInfo = $profile->xpath('.//ota:PersonName');
        if (!empty($nameInfo)) {
            $nameInfo = $nameInfo[0];

            if (isset($nameInfo->NamePrefix)) {
                $guest['title'] = (string)$nameInfo->NamePrefix;
            }

            if (isset($nameInfo->GivenName)) {
                $guest['first_name'] = (string)$nameInfo->GivenName;
            }

            if (isset($nameInfo->MiddleName)) {
                $guest['middle_name'] = (string)$nameInfo->MiddleName;
            }

            if (isset($nameInfo->Surname)) {
                $guest['last_name'] = (string)$nameInfo->Surname;
            }
        }

        // Extract contact information
        $emails = $profile->xpath('.//ota:Email');
        if (!empty($emails)) {
            $guest['email'] = (string)$emails[0];
        }

        $telephones = $profile->xpath('.//ota:Telephone');
        if (!empty($telephones)) {
            $phones = [];
            foreach ($telephones as $telephone) {
                $attr = $telephone->attributes();
                $phoneType = isset($attr['PhoneTechType']) ? (string)$attr['PhoneTechType'] : 'unknown';
                $phoneNumber = isset($attr['PhoneNumber']) ? (string)$attr['PhoneNumber'] : '';

                if (!empty($phoneNumber)) {
                    $phones[$phoneType] = $phoneNumber;
                }
            }

            if (!empty($phones)) {
                $guest['phones'] = $phones;
            }
        }

        // Extract address
        $addresses = $profile->xpath('.//ota:Address');
        if (!empty($addresses)) {
            $address = $addresses[0];
            $addressData = [];

            if (isset($address->AddressLine)) {
                // May have multiple address lines
                $addressLines = [];
                foreach ($address->AddressLine as $line) {
                    $addressLines[] = (string)$line;
                }
                $addressData['address_lines'] = $addressLines;
            }

            if (isset($address->CityName)) {
                $addressData['city'] = (string)$address->CityName;
            }

            if (isset($address->PostalCode)) {
                $addressData['postal_code'] = (string)$address->PostalCode;
            }

            if (isset($address->StateProv)) {
                $stateAttr = $address->StateProv->attributes();
                $addressData['state'] = isset($stateAttr['StateCode']) ?
                    (string)$stateAttr['StateCode'] : (string)$address->StateProv;
            }

            if (isset($address->CountryName)) {
                $countryAttr = $address->CountryName->attributes();
                $addressData['country'] = isset($countryAttr['Code']) ?
                    (string)$countryAttr['Code'] : (string)$address->CountryName;
            }

            if (!empty($addressData)) {
                $guest['address'] = $addressData;
            }
        }

        return $guest;
    }

    /**
     * Extract stay dates from reservation
     *
     * @param SimpleXMLElement $xml The parsed XML response
     * @return array|null Array with check-in and check-out dates
     */
    protected function extractStayDates(SimpleXMLElement $xml): ?array
    {
        $roomStays = $xml->xpath('.//ota:RoomStay');
        if (empty($roomStays)) {
            return null;
        }

        $dates = [];

        // Search for TimeSpan elements
        $timeSpans = $roomStays[0]->xpath('.//ota:TimeSpan');
        if (!empty($timeSpans)) {
            $attributes = $timeSpans[0]->attributes();

            if (isset($attributes['Start'])) {
                try {
                    $dates['check_in'] = Carbon::parse((string)$attributes['Start'])->toIso8601String();
                } catch (Throwable $e) {
                    // Ignore parse errors
                }
            }

            if (isset($attributes['End'])) {
                try {
                    $dates['check_out'] = Carbon::parse((string)$attributes['End'])->toIso8601String();
                } catch (Throwable $e) {
                    // Ignore parse errors
                }
            }
        }

        return !empty($dates) ? $dates : null;
    }

    /**
     * Extract room information from the reservation
     *
     * @param SimpleXMLElement $xml The parsed XML response
     * @return array|null Room information
     */
    protected function extractRoomInformation(SimpleXMLElement $xml): ?array
    {
        $roomStays = $xml->xpath('.//ota:RoomStay');
        if (empty($roomStays)) {
            return null;
        }

        $roomStay = $roomStays[0];
        $roomInfo = [];

        // Get room type info
        $roomTypes = $roomStay->xpath('.//ota:RoomType');
        if (!empty($roomTypes)) {
            $roomType = $roomTypes[0];
            $attributes = $roomType->attributes();

            if (isset($attributes['RoomTypeCode'])) {
                $roomInfo['room_type_code'] = (string)$attributes['RoomTypeCode'];
            }

            if (isset($attributes['RoomDescription'])) {
                $roomInfo['room_description'] = (string)$attributes['RoomDescription'];
            }

            if (isset($attributes['NumberOfUnits'])) {
                $roomInfo['number_of_rooms'] = (int)(string)$attributes['NumberOfUnits'];
            }
        }

        // Get rate plan info
        $ratePlans = $roomStay->xpath('.//ota:RatePlan');
        if (!empty($ratePlans)) {
            $ratePlan = $ratePlans[0];
            $attributes = $ratePlan->attributes();

            if (isset($attributes['RatePlanCode'])) {
                $roomInfo['rate_plan_code'] = (string)$attributes['RatePlanCode'];
            }

            if (isset($attributes['RatePlanName'])) {
                $roomInfo['rate_plan_name'] = (string)$attributes['RatePlanName'];
            }
        }

        // Get guest counts
        $guestCounts = $roomStay->xpath('.//ota:GuestCounts/ota:GuestCount');
        if (!empty($guestCounts)) {
            $guests = [];

            foreach ($guestCounts as $guestCount) {
                $attributes = $guestCount->attributes();
                $ageQualifying = isset($attributes['AgeQualifyingCode']) ? (string)$attributes['AgeQualifyingCode'] : null;
                $count = isset($attributes['Count']) ? (int)(string)$attributes['Count'] : 0;

                // Map age qualifying codes to guest types
                if ($ageQualifying === '10') {
                    $guests['adults'] = $count;
                } elseif ($ageQualifying === '8') {
                    $guests['children'] = $count;
                } elseif ($ageQualifying === '7') {
                    $guests['infants'] = $count;
                }
            }

            if (!empty($guests)) {
                $roomInfo['guests'] = $guests;
            }
        }

        // Get rate information
        $rateInfo = $this->extractRateInformation($roomStay);
        if ($rateInfo) {
            $roomInfo['rates'] = $rateInfo;
        }

        return !empty($roomInfo) ? $roomInfo : null;
    }

    /**
     * Extract rate information from room stay
     *
     * @param SimpleXMLElement $roomStay The RoomStay element
     * @return array|null Rate information
     */
    protected function extractRateInformation(SimpleXMLElement $roomStay): ?array
    {
        $rateElements = $roomStay->xpath('.//ota:RoomRate');
        if (empty($rateElements)) {
            return null;
        }

        $rates = [];

        foreach ($rateElements as $rateElement) {
            $rateInfo = [];
            $attributes = $rateElement->attributes();

            // Extract rate date range
            if (isset($attributes['EffectiveDate'])) {
                try {
                    $rateInfo['effective_date'] = Carbon::parse((string)$attributes['EffectiveDate'])->toIso8601String();
                } catch (Throwable $e) {
                    // Ignore parse errors
                }
            }

            if (isset($attributes['ExpireDate'])) {
                try {
                    $rateInfo['expire_date'] = Carbon::parse((string)$attributes['ExpireDate'])->toIso8601String();
                } catch (Throwable $e) {
                    // Ignore parse errors
                }
            }

            // Extract base rate
            $rates = $rateElement->xpath('.//ota:Rate');
            if (!empty($rates)) {
                $rateAttributes = $rates[0]->attributes();

                if (isset($rateAttributes['AmountBeforeTax'])) {
                    $rateInfo['amount_before_tax'] = (float)(string)$rateAttributes['AmountBeforeTax'];
                }

                if (isset($rateAttributes['AmountAfterTax'])) {
                    $rateInfo['amount_after_tax'] = (float)(string)$rateAttributes['AmountAfterTax'];
                }

                if (isset($rateAttributes['CurrencyCode'])) {
                    $rateInfo['currency_code'] = (string)$rateAttributes['CurrencyCode'];
                }
            }

            // Add to rates array if we have data
            if (!empty($rateInfo)) {
                $rates[] = $rateInfo;
            }
        }

        return !empty($rates) ? $rates : null;
    }

    /**
     * Extract payment information from the reservation
     *
     * @param SimpleXMLElement $xml The parsed XML response
     * @return array|null Payment information
     */
    protected function extractPaymentInformation(SimpleXMLElement $xml): ?array
    {
        $paymentInfo = [];

        // Look for guarantee information
        $guaranteeElements = $xml->xpath('.//ota:GuaranteePayment');
        if (!empty($guaranteeElements)) {
            $guarantee = $guaranteeElements[0];

            // Get guarantee type
            $attributes = $guarantee->attributes();
            if (isset($attributes['GuaranteeCode'])) {
                $paymentInfo['guarantee_code'] = (string)$attributes['GuaranteeCode'];
            }

            if (isset($attributes['GuaranteeType'])) {
                $paymentInfo['guarantee_type'] = (string)$attributes['GuaranteeType'];
            }

            // Extract credit card details
            $cardInfo = $guarantee->xpath('.//ota:PaymentCard');
            if (!empty($cardInfo)) {
                $card = $cardInfo[0];
                $cardDetails = [];
                $cardAttributes = $card->attributes();

                if (isset($cardAttributes['CardCode'])) {
                    $cardDetails['card_type'] = (string)$cardAttributes['CardCode'];
                }

                if (isset($cardAttributes['CardNumber'])) {
                    // Usually masked in responses
                    $cardDetails['card_number'] = (string)$cardAttributes['CardNumber'];
                }

                if (isset($cardAttributes['ExpireDate'])) {
                    $cardDetails['expiry_date'] = (string)$cardAttributes['ExpireDate'];
                }

                if (isset($cardAttributes['CardHolderName'])) {
                    $cardDetails['cardholder_name'] = (string)$cardAttributes['CardHolderName'];
                }

                if (!empty($cardDetails)) {
                    $paymentInfo['payment_card'] = $cardDetails;
                }
            }
        }

        // Extract deposit information
        $depositElements = $xml->xpath('.//ota:DepositPayment');
        if (!empty($depositElements)) {
            $deposit = $depositElements[0];
            $depositInfo = [];

            $attributes = $deposit->attributes();
            if (isset($attributes['AmountPercent'])) {
                $depositInfo['amount'] = (float)(string)$attributes['AmountPercent'];
            }

            if (isset($attributes['DueDate'])) {
                try {
                    $depositInfo['due_date'] = Carbon::parse((string)$attributes['DueDate'])->toIso8601String();
                } catch (Throwable $e) {
                    // Ignore parse errors
                }
            }

            if (!empty($depositInfo)) {
                $paymentInfo['deposit'] = $depositInfo;
            }
        }

        // Check for alternate payment from comments
        $alternatePaymentComments = $xml->xpath('.//ota:ResGlobalInfo//ota:Comments[contains(., "Alternate Provider")]');
        if (!empty($alternatePaymentComments)) {
            $paymentInfo['alternate_payment'] = true;
            $paymentInfo['alternate_payment_details'] = (string)$alternatePaymentComments[0];
        }

        return !empty($paymentInfo) ? $paymentInfo : null;
    }

    /**
     * Add additional data specific to the reservation type
     *
     * @param SimpleXMLElement $xml The parsed XML response
     * @param ReservationType $type The detected reservation type
     * @param array &$data The reservation data array to update
     */
    protected function addTypeSpecificData(SimpleXMLElement $xml, ReservationType $type, array &$data): void
    {
        switch ($type) {
            case ReservationType::TRAVEL_AGENCY:
                $this->addTravelAgencyData($xml, $data);
                break;

            case ReservationType::CORPORATE:
                $this->addCorporateData($xml, $data);
                break;

            case ReservationType::GROUP:
                $this->addGroupData($xml, $data);
                break;

            case ReservationType::PACKAGE:
                $this->addPackageData($xml, $data);
                break;

            case ReservationType::ALTERNATE_PAYMENT:
                $this->addAlternatePaymentData($xml, $data);
                break;

            // For transient, no special handling needed
            case ReservationType::TRANSIENT:
            default:
                break;
        }
    }

    /**
     * Add travel agency specific information
     *
     * @param SimpleXMLElement $xml The parsed XML response
     * @param array &$data The reservation data array to update
     */
    protected function addTravelAgencyData(SimpleXMLElement $xml, array &$data): void
    {
        $agencyProfiles = $xml->xpath('.//ota:Profile[@ProfileType="TravelAgent"]');
        if (empty($agencyProfiles)) {
            return;
        }

        $agency = [];
        $profile = $agencyProfiles[0];

        // Get agency name and IATA number
        $companyInfo = $profile->xpath('.//ota:CompanyName');
        if (!empty($companyInfo)) {
            $company = $companyInfo[0];
            $attributes = $company->attributes();

            $agency['name'] = (string)$company;

            if (isset($attributes['Code'])) {
                $agency['iata_number'] = (string)$attributes['Code'];
            }

            if (isset($attributes['CodeContext']) && (string)$attributes['CodeContext'] === 'IATA') {
                $agency['code_type'] = 'IATA';
            }
        }

        // Get agency contact details
        $emails = $profile->xpath('.//ota:Email');
        if (!empty($emails)) {
            $agency['email'] = (string)$emails[0];
        }

        $telephones = $profile->xpath('.//ota:Telephone');
        if (!empty($telephones)) {
            $telephone = $telephones[0];
            $attributes = $telephone->attributes();

            if (isset($attributes['PhoneNumber'])) {
                $agency['phone'] = (string)$attributes['PhoneNumber'];
            }
        }

        // Commission information
        $commissionElements = $xml->xpath('.//ota:Commission');
        if (!empty($commissionElements)) {
            $commission = $commissionElements[0];
            $attributes = $commission->attributes();

            if (isset($attributes['Percent'])) {
                $agency['commission_percent'] = (float)(string)$attributes['Percent'];
            }

            if (isset($attributes['Amount'])) {
                $agency['commission_amount'] = (float)(string)$attributes['Amount'];
            }
        }

        // Add agency data if we have any
        if (!empty($agency)) {
            $data['travel_agency'] = $agency;
        }
    }

    /**
     * Add corporate specific information
     *
     * @param SimpleXMLElement $xml The parsed XML response
     * @param array &$data The reservation data array to update
     */
    protected function addCorporateData(SimpleXMLElement $xml, array &$data): void
    {
        $corporateProfiles = $xml->xpath('.//ota:Profile[@ProfileType="Company"]');
        if (empty($corporateProfiles)) {
            return;
        }

        $corporate = [];
        $profile = $corporateProfiles[0];

        // Get company name and ID
        $companyInfo = $profile->xpath('.//ota:CompanyName');
        if (!empty($companyInfo)) {
            $company = $companyInfo[0];
            $attributes = $company->attributes();

            $corporate['name'] = (string)$company;

            if (isset($attributes['Code'])) {
                $corporate['company_code'] = (string)$attributes['Code'];
            }

            if (isset($attributes['CompanyShortName'])) {
                $corporate['short_name'] = (string)$attributes['CompanyShortName'];
            }
        }

        // Get company address
        $addresses = $profile->xpath('.//ota:Address');
        if (!empty($addresses)) {
            $address = $addresses[0];
            $addressData = [];

            if (isset($address->AddressLine)) {
                // May have multiple address lines
                $addressLines = [];
                foreach ($address->AddressLine as $line) {
                    $addressLines[] = (string)$line;
                }
                $addressData['address_lines'] = $addressLines;
            }

            if (isset($address->CityName)) {
                $addressData['city'] = (string)$address->CityName;
            }

            if (isset($address->PostalCode)) {
                $addressData['postal_code'] = (string)$address->PostalCode;
            }

            if (isset($address->StateProv)) {
                $stateAttr = $address->StateProv->attributes();
                $addressData['state'] = isset($stateAttr['StateCode']) ?
                    (string)$stateAttr['StateCode'] : (string)$address->StateProv;
            }

            if (isset($address->CountryName)) {
                $countryAttr = $address->CountryName->attributes();
                $addressData['country'] = isset($countryAttr['Code']) ?
                    (string)$countryAttr['Code'] : (string)$address->CountryName;
            }

            if (!empty($addressData)) {
                $corporate['address'] = $addressData;
            }
        }

        // Get corporate reference
        $references = $xml->xpath('.//ota:ResGlobalInfo/ota:HotelReservationIDs/ota:HotelReservationID[@ResID_Type="Corporate"]');
        if (!empty($references)) {
            $reference = $references[0];
            $attributes = $reference->attributes();

            if (isset($attributes['ResID_Value'])) {
                $corporate['reference_number'] = (string)$attributes['ResID_Value'];
            }
        }

        // Add corporate data if we have any
        if (!empty($corporate)) {
            $data['corporate'] = $corporate;
        }
    }

    /**
     * Add group specific information
     *
     * @param SimpleXMLElement $xml The parsed XML response
     * @param array &$data The reservation data array to update
     */
    protected function addGroupData(SimpleXMLElement $xml, array &$data): void
    {
        $group = [];

        // Look for group block code
        $invBlockElements = $xml->xpath('.//ota:ResGlobalInfo//ota:InvBlockCode');
        if (!empty($invBlockElements)) {
            $group['block_code'] = (string)$invBlockElements[0];
        }

        // Look for group name
        $groupNameElements = $xml->xpath('.//ota:ResGlobalInfo//ota:GroupName');
        if (!empty($groupNameElements)) {
            $group['name'] = (string)$groupNameElements[0];
        }

        // Add group data if we have any
        if (!empty($group)) {
            $data['group'] = $group;
        }
    }

    /**
     * Add package specific information
     *
     * @param SimpleXMLElement $xml The parsed XML response
     * @param array &$data The reservation data array to update
     */
    protected function addPackageData(SimpleXMLElement $xml, array &$data): void
    {
        $package = [];

        // Look for package code and name
        $ratePlans = $xml->xpath('.//ota:RatePlan[@RatePlanType="Package"]');
        if (!empty($ratePlans)) {
            $ratePlan = $ratePlans[0];
            $attributes = $ratePlan->attributes();

            if (isset($attributes['RatePlanCode'])) {
                $package['code'] = (string)$attributes['RatePlanCode'];
            }

            if (isset($attributes['RatePlanName'])) {
                $package['name'] = (string)$attributes['RatePlanName'];
            }
        }

        // Look for package description
        $descriptions = $xml->xpath('.//ota:RatePlan[@RatePlanType="Package"]//ota:Description');
        if (!empty($descriptions)) {
            $package['description'] = (string)$descriptions[0];
        }

        // Add package data if we have any
        if (!empty($package)) {
            $data['package'] = $package;
        }
    }

    /**
     * Add alternate payment specific information
     *
     * @param SimpleXMLElement $xml The parsed XML response
     * @param array &$data The reservation data array to update
     */
    protected function addAlternatePaymentData(SimpleXMLElement $xml, array &$data): void
    {
        $alternatePayment = [];

        // Look for alternate payment details in comments
        $commentElements = $xml->xpath('.//ota:ResGlobalInfo//ota:Comments[contains(., "Alternate Provider")]');
        if (!empty($commentElements)) {
            $comment = (string)$commentElements[0];

            // Parse the comment text to extract provider and amount information
            // Format is typically: "Alternate Provider: {provider}, Amount: {amount}"
            if (preg_match('/Alternate Provider: ([^,]+), Amount: ([0-9.]+)/', $comment, $matches)) {
                if (isset($matches[1])) {
                    $alternatePayment['provider'] = trim($matches[1]);
                }

                if (isset($matches[2])) {
                    $alternatePayment['amount'] = (float)$matches[2];
                }
            } else {
                // If we can't parse the format, just store the whole comment
                $alternatePayment['comment'] = $comment;
            }
        }

        // Look for deposit amount in deposit payment
        $depositElements = $xml->xpath('.//ota:DepositPayment');
        if (!empty($depositElements)) {
            $deposit = $depositElements[0];
            $attributes = $deposit->attributes();

            if (isset($attributes['AmountPercent'])) {
                $alternatePayment['deposit_amount'] = (float)(string)$attributes['AmountPercent'];
            }
        }

        // Add alternate payment data if we have any
        if (!empty($alternatePayment)) {
            $data['alternate_payment'] = $alternatePayment;
        }
    }
}