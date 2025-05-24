# TravelClickConfigDto

**Full Class Name:** `App\TravelClick\DTOs\TravelClickConfigDto`

**File:** `DTOs/TravelClickConfigDto.php`

**Type:** Class

## Description

TravelClick Global Configuration DTO
This DTO encapsulates all global TravelClick configuration data.
It provides structured access to system-wide settings and defaults.

## Properties

### `$defaultEnvironment`

**Type:** `Environment`

---

### `$defaultTimeout`

**Type:** `int`

---

### `$defaultRetryAttempts`

**Type:** `int`

---

### `$defaultBackoffSeconds`

**Type:** `array`

---

### `$loggingLevel`

**Type:** `string`

---

### `$enableCache`

**Type:** `bool`

---

### `$defaultCacheTtl`

**Type:** `int`

---

### `$supportedMessageTypes`

**Type:** `array`

---

### `$queueConfig`

**Type:** `array`

---

### `$sslConfig`

**Type:** `array`

---

### `$customHeaders`

**Type:** `array`

---

### `$debug`

**Type:** `bool`

---

### `$lastUpdated`

**Type:** `Carbon\Carbon|null`

---

### `$version`

**Type:** `string|null`

---

## Methods

### `__construct`

```php
public function __construct(Environment $defaultEnvironment, int $defaultTimeout, int $defaultRetryAttempts, array $defaultBackoffSeconds, string $loggingLevel, bool $enableCache, int $defaultCacheTtl, array $supportedMessageTypes, array $queueConfig, array $sslConfig, array $customHeaders, bool $debug, Carbon\Carbon|null $lastUpdated = null, string|null $version = null)
```

---

### `fromArray`

Create from configuration array

```php
public function fromArray(array $config): self
```

---

### `toArray`

Convert to array

```php
public function toArray(): array
```

---

### `fromConfig`

Create from config file

```php
public function fromConfig(): self
```

---

### `getTimeoutForOperation`

Get timeout for specific operation

```php
public function getTimeoutForOperation(string $operation): int
```

---

### `getRetryAttemptsForOperation`

Get retry attempts for specific operation

```php
public function getRetryAttemptsForOperation(string $operation): int
```

---

### `isMessageTypeSupported`

Check if message type is supported

```php
public function isMessageTypeSupported(string $messageType): bool
```

---

### `getQueueForOperation`

Get queue name for operation

```php
public function getQueueForOperation(string $operation): string
```

---

### `isValid`

Check if configuration is valid

```php
public function isValid(): bool
```

---

### `getCacheKey`

Get cache key for this configuration

```php
public function getCacheKey(): string
```

---

### `getCacheTtl`

Get cache TTL for this configuration

```php
public function getCacheTtl(): int
```

---

### `mergeWith`

Merge with another configuration (other takes precedence)

```php
public function mergeWith(self $other): self
```

---

### `with`

Create a copy with updated values

```php
public function with(array $updates): self
```

---

