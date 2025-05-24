# SpecialRequestDto

**Full Class Name:** `App\TravelClick\DTOs\SpecialRequestDto`

**File:** `DTOs/SpecialRequestDto.php`

**Type:** Class

## Description

Data Transfer Object for special requests in TravelClick integrations
This DTO handles special requests that don't incur additional costs.
Examples include accessibility requirements, room preferences, etc.

## Properties

### `$requestCode`

Request details

**Type:** `string`

---

### `$requestName`

**Type:** `string`

---

### `$requestDescription`

**Type:** `string|null`

---

### `$startDate`

Request timing

**Type:** `Carbon\Carbon|null`

---

### `$endDate`

**Type:** `Carbon\Carbon|null`

---

### `$timeSpan`

**Type:** `string|null`

---

### `$comments`

Additional information

**Type:** `string|null`

---

### `$confirmed`

**Type:** `bool`

---

### `$quantity`

**Type:** `int`

---

### `$roomStayIndex`

**Type:** `int|null`

---

## Methods

### `__construct`

Create a new special request DTO instance

```php
public function __construct(array $data)
```

---

### `getFormattedStartDate`

Get formatted start date (YYYY-MM-DD)

```php
public function getFormattedStartDate(): string|null
```

**Returns:** string|null - Formatted date or null if not set

---

### `getFormattedEndDate`

Get formatted end date (YYYY-MM-DD)

```php
public function getFormattedEndDate(): string|null
```

**Returns:** string|null - Formatted date or null if not set

---

### `appliesToSpecificStay`

Check if this special request applies to a specific stay

```php
public function appliesToSpecificStay(): bool
```

**Returns:** bool - True if this applies to a specific room stay

---

### `hasDateRange`

Check if this special request applies to a specific date range

```php
public function hasDateRange(): bool
```

**Returns:** bool - True if this applies to a specific date range

---

### `fromCentriumPropertyBookingComment`

Convert common Centrium property booking comments to special requests

```php
public function fromCentriumPropertyBookingComment($propertyBookingComment): self|null
```

**Parameters:**

- `$propertyBookingComment` (mixed): The Centrium property booking comment

**Returns:** self|null - A SpecialRequestDto if applicable, or null

---

### `toArray`

Convert to array representation

```php
public function toArray(): array
```

**Returns:** array<string, - mixed> The special request data as an array

---

