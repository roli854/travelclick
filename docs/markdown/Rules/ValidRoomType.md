# ValidRoomType

**Full Class Name:** `App\TravelClick\Rules\ValidRoomType`

**File:** `Rules/ValidRoomType.php`

**Type:** Class

## Methods

### `__construct`

Create a new rule instance.

```php
public function __construct(int $propertyId = null)
```

**Parameters:**

- `$propertyId` (int|null): The property ID to validate against

---

### `setData`

Set the data under validation.

```php
public function setData(array $data): static
```

---

### `validate`

Determine if the validation rule passes.

```php
public function validate(string $attribute, mixed $value, Closure $fail): void
```

**Parameters:**

- `$attribute` (string): 
- `$value` (mixed): 
- `$fail` (\Closure): 

---

### `forProperty`

Create a new rule instance for a specific property

```php
public function forProperty(int $propertyId): static
```

**Parameters:**

- `$propertyId` (int): 

**Returns:** static - 

---

### `message`

Get the validation error message.

```php
public function message(): string
```

**Returns:** string - 

---

