# CountType

**Full Class Name:** `App\TravelClick\Enums\CountType`

**File:** `Enums/CountType.php`

**Type:** Class

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

