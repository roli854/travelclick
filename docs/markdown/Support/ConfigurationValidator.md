# ConfigurationValidator

**Full Class Name:** `App\TravelClick\Support\ConfigurationValidator`

**File:** `Support/ConfigurationValidator.php`

**Type:** Class

## Description

Configuration Validator for TravelClick
This class provides comprehensive validation for all TravelClick configurations
using business rules, format validation, and connectivity tests.

## Methods

### `validateGlobalConfig`

Validate global TravelClick configuration

```php
public function validateGlobalConfig(App\TravelClick\DTOs\TravelClickConfigDto $config): array
```

---

### `validatePropertyConfig`

Validate property-specific configuration

```php
public function validatePropertyConfig(App\TravelClick\DTOs\PropertyConfigDto $config): array
```

---

### `validateEndpointConfig`

Validate endpoint configuration

```php
public function validateEndpointConfig(App\TravelClick\DTOs\EndpointConfigDto $config): array
```

---

### `testEndpointConnectivity`

Test endpoint connectivity

```php
public function testEndpointConnectivity(App\TravelClick\DTOs\EndpointConfigDto $config): array
```

---

### `validateComplete`

Perform comprehensive configuration validation

```php
public function validateComplete(App\TravelClick\DTOs\TravelClickConfigDto $globalConfig, App\TravelClick\DTOs\PropertyConfigDto|null $propertyConfig = null, App\TravelClick\DTOs\EndpointConfigDto|null $endpointConfig = null): array
```

---

### `generateReport`

Generate validation report

```php
public function generateReport(array $validationResults): string
```

---

