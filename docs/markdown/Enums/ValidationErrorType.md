# ValidationErrorType

**Full Class Name:** `App\TravelClick\Enums\ValidationErrorType`

**File:** `Enums/ValidationErrorType.php`

**Type:** Enum

## Description

ValidationErrorType Enum
Defines the types of validation errors that can occur in TravelClick operations.
Each error type provides context for logging, debugging, and error handling.

## Constants

### `XML_STRUCTURE`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::XML_STRUCTURE`

---

### `XML_SCHEMA`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::XML_SCHEMA`

---

### `XML_NAMESPACE`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::XML_NAMESPACE`

---

### `BUSINESS_LOGIC`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::BUSINESS_LOGIC`

---

### `REQUIRED_FIELD`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::REQUIRED_FIELD`

---

### `DATA_TYPE`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::DATA_TYPE`

---

### `DATE_RANGE`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::DATE_RANGE`

---

### `INVENTORY_METHOD`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::INVENTORY_METHOD`

---

### `COUNT_TYPE`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::COUNT_TYPE`

---

### `CURRENCY_CODE`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::CURRENCY_CODE`

---

### `PROPERTY_RULES`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::PROPERTY_RULES`

---

### `SOAP_HEADERS`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::SOAP_HEADERS`

---

### `MESSAGE_TYPE`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::MESSAGE_TYPE`

---

### `GUEST_INFORMATION`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::GUEST_INFORMATION`

---

### `ROOM_STAYS`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::ROOM_STAYS`

---

### `RATE_PLAN`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::RATE_PLAN`

---

### `ROOM_TYPE`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::ROOM_TYPE`

---

### `GROUP_BLOCK`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::GROUP_BLOCK`

---

### `RESERVATION_TYPE`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::RESERVATION_TYPE`

---

### `PACKAGE_CODE`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::PACKAGE_CODE`

---

### `TRAVEL_AGENCY`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::TRAVEL_AGENCY`

---

### `CORPORATE`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::CORPORATE`

---

### `AUTHENTICATION`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::AUTHENTICATION`

---

### `SANITIZATION`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::SANITIZATION`

---

### `CONFIGURATION`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::CONFIGURATION`

---

### `UNKNOWN`

**Value:** `\App\TravelClick\Enums\ValidationErrorType::UNKNOWN`

---

## Properties

### `$name`

**Type:** `string`

---

### `$value`

**Type:** `string`

---

## Methods

### `getDescription`

Get human-readable description of the error type

```php
public function getDescription(): string
```

---

### `getSeverity`

Get severity level of the error type

```php
public function getSeverity(): string
```

---

### `isCritical`

Check if this error type is critical

```php
public function isCritical(): bool
```

---

### `shouldBlockProcessing`

Check if this error type should block processing

```php
public function shouldBlockProcessing(): bool
```

---

### `getBySeverity`

Get all error types by severity

```php
public function getBySeverity(string $severity): array
```

**Parameters:**

- `$severity` (string): 

**Returns:** array<self> - 

---

### `fromContext`

Get error type from context string

```php
public function fromContext(string $context): self
```

---

### `toArray`

Convert to array for API responses

```php
public function toArray(): array
```

**Returns:** array<string, - mixed>

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

