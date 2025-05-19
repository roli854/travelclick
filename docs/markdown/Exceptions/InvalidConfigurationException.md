# InvalidConfigurationException

**Full Class Name:** `App\TravelClick\Exceptions\InvalidConfigurationException`

**File:** `Exceptions/InvalidConfigurationException.php`

**Type:** Class

## Description

Exception for invalid configuration values in TravelClick integration
This exception is thrown when configuration values don't meet
the required format, type, or business rules.

## Methods

### `__construct`

```php
public function __construct(string $message = '', array $invalidFields = [], array $validationRules = [], int $code = 0, Throwable|null $previous = null, App\TravelClick\Enums\ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int $propertyId = null, array $context = [])
```

---

### `getInvalidFields`

Get invalid fields

```php
public function getInvalidFields(): array
```

---

### `getValidationRules`

Get validation rules

```php
public function getValidationRules(): array
```

---

### `invalidHotelCode`

Create exception for invalid hotel code

```php
public function invalidHotelCode(string $hotelCode, int $propertyId = null): self
```

---

### `invalidCredentials`

Create exception for invalid credentials

```php
public function invalidCredentials(string $username = null, string $password = null, int $propertyId = null): self
```

---

### `invalidTimeout`

Create exception for invalid timeout values

```php
public function invalidTimeout(int $timeout, string $type = 'connection', int $propertyId = null): self
```

---

### `invalidRetryConfig`

Create exception for invalid retry configuration

```php
public function invalidRetryConfig(int $attempts = null, array $backoffSeconds = null, int $propertyId = null): self
```

---

### `invalidEnvironment`

Create exception for invalid environment configuration

```php
public function invalidEnvironment(string $environment, int $propertyId = null): self
```

---

### `invalidMessageTypes`

Create exception for invalid message types

```php
public function invalidMessageTypes(array $messageTypes, int $propertyId = null): self
```

---

### `invalidEndpoint`

Create exception for invalid endpoint URL

```php
public function invalidEndpoint(string $url, string $type = 'endpoint', int $propertyId = null): self
```

---

### `multipleFields`

Create exception for multiple field validation errors

```php
public function multipleFields(array $errors, App\TravelClick\Enums\ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int $propertyId = null): self
```

---

### `getGroupedErrors`

Get validation errors grouped by field

```php
public function getGroupedErrors(): array
```

---

### `hasInvalidField`

Check if specific field is invalid

```php
public function hasInvalidField(string $field): bool
```

---

### `getInvalidValue`

Get invalid value for specific field

```php
public function getInvalidValue(string $field): mixed
```

---

### `toArray`

Convert to array for API responses

```php
public function toArray(): array
```

---

