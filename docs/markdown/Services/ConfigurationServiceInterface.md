# ConfigurationServiceInterface

**Full Class Name:** `App\TravelClick\Services\Contracts\ConfigurationServiceInterface`

**File:** `Services/Contracts/ConfigurationServiceInterface.php`

**Type:** Interface

## Description

Interface for TravelClick Configuration Service
This interface defines the contract for managing all TravelClick configurations
including global, property-specific, and environment-based settings.

## Methods

### `getPropertyConfig`

Get complete TravelClick configuration for a specific property

```php
public function getPropertyConfig(int $propertyId): PropertyConfigDto
```

---

### `getGlobalConfig`

Get global TravelClick configuration

```php
public function getGlobalConfig(): TravelClickConfigDto
```

---

### `getEndpointConfig`

Get endpoint configuration for current environment

```php
public function getEndpointConfig(Environment|null $environment = null): EndpointConfigDto
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
public function clearCache(ConfigScope $scope = \App\TravelClick\Enums\ConfigScope::ALL, int|null $propertyId = null): bool
```

---

### `updatePropertyConfig`

Update property-specific configuration

```php
public function updatePropertyConfig(int $propertyId, array $config): PropertyConfigDto
```

---

### `getConfigValue`

Get configuration value with fallback logic

```php
public function getConfigValue(string $key, int|null $propertyId = null, mixed $default = null): mixed
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
public function importPropertyConfig(int $propertyId, array $config): PropertyConfigDto
```

---

