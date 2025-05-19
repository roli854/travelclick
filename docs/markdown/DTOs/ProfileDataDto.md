# ProfileDataDto

**Full Class Name:** `App\TravelClick\DTOs\ProfileDataDto`

**File:** `DTOs/ProfileDataDto.php`

**Type:** Class

## Description

Data Transfer Object for profile information in TravelClick integrations
This DTO handles profiles for Travel Agencies, Corporations, and Groups
when sending reservation data to TravelClick.

## Methods

### `__construct`

Create a new profile data DTO instance

```php
public function __construct(array $data)
```

---

### `isTravelAgency`

Check if this is a travel agency profile

```php
public function isTravelAgency(): bool
```

**Returns:** bool - True if this is a travel agency profile

---

### `isCorporate`

Check if this is a corporate profile

```php
public function isCorporate(): bool
```

**Returns:** bool - True if this is a corporate profile

---

### `isGroup`

Check if this is a group profile

```php
public function isGroup(): bool
```

**Returns:** bool - True if this is a group profile

---

### `hasValidAddress`

Check if this profile has a valid address

```php
public function hasValidAddress(): bool
```

**Returns:** bool - True if the profile has at least address line 1, city and country

---

### `hasValidContactInfo`

Check if this profile has valid contact information

```php
public function hasValidContactInfo(): bool
```

**Returns:** bool - True if the profile has at least an email or phone

---

### `hasCommission`

Check if this profile has commission information

```php
public function hasCommission(): bool
```

**Returns:** bool - True if commission information is available

---

### `createTravelAgencyProfile`

Create a travel agency profile from Centrium agency data

```php
public function createTravelAgencyProfile(mixed $agencyData): self
```

**Parameters:**

- `$agencyData` (mixed): The Centrium agency data

**Returns:** self - A new ProfileDataDto for the travel agency

---

### `createCorporateProfile`

Create a corporate profile from Centrium trade data

```php
public function createCorporateProfile(mixed $tradeData): self
```

**Parameters:**

- `$tradeData` (mixed): The Centrium trade/company data

**Returns:** self - A new ProfileDataDto for the corporation

---

### `createGroupProfile`

Create a group profile from Centrium booking group data

```php
public function createGroupProfile(mixed $bookingGroupData): self
```

**Parameters:**

- `$bookingGroupData` (mixed): The Centrium booking group data

**Returns:** self - A new ProfileDataDto for the group

---

### `toArray`

Convert to array representation

```php
public function toArray(): array
```

**Returns:** array<string, - mixed> The profile data as an array

---

