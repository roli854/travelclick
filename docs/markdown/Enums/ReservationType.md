# ReservationType

**Full Class Name:** `App\TravelClick\Enums\ReservationType`

**File:** `Enums/ReservationType.php`

**Type:** Enum

## Description

ReservationType Enum for TravelClick Integration
Defines the different types of reservations supported by TravelClick.
Each type has specific processing requirements and XML structures.
Based on the TravelClick documentation, these are the mandatory reservation types:
- Transient (individual guest)
- Travel Agency
- Corporate
- Package
- Group
- Alternate Payment (special payment scenarios)

## Constants

### `TRANSIENT`

Standard individual guest reservations
Most common type - regular travelers booking directly or through third parties

**Value:** `\App\TravelClick\Enums\ReservationType::TRANSIENT`

---

### `TRAVEL_AGENCY`

Travel Agency reservations
Include travel agency profile and IATA information
Require commission handling

**Value:** `\App\TravelClick\Enums\ReservationType::TRAVEL_AGENCY`

---

### `CORPORATE`

Corporate reservations
Include company profile and corporate rates
May have special terms and conditions

**Value:** `\App\TravelClick\Enums\ReservationType::CORPORATE`

---

### `PACKAGE`

Package reservations
Include bundled services (room + amenities/services)
Rate plan code identifies the package

**Value:** `\App\TravelClick\Enums\ReservationType::PACKAGE`

---

### `GROUP`

Group reservations
Associated with a group block
Decrement group inventory allocation

**Value:** `\App\TravelClick\Enums\ReservationType::GROUP`

---

### `ALTERNATE_PAYMENT`

Reservations with alternate payment methods
For scenarios with special payment processing (deposits, etc.)

**Value:** `\App\TravelClick\Enums\ReservationType::ALTERNATE_PAYMENT`

---

## Properties

### `$name`

**Type:** `string`

---

### `$value`

**Type:** `string`

---

## Methods

### `description`

Get human-readable description

```php
public function description(): string
```

---

### `isMandatory`

Check if this reservation type is mandatory to support

```php
public function isMandatory(): bool
```

---

### `requiresProfile`

Check if this reservation type requires a profile

```php
public function requiresProfile(): bool
```

---

### `getRequiredProfileType`

Get the profile type required for this reservation

```php
public function getRequiredProfileType(): string|null
```

---

### `affectsInventory`

Check if this reservation type affects inventory

```php
public function affectsInventory(): bool
```

---

### `supportsCommission`

Check if this reservation type supports commission

```php
public function supportsCommission(): bool
```

---

### `getProcessingPriority`

Get priority for processing (1 = highest, 10 = lowest)

```php
public function getProcessingPriority(): int
```

---

### `fromCentriumBookingSource`

Map from Centrium booking source to reservation type

```php
public function fromCentriumBookingSource(string $source, string|null $bookingType = null): self
```

---

### `getCentriumSource`

Get Centrium source value for this reservation type

```php
public function getCentriumSource(): string
```

---

### `getRequiredCentriumFields`

Get fields required in Centrium booking for this type

```php
public function getRequiredCentriumFields(): array
```

---

### `inboundTypes`

Get all reservation types that TravelClick can send to us

```php
public function inboundTypes(): array
```

---

### `outboundTypes`

Get reservation types we can send to TravelClick

```php
public function outboundTypes(): array
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

