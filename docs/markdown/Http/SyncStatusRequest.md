# SyncStatusRequest

**Full Class Name:** `App\TravelClick\Http\Requests\SyncStatusRequest`

**File:** `Http/Requests/SyncStatusRequest.php`

**Type:** Class

## Description

Request validation for TravelClick Sync Status operations
This request class validates data for updating sync status records.
Think of it like a quality control inspector that ensures all the data
coming into our sync system meets the required standards before processing.
Validates:
- Property existence
- Message type validity
- Status enum values
- Business logic constraints

## Methods

### `authorize`

Determine if the user is authorized to make this request.
For now, we'll assume all authenticated users can update sync status.
In production, you might want to implement role-based permissions.

```php
public function authorize(): bool
```

---

### `rules`

Get the validation rules that apply to the request.
These rules ensure the data integrity before we try to update
sync status records in the database.

```php
public function rules(): array
```

---

### `messages`

Get custom validation messages
Provides user-friendly error messages that help identify
exactly what's wrong with the submitted data.

```php
public function messages(): array
```

---

### `attributes`

Get custom attributes for validation error messages
Makes error messages more user-friendly by using proper names
instead of field names in validation messages.

```php
public function attributes(): array
```

---

### `withValidator`

Configure the validator instance
Allows for complex validation logic that depends on multiple fields
or business rules that can't be expressed with simple rules.

```php
public function withValidator(mixed $validator): void
```

---

### `getSyncStatusData`

Get data ready for sync status update
Returns only the data needed for updating the sync status record,
properly formatted and with any transformations applied.

```php
public function getSyncStatusData(): array
```

---

### `isUpdateRequest`

Check if this request is for updating an existing record

```php
public function isUpdateRequest(): bool
```

---

### `isCreateRequest`

Check if this request is for creating a new record

```php
public function isCreateRequest(): bool
```

---

### `getApiDocumentation`

Get validation rules for API documentation
This can be used to generate API documentation automatically

```php
public function getApiDocumentation(): array
```

---

