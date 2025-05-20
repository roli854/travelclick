<?php

declare(strict_types=1);

namespace App\TravelClick\Builders;

use App\TravelClick\DTOs\GuestDataDto;
use App\TravelClick\DTOs\ProfileDataDto;
use App\TravelClick\DTOs\ReservationDataDto;
use App\TravelClick\DTOs\RoomStayDataDto;
use App\TravelClick\DTOs\ServiceRequestDto;
use App\TravelClick\DTOs\SoapHeaderDto;
use App\TravelClick\DTOs\SpecialRequestDto;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\ReservationType;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * XML Builder for TravelClick HTNG 2011B reservation messages
 *
 * This class builds OTA_HotelResNotifRQ XML messages for all types of
 * reservations (transient, travel agency, corporate, package, group,
 * and alternate payment) according to HTNG 2011B specifications.
 */
class ReservationXmlBuilder extends XmlBuilder
{
  /**
   * Types of source of business mappings
   *
   * @var array<string, string>
   */
  protected array $sourceOfBusinessMap = [
    'WEB' => 'WEB',
    'WEBTA' => 'WEB Travel Agent',
    'WEBGRP' => 'WEB Group',
    'SABRE' => 'SABRE',
    'WORLDSPAN' => 'WORLDSPAN',
    'AMADEUS' => 'AMADEUS',
    'GALILEO' => 'GALILEO',
    'TRAVELWEB' => 'TRAVELWEB',
    'CALLCTR' => 'Call (3rd party)',
    'CALLHOTEL' => 'Call (property)',
    'PMS' => 'PMS',
    'IHOS' => 'IHOS',
  ];

  /**
   * Create a new ReservationXmlBuilder instance
   *
   * @param SoapHeaderDto $soapHeaders
   * @param bool $validateXml
   * @param bool $formatOutput
   */
  public function __construct(
    SoapHeaderDto $soapHeaders,
    bool $validateXml = true,
    bool $formatOutput = false
  ) {
    parent::__construct(
      MessageType::RESERVATION,
      $soapHeaders,
      $validateXml,
      $formatOutput
    );
  }

  /**
   * Create XML message for a reservation
   *
   * @param ReservationDataDto $reservationData
   * @return string The complete XML message
   */
  public function buildReservationXml(ReservationDataDto $reservationData): string
  {
    return $this->build([
      'reservationData' => $reservationData,
    ]);
  }

  /**
   * Build the message body for a reservation
   *
   * @param array<string, mixed> $messageData
   * @return array<string, mixed>
   * @throws InvalidArgumentException If data is invalid
   */
  protected function buildMessageBody(array $messageData): array
  {
    if (!isset($messageData['reservationData']) || !($messageData['reservationData'] instanceof ReservationDataDto)) {
      throw new InvalidArgumentException('Reservation data DTO is required');
    }

    $reservationData = $messageData['reservationData'];

    // Build the OTA message container
    $otaRoot = $this->getOtaRootElement();
    $body = [
      $otaRoot => [
        '_attributes' => $this->getOtaMessageAttributes()
      ]
    ];

    // For cancellations, the structure is simpler
    if ($reservationData->isCancellation()) {
      $body[$otaRoot]['POS'] = $this->buildPOS($reservationData);
      $body[$otaRoot]['UniqueID'] = [
        '_attributes' => [
          'ID' => $reservationData->confirmationNumber ?? $reservationData->reservationId,
          'Type' => '14', // Reservation
        ],
      ];
      $body[$otaRoot]['CancelRequest'] = $this->buildCancelRequest($reservationData);

      return $body;
    }

    // For new reservations and modifications
    $body[$otaRoot]['POS'] = $this->buildPOS($reservationData);

    // Add profiles if applicable (Travel Agency, Corporate, Group)
    if ($reservationData->hasProfile()) {
      $body[$otaRoot]['Profiles'] = $this->buildProfiles($reservationData);
    }

    // Add guests information
    $body[$otaRoot]['ResGuests'] = $this->buildResGuests(
      $reservationData->primaryGuest,
      $reservationData->additionalGuests
    );

    // Add room stays
    $body[$otaRoot]['RoomStays'] = $this->buildRoomStays($reservationData);

    // Add global information
    $body[$otaRoot]['ResGlobalInfo'] = $this->buildResGlobalInfo($reservationData);

    return $body;
  }

  /**
   * Validate message data for a reservation
   *
   * @param array<string, mixed> $messageData
   * @throws InvalidArgumentException If data is invalid
   */
  protected function validateMessageData(array $messageData): void
  {
    // Required field validation
    if (!isset($messageData['reservationData'])) {
      throw new InvalidArgumentException('Reservation data is required');
    }

    if (!($messageData['reservationData'] instanceof ReservationDataDto)) {
      throw new InvalidArgumentException('Reservation data must be an instance of ReservationDataDto');
    }

    $reservationData = $messageData['reservationData'];

    // Ensure hotel code matches the one in SOAP headers
    if ($reservationData->hotelCode !== $this->soapHeaders->hotelCode) {
      throw new InvalidArgumentException(
        "Hotel code in reservation data ({$reservationData->hotelCode}) does not match SOAP headers ({$this->soapHeaders->hotelCode})"
      );
    }

    // Validate special cases for different reservation types
    switch ($reservationData->reservationType) {
      case ReservationType::TRAVEL_AGENCY:
        if (!$reservationData->hasProfile() || !$reservationData->profile->isTravelAgency()) {
          throw new InvalidArgumentException('Travel agency reservation requires a travel agency profile');
        }
        break;

      case ReservationType::CORPORATE:
        if (!$reservationData->hasProfile() || !$reservationData->profile->isCorporate()) {
          throw new InvalidArgumentException('Corporate reservation requires a corporate profile');
        }
        break;

      case ReservationType::GROUP:
        if (empty($reservationData->invBlockCode)) {
          throw new InvalidArgumentException('Group reservation requires an inventory block code');
        }
        break;

      case ReservationType::ALTERNATE_PAYMENT:
        if (
          empty($reservationData->alternatePaymentType) ||
          $reservationData->alternatePaymentAmount === null
        ) {
          throw new InvalidArgumentException('Alternate payment reservation requires payment type and amount');
        }
        break;

      default:
        // No special validation for other types
        break;
    }
  }

  /**
   * Build the POS (Point of Sale) section
   *
   * @param ReservationDataDto $data
   * @return array<string, mixed>
   */
  protected function buildPOS(ReservationDataDto $data): array
  {
    // Map source of business to standard code
    $sourceCode = $this->sourceOfBusinessMap[$data->sourceOfBusiness] ?? $data->sourceOfBusiness;

    return [
      'Source' => [
        'RequestorID' => [
          '_attributes' => [
            'ID' => $this->soapHeaders->username,
            'Type' => '10',
          ],
        ],
        'BookingChannel' => [
          'CompanyName' => [
            '_attributes' => [
              'Code' => $sourceCode,
            ],
            '_value' => $sourceCode,
          ],
        ],
      ],
    ];
  }

  /**
   * Build profiles section for travel agency, corporate, or group
   *
   * @param ReservationDataDto $data
   * @return array<string, mixed>
   */
  protected function buildProfiles(ReservationDataDto $data): array
  {
    if (!$data->hasProfile()) {
      return [];
    }

    $profile = $data->profile;
    $profiles = ['Profile' => []];

    // Common profile attributes
    $profileData = [
      '_attributes' => [
        'ProfileType' => $profile->profileType,
      ],
      'CompanyInfo' => [
        '_attributes' => [
          'CompanyShortName' => $profile->shortName ?? substr($profile->name, 0, 20),
        ],
        '_value' => $profile->name,
      ],
    ];

    // Add address if available
    if ($profile->hasValidAddress()) {
      $profileData['CompanyInfo']['AddressInfo'] = [
        'AddressLine' => [$profile->addressLine1],
        'CityName' => $profile->city,
        'PostalCode' => $profile->postalCode,
        'CountryName' => [
          '_attributes' => [
            'Code' => $profile->countryCode,
          ],
        ],
      ];

      // Add optional address elements
      if (!empty($profile->addressLine2)) {
        $profileData['CompanyInfo']['AddressInfo']['AddressLine'][] = $profile->addressLine2;
      }

      if (!empty($profile->state)) {
        $profileData['CompanyInfo']['AddressInfo']['StateProv'] = [
          '_attributes' => [
            'StateCode' => $profile->state,
          ],
          '_value' => $profile->state,
        ];
      }
    }

    // Add contact info if available
    if ($profile->hasValidContactInfo()) {
      $profileData['CompanyInfo']['ContactInfo'] = [];

      if (!empty($profile->phone)) {
        $profileData['CompanyInfo']['ContactInfo']['PhoneNumber'] = [
          '_attributes' => [
            'PhoneNumber' => $profile->phone,
          ],
        ];
      }

      if (!empty($profile->email)) {
        $profileData['CompanyInfo']['ContactInfo']['Email'] = $profile->email;
      }
    }

    // Add type-specific elements
    switch ($profile->profileType) {
      case ProfileDataDto::TYPE_TRAVEL_AGENCY:
        // Add IATA number if available
        if (!empty($profile->iataNumber)) {
          $profileData['CompanyInfo']['_attributes']['Code'] = $profile->iataNumber;
          $profileData['CompanyInfo']['_attributes']['CodeContext'] = 'IATA';
        }

        // Add commission if available
        if ($profile->hasCommission()) {
          $profileData['CompanyInfo']['Commission'] = [
            '_attributes' => [
              'Percent' => $profile->commissionPercentage,
            ],
          ];
        }
        break;

      case ProfileDataDto::TYPE_CORPORATE:
        // Add corporate ID if available
        if (!empty($profile->corporateId)) {
          $profileData['CompanyInfo']['_attributes']['CorporateID'] = $profile->corporateId;
        }
        break;

      case ProfileDataDto::TYPE_GROUP:
        // No special handling for group profiles
        break;
    }

    $profiles['Profile'] = $profileData;
    return $profiles;
  }

  /**
   * Build ResGuests section for primary and additional guests
   *
   * @param GuestDataDto $primaryGuest
   * @param Collection $additionalGuests
   * @return array<string, mixed>
   */
  protected function buildResGuests(GuestDataDto $primaryGuest, Collection $additionalGuests): array
  {
    $resGuests = ['ResGuest' => []];

    // Add primary guest
    $primaryGuestData = $this->buildResGuest($primaryGuest, 1, true);

    // Add additional guests if any
    if ($additionalGuests->isNotEmpty()) {
      $resGuests['ResGuest'] = [$primaryGuestData];

      $additionalGuests->each(function ($guest, $index) use (&$resGuests) {
        $resGuests['ResGuest'][] = $this->buildResGuest($guest, $index + 2, false);
      });
    } else {
      $resGuests['ResGuest'] = $primaryGuestData;
    }

    return $resGuests;
  }

  /**
   * Build a single ResGuest element
   *
   * @param GuestDataDto $guest
   * @param int $resGuestRPH The guest reference number
   * @param bool $isPrimary Whether this is the primary guest
   * @return array<string, mixed>
   */
  protected function buildResGuest(GuestDataDto $guest, int $resGuestRPH, bool $isPrimary): array
  {
    // Build basic guest info
    $guestData = [
      '_attributes' => [
        'ResGuestRPH' => (string)$resGuestRPH,
        'PrimaryIndicator' => $isPrimary ? 'true' : 'false',
        'AgeQualifyingCode' => $this->getAgeQualifyingCode($guest),
      ],
      'Profiles' => [
        'ProfileInfo' => [
          'Profile' => [
            'Customer' => [
              'PersonName' => $this->buildPersonName($guest),
            ],
          ],
        ],
      ],
    ];

    // Add age if available
    if ($guest->age !== null) {
      $guestData['_attributes']['Age'] = (string)$guest->age;
    }

    // Add contact info if available
    if ($guest->hasValidContactInfo() || $guest->hasValidAddress()) {
      $customer = &$guestData['Profiles']['ProfileInfo']['Profile']['Customer'];

      // Add contact methods (email, phone)
      if ($guest->hasValidContactInfo()) {
        $customer['ContactInfo'] = [];

        if (!empty($guest->email)) {
          $customer['ContactInfo']['Email'] = $guest->email;
        }

        if (!empty($guest->phone)) {
          $customer['ContactInfo']['PhoneNumber'] = [
            '_attributes' => [
              'PhoneNumber' => $guest->phone,
              'PhoneTechType' => '1', // Voice
            ],
          ];
        }

        if (!empty($guest->phoneMobile)) {
          $customer['ContactInfo']['PhoneNumber'][] = [
            '_attributes' => [
              'PhoneNumber' => $guest->phoneMobile,
              'PhoneTechType' => '3', // Mobile
            ],
          ];
        }
      }

      // Add address if available
      if ($guest->hasValidAddress()) {
        $customer['Address'] = [
          '_attributes' => [
            'Type' => '1', // Home
          ],
          'AddressLine' => [$guest->addressLine1],
          'CityName' => $guest->city,
          'PostalCode' => $guest->postalCode,
          'CountryName' => [
            '_attributes' => [
              'Code' => $guest->countryCode,
            ],
          ],
        ];

        // Add optional address elements
        if (!empty($guest->addressLine2)) {
          $customer['Address']['AddressLine'][] = $guest->addressLine2;
        }

        if (!empty($guest->state)) {
          $customer['Address']['StateProv'] = [
            '_attributes' => [
              'StateCode' => $guest->state,
            ],
            '_value' => $guest->state,
          ];
        }
      }
    }

    // Add document information if available
    if (!empty($guest->passportNumber)) {
      $guestData['Profiles']['ProfileInfo']['Profile']['Customer']['Document'] = [
        '_attributes' => [
          'DocID' => $guest->passportNumber,
          'DocType' => 'Passport',
        ],
      ];
    }

    return $guestData;
  }

  /**
   * Get age qualifying code based on guest type
   *
   * @param GuestDataDto $guest
   * @return string The age qualifying code
   */
  protected function getAgeQualifyingCode(GuestDataDto $guest): string
  {
    return match ($guest->guestType) {
      'adult' => '10', // Adult
      'child' => '8',  // Child
      'youth' => '9',  // Youth/Student
      'infant' => '7', // Infant
      default => '10', // Default to adult
    };
  }

  /**
   * Build person name structure
   *
   * @param GuestDataDto $guest
   * @return array<string, mixed>
   */
  protected function buildPersonName(GuestDataDto $guest): array
  {
    $personName = [
      'NamePrefix' => $guest->title,
      'GivenName' => $guest->firstName,
      'Surname' => $guest->lastName,
    ];

    // Add optional elements
    if (!empty($guest->middleName)) {
      $personName['MiddleName'] = $guest->middleName;
    }

    if (!empty($guest->suffix)) {
      $personName['NameSuffix'] = $guest->suffix;
    }

    return $personName;
  }

  /**
   * Build room stays section
   *
   * @param ReservationDataDto $data
   * @return array<string, mixed>
   */
  protected function buildRoomStays(ReservationDataDto $data): array
  {
    $roomStays = ['RoomStay' => []];

    // Build each room stay
    if ($data->roomStays->count() === 1) {
      // Single room stay (common case)
      $roomStays['RoomStay'] = $this->buildRoomStay($data->roomStays->first(), $data);
    } else {
      // Multiple room stays
      $data->roomStays->each(function ($roomStay) use (&$roomStays, $data) {
        $roomStays['RoomStay'][] = $this->buildRoomStay($roomStay, $data);
      });
    }

    return $roomStays;
  }

  /**
   * Build a single room stay element
   *
   * @param RoomStayDataDto $roomStay
   * @param ReservationDataDto $reservationData
   * @return array<string, mixed>
   */
  protected function buildRoomStay(RoomStayDataDto $roomStay, ReservationDataDto $reservationData): array
  {
    // Build room stay structure
    $roomStayData = [
      'RoomTypes' => [
        'RoomType' => [
          '_attributes' => [
            'RoomTypeCode' => $roomStay->roomTypeCode,
            'NumberOfUnits' => '1',
          ],
        ],
      ],
      'RatePlans' => [
        'RatePlan' => [
          '_attributes' => [
            'RatePlanCode' => $roomStay->ratePlanCode,
          ],
        ],
      ],
      'GuestCounts' => [
        'GuestCount' => [],
      ],
      'TimeSpan' => [
        '_attributes' => [
          'Start' => $roomStay->getFormattedCheckInDate(),
          'End' => $roomStay->getFormattedCheckOutDate(),
        ],
      ],
      'Total' => [
        '_attributes' => [
          'AmountBeforeTax' => number_format($roomStay->rateAmount, 2, '.', ''),
          'CurrencyCode' => $roomStay->currencyCode,
        ],
      ],
      'BasicPropertyInfo' => [
        '_attributes' => [
          'HotelCode' => $reservationData->hotelCode,
        ],
      ],
    ];

    // Add optional room description
    if (!empty($roomStay->roomDescription)) {
      $roomStayData['RoomTypes']['RoomType']['RoomDescription'] = [
        'Text' => $roomStay->roomDescription,
      ];
    }

    // Add upgraded room type if available
    if (!empty($roomStay->upgradedRoomTypeCode)) {
      $roomStayData['RoomTypes']['RoomType']['_attributes']['UpgradeRoomTypeCode'] =
        $roomStay->upgradedRoomTypeCode;
    }

    // Add meal plan if available
    if (!empty($roomStay->mealPlanCode)) {
      $roomStayData['RatePlans']['RatePlan']['MealsIncluded'] = [
        '_attributes' => [
          'MealPlanCodes' => $roomStay->mealPlanCode,
        ],
      ];
    }

    // Add guest counts for all guest types
    if ($roomStay->adultCount > 0) {
      $roomStayData['GuestCounts']['GuestCount'][] = [
        '_attributes' => [
          'AgeQualifyingCode' => '10',
          'Count' => (string)$roomStay->adultCount,
        ],
      ];
    }

    if ($roomStay->childCount > 0) {
      $roomStayData['GuestCounts']['GuestCount'][] = [
        '_attributes' => [
          'AgeQualifyingCode' => '8',
          'Count' => (string)$roomStay->childCount,
        ],
      ];
    }

    if ($roomStay->infantCount > 0) {
      $roomStayData['GuestCounts']['GuestCount'][] = [
        '_attributes' => [
          'AgeQualifyingCode' => '7',
          'Count' => (string)$roomStay->infantCount,
        ],
      ];
    }

    // Add index number for reference
    $roomStayData['IndexNumber'] = (string)$roomStay->indexNumber;

    // Add daily rates if available
    if ($roomStay->hasDailyRates()) {
      $roomStayData['RoomRates'] = [
        'RoomRate' => $this->buildDailyRates($roomStay),
      ];
    }

    // Add special offers if available
    if ($roomStay->hasSpecialOffers()) {
      $roomStayData['SpecialOffers'] = [
        'SpecialOffer' => $this->buildSpecialOffers($roomStay),
      ];
    }

    // Add comments if available
    if (!empty($roomStay->confirmationNumber)) {
      $roomStayData['Comments'] = [
        'Comment' => [
          '_attributes' => [
            'Name' => 'ConfirmationNumber',
          ],
          '_value' => $roomStay->confirmationNumber,
        ],
      ];
    }

    return $roomStayData;
  }

  /**
   * Build daily rates structure
   *
   * @param RoomStayDataDto $roomStay
   * @return array<string, mixed>
   */
  protected function buildDailyRates(RoomStayDataDto $roomStay): array
  {
    if (!$roomStay->hasDailyRates()) {
      return [];
    }

    $rates = [];
    foreach ($roomStay->dailyRates as $date => $rateInfo) {
      $rates[] = [
        '_attributes' => [
          'EffectiveDate' => $date,
          'ExpireDate' => $date,
        ],
        'Rates' => [
          'Rate' => [
            '_attributes' => [
              'AmountBeforeTax' => number_format((float)$rateInfo['rate'], 2, '.', ''),
              'CurrencyCode' => $roomStay->currencyCode,
            ],
          ],
        ],
      ];
    }

    return $rates;
  }

  /**
   * Build special offers structure
   *
   * @param RoomStayDataDto $roomStay
   * @return array<string, mixed>
   */
  protected function buildSpecialOffers(RoomStayDataDto $roomStay): array
  {
    if (!$roomStay->hasSpecialOffers()) {
      return [];
    }

    $offers = [];
    foreach ($roomStay->specialOffers as $offer) {
      $offers[] = [
        '_attributes' => [
          'OfferCode' => $offer['code'] ?? 'SPECIAL',
        ],
        'OfferDescription' => [
          'Text' => $offer['description'] ?? 'Special Offer',
        ],
      ];
    }

    return $offers;
  }

  /**
   * Build global reservation information
   *
   * @param ReservationDataDto $data
   * @return array<string, mixed>
   */
  protected function buildResGlobalInfo(ReservationDataDto $data): array
  {
    $globalInfo = [
      'HotelReservationIDs' => [
        'HotelReservationID' => [],
      ],
    ];

    // Add reservation identifiers
    $globalInfo['HotelReservationIDs']['HotelReservationID'][] = [
      '_attributes' => [
        'ResID_Type' => '14', // Reservation
        'ResID_Value' => $data->reservationId,
      ],
    ];

    // Add confirmation number if available
    if (!empty($data->confirmationNumber)) {
      $globalInfo['HotelReservationIDs']['HotelReservationID'][] = [
        '_attributes' => [
          'ResID_Type' => '10', // Confirmation
          'ResID_Value' => $data->confirmationNumber,
        ],
      ];
    }

    // Add transaction information
    $globalInfo['HotelReservationIDs']['HotelReservationID'][] = [
      '_attributes' => [
        'ResID_Type' => '36', // Transaction ID
        'ResID_Value' => $data->transactionIdentifier,
      ],
    ];

    // Add creation info
    $globalInfo['CreateDateTime'] = $data->createDateTime;

    // Add modification info if applicable
    if ($data->isModification() && !empty($data->lastModifyDateTime)) {
      $globalInfo['LastModifyDateTime'] = $data->lastModifyDateTime;
    }

    // Add group block code if applicable
    if ($data->reservationType === ReservationType::GROUP && !empty($data->invBlockCode)) {
      $globalInfo['InvBlockCode'] = $data->invBlockCode;
    }

    // Add guarantee information if applicable
    if ($data->hasPaymentInfo()) {
      $globalInfo['Guarantee'] = $this->buildGuaranteeInfo($data);
    }

    // Add deposit payment information if applicable
    if ($data->depositAmount !== null) {
      $globalInfo['DepositPayments'] = $this->buildDepositPayments($data);
    }

    // Add total for the entire reservation
    $globalInfo['Total'] = [
      '_attributes' => [
        'AmountBeforeTax' => number_format($data->getTotalAmount(), 2, '.', ''),
        'CurrencyCode' => $data->roomStays->first()->currencyCode ?? 'USD',
      ],
    ];

    // Add special requests if applicable
    if ($data->hasSpecialRequests()) {
      $globalInfo['SpecialRequests'] = $this->buildSpecialRequests($data->specialRequests);
    }

    // Add service requests if applicable
    if ($data->hasServiceRequests()) {
      $globalInfo['Services'] = $this->buildServiceRequests($data->serviceRequests);
    }

    // Add comments if applicable
    if (!empty($data->comments)) {
      $globalInfo['Comments'] = [
        'Comment' => [
          '_value' => $data->comments,
        ],
      ];
    }

    // Add alternate payment information if applicable
    if (
      $data->reservationType === ReservationType::ALTERNATE_PAYMENT &&
      !empty($data->alternatePaymentType) &&
      $data->alternatePaymentAmount !== null
    ) {

      // Add to existing comments or create new
      $alternatePaymentComment = "Alternate Payment: {$data->alternatePaymentType}, " .
        "Amount: {$data->alternatePaymentAmount}";

      if (isset($globalInfo['Comments'])) {
        if (is_array($globalInfo['Comments']['Comment'])) {
          $globalInfo['Comments']['Comment'][] = [
            '_value' => $alternatePaymentComment,
          ];
        } else {
          $existingComment = $globalInfo['Comments']['Comment']['_value'];
          $globalInfo['Comments']['Comment']['_value'] = $existingComment . "\n" . $alternatePaymentComment;
        }
      } else {
        $globalInfo['Comments'] = [
          'Comment' => [
            '_value' => $alternatePaymentComment,
          ],
        ];
      }
    }

    return $globalInfo;
  }

  /**
   * Build guarantee information
   *
   * @param ReservationDataDto $data
   * @return array<string, mixed>
   */
  protected function buildGuaranteeInfo(ReservationDataDto $data): array
  {
    $guarantee = [];

    // Credit card guarantee
    if (!empty($data->paymentCardNumber)) {
      $guarantee['GuaranteeType'] = [
        '_attributes' => [
          'Code' => '1', // Guarantee
        ],
      ];

      $guarantee['GuaranteesAccepted'] = [
        'GuaranteeAccepted' => [
          'PaymentCard' => [
            '_attributes' => [
              'CardNumber' => $data->paymentCardNumber,
              'CardType' => $data->paymentCardType ?? '1', // Default to Credit
              'ExpireDate' => $data->paymentCardExpiration ?? '1225', // Default to avoid errors
            ],
            'CardHolderName' => $data->paymentCardHolderName ?? $data->primaryGuest->getFullName(),
          ],
        ],
      ];
    }
    // Other guarantee types
    elseif (!empty($data->guaranteeCode)) {
      $guarantee['GuaranteeType'] = [
        '_attributes' => [
          'Code' => $data->guaranteeCode,
        ],
      ];
    }

    return $guarantee;
  }

  /**
   * Build deposit payments information
   *
   * @param ReservationDataDto $data
   * @return array<string, mixed>
   */
  protected function buildDepositPayments(ReservationDataDto $data): array
  {
    if ($data->depositAmount === null) {
      return [];
    }

    $depositPayments = [
      'GuaranteePayment' => [
        '_attributes' => [
          'Type' => $data->depositPaymentType ?? 'Deposit',
        ],
        'AmountPercent' => [
          '_attributes' => [
            'Amount' => number_format($data->depositAmount, 2, '.', ''),
            'CurrencyCode' => $data->roomStays->first()->currencyCode ?? 'USD',
          ],
        ],
      ],
    ];

    return $depositPayments;
  }

  /**
   * Build special requests section
   *
   * @param Collection $specialRequests
   * @return array<string, mixed>
   */
  protected function buildSpecialRequests(Collection $specialRequests): array
  {
    if ($specialRequests->isEmpty()) {
      return [];
    }

    $requests = ['SpecialRequest' => []];

    $specialRequests->each(function ($request) use (&$requests) {
      $requestData = [
        '_attributes' => [
          'RequestCode' => $request->requestCode,
        ],
        'Text' => $request->requestName,
      ];

      // Add date range if available
      if ($request->hasDateRange()) {
        $requestData['_attributes']['Start'] = $request->getFormattedStartDate();
        $requestData['_attributes']['End'] = $request->getFormattedEndDate();
      }

      // Add comments if available
      if (!empty($request->comments)) {
        if (!empty($requestData['Text'])) {
          $requestData['Text'] .= ' - ' . $request->comments;
        } else {
          $requestData['Text'] = $request->comments;
        }
      }

      // Handle multiple requests
      if (isset($requests['SpecialRequest']['_attributes'])) {
        // Convert to array if only one item exists
        $existingRequest = $requests['SpecialRequest'];
        $requests['SpecialRequest'] = [$existingRequest, $requestData];
      } elseif (is_array($requests['SpecialRequest']) && !isset($requests['SpecialRequest'][0])) {
        // Convert associative to indexed array
        $requests['SpecialRequest'] = [$requests['SpecialRequest'], $requestData];
      } else {
        // Add to existing array or create first item
        $requests['SpecialRequest'][] = $requestData;
      }
    });

    return $requests;
  }

  /**
   * Build service requests section
   *
   * @param Collection $serviceRequests
   * @return array<string, mixed>
   */
  protected function buildServiceRequests(Collection $serviceRequests): array
  {
    if ($serviceRequests->isEmpty()) {
      return [];
    }

    $services = ['Service' => []];

    $serviceRequests->each(function ($service) use (&$services) {
      $serviceData = [
        '_attributes' => [
          'ServiceCode' => $service->serviceCode,
          'Quantity' => (string)$service->quantity,
          'ServiceInventoryCode' => $service->serviceCode,
        ],
        'ServiceDetails' => [
          '_attributes' => [
            'PriceAmount' => number_format($service->amount, 2, '.', ''),
            'PriceCurrencyCode' => $service->currencyCode,
          ],
          'GuestCount' => [],
        ],
        'ServiceDescription' => [
          'Text' => $service->serviceName,
        ],
      ];

      // Add service dates if available
      if ($service->startDate) {
        $serviceData['_attributes']['ServiceDate'] = $service->getFormattedStartDate();
      }

      // Add guest counts
      if ($service->numberOfAdults > 0) {
        $serviceData['ServiceDetails']['GuestCount'][] = [
          '_attributes' => [
            'AgeQualifyingCode' => '10',
            'Count' => (string)$service->numberOfAdults,
          ],
        ];
      }

      if ($service->numberOfChildren > 0) {
        $serviceData['ServiceDetails']['GuestCount'][] = [
          '_attributes' => [
            'AgeQualifyingCode' => '8',
            'Count' => (string)$service->numberOfChildren,
          ],
        ];
      }

      // Add comments if available
      if (!empty($service->comments)) {
        $serviceData['Comments'] = [
          'Comment' => [
            '_value' => $service->comments,
          ],
        ];
      }

      // Add service description
      if (!empty($service->serviceDescription)) {
        $serviceData['ServiceDescription']['Text'] =
          $service->serviceName . ' - ' . $service->serviceDescription;
      }

      // Handle multiple services
      if (isset($services['Service']['_attributes'])) {
        // Convert to array if only one item exists
        $existingService = $services['Service'];
        $services['Service'] = [$existingService, $serviceData];
      } elseif (is_array($services['Service']) && !isset($services['Service'][0])) {
        // Convert associative to indexed array
        $services['Service'] = [$services['Service'], $serviceData];
      } else {
        // Add to existing array or create first item
        $services['Service'][] = $serviceData;
      }
    });

    return $services;
  }

  /**
   * Build cancellation request section
   *
   * @param ReservationDataDto $data
   * @return array<string, mixed>
   */
  protected function buildCancelRequest(ReservationDataDto $data): array
  {
    $cancelRequest = [
      '_attributes' => [
        'Type' => 'Cancel',
      ],
    ];

    // Add cancellation reason if available
    if (!empty($data->comments)) {
      $cancelRequest['CancelReason'] = [
        '_value' => $data->comments,
      ];
    }

    return $cancelRequest;
  }
}
