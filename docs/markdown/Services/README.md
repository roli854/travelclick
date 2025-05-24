# Services
## Overview
This namespace contains 7 classes/interfaces/enums.
## Table of Contents
- [ConfigurationService](#configurationservice) (Class)
- [ConfigurationServiceInterface](#configurationserviceinterface) (Interface)
- [SoapServiceInterface](#soapserviceinterface) (Interface)
- [ValidationServiceInterface](#validationserviceinterface) (Interface)
- [ReservationService](#reservationservice) (Class)
- [SoapService](#soapservice) (Class)
- [ValidationService](#validationservice) (Class)
## Complete API Reference
---
### ConfigurationService
**Type:** Class
**Full Name:** `App\TravelClick\Services\ConfigurationService`
**Description:** TravelClick Configuration Service
#### Methods
```php
public function __construct(ConfigurationValidator $validator, ConfigurationCache $cache);
public function getPropertyConfig(int $propertyId): PropertyConfigDto;
public function getGlobalConfig(): TravelClickConfigDto;
public function getEndpointConfig(Environment|null $environment = null): EndpointConfigDto;
public function validatePropertyConfig(int $propertyId): array;
public function cacheConfiguration(int $propertyId): bool;
public function clearCache(ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int|null $propertyId = null): bool;
public function updatePropertyConfig(int $propertyId, array $config): PropertyConfigDto;
public function getConfigValue(string $key, int|null $propertyId = null, mixed $default = null): mixed;
public function isPropertyConfigured(int $propertyId): bool;
public function getConfiguredProperties(): array;
public function exportPropertyConfig(int $propertyId): array;
public function importPropertyConfig(int $propertyId, array $config): PropertyConfigDto;
```
---
### ConfigurationServiceInterface
**Type:** Interface
**Full Name:** `App\TravelClick\Services\Contracts\ConfigurationServiceInterface`
**Description:** Interface for TravelClick Configuration Service
#### Methods
```php
public abstract function getPropertyConfig(int $propertyId): PropertyConfigDto;
public abstract function getGlobalConfig(): TravelClickConfigDto;
public abstract function getEndpointConfig(Environment|null $environment = null): EndpointConfigDto;
public abstract function validatePropertyConfig(int $propertyId): array;
public abstract function cacheConfiguration(int $propertyId): bool;
public abstract function clearCache(ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int|null $propertyId = null): bool;
public abstract function updatePropertyConfig(int $propertyId, array $config): PropertyConfigDto;
public abstract function getConfigValue(string $key, int|null $propertyId = null, mixed $default = null): mixed;
public abstract function isPropertyConfigured(int $propertyId): bool;
public abstract function getConfiguredProperties(): array;
public abstract function exportPropertyConfig(int $propertyId): array;
public abstract function importPropertyConfig(int $propertyId, array $config): PropertyConfigDto;
```
---
### SoapServiceInterface
**Type:** Interface
**Full Name:** `App\TravelClick\Services\Contracts\SoapServiceInterface`
**Description:** Interface for TravelClick SOAP service operations
#### Methods
```php
public abstract function sendRequest(SoapRequestDto $request): SoapResponseDto;
public abstract function updateInventory(string $xml, string $hotelCode): SoapResponseDto;
public abstract function updateRates(string $xml, string $hotelCode): SoapResponseDto;
public abstract function sendReservation(string $xml, string $hotelCode): SoapResponseDto;
public abstract function testConnection(): bool;
public abstract function getClient(): SoapClient;
public abstract function isConnected(): bool;
```
---
### ValidationServiceInterface
**Type:** Interface
**Full Name:** `App\TravelClick\Services\Contracts\ValidationServiceInterface`
**Description:** ValidationService Interface
#### Methods
```php
public abstract function validateSoapMessage(App\TravelClick\DTOs\SoapRequestDto|App\TravelClick\DTOs\SoapResponseDto $message, MessageType $messageType): array;
public abstract function validateXmlStructure(string $xml, string $schemaType): array;
public abstract function validateInventoryData(array $inventoryData, string $propertyId): array;
public abstract function validateRateData(array $rateData, string $propertyId): array;
public abstract function validateReservationData(array $reservationData, ReservationType $reservationType, string $propertyId): array;
public abstract function validateGroupBlockData(array $groupData, string $propertyId): array;
public abstract function validateInventoryCounts(array $inventoryCounts, string $inventoryMethod): array;
public abstract function sanitizeData(array $data, array $rules = []): array;
public abstract function validateDateRange(string $startDate, string $endDate, array $constraints = []): array;
public abstract function validatePropertyRules(string $propertyId, array $data, string $operation): array;
public abstract function validateRequiredFields(array $data, MessageType $messageType, array $optionalFields = []): array;
public abstract function validateBusinessLogic(array $data, string $operationType, MessageType $messageType): array;
public abstract function validateSoapHeaders(array $headers, string $propertyId): array;
public abstract function getValidationRules(MessageType $messageType, string $operation = 'create'): array;
public abstract function allValidationsPassed(array $validationResults): bool;
public abstract function getValidationErrors(array $validationResults): array;
```
---
### ReservationService
**Type:** Class
**Full Name:** `App\TravelClick\Services\ReservationService`
**Description:** Service class for handling reservation operations with TravelClick
#### Methods
```php
public function __construct(SoapServiceInterface $soapService, ReservationXmlBuilder $xmlBuilder, ReservationParser $parser);
public function processModification(ReservationDataDto $reservationData, bool $validateRoomTypes = true): ReservationResponseDto;
public function findOriginalReservation(string $confirmationNumber): ReservationDataDto|null;
public function processNewReservation(ReservationDataDto $reservationData, bool $validateRoomTypes = true): ReservationResponseDto;
public function processCancellation(ReservationDataDto $reservationData): ReservationResponseDto;
```
---
### SoapService
**Type:** Class
**Full Name:** `App\TravelClick\Services\SoapService`
**Description:** Service for handling all SOAP communications with TravelClick
#### Methods
```php
public function __construct(SoapClientFactory|null $clientFactory = null);
public function sendRequest(SoapRequestDto $request): SoapResponseDto;
public function updateInventory(string $xml, string $hotelCode): SoapResponseDto;
public function updateRates(string $xml, string $hotelCode): SoapResponseDto;
public function sendReservation(string $xml, string $hotelCode): SoapResponseDto;
public function testConnection(): bool;
public function getClient(): SoapClient;
public function isConnected(): bool;
public function getLastRequestId(): string;
public function getConfigSummary(): array;
public function reconnect(): void;
public function __destruct();
```
---
### ValidationService
**Type:** Class
**Full Name:** `App\TravelClick\Services\ValidationService`
**Description:** Complete validation service implementing ValidationServiceInterface
#### Methods
```php
public function __construct(ValidationRulesHelper|null $rulesHelper = null, BusinessRulesValidator|null $businessRulesValidator = null);
public function validateSoapMessage(App\TravelClick\DTOs\SoapRequestDto|App\TravelClick\DTOs\SoapResponseDto $message, MessageType $messageType): array;
public function validateXmlStructure(string $xml, string $schemaType): array;
public function validateInventoryData(array $inventoryData, string $propertyId): array;
public function validateRateData(array $rateData, string $propertyId): array;
public function validateReservationData(array $reservationData, ReservationType $reservationType, string $propertyId): array;
public function validateGroupBlockData(array $groupData, string $propertyId): array;
public function validateInventoryCounts(array $inventoryCounts, string $inventoryMethod): array;
public function sanitizeData(array $data, array $rules = []): array;
public function validateDateRange(string $startDate, string $endDate, array $constraints = []): array;
public function validatePropertyRules(string $propertyId, array $data, string $operation): array;
public function validateRequiredFields(array $data, MessageType $messageType, array $optionalFields = []): array;
public function validateBusinessLogic(array $data, string $operationType, MessageType $messageType): array;
public function validateSoapHeaders(array $headers, string $propertyId): array;
public function getValidationRules(MessageType $messageType, string $operation = 'create'): array;
public function allValidationsPassed(array $validationResults): bool;
public function getValidationErrors(array $validationResults): array;
```
## Detailed Documentation
For detailed documentation of each class, see:
- [ConfigurationService](ConfigurationService.md)
- [ConfigurationServiceInterface](ConfigurationServiceInterface.md)
- [SoapServiceInterface](SoapServiceInterface.md)
- [ValidationServiceInterface](ValidationServiceInterface.md)
- [ReservationService](ReservationService.md)
- [SoapService](SoapService.md)
- [ValidationService](ValidationService.md)