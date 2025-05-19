# RateStructureValidator

**Full Class Name:** `App\TravelClick\Support\RateStructureValidator`

**File:** `Support/RateStructureValidator.php`

**Type:** Class

## Description

Rate Structure Validator for TravelClick HTNG 2011B Integration
This validator enforces specific business rules for rate data that are required
by the HTNG 2011B specification and TravelClick's integration requirements.
Think of it as a quality inspector that ensures all rate data meets the
strict hotel industry standards before being sent to TravelClick.
Key responsibilities:
- Validate mandatory 1st and 2nd adult rates (HTNG certification requirement)
- Verify linked rate logic and relationships
- Ensure date ranges are valid and coherent
- Check rate plan codes and structure consistency
- Validate currency consistency across rate plans
- Enforce operation-specific validation rules

## Methods

### `__construct`

```php
public function __construct()
```

---

### `validateRateData`

Validate a single rate data structure
This is like having a detailed checklist for each rate card.
Every rate must pass these checks before being processed.

```php
public function validateRateData(App\TravelClick\DTOs\RateData $rateData, App\TravelClick\Enums\RateOperationType $operationType): void
```

**Parameters:**

- `$rateData` (RateData): The rate to validate
- `$operationType` (RateOperationType): The operation being performed

---

### `validateRatePlan`

Validate a complete rate plan structure
This validates the entire rate plan ensuring all rates work together
cohesively and follow business logic rules.

```php
public function validateRatePlan(App\TravelClick\DTOs\RatePlanData $ratePlan, App\TravelClick\Enums\RateOperationType $operationType): void
```

**Parameters:**

- `$ratePlan` (RatePlanData): The rate plan to validate
- `$operationType` (RateOperationType): The operation being performed

---

### `validateBatchRatePlans`

Validate multiple rate plans for batch operations
This ensures that when sending multiple rate plans together,
they don't conflict with each other.

```php
public function validateBatchRatePlans(array $ratePlans, App\TravelClick\Enums\RateOperationType $operationType): void
```

**Parameters:**

- `$ratePlans` (array<RatePlanData>): Array of rate plans to validate
- `$operationType` (RateOperationType): The operation being performed

---

### `getValidationSummary`

Get validation summary for a rate plan
Useful for debugging and logging

```php
public function getValidationSummary(App\TravelClick\DTOs\RatePlanData $ratePlan): array
```

**Parameters:**

- `$ratePlan` (RatePlanData): 

**Returns:** array - Summary of validation checks performed

---

