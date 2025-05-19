# ConfigurationCache

**Full Class Name:** `App\TravelClick\Support\ConfigurationCache`

**File:** `Support/ConfigurationCache.php`

**Type:** Class

## Description

Configuration Cache Handler
Manages caching of TravelClick configurations for optimal performance.
Uses Laravel's cache system with intelligent TTL management.

## Methods

### `__construct`

```php
public function __construct()
```

---

### `getPropertyConfig`

Get cached property configuration

```php
public function getPropertyConfig(int $propertyId): App\TravelClick\DTOs\PropertyConfigDto|null
```

---

### `putPropertyConfig`

Cache property configuration

```php
public function putPropertyConfig(int $propertyId, App\TravelClick\DTOs\PropertyConfigDto $config): bool
```

---

### `getGlobalConfig`

Get cached global configuration

```php
public function getGlobalConfig(): App\TravelClick\DTOs\TravelClickConfigDto|null
```

---

### `putGlobalConfig`

Cache global configuration

```php
public function putGlobalConfig(App\TravelClick\DTOs\TravelClickConfigDto $config): bool
```

---

### `getEndpointConfig`

Get cached endpoint configuration

```php
public function getEndpointConfig(App\TravelClick\Enums\Environment $environment): App\TravelClick\DTOs\EndpointConfigDto|null
```

---

### `putEndpointConfig`

Cache endpoint configuration

```php
public function putEndpointConfig(App\TravelClick\Enums\Environment $environment, App\TravelClick\DTOs\EndpointConfigDto $config): bool
```

---

### `clearPropertyConfig`

Clear property configuration cache

```php
public function clearPropertyConfig(int $propertyId = null): bool
```

---

### `clearGlobalConfig`

Clear global configuration cache

```php
public function clearGlobalConfig(): bool
```

---

### `clearEndpointConfigs`

Clear endpoint configurations cache

```php
public function clearEndpointConfigs(): bool
```

---

### `clearAll`

Clear all TravelClick configuration caches

```php
public function clearAll(): bool
```

---

### `warmup`

Warm up cache for a property

```php
public function warmup(int $propertyId): bool
```

---

### `getStats`

Get statistics about cache usage

```php
public function getStats(): array
```

---

