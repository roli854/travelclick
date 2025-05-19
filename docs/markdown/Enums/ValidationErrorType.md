# ValidationErrorType

**Full Class Name:** `App\TravelClick\Enums\ValidationErrorType`

**File:** `Enums/ValidationErrorType.php`

**Type:** Class

## Description

ValidationErrorType Enum
Defines the types of validation errors that can occur in TravelClick operations.
Each error type provides context for logging, debugging, and error handling.

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

