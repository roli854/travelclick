# ValidCurrencyCode

**Full Class Name:** `App\TravelClick\Rules\ValidCurrencyCode`

**File:** `Rules/ValidCurrencyCode.php`

**Type:** Class

## Description

Validates currency codes according to ISO 4217 standard
and TravelClick supported currencies

## Methods

### `passes`

Determine if the validation rule passes.

```php
public function passes(mixed $attribute, mixed $value): bool
```

**Parameters:**

- `$attribute` (string): 
- `$value` (mixed): 

**Returns:** bool - 

---

### `message`

Get the validation error message.

```php
public function message(): string
```

**Returns:** string - 

---

### `getSupportedCurrencies`

Get all supported currency codes

```php
public function getSupportedCurrencies(): array
```

**Returns:** array - 

---

### `isSupported`

Check if a specific currency is supported

```php
public function isSupported(string $currency): bool
```

**Parameters:**

- `$currency` (string): 

**Returns:** bool - 

---

### `getCurrencyInfo`

Get currency information for debugging/logging

```php
public function getCurrencyInfo(string $currency): array
```

**Parameters:**

- `$currency` (string): 

**Returns:** array - 

---

