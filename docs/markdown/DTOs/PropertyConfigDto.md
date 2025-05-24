# PropertyConfigDto

**Full Class Name:** `App\TravelClick\DTOs\PropertyConfigDto`

**File:** `DTOs/PropertyConfigDto.php`

**Type:** Class

## Description

Property Configuration DTO
This DTO encapsulates property-specific TravelClick configuration data.
It provides structured access to hotel-specific settings and overrides.

## Properties

### `$propertyId`

**Type:** `int`

---

### `$hotelCode`

**Type:** `string`

---

### `$propertyName`

**Type:** `string`

---

### `$environment`

**Type:** `Environment`

---

### `$username`

**Type:** `string`

---

### `$password`

**Type:** `string`

---

### `$timeout`

**Type:** `int|null`

---

### `$retryAttempts`

**Type:** `int|null`

---

### `$backoffSeconds`

**Type:** `array|null`

---

### `$enabledMessageTypes`

**Type:** `array`

---

### `$customSettings`

**Type:** `array`

---

### `$overrideGlobal`

**Type:** `bool`

---

### `$isActive`

**Type:** `bool`

---

### `$queueOverrides`

**Type:** `array`

---

### `$endpointOverrides`

**Type:** `array`

---

### `$lastSyncDate`

**Type:** `Carbon\Carbon|null`

---

### `$lastUpdated`

**Type:** `Carbon\Carbon|null`

---

### `$notes`

**Type:** `string|null`

---

## Methods

### `__construct`

```php
public function __construct(int $propertyId, string $hotelCode, string $propertyName, Environment $environment, string $username, string $password, int|null $timeout = null, int|null $retryAttempts = null, array|null $backoffSeconds = null, array $enabledMessageTypes = [], array $customSettings = [], bool $overrideGlobal = false, bool $isActive = true, array $queueOverrides = [], array $endpointOverrides = [], Carbon\Carbon|null $lastSyncDate = null, Carbon\Carbon|null $lastUpdated = null, string|null $notes = null)
```

---

### `fromArray`

Create from array data

```php
public function fromArray(array $data): self
```

---

### `fromModel`

Create from database model

```php
public function fromModel(TravelClickPropertyConfig $model): self
```

---

### `toArray`

Convert to array

```php
public function toArray(): array
```

---

### `toDatabase`

Convert to database format

```php
public function toDatabase(): array
```

---

### `getEffectiveTimeout`

Get effective timeout (property or fallback to global)

```php
public function getEffectiveTimeout(int $globalTimeout): int
```

---

### `getEffectiveRetryAttempts`

Get effective retry attempts (property or fallback to global)

```php
public function getEffectiveRetryAttempts(int $globalRetryAttempts): int
```

---

### `getEffectiveBackoffSeconds`

Get effective backoff seconds (property or fallback to global)

```php
public function getEffectiveBackoffSeconds(array $globalBackoffSeconds): array
```

---

### `isMessageTypeEnabled`

Check if a message type is enabled for this property

```php
public function isMessageTypeEnabled(string $messageType): bool
```

---

### `getCustomSetting`

Get custom setting value

```php
public function getCustomSetting(string $key, mixed $default = null): mixed
```

---

### `isComplete`

Check if property configuration is complete

```php
public function isComplete(): bool
```

---

### `requiresSync`

Check if property requires sync

```php
public function requiresSync(int $maxDaysWithoutSync = 7): bool
```

---

### `mergeWithGlobal`

Merge with global configuration

```php
public function mergeWithGlobal(TravelClickConfigDto $global): self
```

---

### `getCacheKey`

Get cache key for this property configuration

```php
public function getCacheKey(): string
```

---

### `with`

Create a copy with updated values

```php
public function with(array $updates): self
```

---

### `validate`

Validate property configuration

```php
public function validate(): array
```

---

