# ReservationDataDto

**Full Class Name:** `App\TravelClick\DTOs\ReservationDataDto`

**File:** `DTOs/ReservationDataDto.php`

**Type:** Class

## Description

Data Transfer Object for reservation information in TravelClick integrations
This is the main DTO that integrates all aspects of a reservation for HTNG
message construction. It combines guest, room stay, profile and request data
into a single cohesive structure.

## Methods

### `__construct`

Create a new reservation data DTO instance

```php
public function __construct(array $data)
```

---

### `getArrivalDate`

Get arrival date (from first room stay)

```php
public function getArrivalDate(): Carbon\Carbon
```

**Returns:** Carbon - The arrival date

---

### `getDepartureDate`

Get departure date (from last room stay)

```php
public function getDepartureDate(): Carbon\Carbon
```

**Returns:** Carbon - The departure date

---

### `getTotalNights`

Get total number of nights

```php
public function getTotalNights(): int
```

**Returns:** int - Total nights across all room stays

---

### `getTotalAmount`

Calculate total reservation amount

```php
public function getTotalAmount(): float
```

**Returns:** float - Total amount across all room stays and services

---

### `hasSpecialRequests`

Check if this reservation has special requests

```php
public function hasSpecialRequests(): bool
```

**Returns:** bool - True if there are special requests

---

### `hasServiceRequests`

Check if this reservation has service requests

```php
public function hasServiceRequests(): bool
```

**Returns:** bool - True if there are service requests

---

### `hasPaymentInfo`

Check if this reservation has payment information

```php
public function hasPaymentInfo(): bool
```

**Returns:** bool - True if payment information is available

---

### `hasProfile`

Check if this reservation has a profile

```php
public function hasProfile(): bool
```

**Returns:** bool - True if a profile is attached

---

### `isModification`

Check if this is a modification

```php
public function isModification(): bool
```

**Returns:** bool - True if this is a modification

---

### `isCancellation`

Check if this is a cancellation

```php
public function isCancellation(): bool
```

**Returns:** bool - True if this is a cancellation

---

### `isNew`

Check if this is a new reservation

```php
public function isNew(): bool
```

**Returns:** bool - True if this is a new reservation

---

### `fromCentriumBooking`

Create from a Centrium booking

```php
public function fromCentriumBooking(mixed $booking, App\TravelClick\Enums\ReservationType|null $type = null): self
```

**Parameters:**

- `$booking` (mixed): The Centrium booking
- `$type` (ReservationType|null): Override reservation type

**Returns:** self - A new ReservationDataDto

---

### `createCancellation`

Create for a cancellation transaction

```php
public function createCancellation(string $reservationId, string $confirmationNumber, string $hotelCode, string $cancellationReason = null): self
```

**Parameters:**

- `$reservationId` (string): The ID of the reservation to cancel
- `$confirmationNumber` (string): The confirmation number if available
- `$hotelCode` (string): The hotel code
- `$cancellationReason` (string|null): Optional cancellation reason

**Returns:** self - A new ReservationDataDto configured for cancellation

---

### `createModification`

Create for a modification transaction

```php
public function createModification(string $reservationId, string $confirmationNumber, array $modificationData): self
```

**Parameters:**

- `$reservationId` (string): The ID of the reservation to modify
- `$confirmationNumber` (string): The confirmation number

**Returns:** self - A new ReservationDataDto configured for modification

---

### `toArray`

Convert to array representation

```php
public function toArray(): array
```

**Returns:** array<string, - mixed> The reservation data as an array

---

