# ErrorType

**Full Class Name:** `App\TravelClick\Enums\ErrorType`

**File:** `Enums/ErrorType.php`

**Type:** Enum

## Description

ErrorType Enum for TravelClick Integration
Categorizes different types of errors that can occur during TravelClick integration.
This helps with error handling, logging, and automated recovery procedures.
Think of this as a filing system for problems - each type goes in its own drawer.

## Constants

### `CONNECTION`

Connection errors - Can't reach TravelClick services

**Value:** `\App\TravelClick\Enums\ErrorType::CONNECTION`

---

### `AUTHENTICATION`

Authentication errors - Wrong credentials or expired tokens

**Value:** `\App\TravelClick\Enums\ErrorType::AUTHENTICATION`

---

### `VALIDATION`

Validation errors - Data doesn't match required format/rules

**Value:** `\App\TravelClick\Enums\ErrorType::VALIDATION`

---

### `SOAP_XML`

SOAP/XML errors - Invalid XML structure or SOAP faults

**Value:** `\App\TravelClick\Enums\ErrorType::SOAP_XML`

---

### `BUSINESS_LOGIC`

Business logic errors - Data conflicts, inventory issues, etc.

**Value:** `\App\TravelClick\Enums\ErrorType::BUSINESS_LOGIC`

---

### `RATE_LIMIT`

Rate limiting - Too many requests to TravelClick

**Value:** `\App\TravelClick\Enums\ErrorType::RATE_LIMIT`

---

### `TIMEOUT`

Timeout errors - Request took too long

**Value:** `\App\TravelClick\Enums\ErrorType::TIMEOUT`

---

### `CONFIGURATION`

Configuration errors - Wrong settings, missing config, etc.

**Value:** `\App\TravelClick\Enums\ErrorType::CONFIGURATION`

---

### `DATA_MAPPING`

Data mapping errors - Problems converting between systems

**Value:** `\App\TravelClick\Enums\ErrorType::DATA_MAPPING`

---

### `UNKNOWN`

Unknown/unexpected errors

**Value:** `\App\TravelClick\Enums\ErrorType::UNKNOWN`

---

### `WARNING`

**Value:** `\App\TravelClick\Enums\ErrorType::WARNING`

---

## Properties

### `$name`

**Type:** `string`

---

### `$value`

**Type:** `string`

---

## Methods

### `description`

Get human-readable description

```php
public function description(): string
```

---

### `canRetry`

Check if this error type can be automatically retried

```php
public function canRetry(): bool
```

---

### `getRetryDelay`

Get retry delay in seconds for this error type

```php
public function getRetryDelay(): int
```

---

### `getSeverity`

Get severity level (1 = critical, 2 = high, 3 = medium, 4 = low)

```php
public function getSeverity(): int
```

---

### `requiresImmediateAttention`

Check if this error requires immediate attention

```php
public function requiresImmediateAttention(): bool
```

---

### `getNotificationType`

Get notification type for this error

```php
public function getNotificationType(): string
```

---

### `getIcon`

Get icon for UI display

```php
public function getIcon(): string
```

---

### `getColor`

Get color for UI display

```php
public function getColor(): string
```

---

### `fromException`

Map from exception type to error category

```php
public function fromException(Throwable $exception): self
```

---

### `criticalTypes`

Get all error types that are considered critical

```php
public function criticalTypes(): array
```

---

### `retryableTypes`

Get all error types that can be retried

```php
public function retryableTypes(): array
```

---

### `cases`

```php
public function cases(): array
```

---

### `from`

```php
public function from(string|int $value): static
```

---

### `tryFrom`

```php
public function tryFrom(string|int $value): static|null
```

---

