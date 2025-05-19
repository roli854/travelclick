# SyncStatus

**Full Class Name:** `App\TravelClick\Enums\SyncStatus`

**File:** `Enums/SyncStatus.php`

**Type:** Class

## Description

SyncStatus Enum for TravelClick Integration
Tracks the status of synchronization operations between Centrium and TravelClick.
This helps us know exactly where each piece of data stands in the sync process.
Think of this like tracking a package shipment:
- Pending: Ready to ship
- Processing: In transit
- Completed: Delivered successfully
- Failed: Delivery failed
- etc.

## Methods

### `label`

Get human-readable description (alias of description for compatibility)

```php
public function label(): string
```

---

### `description`

Get human-readable description

```php
public function description(): string
```

---

### `isSuccess`

Check if this status indicates success

```php
public function isSuccess(): bool
```

---

### `isFailure`

Check if this status indicates failure

```php
public function isFailure(): bool
```

---

### `isInProgress`

Check if this status indicates the sync is in progress

```php
public function isInProgress(): bool
```

---

### `canRetry`

Check if this status can be retried

```php
public function canRetry(): bool
```

---

### `requiresAttention`

Check if this status requires attention

```php
public function requiresAttention(): bool
```

---

### `getNextRetryStatus`

Get the next logical status after a failed attempt

```php
public function getNextRetryStatus(int $attemptCount, int $maxAttempts): self
```

---

### `getColor`

Get color for UI display (useful for dashboards)

```php
public function getColor(): string
```

---

### `getIcon`

Get icon for UI display

```php
public function getIcon(): string
```

---

### `finalStatuses`

Get all statuses that indicate completion (success or permanent failure)

```php
public function finalStatuses(): array
```

---

### `activeStatuses`

Get all statuses that indicate active processing

```php
public function activeStatuses(): array
```

---

### `cases`

```php
public function cases(): array
```

---

### `from`

```php
public function from(string|int $value): static
```

---

### `tryFrom`

```php
public function tryFrom(string|int $value): static|null
```

---

