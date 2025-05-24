# RateOperationType

**Full Class Name:** `App\TravelClick\Enums\RateOperationType`

**File:** `Enums/RateOperationType.php`

**Type:** Enum

## Description

Rate operation types supported by TravelClick HTNG 2011B interface
This enum defines the different types of rate operations that can be performed
through the TravelClick integration. Think of it as the different "actions"
you can take with rates - like having different buttons on a remote control
for different functions.
According to HTNG 2011B specification:
- Rate Update is mandatory for certification
- Other operations are optional but recommended for advanced integrations

## Constants

### `RATE_UPDATE`

Rate Update - Update existing rate information
This is the most common operation and the ONLY mandatory one for certification.
Use this when you need to modify existing rates in TravelClick.
Example: Changing room rates for specific dates, updating seasonal pricing

**Value:** `\App\TravelClick\Enums\RateOperationType::RATE_UPDATE`

---

### `RATE_CREATION`

Rate Creation - Create new rate plans
Optional operation to create entirely new rate plans in TravelClick.
Usually done during initial setup or when launching new packages.
Example: Creating a new "Summer Special" rate plan

**Value:** `\App\TravelClick\Enums\RateOperationType::RATE_CREATION`

---

### `INACTIVE_RATE`

Inactive Rate - Mark existing rates as inactive
Optional operation to deactivate rate plans without deleting them.
Useful for seasonal rates or discontinued packages.
Example: Deactivating winter rates during summer season

**Value:** `\App\TravelClick\Enums\RateOperationType::INACTIVE_RATE`

---

### `REMOVE_ROOM_TYPES`

Remove Room Types - Remove specific room types from a rate plan
Optional operation to exclude certain room types from a rate plan
without affecting the plan itself.
Example: Removing suites from a budget rate plan

**Value:** `\App\TravelClick\Enums\RateOperationType::REMOVE_ROOM_TYPES`

---

### `FULL_SYNC`

Full Synchronization - Complete rate data sync
Special operation type for full overlay synchronization.
Should ONLY be used when explicitly requested by user, not routinely.
Note: TravelClick strongly recommends against daily full syncs
to minimize message traffic and processing delays.

**Value:** `\App\TravelClick\Enums\RateOperationType::FULL_SYNC`

---

### `DELTA_UPDATE`

Delta Update - Send only changed rates
Recommended operation type for regular synchronization.
Only sends rate plans that have been affected by changes.
This is the preferred method for real-time updates.

**Value:** `\App\TravelClick\Enums\RateOperationType::DELTA_UPDATE`

---

## Properties

### `$name`

**Type:** `string`

---

### `$value`

**Type:** `string`

---

## Methods

### `getDescription`

Get a human-readable description of the operation

```php
public function getDescription(): string
```

**Returns:** string - Descriptive text explaining the operation

---

### `isMandatory`

Check if this operation type is mandatory for certification

```php
public function isMandatory(): bool
```

**Returns:** bool - True if mandatory, false if optional

---

### `supportsLinkedRates`

Check if this operation type supports linked rates
Linked rates are rates derived from a master rate (e.g., AAA rate = BAR rate - 10%).
Not all operations support this feature.

```php
public function supportsLinkedRates(): bool
```

**Returns:** bool - True if linked rates are supported

---

### `getRecommendedBatchSize`

Get the recommended batch size for this operation type
Different operations have different optimal batch sizes based on
processing complexity and TravelClick recommendations.

```php
public function getRecommendedBatchSize(): int
```

**Returns:** int - Recommended number of rate plans per batch

---

### `getTimeoutSeconds`

Get the timeout in seconds for this operation type
Different operations may take different amounts of time to process
in TravelClick's systems.

```php
public function getTimeoutSeconds(): int
```

**Returns:** int - Timeout in seconds

---

### `shouldTriggerInventoryUpdate`

Check if this operation should trigger automatic inventory updates
Some rate operations may affect inventory availability and should
trigger automatic inventory synchronization.

```php
public function shouldTriggerInventoryUpdate(): bool
```

**Returns:** bool - True if should trigger inventory update

---

### `getMandatoryOperations`

Get all mandatory operation types

```php
public function getMandatoryOperations(): array
```

**Returns:** array<self> - Array of mandatory operations

---

### `getOptionalOperations`

Get all optional operation types

```php
public function getOptionalOperations(): array
```

**Returns:** array<self> - Array of optional operations

---

### `getBatchableOperations`

Get operations that support batch processing

```php
public function getBatchableOperations(): array
```

**Returns:** array<self> - Array of operations that can be batched

---

### `getXmlElementName`

Get the XML element name for this operation type
Different operations may require different XML structures
or element names in the SOAP payload.

```php
public function getXmlElementName(): string
```

**Returns:** string - XML element name

---

### `getValidationRules`

Get validation rules specific to this operation type

```php
public function getValidationRules(): array
```

**Returns:** array<string, - mixed> Validation rules

---

### `cases`

```php
public function cases(): array
```

---

### `from`

```php
public function from(string|int $value): static
```

---

### `tryFrom`

```php
public function tryFrom(string|int $value): static|null
```

---

