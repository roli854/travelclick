# InventoryData

**Full Class Name:** `App\TravelClick\DTOs\InventoryData`

**File:** `DTOs/InventoryData.php`

**Type:** Class

## Description

Data Transfer Object for TravelClick Inventory Messages
This class represents a single inventory item that will be sent to TravelClick.
It handles all validation and transformation logic for inventory data, ensuring
that the data is properly formatted before building the XML message.
Think of this as a smart container that knows exactly what inventory data
should look like and can validate itself before sending it anywhere.

## Properties

### `$hotelCode`

**Type:** `string`

---

### `$startDate`

**Type:** `string`

---

### `$endDate`

**Type:** `string`

---

### `$roomTypeCode`

**Type:** `string|null`

---

### `$isPropertyLevel`

**Type:** `bool`

---

### `$counts`

**Type:** `Spatie\LaravelData\DataCollection`

---

### `$uniqueId`

**Type:** `string|null`

---

## Methods

### `__construct`

```php
public function __construct(string $hotelCode, string $startDate, string $endDate, string|null $roomTypeCode, bool $isPropertyLevel, Spatie\LaravelData\DataCollection $counts, string|null $uniqueId = null)
```

**Parameters:**

- `$hotelCode` (string): The property identifier in TravelClick
- `$startDate` (string): Start date for inventory period (YYYY-MM-DD)
- `$endDate` (string): End date for inventory period (YYYY-MM-DD)
- `$roomTypeCode` (string|null): Room type code (required unless property-level)
- `$isPropertyLevel` (bool): Whether this is property-level inventory (AllInvCode=true)
- `$uniqueId` (string|null): Optional unique identifier for the inventory record

---

### `fromCentrium`

Create inventory data from Centrium database models

```php
public function fromCentrium(array $inventoryRecord): self
```

**Returns:** self - 

---

### `createCalculated`

Create calculated inventory data (using count types 4, 5, 6, 99)

```php
public function createCalculated(string $hotelCode, string $startDate, string $endDate, string|null $roomTypeCode = null, int $definiteSold = 0, int $tentativeSold = 0, int $outOfOrder = 0, int $oversell = 0, int|null $physical = null): self
```

**Parameters:**

- `$hotelCode` (string): 
- `$startDate` (string): 
- `$endDate` (string): 
- `$roomTypeCode` (string|null): 
- `$definiteSold` (int): 
- `$tentativeSold` (int): 
- `$outOfOrder` (int): 
- `$oversell` (int): 
- `$physical` (int|null): 

**Returns:** self - 

---

### `createAvailable`

Create available count inventory data (using count type 2)

```php
public function createAvailable(string $hotelCode, string $startDate, string $endDate, string|null $roomTypeCode = null, int $availableCount = 0): self
```

**Parameters:**

- `$hotelCode` (string): 
- `$startDate` (string): 
- `$endDate` (string): 
- `$roomTypeCode` (string|null): 
- `$availableCount` (int): 

**Returns:** self - 

---

### `validateBusinessRules`

Validate inventory data business rules

```php
public function validateBusinessRules(): array
```

**Returns:** array<string> - Array of validation errors, empty if valid

---

### `isCalculatedMethod`

Check if this uses the calculated method

```php
public function isCalculatedMethod(): bool
```

**Returns:** bool - 

---

### `isDirectMethod`

Check if this uses the not-calculated method (direct count)

```php
public function isDirectMethod(): bool
```

**Returns:** bool - 

---

### `getTotalCount`

Get the total inventory count for display purposes

```php
public function getTotalCount(): int
```

**Returns:** int - 

---

### `getCountByType`

Get count by specific count type

```php
public function getCountByType(CountType $countType): int
```

**Parameters:**

- `$countType` (CountType): 

**Returns:** int - 

---

