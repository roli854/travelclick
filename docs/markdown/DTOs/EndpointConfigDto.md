# EndpointConfigDto

**Full Class Name:** `App\TravelClick\DTOs\EndpointConfigDto`

**File:** `DTOs/EndpointConfigDto.php`

**Type:** Class

## Description

Endpoint Configuration DTO
This DTO encapsulates TravelClick endpoint configuration data.
It provides structured access to SOAP endpoint settings, URLs, and connection parameters.

## Properties

### `$environment`

**Type:** `Environment`

---

### `$url`

**Type:** `string`

---

### `$wsdlUrl`

**Type:** `string`

---

### `$connectionTimeout`

**Type:** `int`

---

### `$requestTimeout`

**Type:** `int`

---

### `$sslVerifyPeer`

**Type:** `bool`

---

### `$sslVerifyHost`

**Type:** `bool`

---

### `$sslCaFile`

**Type:** `string|null`

---

### `$soapOptions`

**Type:** `array`

---

### `$httpHeaders`

**Type:** `array`

---

### `$userAgent`

**Type:** `string|null`

---

### `$compression`

**Type:** `bool`

---

### `$encoding`

**Type:** `string`

---

### `$maxRedirects`

**Type:** `int`

---

### `$keepAlive`

**Type:** `bool`

---

### `$streamContext`

**Type:** `array`

---

## Methods

### `__construct`

```php
public function __construct(Environment $environment, string $url, string $wsdlUrl, int $connectionTimeout, int $requestTimeout, bool $sslVerifyPeer, bool $sslVerifyHost, string|null $sslCaFile = null, array $soapOptions = [], array $httpHeaders = [], string|null $userAgent = null, bool $compression = false, string $encoding = 'UTF-8', int $maxRedirects = 0, bool $keepAlive = true, array $streamContext = [])
```

---

### `fromEnvironment`

Create from environment

```php
public function fromEnvironment(Environment $environment): self
```

---

### `fromArray`

Create from array data

```php
public function fromArray(array $data): self
```

---

### `toArray`

Convert to array

```php
public function toArray(): array
```

---

### `getSoapClientOptions`

Get SOAP client options

```php
public function getSoapClientOptions(): array
```

---

### `getStreamContext`

Get stream context for SOAP client

```php
public function getStreamContext(): array
```

---

### `validate`

Validate endpoint configuration

```php
public function validate(): array
```

---

### `testConnection`

Test connection to endpoint

```php
public function testConnection(): bool
```

---

### `with`

Create a copy with updated values

```php
public function with(array $updates): self
```

---

### `getCacheKey`

Get cache key for this endpoint configuration

```php
public function getCacheKey(): string
```

---

### `isDevelopment`

Check if configuration is for development/testing

```php
public function isDevelopment(): bool
```

---

### `isProduction`

Check if configuration is for production

```php
public function isProduction(): bool
```

---

### `getOptimizations`

Get environment-specific optimizations

```php
public function getOptimizations(): array
```

---

