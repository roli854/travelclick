# Support Classes

## Overview

This namespace contains 19 classes/interfaces/enums.

## Table of Contents

- [BusinessRulesValidator](#businessrulesvalidator) (Class)
- [CircuitBreaker](#circuitbreaker) (Class)
- [ConfigurationCache](#configurationcache) (Class)
- [ConfigurationValidator](#configurationvalidator) (Class)
- [RetryStrategyInterface](#retrystrategyinterface) (Interface)
- [ExponentialBackoffStrategy](#exponentialbackoffstrategy) (Class)
- [LinearBackoffStrategy](#linearbackoffstrategy) (Class)
- [LinkedRateHandler](#linkedratehandler) (Class)
- [MessageIdGenerator](#messageidgenerator) (Class)
- [RateStructureValidator](#ratestructurevalidator) (Class)
- [RetryHelper](#retryhelper) (Class)
- [SoapClientFactory](#soapclientfactory) (Class)
- [SoapHeaders](#soapheaders) (Class)
- [SoapLogger](#soaplogger) (Class)
- [TravelClickHelper](#travelclickhelper) (Class)
- [ValidationRulesHelper](#validationruleshelper) (Class)
- [XmlNamespaces](#xmlnamespaces) (Class)
- [XmlValidator](#xmlvalidator) (Class)
- [XsdSchemas](#xsdschemas) (Class)

## Complete API Reference

---

### BusinessRulesValidator

**Type:** Class
**Full Name:** `App\TravelClick\Support\BusinessRulesValidator`

**Description:** BusinessRulesValidator

#### Methods

```php
public function __construct(ConfigurationServiceInterface $configurationService);
public function validateInventoryRules(array $data, string $operation): array;
public function validateRateRules(array $data, string $operation): array;
public function validateReservationRules(array $data, string $operation): array;
public function validateGroupBlockRules(array $data, string $operation): array;
```

---

### CircuitBreaker

**Type:** Class
**Full Name:** `App\TravelClick\Support\CircuitBreaker`

**Description:** Class CircuitBreaker

#### Methods

```php
public function __construct(string $service, int $threshold = 5, int $resetTimeoutSeconds = 60);
public function allowRequest(): bool;
public function recordSuccess(): void;
public function recordFailure(): void;
public function isOpen(): bool;
public function reset(): void;
```

---

### ConfigurationCache

**Type:** Class
**Full Name:** `App\TravelClick\Support\ConfigurationCache`

**Description:** Configuration Cache Handler

#### Methods

```php
public function __construct();
public function getPropertyConfig(int $propertyId): PropertyConfigDto|null;
public function putPropertyConfig(int $propertyId, PropertyConfigDto $config): bool;
public function getGlobalConfig(): TravelClickConfigDto|null;
public function putGlobalConfig(TravelClickConfigDto $config): bool;
public function getEndpointConfig(Environment $environment): EndpointConfigDto|null;
public function putEndpointConfig(Environment $environment, EndpointConfigDto $config): bool;
public function clearPropertyConfig(int|null $propertyId = null): bool;
public function clearGlobalConfig(): bool;
public function clearEndpointConfigs(): bool;
public function clearAll(): bool;
public function warmup(int $propertyId): bool;
public function getStats(): array;
```

---

### ConfigurationValidator

**Type:** Class
**Full Name:** `App\TravelClick\Support\ConfigurationValidator`

**Description:** Configuration Validator for TravelClick

#### Methods

```php
public function validateGlobalConfig(TravelClickConfigDto $config): array;
public function validatePropertyConfig(PropertyConfigDto $config): array;
public function validateEndpointConfig(EndpointConfigDto $config): array;
public function testEndpointConnectivity(EndpointConfigDto $config): array;
public function validateComplete(TravelClickConfigDto $globalConfig, PropertyConfigDto|null $propertyConfig = null, EndpointConfigDto|null $endpointConfig = null): array;
public function generateReport(array $validationResults): string;
```

---

### RetryStrategyInterface

**Type:** Interface
**Full Name:** `App\TravelClick\Support\Contracts\RetryStrategyInterface`

**Description:** Interface RetryStrategyInterface

#### Methods

```php
public abstract function calculateDelay(int $attemptNumber): int;
public abstract function shouldRetry(Throwable $exception): bool;
public abstract function getMaxAttempts(): int;
```

---

### ExponentialBackoffStrategy

**Type:** Class
**Full Name:** `App\TravelClick\Support\ExponentialBackoffStrategy`

**Description:** Class ExponentialBackoffStrategy

#### Methods

```php
public function __construct(int $maxAttempts = 3, int $initialDelay = 10, int $maxDelay = 300, float $multiplier = 2.0, array $retryableExceptions = []);
public function calculateDelay(int $attemptNumber): int;
public function shouldRetry(Throwable $exception): bool;
public function getMaxAttempts(): int;
```

---

### LinearBackoffStrategy

**Type:** Class
**Full Name:** `App\TravelClick\Support\LinearBackoffStrategy`

**Description:** Class LinearBackoffStrategy

#### Methods

```php
public function __construct(int $maxAttempts = 3, int $initialDelay = 10, int $increment = 20, int $maxDelay = 300, array $retryableExceptions = []);
public function calculateDelay(int $attemptNumber): int;
public function shouldRetry(Throwable $exception): bool;
public function getMaxAttempts(): int;
```

---

### LinkedRateHandler

**Type:** Class
**Full Name:** `App\TravelClick\Support\LinkedRateHandler`

**Description:** Linked Rate Handler for TravelClick HTNG 2011B Integration

#### Methods

```php
public function __construct();
public function applyLinkedRateCalculations(Illuminate\Support\Collection $rates, RateOperationType $operationType): Illuminate\Support\Collection;
public function shouldSendLinkedRates(RateOperationType $operationType): bool;
public function filterLinkedRatesIfNeeded(RatePlanData $ratePlan, RateOperationType $operationType): RatePlanData;
public function getRequiredMasterRates(Illuminate\Support\Collection $linkedRates): Illuminate\Support\Collection;
public function validateLinkedRateDependencies(Illuminate\Support\Collection $ratePlans, RateOperationType $operationType): void;
public function getLinkedRateSummary(Illuminate\Support\Collection $rates): array;
public function calculateLinkedRateFromMaster(RateData $linkedRate, RateData $masterRate): RateData;
public function validateMasterRate(RateData $rate): void;
public function externalSystemHandlesLinkedRates(): bool;
public function getLinkedRateStrategy(RateOperationType $operationType): array;
```

---

### MessageIdGenerator

**Type:** Class
**Full Name:** `App\TravelClick\Support\MessageIdGenerator`

**Description:** MessageIdGenerator - Generates unique identifiers for SOAP messages

#### Methods

```php
public static function generate($hotelId, MessageType $messageType, string|null $prefix = null): string;
public static function generateWithTimestamp($hotelId, MessageType $messageType): string;
public static function generateIdempotent($hotelId, MessageType $messageType, string $payload): string;
public static function parseMessageId(string $messageId): array;
public static function isValid(string $messageId): bool;
public static function extractHotelId(string $messageId): string|null;
public static function extractMessageType(string $messageId): string|null;
```

---

### RateStructureValidator

**Type:** Class
**Full Name:** `App\TravelClick\Support\RateStructureValidator`

**Description:** Rate Structure Validator for TravelClick HTNG 2011B Integration

#### Methods

```php
public function __construct();
public function validateRateData(RateData $rateData, RateOperationType $operationType): void;
public function validateRatePlan(RatePlanData $ratePlan, RateOperationType $operationType): void;
public function validateBatchRatePlans(array $ratePlans, RateOperationType $operationType): void;
public function getValidationSummary(RatePlanData $ratePlan): array;
```

---

### RetryHelper

**Type:** Class
**Full Name:** `App\TravelClick\Support\RetryHelper`

**Description:** Class RetryHelper

#### Methods

```php
public function __construct(array $config = []);
public function registerStrategy(string $operationType, RetryStrategyInterface $strategy): self;
public function executeWithRetry(callable $operation, string $operationType, string|null $serviceIdentifier = null): mixed;
public function getStrategyForOperationType(string $operationType): RetryStrategyInterface;
```

---

### SoapClientFactory

**Type:** Class
**Full Name:** `App\TravelClick\Support\SoapClientFactory`

**Description:** Factory for creating SOAP clients configured for TravelClick

#### Methods

```php
public function __construct(string $wsdl, string $username, string $password, string $hotelCode, array $options = []);
public function create(): SoapClient;
public function createWithHeaders(string $messageId, string $action = 'HTNG2011B_SubmitRequest'): SoapClient;
public function injectHeaders(SoapClient $client, string $messageId, string $action = 'HTNG2011B_SubmitRequest'): void;
public function createWithOptions(array $customOptions): SoapClient;
public function createForTesting(): SoapClient;
public function validateConfiguration(): bool;
public static function fromConfig(array|null $config = null): self;
public function testConnection(): bool;
public function getConfigSummary(): array;
```

---

### SoapHeaders

**Type:** Class
**Full Name:** `App\TravelClick\Support\SoapHeaders`

**Description:** SoapHeaders - Manages WSSE authentication headers for TravelClick integration

#### Methods

```php
public function __construct(string $username, string $password, string $hotelCode, string $endpoint);
public function createHeaders(string $messageId, string $action = 'HTNG2011B_SubmitRequest'): string;
public static function fromConfig(array $config, string $messageId, string $action = 'HTNG2011B_SubmitRequest'): string;
public static function create(string $messageId, string $action = 'HTNG2011B_SubmitRequest'): string;
public static function generateMessageId(string $prefix = 'MSG'): string;
public static function forOperation(string $operationType, string|null $messageId = null): array;
public function createHeadersWithNamespaces(string $messageId, array $customNamespaces = [], string $action = 'HTNG2011B_SubmitRequest'): string;
public static function validateHeaders(string $headers): bool;
```

---

### SoapLogger

**Type:** Class
**Full Name:** `App\TravelClick\Support\SoapLogger`

**Description:** SoapLogger - Comprehensive logging for TravelClick SOAP operations

#### Constants

```php
public const LEVEL_MINIMAL = 'minimal';
public const LEVEL_STANDARD = 'standard';
public const LEVEL_DETAILED = 'detailed';
public const LEVEL_DEBUG = 'debug';
```

#### Methods

```php
public function __construct(array $config = []);
public static function create(): self;
public function logRequestStart(SoapRequestDto $request, int|null $propertyId = null, string|null $jobId = null): TravelClickLog;
public function logResponseSuccess(TravelClickLog $log, SoapResponseDto $response): TravelClickLog;
public function logResponseFailure(TravelClickLog $log, SoapResponseDto|null $response = null, Throwable|null $exception = null): TravelClickLog;
public function logWarnings(TravelClickLog $log, array $warnings): void;
public function logPerformanceMetrics(TravelClickLog $log, array $metrics = []): void;
public function logDebug(string $message, array $context = [], string|null $messageId = null): void;
public function getPerformanceStats(int $minutes = 60): array;
public function cleanupLogs(int $daysToKeep = 30): array;
public function generateOperationReport(string $messageId): array;
public static function forOperation(string $operationType, string $messageId): self;
```

---

### TravelClickHelper

**Type:** Class
**Full Name:** `App\TravelClick\Support\TravelClickHelper`

**Description:** TravelClick Helper

#### Methods

```php
public static function isValidHotelCode(string $hotelCode): bool;
public static function formatDateForHtng(Carbon\Carbon|string $date): string;
public static function formatDateTimeForHtng(Carbon\Carbon|string $dateTime): string;
public static function parseDateFromHtng(string $htngDate): Carbon\Carbon;
public static function parseDateTimeFromHtng(string $htngDateTime): Carbon\Carbon;
public static function mapSourceOfBusiness(string $source): string|null;
public static function getAllSourceOfBusinessMappings(): array;
public static function generateWsseHeaders(string|null $username = null, string|null $password = null): array;
public static function getRequestTimestamp(): string;
public static function generateEchoToken(): string;
public static function getActiveEndpoint(): string;
public static function getWsdlUrl(): string;
public static function normalizeRoomTypeCode(string $roomTypeCode): string;
public static function normalizeRatePlanCode(string $ratePlanCode): string;
public static function getXmlElementName(MessageType $messageType): string;
public static function getXmlDeclaration(): string;
```

---

### ValidationRulesHelper

**Type:** Class
**Full Name:** `App\TravelClick\Support\ValidationRulesHelper`

**Description:** ValidationRulesHelper

#### Constants

```php
public const HTNG_DATE_FORMAT = 'Y-m-d';
public const HTNG_DATETIME_FORMAT = 'Y-m-d\TH:i:s';
public const HTNG_DATETIME_WITH_TZ_FORMAT = 'Y-m-d\TH:i:s.u\Z';
public const MAX_DATE_RANGE_DAYS = 365;
public const MAX_FUTURE_BOOKING_YEARS = 2;
public const HOTEL_CODE_PATTERN = '/^\d{6}$/';
public const ROOM_TYPE_CODE_PATTERN = '/^[A-Z0-9]{3,10}$/';
public const RATE_PLAN_CODE_PATTERN = '/^[A-Z0-9\-]{3,20}$/';
```

#### Methods

```php
public static function validateAndParseHtngDate(string $dateString, bool $allowNull = false): array;
public static function validateDateRange(string $startDate, string $endDate, array $options = []): array;
public static function validateHotelCode(string $hotelCode): array;
public static function validateRoomTypeCode(string $roomTypeCode): array;
public static function validateRatePlanCode(string $ratePlanCode): array;
public static function validateCurrencyCode(string $currencyCode): array;
public static function validateNumericRange(int|float $value, int|float $min, int|float $max, string $fieldName = 'Value'): array;
public static function validateCountTypeAndValue(CountType $countType, int $count): array;
public static function validateOccupancy(int $adults, int $children = 0, int $infants = 0, array $roomTypeRules = []): array;
public static function validateRateAmounts(array $guestAmounts): array;
public static function validateMessageId(string $messageId): array;
public static function validateEmail(string $email, bool $required = true): array;
public static function validatePhone(string $phone, bool $required = true): array;
public static function validateBatchSize(int $batchSize, int $maxBatchSize = 1000): array;
public static function formatDateForHtng(Carbon\CarbonInterface $date, bool $includeTime = false): string;
public static function sanitizeForXml(string $text, int $maxLength = 255): string;
public static function createValidationResult(bool $valid, array|string|null $errors = null, array|string|null $warnings = null): array;
```

---

### XmlNamespaces

**Type:** Class
**Full Name:** `App\TravelClick\Support\XmlNamespaces`

**Description:** XML Namespaces manager for HTNG 2011B Interface

#### Constants

```php
public const SOAP_ENVELOPE = 'http://www.w3.org/2003/05/soap-envelope';
public const WS_ADDRESSING = 'http://www.w3.org/2005/08/addressing';
public const WS_SECURITY = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
public const HTN_SERVICE = 'http://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService';
public const OTA_2003 = 'http://www.opentravel.org/OTA/2003/05';
public const XSI = 'http://www.w3.org/2001/XMLSchema-instance';
public const XSD = 'http://www.w3.org/2001/XMLSchema';
public const PREFIX_MAPPING = [...];
```

#### Methods

```php
public static function getStandardNamespaces(): array;
public static function getSoapEnvelopeNamespaces(): array;
public static function getOtaNamespaces(): array;
public static function buildNamespaceAttributes(array $namespaces): array;
public static function getSoapEnvelopeAttributes(): array;
public static function getOtaSchemaLocation(): string;
public static function isValidPrefix(string $prefix): bool;
public static function getNamespaceByPrefix(string $prefix): string|null;
public static function getDefaultNamespaceForMessageType(string $messageType): string;
public static function getCompleteNamespaceContext(bool $includeSoapNamespaces = true): array;
```

---

### XmlValidator

**Type:** Class
**Full Name:** `App\TravelClick\Support\XmlValidator`

**Description:** Advanced XML validator with support for HTNG 2011B schema validation

#### Methods

```php
public static function validateXmlStructure(string $xml): bool;
public static function validateAgainstSchema(string $xml, MessageType $messageType): bool;
public static function validate(string $xml): bool;
public static function getValidationInfo(): array;
public static function validateXsdSchema(string $xsdPath): bool;
```

---

### XsdSchemas

**Type:** Class
**Full Name:** `App\TravelClick\Support\XsdSchemas`

**Description:** Registry for mapping HTNG 2011B message types to their corresponding XSD schema files.

#### Methods

```php
public static function getSchemaPath(MessageType $messageType): string;
public static function getSchemaContent(MessageType $messageType): string;
public static function hasSchema(MessageType $messageType): bool;
public static function getAvailableMessageTypes(): array;
public static function clearCache(): void;
public static function validateSchemaAvailability(): array;
public static function getSchemaStats(): array;
```

## Detailed Documentation

For detailed documentation of each class, see:

- [BusinessRulesValidator](BusinessRulesValidator.md)
- [CircuitBreaker](CircuitBreaker.md)
- [ConfigurationCache](ConfigurationCache.md)
- [ConfigurationValidator](ConfigurationValidator.md)
- [RetryStrategyInterface](RetryStrategyInterface.md)
- [ExponentialBackoffStrategy](ExponentialBackoffStrategy.md)
- [LinearBackoffStrategy](LinearBackoffStrategy.md)
- [LinkedRateHandler](LinkedRateHandler.md)
- [MessageIdGenerator](MessageIdGenerator.md)
- [RateStructureValidator](RateStructureValidator.md)
- [RetryHelper](RetryHelper.md)
- [SoapClientFactory](SoapClientFactory.md)
- [SoapHeaders](SoapHeaders.md)
- [SoapLogger](SoapLogger.md)
- [TravelClickHelper](TravelClickHelper.md)
- [ValidationRulesHelper](ValidationRulesHelper.md)
- [XmlNamespaces](XmlNamespaces.md)
- [XmlValidator](XmlValidator.md)
- [XsdSchemas](XsdSchemas.md)
