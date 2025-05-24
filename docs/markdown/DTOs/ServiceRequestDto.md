# ServiceRequestDto

**Full Class Name:** `App\TravelClick\DTOs\ServiceRequestDto`

**File:** `DTOs/ServiceRequestDto.php`

**Type:** Class

## Description

Data Transfer Object for service requests in TravelClick integrations
This DTO handles service requests that incur additional costs.
Examples include room service, spa treatments, transfers, etc.

## Properties

### `$serviceCode`

Service details

**Type:** `string`

---

### `$serviceName`

**Type:** `string`

---

### `$serviceDescription`

**Type:** `string|null`

---

### `$quantity`

**Type:** `int`

---

### `$startDate`

Service timing

**Type:** `Carbon\Carbon|null`

---

### `$endDate`

**Type:** `Carbon\Carbon|null`

---

### `$deliveryTime`

**Type:** `string|null`

---

### `$amount`

Financial information

**Type:** `float`

---

### `$totalAmount`

**Type:** `float|null`

---

### `$currencyCode`

**Type:** `string`

---

### `$includedInRate`

**Type:** `bool`

---

### `$numberOfAdults`

Guest information

**Type:** `int`

---

### `$numberOfChildren`

**Type:** `int`

---

### `$roomStayIndex`

**Type:** `int|null`

---

### `$supplierConfirmationNumber`

**Type:** `string|null`

---

### `$comments`

Additional information

**Type:** `string|null`

---

### `$confirmed`

**Type:** `bool`

---

## Methods

### `__construct`

Create a new service request DTO instance

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

Check if this service applies to a specific stay

```php
public function appliesToSpecificStay(): bool
```

**Returns:** bool - True if this applies to a specific room stay

---

### `hasConfirmation`

Check if this service has a confirmation number

```php
public function hasConfirmation(): bool
```

**Returns:** bool - True if this service has a confirmation

---

### `getTotalCost`

Calculate total cost of the service (unit price * quantity)

```php
public function getTotalCost(): float
```

**Returns:** float - The total cost

---

### `fromCentriumPropertyRoomBookingAdjust`

Convert from Centrium property booking adjustment

```php
public function fromCentriumPropertyRoomBookingAdjust($adjustment): self|null
```

**Parameters:**

- `$adjustment` (mixed): The Centrium property room booking adjustment

**Returns:** self|null - A ServiceRequestDto if applicable, or null

---

### `toArray`

Convert to array representation

```php
public function toArray(): array
```

**Returns:** array<string, - mixed> The service request data as an array

---

