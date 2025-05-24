# RoomStayDataDto

**Full Class Name:** `App\TravelClick\DTOs\RoomStayDataDto`

**File:** `DTOs/RoomStayDataDto.php`

**Type:** Class

## Description

Data Transfer Object for room stay information in TravelClick integration
This DTO encapsulates all details about a room stay, including dates,
room type, rate plan, guest counts, and pricing information. It enforces
validation and provides structured access to data for XML construction.

## Properties

### `$checkInDate`

Room stay dates

**Type:** `Carbon\Carbon`

---

### `$checkOutDate`

**Type:** `Carbon\Carbon`

---

### `$stayDurationNights`

**Type:** `int`

---

### `$roomTypeCode`

Room configuration

**Type:** `string`

---

### `$ratePlanCode`

**Type:** `string`

---

### `$upgradedRoomTypeCode`

**Type:** `string|null`

---

### `$mealPlanCode`

**Type:** `string|null`

---

### `$adultCount`

Guest counts

**Type:** `int`

---

### `$childCount`

**Type:** `int`

---

### `$infantCount`

**Type:** `int`

---

### `$totalGuestCount`

**Type:** `int`

---

### `$rateAmount`

Financial information

**Type:** `float`

---

### `$totalAmount`

**Type:** `float|null`

---

### `$discountAmount`

**Type:** `float|null`

---

### `$taxAmount`

**Type:** `float|null`

---

### `$currencyCode`

**Type:** `string`

---

### `$indexNumber`

Room stay identifiers

**Type:** `int`

---

### `$confirmationNumber`

**Type:** `string|null`

---

### `$specialRequestCode`

**Type:** `string|null`

---

### `$roomDescription`

Additional information

**Type:** `string|null`

---

### `$dailyRates`

**Type:** `array|null`

---

### `$supplements`

**Type:** `array|null`

---

### `$specialOffers`

**Type:** `array|null`

---

## Methods

### `__construct`

Create a new room stay data DTO instance

```php
public function __construct(array $data)
```

---

### `getFormattedCheckInDate`

Get check-in date in HTNG format (YYYY-MM-DD)

```php
public function getFormattedCheckInDate(): string
```

**Returns:** string - The check-in date

---

### `getFormattedCheckOutDate`

Get check-out date in HTNG format (YYYY-MM-DD)

```php
public function getFormattedCheckOutDate(): string
```

**Returns:** string - The check-out date

---

### `hasDailyRates`

Check if this stay has daily rate breakdown available

```php
public function hasDailyRates(): bool
```

**Returns:** bool - True if daily rates are available

---

### `hasSupplements`

Check if this stay has supplements

```php
public function hasSupplements(): bool
```

**Returns:** bool - True if supplements are available

---

### `hasSpecialOffers`

Check if this stay has special offers applied

```php
public function hasSpecialOffers(): bool
```

**Returns:** bool - True if special offers are applied

---

### `hasConfirmationNumber`

Check if this stay has a confirmation number

```php
public function hasConfirmationNumber(): bool
```

**Returns:** bool - True if confirmation number is available

---

### `isPackageRate`

Check if this is a package rate

```php
public function isPackageRate(): bool
```

**Returns:** bool - True if this is a package rate

---

### `fromCentriumPropertyRoomBooking`

Create from a Centrium property room booking

```php
public function fromCentriumPropertyRoomBooking($propertyRoomBooking, int $index = 1): self
```

**Parameters:**

- `$propertyRoomBooking` (mixed): The Centrium property room booking
- `$index` (int): The index/sequence number for this room

**Returns:** self - A new RoomStayDataDto instance

---

### `toArray`

Convert to array representation

```php
public function toArray(): array
```

**Returns:** array<string, - mixed> The room stay data as an array

---

