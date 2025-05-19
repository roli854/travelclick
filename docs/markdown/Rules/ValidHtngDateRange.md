# ValidHtngDateRange

**Full Class Name:** `App\TravelClick\Rules\ValidHtngDateRange`

**File:** `Rules/ValidHtngDateRange.php`

**Type:** Class

## Description

ValidHtngDateRange
Custom validation rule for HTNG 2011B date ranges.
Validates start and end dates according to HTNG specifications, ensuring proper
format, logical ordering, and business rule compliance.
Features:
- Validates ISO 8601 date formats
- Ensures end date is after start date
- Enforces maximum date range limits
- Validates against past/future date restrictions
- Supports configurable validation options

## Methods

### `__construct`

Create a new rule instance

```php
public function __construct(array $options = [])
```

**Parameters:**

- `$options` (array): Custom validation options

---

### `validate`

Run the validation rule

```php
public function validate(string $attribute, mixed $value, Closure $fail): void
```

**Parameters:**

- `$attribute` (string): The attribute being validated
- `$value` (mixed): The value to validate (expected to be array with 'start' and 'end' keys)

---

### `forInventory`

Create instance for inventory date range validation

```php
public function forInventory(): static
```

**Returns:** static - 

---

### `forRates`

Create instance for rate date range validation

```php
public function forRates(): static
```

**Returns:** static - 

---

### `forReservation`

Create instance for reservation date range validation

```php
public function forReservation(): static
```

**Returns:** static - 

---

### `forGroupBlock`

Create instance for group block date range validation

```php
public function forGroupBlock(): static
```

**Returns:** static - 

---

### `forRestrictions`

Create instance for restriction date range validation

```php
public function forRestrictions(): static
```

**Returns:** static - 

---

### `withBlackoutDates`

Create instance with custom blackout dates

```php
public function withBlackoutDates(array $blackoutDates, array $additionalOptions = []): static
```

**Parameters:**

- `$blackoutDates` (array): Array of blackout dates (Y-m-d format)
- `$additionalOptions` (array): Additional validation options

**Returns:** static - 

---

### `withWeekendRestrictions`

Create instance with weekend restrictions

```php
public function withWeekendRestrictions(bool $excludeWeekends = true, array $additionalOptions = []): static
```

**Parameters:**

- `$excludeWeekends` (bool): Whether to exclude weekends
- `$additionalOptions` (array): Additional validation options

**Returns:** static - 

---

### `withAllowedDays`

Create instance with specific allowed days

```php
public function withAllowedDays(array $allowedDays, array $additionalOptions = []): static
```

**Parameters:**

- `$allowedDays` (array): Array of allowed day numbers (0=Sunday, 1=Monday, etc.)
- `$additionalOptions` (array): Additional validation options

**Returns:** static - 

---

### `withMinimumStay`

Create instance with minimum stay requirement

```php
public function withMinimumStay(int $minStayDays, array $additionalOptions = []): static
```

**Parameters:**

- `$minStayDays` (int): Minimum number of days required
- `$additionalOptions` (array): Additional validation options

**Returns:** static - 

---

### `withMaxAdvanceBooking`

Create instance with maximum advance booking restriction

```php
public function withMaxAdvanceBooking(int $maxAdvanceDays, array $additionalOptions = []): static
```

**Parameters:**

- `$maxAdvanceDays` (int): Maximum days in advance booking is allowed
- `$additionalOptions` (array): Additional validation options

**Returns:** static - 

---

### `message`

Get the validation error message

```php
public function message(): string
```

**Returns:** string - 

---

