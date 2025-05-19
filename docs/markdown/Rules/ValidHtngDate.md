# ValidHtngDate

**Full Class Name:** `App\TravelClick\Rules\ValidHtngDate`

**File:** `Rules/ValidHtngDate.php`

**Type:** Class

## Description

ValidHtngDate
Custom validation rule for individual HTNG 2011B dates.
Validates single dates according to HTNG specifications and business rules.
Features:
- Validates ISO 8601 date formats
- Supports HTNG-specific date formats
- Enforces business rules for past/future dates
- Configurable validation options

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
- `$value` (mixed): The value to validate

---

### `forArrival`

Create instance for arrival dates

```php
public function forArrival(): static
```

**Returns:** static - 

---

### `forDeparture`

Create instance for departure dates

```php
public function forDeparture(): static
```

**Returns:** static - 

---

### `forBooking`

Create instance for booking dates

```php
public function forBooking(): static
```

**Returns:** static - 

---

### `forCancellationCutoff`

Create instance for cancellation cutoff dates

```php
public function forCancellationCutoff(): static
```

**Returns:** static - 

---

### `forInventorySync`

Create instance for inventory sync dates

```php
public function forInventorySync(): static
```

**Returns:** static - 

---

### `withBlackoutDates`

Create instance with blackout dates

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

### `message`

Get the validation error message

```php
public function message(): string
```

**Returns:** string - 

---

