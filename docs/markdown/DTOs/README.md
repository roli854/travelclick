# Data Transfer Objects
## Overview
This namespace contains 17 classes/interfaces/enums.
## Table of Contents
- [EndpointConfigDto](#endpointconfigdto) (Class)
- [GuestDataDto](#guestdatadto) (Class)
- [InventoryData](#inventorydata) (Class)
- [InventoryResponseDto](#inventoryresponsedto) (Class)
- [ProfileDataDto](#profiledatadto) (Class)
- [PropertyConfigDto](#propertyconfigdto) (Class)
- [RateData](#ratedata) (Class)
- [RatePlanData](#rateplandata) (Class)
- [ReservationDataDto](#reservationdatadto) (Class)
- [ReservationResponseDto](#reservationresponsedto) (Class)
- [RoomStayDataDto](#roomstaydatadto) (Class)
- [ServiceRequestDto](#servicerequestdto) (Class)
- [SoapHeaderDto](#soapheaderdto) (Class)
- [SoapRequestDto](#soaprequestdto) (Class)
- [SoapResponseDto](#soapresponsedto) (Class)
- [SpecialRequestDto](#specialrequestdto) (Class)
- [TravelClickConfigDto](#travelclickconfigdto) (Class)
## Complete API Reference
---
### EndpointConfigDto
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\EndpointConfigDto`
**Description:** Endpoint Configuration DTO
#### Properties
```php
public readonly Environment $environment;
public readonly string $url;
public readonly string $wsdlUrl;
public readonly int $connectionTimeout;
public readonly int $requestTimeout;
public readonly bool $sslVerifyPeer;
public readonly bool $sslVerifyHost;
public readonly string|null $sslCaFile;
public readonly array $soapOptions;
public readonly array $httpHeaders;
public readonly string|null $userAgent;
public readonly bool $compression;
public readonly string $encoding;
public readonly int $maxRedirects;
public readonly bool $keepAlive;
public readonly array $streamContext;
```
#### Methods
```php
public function __construct(Environment $environment, string $url, string $wsdlUrl, int $connectionTimeout, int $requestTimeout, bool $sslVerifyPeer, bool $sslVerifyHost, string|null $sslCaFile = null, array $soapOptions = [], array $httpHeaders = [], string|null $userAgent = null, bool $compression = false, string $encoding = 'UTF-8', int $maxRedirects = 0, bool $keepAlive = true, array $streamContext = []);
public static function fromEnvironment(Environment $environment): self;
public static function fromArray(array $data): self;
public function toArray(): array;
public function getSoapClientOptions(): array;
public function getStreamContext(): array;
public function validate(): array;
public function testConnection(): bool;
public function with(array $updates): self;
public function getCacheKey(): string;
public function isDevelopment(): bool;
public function isProduction(): bool;
public function getOptimizations(): array;
```
---
### GuestDataDto
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\GuestDataDto`
**Description:** Data Transfer Object for guest information in TravelClick integrations
#### Properties
```php
public readonly string $title;
public readonly string $firstName;
public readonly string $lastName;
public readonly string|null $middleName;
public readonly string|null $suffix;
public readonly Carbon\Carbon|null $dateOfBirth;
public readonly string|null $passportNumber;
public readonly string|null $email;
public readonly string|null $phone;
public readonly string|null $phoneMobile;
public readonly string|null $fax;
public readonly string|null $addressLine1;
public readonly string|null $addressLine2;
public readonly string|null $city;
public readonly string|null $state;
public readonly string|null $postalCode;
public readonly string|null $countryCode;
public readonly string $guestType;
public readonly int|null $age;
public readonly bool $isPrimaryGuest;
```
#### Methods
```php
public function __construct(array $data);
public function isAdult(): bool;
public function isChild(): bool;
public function isYouth(): bool;
public function isInfant(): bool;
public function hasValidAddress(): bool;
public function hasValidContactInfo(): bool;
public function getFullName(): string;
public function getFormalName(): string;
public static function fromCentriumBooking($booking): self;
public function toArray(): array;
```
---
### InventoryData
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\InventoryData`
**Description:** Data Transfer Object for TravelClick Inventory Messages
#### Properties
```php
public readonly string $hotelCode;
public readonly string $startDate;
public readonly string $endDate;
public readonly string|null $roomTypeCode;
public readonly bool $isPropertyLevel;
public readonly Spatie\LaravelData\DataCollection $counts;
public readonly string|null $uniqueId;
```
#### Methods
```php
public function __construct(string $hotelCode, string $startDate, string $endDate, string|null $roomTypeCode, bool $isPropertyLevel, Spatie\LaravelData\DataCollection $counts, string|null $uniqueId = null);
public static function fromCentrium(array $inventoryRecord): self;
public static function createCalculated(string $hotelCode, string $startDate, string $endDate, string|null $roomTypeCode = null, int $definiteSold = 0, int $tentativeSold = 0, int $outOfOrder = 0, int $oversell = 0, int|null $physical = null): self;
public static function createAvailable(string $hotelCode, string $startDate, string $endDate, string|null $roomTypeCode = null, int $availableCount = 0): self;
public function validateBusinessRules(): array;
public function isCalculatedMethod(): bool;
public function isDirectMethod(): bool;
public function getTotalCount(): int;
public function getCountByType(CountType $countType): int;
```
---
### InventoryResponseDto
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\InventoryResponseDto`
**Description:** Data Transfer Object for TravelClick Inventory Response
#### Methods
```php
public function __construct(string $messageId, bool $isSuccess, string $rawResponse, array|null $processedCounts = null, string|null $hotelCode = null, array|null $roomTypes = null, Carbon\Carbon|null $startDate = null, Carbon\Carbon|null $endDate = null, string|null $errorMessage = null, string|null $errorCode = null, array|null $warnings = null, Carbon\Carbon|null $timestamp = null, string|null $echoToken = null, array|null $headers = null, float|null $durationMs = null);
public static function success(string $messageId, string $rawResponse, string|null $echoToken = null, array|null $headers = null, float|null $durationMs = null, array $processedCounts = [], string|null $hotelCode = null, array $roomTypes = [], Carbon\Carbon|null $startDate = null, Carbon\Carbon|null $endDate = null, array|null $warnings = null): self;
public static function failure(string $messageId, string $rawResponse, string $errorMessage, string|null $errorCode = null, array|null $warnings = null, float|null $durationMs = null): self;
public function getCountValue(CountType $countType, string|null $roomType = null): int|null;
public function getRoomTypes(): array;
public function getDateRange(): array;
public function getProcessedCounts(): array;
public function hasRoomType(string $roomType): bool;
public function getHotelCode(): string|null;
public function toArray(): array;
```
---
### ProfileDataDto
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\ProfileDataDto`
**Description:** Data Transfer Object for profile information in TravelClick integrations
#### Constants
```php
public const TYPE_TRAVEL_AGENCY = 'TravelAgency';
public const TYPE_CORPORATE = 'Corporate';
public const TYPE_GROUP = 'Group';
```
#### Properties
```php
public readonly string $profileType;
public readonly string $profileId;
public readonly string $name;
public readonly string|null $shortName;
public readonly string|null $iataNumber;
public readonly string|null $contactName;
public readonly string|null $email;
public readonly string|null $phone;
public readonly string|null $fax;
public readonly string|null $addressLine1;
public readonly string|null $addressLine2;
public readonly string|null $city;
public readonly string|null $state;
public readonly string|null $postalCode;
public readonly string|null $countryCode;
public readonly float|null $commissionPercentage;
public readonly string|null $corporateId;
public readonly string|null $travelAgentId;
```
#### Methods
```php
public function __construct(array $data);
public function isTravelAgency(): bool;
public function isCorporate(): bool;
public function isGroup(): bool;
public function hasValidAddress(): bool;
public function hasValidContactInfo(): bool;
public function hasCommission(): bool;
public static function createTravelAgencyProfile($agencyData): self;
public static function createCorporateProfile($tradeData): self;
public static function createGroupProfile($bookingGroupData): self;
public function toArray(): array;
```
---
### PropertyConfigDto
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\PropertyConfigDto`
**Description:** Property Configuration DTO
#### Properties
```php
public readonly int $propertyId;
public readonly string $hotelCode;
public readonly string $propertyName;
public readonly Environment $environment;
public readonly string $username;
public readonly string $password;
public readonly int|null $timeout;
public readonly int|null $retryAttempts;
public readonly array|null $backoffSeconds;
public readonly array $enabledMessageTypes;
public readonly array $customSettings;
public readonly bool $overrideGlobal;
public readonly bool $isActive;
public readonly array $queueOverrides;
public readonly array $endpointOverrides;
public readonly Carbon\Carbon|null $lastSyncDate;
public readonly Carbon\Carbon|null $lastUpdated;
public readonly string|null $notes;
```
#### Methods
```php
public function __construct(int $propertyId, string $hotelCode, string $propertyName, Environment $environment, string $username, string $password, int|null $timeout = null, int|null $retryAttempts = null, array|null $backoffSeconds = null, array $enabledMessageTypes = [], array $customSettings = [], bool $overrideGlobal = false, bool $isActive = true, array $queueOverrides = [], array $endpointOverrides = [], Carbon\Carbon|null $lastSyncDate = null, Carbon\Carbon|null $lastUpdated = null, string|null $notes = null);
public static function fromArray(array $data): self;
public static function fromModel(TravelClickPropertyConfig $model): self;
public function toArray(): array;
public function toDatabase(): array;
public function getEffectiveTimeout(int $globalTimeout): int;
public function getEffectiveRetryAttempts(int $globalRetryAttempts): int;
public function getEffectiveBackoffSeconds(array $globalBackoffSeconds): array;
public function isMessageTypeEnabled(string $messageType): bool;
public function getCustomSetting(string $key, mixed $default = null): mixed;
public function isComplete(): bool;
public function requiresSync(int $maxDaysWithoutSync = 7): bool;
public function mergeWithGlobal(TravelClickConfigDto $global): self;
public function getCacheKey(): string;
public function with(array $updates): self;
public function validate(): array;
```
---
### RateData
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\RateData`
**Description:** Rate data structure for TravelClick HTNG 2011B integration
#### Properties
```php
public readonly float $firstAdultRate;
public readonly float $secondAdultRate;
public readonly float|null $additionalAdultRate;
public readonly float|null $additionalChildRate;
public readonly string $currencyCode;
public readonly Carbon\Carbon $startDate;
public readonly Carbon\Carbon $endDate;
public readonly string $roomTypeCode;
public readonly string $ratePlanCode;
public readonly bool|null $restrictedDisplayIndicator;
public readonly bool|null $isCommissionable;
public readonly string|null $ratePlanQualifier;
public readonly string|null $marketCode;
public readonly int|null $maxGuestApplicable;
public readonly bool $isLinkedRate;
public readonly string|null $masterRatePlanCode;
public readonly float|null $linkedRateOffset;
public readonly float|null $linkedRatePercentage;
```
#### Methods
```php
public function __construct(float $firstAdultRate, float $secondAdultRate, string $roomTypeCode, string $ratePlanCode, Carbon\Carbon|string $startDate, Carbon\Carbon|string $endDate, float|null $additionalAdultRate = null, float|null $additionalChildRate = null, string|null $currencyCode = null, bool|null $restrictedDisplayIndicator = null, bool|null $isCommissionable = null, string|null $ratePlanQualifier = null, string|null $marketCode = null, int|null $maxGuestApplicable = null, bool $isLinkedRate = false, string|null $masterRatePlanCode = null, float|null $linkedRateOffset = null, float|null $linkedRatePercentage = null);
public static function fromArray(array $data): self;
public function toArray(): array;
public function toXmlAttributes(): array;
public function isValidForDate(Carbon\Carbon $date): bool;
public function getRateForGuests(int $guests): float;
public function equals(RateData $other): bool;
public function withDateRange(Carbon\Carbon $startDate, Carbon\Carbon $endDate): self;
```
---
### RatePlanData
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\RatePlanData`
**Description:** Rate plan data structure for TravelClick HTNG 2011B integration
#### Properties
```php
public readonly string $ratePlanCode;
public readonly string $hotelCode;
public readonly RateOperationType $operationType;
public readonly Illuminate\Support\Collection $rates;
public readonly Carbon\Carbon $startDate;
public readonly Carbon\Carbon $endDate;
public readonly string|null $ratePlanName;
public readonly string $currencyCode;
public readonly bool $isLinkedRate;
public readonly string|null $masterRatePlanCode;
public readonly Illuminate\Support\Collection $roomTypes;
public readonly int|null $maxGuestApplicable;
public readonly bool|null $isCommissionable;
public readonly Illuminate\Support\Collection $marketCodes;
public readonly bool $isDeltaUpdate;
public readonly Carbon\Carbon|null $lastModified;
```
#### Methods
```php
public function __construct(string $ratePlanCode, string $hotelCode, RateOperationType $operationType, Illuminate\Support\Collection|array $rates, string|null $ratePlanName = null, string|null $currencyCode = null, bool $isLinkedRate = false, string|null $masterRatePlanCode = null, int|null $maxGuestApplicable = null, bool|null $isCommissionable = null, array $marketCodes = [], bool $isDeltaUpdate = true, Carbon\Carbon|null $lastModified = null);
public static function fromArray(array $data): self;
public function toArray(): array;
public function getRatesForRoomType(string $roomTypeCode): Illuminate\Support\Collection;
public function getRatesForDate(Carbon\Carbon $date): Illuminate\Support\Collection;
public function hasRatesForRoomType(string $roomTypeCode): bool;
public function getCurrencies(): Illuminate\Support\Collection;
public function isValidForCertification(): bool;
public function splitByDateRanges(int $maxDaysPerPlan = 30): Illuminate\Support\Collection;
public function filterLinkedRatesIfNeeded(bool $externalSystemHandlesLinkedRates = false): self;
```
---
### ReservationDataDto
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\ReservationDataDto`
**Description:** Data Transfer Object for reservation information in TravelClick integrations
#### Properties
```php
public readonly ReservationType $reservationType;
public readonly string $reservationId;
public readonly string|null $confirmationNumber;
public readonly string $createDateTime;
public readonly string|null $lastModifyDateTime;
public readonly string $transactionIdentifier;
public readonly string $transactionType;
public readonly string $hotelCode;
public readonly string|null $chainCode;
public readonly GuestDataDto $primaryGuest;
public readonly Illuminate\Support\Collection $additionalGuests;
public readonly Illuminate\Support\Collection $roomStays;
public readonly Illuminate\Support\Collection $specialRequests;
public readonly Illuminate\Support\Collection $serviceRequests;
public readonly ProfileDataDto|null $profile;
public readonly string $sourceOfBusiness;
public readonly string|null $marketSegment;
public readonly string|null $departmentCode;
public readonly string|null $guaranteeType;
public readonly string|null $guaranteeCode;
public readonly float|null $depositAmount;
public readonly string|null $depositPaymentType;
public readonly string|null $paymentCardNumber;
public readonly string|null $paymentCardType;
public readonly string|null $paymentCardExpiration;
public readonly string|null $paymentCardHolderName;
public readonly string|null $alternatePaymentType;
public readonly string|null $alternatePaymentIdentifier;
public readonly float|null $alternatePaymentAmount;
public readonly string|null $invBlockCode;
public readonly string|null $comments;
public readonly bool $priorityProcessing;
```
#### Methods
```php
public function __construct(array $data);
public function getArrivalDate(): Carbon\Carbon;
public function getDepartureDate(): Carbon\Carbon;
public function getTotalNights(): int;
public function getTotalAmount(): float;
public function hasSpecialRequests(): bool;
public function hasServiceRequests(): bool;
public function hasPaymentInfo(): bool;
public function hasProfile(): bool;
public function isModification(): bool;
public function isCancellation(): bool;
public function isNew(): bool;
public static function fromCentriumBooking($booking, ReservationType|null $type = null): self;
public static function createCancellation(string $reservationId, string $confirmationNumber, string $hotelCode, string|null $cancellationReason = null): self;
public static function createModification(string $reservationId, string $confirmationNumber, array $modificationData): self;
public function toArray(): array;
```
---
### ReservationResponseDto
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\ReservationResponseDto`
**Description:** Specialized DTO for Reservation SOAP responses
#### Methods
```php
public function __construct(string $messageId, bool $isSuccess, string $rawResponse, array|null $payload = null, string|null $errorMessage = null, string|null $errorCode = null, array|null $warnings = null, Carbon\Carbon|null $timestamp = null, string|null $echoToken = null, array|null $headers = null, float|null $durationMs = null);
public static function successWithPayload(string $messageId, string $rawResponse, array $payload, string|null $echoToken = null, array|null $headers = null, float|null $durationMs = null): self;
public static function success(string $messageId, string $rawResponse, string|null $echoToken = null, array|null $headers = null, float|null $durationMs = null): self;
public static function fromSoapResponse(SoapResponseDto $response, array|null $payload = null): self;
public function getPayload(): array|null;
public function withPayload(array $payload): self;
public function hasPayload(): bool;
public function toArray(): array;
```
---
### RoomStayDataDto
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\RoomStayDataDto`
**Description:** Data Transfer Object for room stay information in TravelClick integration
#### Properties
```php
public readonly Carbon\Carbon $checkInDate;
public readonly Carbon\Carbon $checkOutDate;
public readonly int $stayDurationNights;
public readonly string $roomTypeCode;
public readonly string $ratePlanCode;
public readonly string|null $upgradedRoomTypeCode;
public readonly string|null $mealPlanCode;
public readonly int $adultCount;
public readonly int $childCount;
public readonly int $infantCount;
public readonly int $totalGuestCount;
public readonly float $rateAmount;
public readonly float|null $totalAmount;
public readonly float|null $discountAmount;
public readonly float|null $taxAmount;
public readonly string $currencyCode;
public readonly int $indexNumber;
public readonly string|null $confirmationNumber;
public readonly string|null $specialRequestCode;
public readonly string|null $roomDescription;
public readonly array|null $dailyRates;
public readonly array|null $supplements;
public readonly array|null $specialOffers;
```
#### Methods
```php
public function __construct(array $data);
public function getFormattedCheckInDate(): string;
public function getFormattedCheckOutDate(): string;
public function hasDailyRates(): bool;
public function hasSupplements(): bool;
public function hasSpecialOffers(): bool;
public function hasConfirmationNumber(): bool;
public function isPackageRate(): bool;
public static function fromCentriumPropertyRoomBooking($propertyRoomBooking, int $index = 1): self;
public function toArray(): array;
```
---
### ServiceRequestDto
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\ServiceRequestDto`
**Description:** Data Transfer Object for service requests in TravelClick integrations
#### Properties
```php
public readonly string $serviceCode;
public readonly string $serviceName;
public readonly string|null $serviceDescription;
public readonly int $quantity;
public readonly Carbon\Carbon|null $startDate;
public readonly Carbon\Carbon|null $endDate;
public readonly string|null $deliveryTime;
public readonly float $amount;
public readonly float|null $totalAmount;
public readonly string $currencyCode;
public readonly bool $includedInRate;
public readonly int $numberOfAdults;
public readonly int $numberOfChildren;
public readonly int|null $roomStayIndex;
public readonly string|null $supplierConfirmationNumber;
public readonly string|null $comments;
public readonly bool $confirmed;
```
#### Methods
```php
public function __construct(array $data);
public function getFormattedStartDate(): string|null;
public function getFormattedEndDate(): string|null;
public function appliesToSpecificStay(): bool;
public function hasConfirmation(): bool;
public function getTotalCost(): float;
public static function fromCentriumPropertyRoomBookingAdjust($adjustment): self|null;
public function toArray(): array;
```
---
### SoapHeaderDto
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\SoapHeaderDto`
**Description:** Data Transfer Object for SOAP headers required by HTNG 2011B interface
#### Properties
```php
public readonly string $messageId;
public readonly string $to;
public readonly string $replyTo;
public readonly string $action;
public readonly string $from;
public readonly string $hotelCode;
public readonly string $username;
public readonly string $password;
public readonly string|null $timeStamp;
public readonly string|null $echoToken;
```
#### Methods
```php
public function __construct(string $messageId, string $to, string $replyTo, string $action, string $from, string $hotelCode, string $username, string $password, string|null $timeStamp = null, string|null $echoToken = null);
public static function create(string $action, string $hotelCode, string $username, string $password, string|null $endpoint = null, string|null $replyToEndpoint = null): self;
public static function forInventory(string $hotelCode, string $username, string $password): self;
public static function forRates(string $hotelCode, string $username, string $password): self;
public static function forReservation(string $hotelCode, string $username, string $password): self;
public static function forGroup(string $hotelCode, string $username, string $password): self;
public function toSoapHeaders(): array;
public function toNamespacedHeaders(): array;
public function toArray(): array;
public function validate(): bool;
public static function fromConfig(string $action, array|null $overrides = null): self;
```
---
### SoapRequestDto
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\SoapRequestDto`
**Description:** Data Transfer Object for SOAP Request
#### Properties
```php
public readonly string $messageId;
public readonly string $action;
public readonly string $xmlBody;
public readonly string $hotelCode;
public readonly array $headers;
public readonly string|null $echoToken;
public readonly string|null $version;
public readonly string|null $target;
```
#### Methods
```php
public function __construct(string $messageId, string $action, string $xmlBody, string $hotelCode, array $headers = [], string|null $echoToken = null, string|null $version = '1.0', string|null $target = 'Production');
public static function forInventory(string $messageId, string $xmlBody, string $hotelCode, string|null $echoToken = null): self;
public static function forRates(string $messageId, string $xmlBody, string $hotelCode, string|null $echoToken = null): self;
public static function forReservation(string $messageId, string $xmlBody, string $hotelCode, string|null $echoToken = null): self;
public function getCompleteHeaders(): array;
public function toArray(): array;
```
---
### SoapResponseDto
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\SoapResponseDto`
**Description:** Data Transfer Object for SOAP Response
#### Properties
```php
public readonly string $messageId;
public readonly bool $isSuccess;
public readonly string $rawResponse;
public readonly string|null $errorMessage;
public readonly string|null $errorCode;
public readonly array|null $warnings;
public readonly Carbon\Carbon|null $timestamp;
public readonly string|null $echoToken;
public readonly array|null $headers;
public readonly float|null $durationMs;
```
#### Methods
```php
public function __construct(string $messageId, bool $isSuccess, string $rawResponse, string|null $errorMessage = null, string|null $errorCode = null, array|null $warnings = null, Carbon\Carbon|null $timestamp = null, string|null $echoToken = null, array|null $headers = null, float|null $durationMs = null);
public static function success(string $messageId, string $rawResponse, string|null $echoToken = null, array|null $headers = null, float|null $durationMs = null): self;
public static function failure(string $messageId, string $rawResponse, string $errorMessage, string|null $errorCode = null, array|null $warnings = null, float|null $durationMs = null): self;
public static function fromSoapFault(string $messageId, SoapFault $fault, float|null $durationMs = null): self;
public function hasWarnings(): bool;
public function getWarningsAsString(): string;
public function getFormattedDuration(): string;
public function toArray(): array;
public function getLogContext(): array;
```
---
### SpecialRequestDto
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\SpecialRequestDto`
**Description:** Data Transfer Object for special requests in TravelClick integrations
#### Properties
```php
public readonly string $requestCode;
public readonly string $requestName;
public readonly string|null $requestDescription;
public readonly Carbon\Carbon|null $startDate;
public readonly Carbon\Carbon|null $endDate;
public readonly string|null $timeSpan;
public readonly string|null $comments;
public readonly bool $confirmed;
public readonly int $quantity;
public readonly int|null $roomStayIndex;
```
#### Methods
```php
public function __construct(array $data);
public function getFormattedStartDate(): string|null;
public function getFormattedEndDate(): string|null;
public function appliesToSpecificStay(): bool;
public function hasDateRange(): bool;
public static function fromCentriumPropertyBookingComment($propertyBookingComment): self|null;
public function toArray(): array;
```
---
### TravelClickConfigDto
**Type:** Class
**Full Name:** `App\TravelClick\DTOs\TravelClickConfigDto`
**Description:** TravelClick Global Configuration DTO
#### Properties
```php
public readonly Environment $defaultEnvironment;
public readonly int $defaultTimeout;
public readonly int $defaultRetryAttempts;
public readonly array $defaultBackoffSeconds;
public readonly string $loggingLevel;
public readonly bool $enableCache;
public readonly int $defaultCacheTtl;
public readonly array $supportedMessageTypes;
public readonly array $queueConfig;
public readonly array $sslConfig;
public readonly array $customHeaders;
public readonly bool $debug;
public readonly Carbon\Carbon|null $lastUpdated;
public readonly string|null $version;
```
#### Methods
```php
public function __construct(Environment $defaultEnvironment, int $defaultTimeout, int $defaultRetryAttempts, array $defaultBackoffSeconds, string $loggingLevel, bool $enableCache, int $defaultCacheTtl, array $supportedMessageTypes, array $queueConfig, array $sslConfig, array $customHeaders, bool $debug, Carbon\Carbon|null $lastUpdated = null, string|null $version = null);
public static function fromArray(array $config): self;
public function toArray(): array;
public static function fromConfig(): self;
public function getTimeoutForOperation(string $operation): int;
public function getRetryAttemptsForOperation(string $operation): int;
public function isMessageTypeSupported(string $messageType): bool;
public function getQueueForOperation(string $operation): string;
public function isValid(): bool;
public function getCacheKey(): string;
public function getCacheTtl(): int;
public function mergeWith(self $other): self;
public function with(array $updates): self;
```
## Detailed Documentation
For detailed documentation of each class, see:
- [EndpointConfigDto](EndpointConfigDto.md)
- [GuestDataDto](GuestDataDto.md)
- [InventoryData](InventoryData.md)
- [InventoryResponseDto](InventoryResponseDto.md)
- [ProfileDataDto](ProfileDataDto.md)
- [PropertyConfigDto](PropertyConfigDto.md)
- [RateData](RateData.md)
- [RatePlanData](RatePlanData.md)
- [ReservationDataDto](ReservationDataDto.md)
- [ReservationResponseDto](ReservationResponseDto.md)
- [RoomStayDataDto](RoomStayDataDto.md)
- [ServiceRequestDto](ServiceRequestDto.md)
- [SoapHeaderDto](SoapHeaderDto.md)
- [SoapRequestDto](SoapRequestDto.md)
- [SoapResponseDto](SoapResponseDto.md)
- [SpecialRequestDto](SpecialRequestDto.md)
- [TravelClickConfigDto](TravelClickConfigDto.md)