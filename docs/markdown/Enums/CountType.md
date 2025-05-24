# CountType

**Full Class Name:** `App\TravelClick\Enums\CountType`

**File:** `Enums/CountType.php`

**Type:** Enum

## Description

CountType Enum for TravelClick Inventory Messages
These are the official count types supported by TravelClick HTNG 2011B interface.
Each type represents a different kind of room count in the inventory system.
Think of these as different categories of room status:
- Physical: Actual physical rooms available
- Available: Rooms ready to be sold
- Definite Sold: Confirmed bookings
- Tentative: Group blocks or options
- Out of Order: Rooms unavailable (maintenance, etc.)
- Oversell: Additional rooms beyond physical capacity

## Constants

### `PHYSICAL`

Physical Rooms - The actual number of physical units available
Use only if the external system supports inventory messages at room and property level

**Value:** `\App\TravelClick\Enums\CountType::PHYSICAL`

---

### `AVAILABLE`

Available Rooms - Actual count of rooms available for sale
Send only when inventory is managed for available rooms
Do not send with other count types

**Value:** `\App\TravelClick\Enums\CountType::AVAILABLE`

---

### `DEFINITE_SOLD`

Definite Sold - Confirmed bookings/reservations
This is the main count for sold rooms

**Value:** `\App\TravelClick\Enums\CountType::DEFINITE_SOLD`

---

### `TENTATIVE_SOLD`

Tentative Sold - Group booking count, pickup count for group inventory
Must be passed with value of zero in calculated method

**Value:** `\App\TravelClick\Enums\CountType::TENTATIVE_SOLD`

---

### `OUT_OF_ORDER`

Out of Order - Rooms unavailable due to maintenance, repairs, etc.
Optional count type

**Value:** `\App\TravelClick\Enums\CountType::OUT_OF_ORDER`

---

### `OVERSELL`

Oversell Rooms - Used to send oversell counts for specific dates/periods
Optional if oversell is supported

**Value:** `\App\TravelClick\Enums\CountType::OVERSELL`

---

## Properties

### `$name`

**Type:** `string`

---

### `$value`

**Type:** `int`

---

## Methods

### `description`

Get human-readable description of the count type

```php
public function description(): string
```

---

### `requiresCalculation`

Check if this count type requires calculation
Some count types work together in calculated method

```php
public function requiresCalculation(): bool
```

---

### `canBeUsedAlone`

Check if this count type can be used alone

```php
public function canBeUsedAlone(): bool
```

---

### `calculatedTypes`

Get all count types valid for calculated method

```php
public function calculatedTypes(): array
```

---

### `directTypes`

Get count types that don't require calculation

```php
public function directTypes(): array
```

---

### `fromCentriumInventory`

Map from Centrium inventory system to TravelClick
This will help convert Centrium data to TravelClick format

```php
public function fromCentriumInventory(array $inventoryData): array
```

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

