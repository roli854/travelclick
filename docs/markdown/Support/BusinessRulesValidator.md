# BusinessRulesValidator

**Full Class Name:** `App\TravelClick\Support\BusinessRulesValidator`

**File:** `Support/BusinessRulesValidator.php`

**Type:** Class

## Description

BusinessRulesValidator
Validates business logic rules specific to HTNG 2011B operations.
Ensures compliance with TravelClick/iHotelier business requirements.

## Methods

### `__construct`

Constructor

```php
public function __construct(ConfigurationServiceInterface $configurationService)
```

---

### `validateInventoryRules`

Validate inventory business rules

```php
public function validateInventoryRules(array $data, string $operation): array
```

**Parameters:**

- `$operation` (string): Operation type (create, modify, remove)

**Returns:** array<string, - mixed> Validation results

---

### `validateRateRules`

Validate rate business rules

```php
public function validateRateRules(array $data, string $operation): array
```

**Parameters:**

- `$operation` (string): Operation type (create, modify, remove)

**Returns:** array<string, - mixed> Validation results

---

### `validateReservationRules`

Validate reservation business rules

```php
public function validateReservationRules(array $data, string $operation): array
```

**Parameters:**

- `$operation` (string): Operation type (create, modify, cancel)

**Returns:** array<string, - mixed> Validation results

---

### `validateGroupBlockRules`

Validate group block business rules

```php
public function validateGroupBlockRules(array $data, string $operation): array
```

**Parameters:**

- `$operation` (string): Operation type (create, modify, cancel)

**Returns:** array<string, - mixed> Validation results

---

