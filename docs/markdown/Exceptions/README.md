# Exceptions

## Overview

This namespace contains 6 classes/interfaces/enums.

## Table of Contents

- [ConfigurationException](#configurationexception) (Class)
- [InvalidConfigurationException](#invalidconfigurationexception) (Class)
- [SoapException](#soapexception) (Class)
- [TravelClickAuthenticationException](#travelclickauthenticationexception) (Class)
- [TravelClickConnectionException](#travelclickconnectionexception) (Class)
- [ValidationException](#validationexception) (Class)

## Complete API Reference

---

### ConfigurationException

**Type:** Class
**Full Name:** `App\TravelClick\Exceptions\ConfigurationException`

**Description:** Base exception for configuration-related errors in TravelClick integration

#### Methods

```php
public function __construct(string $message = '', int $code = 0, Throwable|null $previous = null, ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int|null $propertyId = null, array $context = [], array $suggestions = []);
public function getScope(): ConfigScope;
public function getPropertyId(): int|null;
public function getContext(): array;
public function getSuggestions(): array;
public static function missing(string $configKey, ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int|null $propertyId = null): self;
public static function invalid(string $configKey, mixed $value, string|null $expectedType = null, ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int|null $propertyId = null): self;
public static function cache(string $operation, string $reason = '', int|null $propertyId = null): self;
public static function propertyNotFound(int $propertyId): self;
public static function environmentMismatch(string $expected, string $actual, int|null $propertyId = null): self;
public static function validationFailed(array $errors, ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int|null $propertyId = null): self;
public function getDetailedInfo(): array;
public function getUserMessage(): string;
public function isRecoverable(): bool;
public function toArray(): array;
```

---

### InvalidConfigurationException

**Type:** Class
**Full Name:** `App\TravelClick\Exceptions\InvalidConfigurationException`

**Description:** Exception for invalid configuration values in TravelClick integration

#### Methods

```php
public function __construct(string $message = '', array $invalidFields = [], array $validationRules = [], int $code = 0, Throwable|null $previous = null, ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int|null $propertyId = null, array $context = []);
public function getInvalidFields(): array;
public function getValidationRules(): array;
public static function invalidHotelCode(string $hotelCode, int|null $propertyId = null): self;
public static function invalidCredentials(string|null $username = null, string|null $password = null, int|null $propertyId = null): self;
public static function invalidTimeout(int $timeout, string $type = 'connection', int|null $propertyId = null): self;
public static function invalidRetryConfig(int|null $attempts = null, array|null $backoffSeconds = null, int|null $propertyId = null): self;
public static function invalidEnvironment(string $environment, int|null $propertyId = null): self;
public static function invalidMessageTypes(array $messageTypes, int|null $propertyId = null): self;
public static function invalidEndpoint(string $url, string $type = 'endpoint', int|null $propertyId = null): self;
public static function multipleFields(array $errors, ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int|null $propertyId = null): self;
public function getGroupedErrors(): array;
public function hasInvalidField(string $field): bool;
public function getInvalidValue(string $field): mixed;
public function toArray(): array;
```

---

### SoapException

**Type:** Class
**Full Name:** `App\TravelClick\Exceptions\SoapException`

**Description:** Base exception for all SOAP-related errors in TravelClick integration

#### Properties

```php
public readonly string|null $messageId;
public readonly string|null $soapFaultCode;
public readonly string|null $soapFaultString;
public readonly array|null $context;
```

#### Methods

```php
public function __construct(string $message, string|null $messageId = null, string|null $soapFaultCode = null, string|null $soapFaultString = null, array|null $context = null, int $code = 0, Throwable|null $previous = null);
public static function fromSoapFault(SoapFault $fault, string|null $messageId = null, array|null $context = null): self;
public function getContext(): array;
public function isFaultCode(string $code): bool;
public function isConnectionError(): bool;
public function isAuthenticationError(): bool;
```

---

### TravelClickAuthenticationException

**Type:** Class
**Full Name:** `App\TravelClick\Exceptions\TravelClickAuthenticationException`

**Description:** Exception thrown when authentication with TravelClick fails

#### Properties

```php
public readonly string|null $username;
public readonly string|null $authenticationMethod;
public readonly string|null $faultDetail;
```

#### Methods

```php
public function __construct(string $message, string|null $messageId = null, string|null $username = null, string|null $authenticationMethod = 'WSSE', string|null $faultDetail = null, Throwable|null $previous = null);
public static function invalidCredentials(string $username, string|null $messageId = null, string|null $details = null): self;
public static function expiredCredentials(string $username, string|null $messageId = null): self;
public static function insufficientPermissions(string $username, string $requiredPermission, string|null $messageId = null): self;
public static function serviceUnavailable(string|null $messageId = null, string|null $details = null): self;
public function isRetryable(): bool;
public function getSuggestedRetryDelay(): int;
public function getTroubleshootingSteps(): array;
public function getCredentialValidationSteps(): array;
public function getSecurityNote(): string;
```

---

### TravelClickConnectionException

**Type:** Class
**Full Name:** `App\TravelClick\Exceptions\TravelClickConnectionException`

**Description:** Exception thrown when there are connection issues with TravelClick

#### Properties

```php
public readonly int|null $timeoutSeconds;
public readonly string|null $endpoint;
```

#### Methods

```php
public function __construct(string $message, string|null $messageId = null, int|null $timeoutSeconds = null, string|null $endpoint = null, Throwable|null $previous = null);
public static function timeout(int $timeoutSeconds, string $endpoint, string|null $messageId = null): self;
public static function unreachable(string $endpoint, string|null $messageId = null, string|null $details = null): self;
public static function sslError(string $sslError, string $endpoint, string|null $messageId = null): self;
public function isRetryable(): bool;
public function getSuggestedRetryDelay(): int;
public function getTroubleshootingSteps(): array;
```

---

### ValidationException

**Type:** Class
**Full Name:** `App\TravelClick\Exceptions\ValidationException`

**Description:** ValidationException

#### Methods

```php
public function __construct(string $message = 'Validation failed', string $context = '', array $validationErrors = [], array $validationWarnings = [], mixed $invalidData = null, int $code = 0, Throwable|null $previous = null);
public function getContext(): string;
public function getValidationErrors(): array;
public function getValidationWarnings(): array;
public function getInvalidData(): mixed;
public function getDetailedMessage(): string;
public function toArray(): array;
public static function forXmlValidation(array $errors, string $xmlType = 'XML'): static;
public static function forBusinessLogic(string $rule, mixed $data = null): static;
public static function forInventory(array $errors, array $data = []): static;
public static function forRate(array $errors, array $data = []): static;
public static function forReservation(array $errors, array $data = []): static;
public static function forGroupBlock(array $errors, array $data = []): static;
public static function forSoapHeaders(array $errors): static;
public static function forPropertyRules(string $propertyId, array $errors): static;
public static function forRequiredFields(array $missingFields): static;
public static function forDateRange(string $error): static;
public static function fromValidationResults(array $results, string $context = ''): static;
public static function forSchemaValidation(string $schemaType, array $errors): static;
public static function forCountType(string $method, array $errors): static;
public static function forMessageTypeMismatch(string $expected, string $actual): static;
public static function forSanitization(string $error): static;
public function hasValidationErrors(): bool;
public function hasValidationWarnings(): bool;
public function getErrorCount(): int;
public function getWarningCount(): int;
public function addError(string $error): void;
public function addWarning(string $warning): void;
public function getSummary(): array;
public function toJson(): string;
```

## Detailed Documentation

For detailed documentation of each class, see:

- [ConfigurationException](ConfigurationException.md)
- [InvalidConfigurationException](InvalidConfigurationException.md)
- [SoapException](SoapException.md)
- [TravelClickAuthenticationException](TravelClickAuthenticationException.md)
- [TravelClickConnectionException](TravelClickConnectionException.md)
- [ValidationException](ValidationException.md)
