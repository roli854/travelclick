# GuestDataDto

**Full Class Name:** `App\TravelClick\DTOs\GuestDataDto`

**File:** `DTOs/GuestDataDto.php`

**Type:** Class

## Description

Data Transfer Object for guest information in TravelClick integrations
This DTO handles all guest-related data for constructing reservation XML.
It provides structured access to guest details while enforcing validation
for required fields based on TravelClick HTNG 2011B specifications.

## Properties

### `$title`

Guest personal details

**Type:** `string`

---

### `$firstName`

**Type:** `string`

---

### `$lastName`

**Type:** `string`

---

### `$middleName`

**Type:** `string|null`

---

### `$suffix`

**Type:** `string|null`

---

### `$dateOfBirth`

**Type:** `Carbon\Carbon|null`

---

### `$passportNumber`

**Type:** `string|null`

---

### `$email`

Guest contact information

**Type:** `string|null`

---

### `$phone`

**Type:** `string|null`

---

### `$phoneMobile`

**Type:** `string|null`

---

### `$fax`

**Type:** `string|null`

---

### `$addressLine1`

Guest address details

**Type:** `string|null`

---

### `$addressLine2`

**Type:** `string|null`

---

### `$city`

**Type:** `string|null`

---

### `$state`

**Type:** `string|null`

---

### `$postalCode`

**Type:** `string|null`

---

### `$countryCode`

**Type:** `string|null`

---

### `$guestType`

Guest type and age classification

**Type:** `string`

---

### `$age`

**Type:** `int|null`

---

### `$isPrimaryGuest`

**Type:** `bool`

---

## Methods

### `__construct`

Create a new guest data DTO instance

```php
public function __construct(array $data)
```

---

### `isAdult`

Check if the guest is an adult

```php
public function isAdult(): bool
```

**Returns:** bool - True if guest is an adult

---

### `isChild`

Check if the guest is a child

```php
public function isChild(): bool
```

**Returns:** bool - True if guest is a child

---

### `isYouth`

Check if the guest is a youth

```php
public function isYouth(): bool
```

**Returns:** bool - True if guest is a youth

---

### `isInfant`

Check if the guest is an infant

```php
public function isInfant(): bool
```

**Returns:** bool - True if guest is an infant

---

### `hasValidAddress`

Check if the guest has a valid address

```php
public function hasValidAddress(): bool
```

**Returns:** bool - True if guest has at least address line 1, city and country

---

### `hasValidContactInfo`

Check if guest has valid contact information

```php
public function hasValidContactInfo(): bool
```

**Returns:** bool - True if guest has either email or phone

---

### `getFullName`

Get the full name of the guest (first + last)

```php
public function getFullName(): string
```

**Returns:** string - The guest's full name

---

### `getFormalName`

Get the formal name with title (Mr. John Smith)

```php
public function getFormalName(): string
```

**Returns:** string - The guest's name with title

---

### `fromCentriumBooking`

Create from a lead guest in a Centrium booking

```php
public function fromCentriumBooking($booking): self
```

**Parameters:**

- `$booking` (mixed): The Centrium booking object or array

**Returns:** self - A new GuestDataDto instance

---

### `toArray`

Convert to array representation

```php
public function toArray(): array
```

**Returns:** array<string, - mixed> The guest data as an array

---

