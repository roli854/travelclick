# RateOperationType

**Full Class Name:** `App\TravelClick\Enums\RateOperationType`

**File:** `Enums/RateOperationType.php`

**Type:** Class

## Description

Rate operation types supported by TravelClick HTNG 2011B interface
This enum defines the different types of rate operations that can be performed
through the TravelClick integration. Think of it as the different "actions"
you can take with rates - like having different buttons on a remote control
for different functions.
According to HTNG 2011B specification:
- Rate Update is mandatory for certification
- Other operations are optional but recommended for advanced integrations

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

