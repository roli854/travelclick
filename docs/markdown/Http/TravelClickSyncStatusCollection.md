# TravelClickSyncStatusCollection

**Full Class Name:** `App\TravelClick\Http\Resources\TravelClickSyncStatusCollection`

**File:** `Http/Resources/TravelClickSyncStatusCollection.php`

**Type:** Class

## Description

TravelClick Sync Status Collection Resource
Handles collections of TravelClickSyncStatus resources with additional
aggregation and summary data. Think of this as a dashboard summary
that provides insights across multiple synchronization statuses.

## Properties

### `$collects`

The resource that this resource collects

---

## Methods

### `toArray`

Transform the resource collection into an array.

```php
public function toArray(Illuminate\Http\Request $request): array
```

**Parameters:**

- `$request` (Request): 

**Returns:** array<string, - mixed>

---

### `with`

Add additional metadata to the response

```php
public function with(Illuminate\Http\Request $request): array
```

---

### `withResponse`

Customize the HTTP response for the collection

```php
public function withResponse(Illuminate\Http\Request $request, $response): void
```

---

