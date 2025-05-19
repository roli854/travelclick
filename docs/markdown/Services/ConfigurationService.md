# ConfigurationService

**Full Class Name:** `App\TravelClick\Services\ConfigurationService`

**File:** `Services/ConfigurationService.php`

**Type:** Class

## Description

TravelClick Configuration Service
Manages all configuration aspects for TravelClick integration including
global settings, property-specific configurations, and environment handling.

## Methods

### `__construct`

```php
public function __construct(App\TravelClick\Support\ConfigurationValidator $validator, App\TravelClick\Support\ConfigurationCache $cache)
```

---

### `getPropertyConfig`

Get complete TravelClick configuration for a specific property

```php
public function getPropertyConfig(int $propertyId): App\TravelClick\DTOs\PropertyConfigDto
```

---

### `getGlobalConfig`

Get global TravelClick configuration

```php
public function getGlobalConfig(): App\TravelClick\DTOs\TravelClickConfigDto
```

---

### `getEndpointConfig`

Get endpoint configuration for current environment

```php
public function getEndpointConfig(App\TravelClick\Enums\Environment|null $environment = null): App\TravelClick\DTOs\EndpointConfigDto
```

---

### `validatePropertyConfig`

Validate configuration for a property

```php
public function validatePropertyConfig(int $propertyId): array
```

---

### `cacheConfiguration`

Cache configuration for performance

```php
public function cacheConfiguration(int $propertyId): bool
```

---

### `clearCache`

Clear configuration cache

```php
public function clearCache(App\TravelClick\Enums\ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int $propertyId = null): bool
```

---

### `updatePropertyConfig`

Update property-specific configuration

```php
public function updatePropertyConfig(int $propertyId, array $config): App\TravelClick\DTOs\PropertyConfigDto
```

---

### `getConfigValue`

Get configuration value with fallback logic

```php
public function getConfigValue(string $key, int $propertyId = null, mixed $default = null): mixed
```

---

### `isPropertyConfigured`

Check if property has complete configuration

```php
public function isPropertyConfigured(int $propertyId): bool
```

---

### `getConfiguredProperties`

Get all configured properties

```php
public function getConfiguredProperties(): array
```

---

### `exportPropertyConfig`

Export configuration for a property

```php
public function exportPropertyConfig(int $propertyId): array
```

---

### `importPropertyConfig`

Import configuration for a property

```php
public function importPropertyConfig(int $propertyId, array $config): App\TravelClick\DTOs\PropertyConfigDto
```

---

