# ValidationException

**Full Class Name:** `App\TravelClick\Exceptions\ValidationException`

**File:** `Exceptions/ValidationException.php`

**Type:** Class

## Description

ValidationException
Thrown when data validation fails for TravelClick operations.
This exception provides detailed information about what validation failed
and includes context for debugging and error reporting.

## Methods

### `__construct`

Create a new ValidationException instance

```php
public function __construct(string $message = 'Validation failed', string $context = '', array $validationErrors = [], array $validationWarnings = [], mixed $invalidData = null, int $code = 0, Throwable|null $previous = null)
```

**Parameters:**

- `$message` (string): The exception message
- `$context` (string): The validation context
- `$validationErrors` (array<string>): Array of validation errors
- `$validationWarnings` (array<string>): Array of validation warnings
- `$invalidData` (mixed): The data that failed validation
- `$code` (int): The exception code
- `$previous` (Throwable|null): Previous exception

---

### `getContext`

Get the validation context

```php
public function getContext(): string
```

---

### `getValidationErrors`

Get validation errors

```php
public function getValidationErrors(): array
```

**Returns:** array<string> - 

---

### `getValidationWarnings`

Get validation warnings

```php
public function getValidationWarnings(): array
```

**Returns:** array<string> - 

---

### `getInvalidData`

Get the invalid data

```php
public function getInvalidData(): mixed
```

---

### `getDetailedMessage`

Get formatted error message with full details

```php
public function getDetailedMessage(): string
```

---

### `toArray`

Convert exception to array for logging/API responses

```php
public function toArray(): array
```

**Returns:** array<string, - mixed>

---

### `forXmlValidation`

Create exception for XML validation failure

```php
public function forXmlValidation(array $errors, string $xmlType = 'XML'): static
```

---

### `forBusinessLogic`

Create exception for business logic validation failure

```php
public function forBusinessLogic(string $rule, mixed $data = null): static
```

---

### `forInventory`

Create exception for inventory validation failure

```php
public function forInventory(array $errors, array $data = []): static
```

---

### `forRate`

Create exception for rate validation failure

```php
public function forRate(array $errors, array $data = []): static
```

---

### `forReservation`

Create exception for reservation validation failure

```php
public function forReservation(array $errors, array $data = []): static
```

---

### `forGroupBlock`

Create exception for group block validation failure

```php
public function forGroupBlock(array $errors, array $data = []): static
```

---

### `forSoapHeaders`

Create exception for SOAP header validation failure

```php
public function forSoapHeaders(array $errors): static
```

---

### `forPropertyRules`

Create exception for property rules validation failure

```php
public function forPropertyRules(string $propertyId, array $errors): static
```

---

### `forRequiredFields`

Create exception for required fields validation failure

```php
public function forRequiredFields(array $missingFields): static
```

---

### `forDateRange`

Create exception for date range validation failure

```php
public function forDateRange(string $error): static
```

---

### `fromValidationResults`

Create exception from validation results array

```php
public function fromValidationResults(array $results, string $context = ''): static
```

**Parameters:**

- `$context` (string): Validation context

**Returns:** static - 

---

### `forSchemaValidation`

Create exception for schema validation failure

```php
public function forSchemaValidation(string $schemaType, array $errors): static
```

---

### `forCountType`

Create exception for count type validation failure

```php
public function forCountType(string $method, array $errors): static
```

---

### `forMessageTypeMismatch`

Create exception for message type mismatch

```php
public function forMessageTypeMismatch(string $expected, string $actual): static
```

---

### `forSanitization`

Create exception for data sanitization failure

```php
public function forSanitization(string $error): static
```

---

### `hasValidationErrors`

Check if this exception has validation errors

```php
public function hasValidationErrors(): bool
```

---

### `hasValidationWarnings`

Check if this exception has validation warnings

```php
public function hasValidationWarnings(): bool
```

---

### `getErrorCount`

Get total error count

```php
public function getErrorCount(): int
```

---

### `getWarningCount`

Get total warning count

```php
public function getWarningCount(): int
```

---

### `addError`

Add additional validation error

```php
public function addError(string $error): void
```

---

### `addWarning`

Add additional validation warning

```php
public function addWarning(string $warning): void
```

---

### `getSummary`

Get summary of validation issues

```php
public function getSummary(): array
```

**Returns:** array<string, - mixed>

---

### `toJson`

Convert to JSON string for API responses

```php
public function toJson(): string
```

---

