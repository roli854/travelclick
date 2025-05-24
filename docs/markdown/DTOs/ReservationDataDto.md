# ReservationDataDto

**Full Class Name:** `App\TravelClick\DTOs\ReservationDataDto`

**File:** `DTOs/ReservationDataDto.php`

**Type:** Class

## Description

Data Transfer Object for reservation information in TravelClick integrations
This is the main DTO that integrates all aspects of a reservation for HTNG
message construction. It combines guest, room stay, profile and request data
into a single cohesive structure.

## Properties

### `$reservationType`

Reservation type and identifiers

**Type:** `ReservationType`

---

### `$reservationId`

**Type:** `string`

---

### `$confirmationNumber`

**Type:** `string|null`

---

### `$createDateTime`

**Type:** `string`

---

### `$lastModifyDateTime`

**Type:** `string|null`

---

### `$transactionIdentifier`

**Type:** `string`

---

### `$transactionType`

**Type:** `string`

---

### `$hotelCode`

Hotel information

**Type:** `string`

---

### `$chainCode`

**Type:** `string|null`

---

### `$primaryGuest`

Lead guest and additional guests

**Type:** `GuestDataDto`

---

### `$additionalGuests`

**Type:** `Illuminate\Support\Collection`

---

### `$roomStays`

Room stay details

**Type:** `Illuminate\Support\Collection`

---

### `$specialRequests`

Special requests and services

**Type:** `Illuminate\Support\Collection`

---

### `$serviceRequests`

**Type:** `Illuminate\Support\Collection`

---

### `$profile`

Profile information (for Travel Agency, Corporate, Group)

**Type:** `ProfileDataDto|null`

---

### `$sourceOfBusiness`

Source information

**Type:** `string`

---

### `$marketSegment`

**Type:** `string|null`

---

### `$departmentCode`

**Type:** `string|null`

---

### `$guaranteeType`

Payment information

**Type:** `string|null`

---

### `$guaranteeCode`

**Type:** `string|null`

---

### `$depositAmount`

**Type:** `float|null`

---

### `$depositPaymentType`

**Type:** `string|null`

---

### `$paymentCardNumber`

**Type:** `string|null`

---

### `$paymentCardType`

**Type:** `string|null`

---

### `$paymentCardExpiration`

**Type:** `string|null`

---

### `$paymentCardHolderName`

**Type:** `string|null`

---

### `$alternatePaymentType`

Alternate payment info (for special deposits)

**Type:** `string|null`

---

### `$alternatePaymentIdentifier`

**Type:** `string|null`

---

### `$alternatePaymentAmount`

**Type:** `float|null`

---

### `$invBlockCode`

Group booking specific

**Type:** `string|null`

---

### `$comments`

Additional information

**Type:** `string|null`

---

### `$priorityProcessing`

**Type:** `bool`

---

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
public function fromCentriumBooking($booking, ReservationType|null $type = null): self
```

**Parameters:**

- `$booking` (mixed): The Centrium booking
- `$type` (ReservationType|null): Override reservation type

**Returns:** self - A new ReservationDataDto

---

### `createCancellation`

Create for a cancellation transaction

```php
public function createCancellation(string $reservationId, string $confirmationNumber, string $hotelCode, string|null $cancellationReason = null): self
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

