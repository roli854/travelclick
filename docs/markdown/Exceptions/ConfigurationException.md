# ConfigurationException

**Full Class Name:** `App\TravelClick\Exceptions\ConfigurationException`

**File:** `Exceptions/ConfigurationException.php`

**Type:** Class

## Description

Base exception for configuration-related errors in TravelClick integration
This exception is thrown when configuration issues are encountered,
providing context about what went wrong and how to fix it.

## Methods

### `__construct`

```php
public function __construct(string $message = '', int $code = 0, Throwable|null $previous = null, App\TravelClick\Enums\ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int $propertyId = null, array $context = [], array $suggestions = [])
```

---

### `getScope`

Get the configuration scope this error relates to

```php
public function getScope(): App\TravelClick\Enums\ConfigScope
```

---

### `getPropertyId`

Get the property ID if this is a property-specific error

```php
public function getPropertyId(): int
```

---

### `getContext`

Get additional context about the error

```php
public function getContext(): array
```

---

### `getSuggestions`

Get suggestions for resolving the error

```php
public function getSuggestions(): array
```

---

### `missing`

Create exception for missing configuration

```php
public function missing(string $configKey, App\TravelClick\Enums\ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int $propertyId = null): self
```

---

### `invalid`

Create exception for invalid configuration value

```php
public function invalid(string $configKey, mixed $value, string $expectedType = null, App\TravelClick\Enums\ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int $propertyId = null): self
```

---

### `cache`

Create exception for cache-related configuration errors

```php
public function cache(string $operation, string $reason = '', int $propertyId = null): self
```

---

### `propertyNotFound`

Create exception for property not found

```php
public function propertyNotFound(int $propertyId): self
```

---

### `environmentMismatch`

Create exception for environment mismatch

```php
public function environmentMismatch(string $expected, string $actual, int $propertyId = null): self
```

---

### `validationFailed`

Create exception for validation failure

```php
public function validationFailed(array $errors, App\TravelClick\Enums\ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int $propertyId = null): self
```

---

### `getDetailedInfo`

Get detailed error information for logging

```php
public function getDetailedInfo(): array
```

---

### `getUserMessage`

Get user-friendly error message

```php
public function getUserMessage(): string
```

---

### `isRecoverable`

Check if error is recoverable

```php
public function isRecoverable(): bool
```

---

### `toArray`

Convert to array for API responses

```php
public function toArray(): array
```

---

