# TravelClickSyncStatusResource

**Full Class Name:** `App\TravelClick\Http\Resources\TravelClickSyncStatusResource`

**File:** `Http/Resources/TravelClickSyncStatusResource.php`

**Type:** Class

## Description

TravelClick Sync Status Resource
Transforms TravelClickSyncStatus model into a JSON structure suitable for API responses.
Like a professional interpreter, this resource takes complex internal data and
presents it in a clean, consistent format that front-end applications love.

## Methods

### `toArray`

Transform the resource into an array.

```php
public function toArray(Illuminate\Http\Request $request): array
```

**Parameters:**

- `$request` (Request): 

**Returns:** array<string, - mixed>

---

### `collection`

Static method to create a collection resource with additional metadata

```php
public function collection(mixed $resource): App\TravelClick\Http\Resources\TravelClickSyncStatusCollection
```

---

### `with`

Add additional metadata when this resource is used in responses

```php
public function with(Illuminate\Http\Request $request): array
```

---

### `withResponse`

Customize the HTTP response when this resource is served

```php
public function withResponse(Illuminate\Http\Request $request, mixed $response): void
```

---

