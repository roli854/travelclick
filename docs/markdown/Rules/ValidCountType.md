# ValidCountType

**Full Class Name:** `App\TravelClick\Rules\ValidCountType`

**File:** `Rules/ValidCountType.php`

**Type:** Class

## Description

ValidCountType - Custom validation rule for HTNG 2011B CountType values
This rule validates CountType values according to HTNG specifications:
- Ensures only valid CountType values are used
- Enforces business rules about CountType combinations
- Validates calculated vs non-calculated method constraints
Think of this as a quality inspector for inventory count types:
- Checks each type is valid according to HTNG standards
- Ensures combinations make business sense
- Prevents common mistakes in inventory messages

## Methods

### `__construct`

Create a new rule instance

```php
public function __construct(bool $validateCalculated = true, bool $allowMultiple = true)
```

**Parameters:**

- `$validateCalculated` (bool): Enforce calculated method rules
- `$allowMultiple` (bool): Allow multiple CountTypes in same message

---

### `validate`

Run the validation rule

```php
public function validate(string $attribute, mixed $value, Closure $fail): void
```

**Parameters:**

- `$attribute` (string): The attribute being validated
- `$value` (mixed): The value to validate
- `$fail` (Closure): Callback to call if validation fails

---

### `nonCalculated`

Create instance for non-calculated method validation

```php
public function nonCalculated(bool $allowMultiple = true): self
```

---

### `single`

Create instance for single CountType validation

```php
public function single(bool $validateCalculated = true): self
```

---

### `calculated`

Create instance for calculated method validation

```php
public function calculated(): self
```

---

