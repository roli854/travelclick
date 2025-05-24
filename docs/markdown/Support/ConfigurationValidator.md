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
public function validateGlobalConfig(TravelClickConfigDto $config): array
```

---

### `validatePropertyConfig`

Validate property-specific configuration

```php
public function validatePropertyConfig(PropertyConfigDto $config): array
```

---

### `validateEndpointConfig`

Validate endpoint configuration

```php
public function validateEndpointConfig(EndpointConfigDto $config): array
```

---

### `testEndpointConnectivity`

Test endpoint connectivity

```php
public function testEndpointConnectivity(EndpointConfigDto $config): array
```

---

### `validateComplete`

Perform comprehensive configuration validation

```php
public function validateComplete(TravelClickConfigDto $globalConfig, PropertyConfigDto|null $propertyConfig = null, EndpointConfigDto|null $endpointConfig = null): array
```

---

### `generateReport`

Generate validation report

```php
public function generateReport(array $validationResults): string
```

---

