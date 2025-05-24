# SyncStatus

**Full Class Name:** `App\TravelClick\Enums\SyncStatus`

**File:** `Enums/SyncStatus.php`

**Type:** Enum

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

## Constants

### `PENDING`

Data is ready to be synchronized but hasn't started yet

**Value:** `\App\TravelClick\Enums\SyncStatus::PENDING`

---

### `PROCESSING`

Synchronization is currently in progress

**Value:** `\App\TravelClick\Enums\SyncStatus::PROCESSING`

---

### `COMPLETED`

Synchronization completed successfully

**Value:** `\App\TravelClick\Enums\SyncStatus::COMPLETED`

---

### `FAILED`

Synchronization failed but can be retried

**Value:** `\App\TravelClick\Enums\SyncStatus::FAILED`

---

### `FAILED_PERMANENT`

Synchronization failed and will not be retried

**Value:** `\App\TravelClick\Enums\SyncStatus::FAILED_PERMANENT`

---

### `CANCELLED`

Synchronization was cancelled by user or system

**Value:** `\App\TravelClick\Enums\SyncStatus::CANCELLED`

---

### `RETRY_PENDING`

Waiting for retry after a failed attempt

**Value:** `\App\TravelClick\Enums\SyncStatus::RETRY_PENDING`

---

### `PARTIAL`

Partial success - some items succeeded, some failed

**Value:** `\App\TravelClick\Enums\SyncStatus::PARTIAL`

---

### `ON_HOLD`

Synchronization is on hold (manual intervention needed)

**Value:** `\App\TravelClick\Enums\SyncStatus::ON_HOLD`

---

### `MARKED_FOR_DELETION`

Data is marked for deletion/cleanup

**Value:** `\App\TravelClick\Enums\SyncStatus::MARKED_FOR_DELETION`

---

### `SUCCESS`

**Value:** `\App\TravelClick\Enums\SyncStatus::SUCCESS`

---

### `ERROR`

**Value:** `\App\TravelClick\Enums\SyncStatus::ERROR`

---

### `INACTIVE`

**Value:** `\App\TravelClick\Enums\SyncStatus::INACTIVE`

---

### `RUNNING`

**Value:** `\App\TravelClick\Enums\SyncStatus::RUNNING`

---

## Properties

### `$name`

**Type:** `string`

---

### `$value`

**Type:** `string`

---

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

