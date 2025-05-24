# TravelClickSyncStatus

**Full Class Name:** `App\TravelClick\Models\TravelClickSyncStatus`

**File:** `Models/TravelClickSyncStatus.php`

**Type:** Class

## Description

TravelClick Sync Status Model
Tracks the synchronization status for each property and message type.
This model is like a "status board" that shows the current state of all
sync operations, helping identify what needs attention and what's running smoothly.

## Properties

### `$timestamps`

Disable Laravel timestamps as we use Centrium conventions

---

## Methods

### `property`

Relationship: Property (cross-database relationship)
Since TravelClickSyncStatus is in centriumLog database and Property is in main database,
we need to handle this relationship carefully.

```php
public function property(): Illuminate\Database\Eloquent\Relations\BelongsTo
```

---

### `lastSyncUser`

Relationship: User who performed the last sync

```php
public function lastSyncUser(): Illuminate\Database\Eloquent\Relations\BelongsTo
```

---

### `travelClickLogs`

Relationship: Related TravelClick logs

```php
public function travelClickLogs(): Illuminate\Database\Eloquent\Relations\HasMany
```

---

### `recentErrorLogs`

Relationship: Recent error logs for this sync

```php
public function recentErrorLogs(): Illuminate\Database\Eloquent\Relations\HasMany
```

---

### `scopeActive`

Scope: Active sync statuses only

```php
public function scopeActive(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
```

---

### `scopeForProperty`

Scope: Filter by specific property

```php
public function scopeForProperty(Illuminate\Database\Eloquent\Builder $query, int $propertyId): Illuminate\Database\Eloquent\Builder
```

---

### `scopeOfType`

Scope: Filter by message type

```php
public function scopeOfType(Illuminate\Database\Eloquent\Builder $query, MessageType $messageType): Illuminate\Database\Eloquent\Builder
```

---

### `scopeWithStatus`

Scope: Filter by status

```php
public function scopeWithStatus(Illuminate\Database\Eloquent\Builder $query, SyncStatus $status): Illuminate\Database\Eloquent\Builder
```

---

### `scopeNeedsRetry`

Scope: Records that need retry

```php
public function scopeNeedsRetry(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
```

---

### `scopeLongRunning`

Scope: Long running syncs

```php
public function scopeLongRunning(Illuminate\Database\Eloquent\Builder $query, int $minutes = 30): Illuminate\Database\Eloquent\Builder
```

---

### `scopeRecentFailures`

Scope: Failed syncs in the last period

```php
public function scopeRecentFailures(Illuminate\Database\Eloquent\Builder $query, int $hours = 24): Illuminate\Database\Eloquent\Builder
```

---

### `scopeLowSuccessRate`

Scope: Sync statuses with low success rate

```php
public function scopeLowSuccessRate(Illuminate\Database\Eloquent\Builder $query, float $threshold = 80.0): Illuminate\Database\Eloquent\Builder
```

---

### `isRunning`

Check if sync is currently running

```php
public function isRunning(): bool
```

---

### `hasFailed`

Check if sync has failed

```php
public function hasFailed(): bool
```

---

### `canRetry`

Check if sync can be retried

```php
public function canRetry(): bool
```

---

### `isOverdueForRetry`

Check if sync is overdue for a retry

```php
public function isOverdueForRetry(): bool
```

---

### `getProgressPercentage`

Get progress percentage

```php
public function getProgressPercentage(): int
```

---

### `getTimeSinceLastSync`

Get formatted duration since last sync attempt

```php
public function getTimeSinceLastSync(): string
```

---

### `getTimeSinceLastSuccess`

Get formatted duration since last successful sync

```php
public function getTimeSinceLastSuccess(): string
```

---

### `markAsStarted`

Mark sync as started

```php
public function markAsStarted(int|null $totalRecords = null, int|null $userId = null): self
```

---

### `updateProgress`

Update progress during sync

```php
public function updateProgress(int $processed, string|null $messageId = null): self
```

---

### `markAsCompleted`

Mark sync as completed successfully

```php
public function markAsCompleted(int|null $finalProcessed = null, array $context = []): self
```

---

### `markAsFailed`

Mark sync as failed

```php
public function markAsFailed(string $errorMessage, array $context = []): self
```

---

### `resetForRetry`

Reset for manual retry

```php
public function resetForRetry(int|null $userId = null): self
```

---

### `disableAutoRetry`

Disable auto-retry

```php
public function disableAutoRetry(): self
```

---

### `enableAutoRetry`

Enable auto-retry

```php
public function enableAutoRetry(): self
```

---

### `scheduleNextRetry`

Schedule next retry attempt

```php
public function scheduleNextRetry(int|null $delayMinutes = null): self
```

---

### `getStatusWithColorAttribute`

Get status with color for UI display

```php
public function getStatusWithColorAttribute(): array
```

---

### `getOperationsSummaryAttribute`

Get operations summary for dashboard

```php
public function getOperationsSummaryAttribute(): array
```

---

### `getSyncHealthScoreAttribute`

Get sync health score (0-100)
Based on success rate, retry count, and time since last success

```php
public function getSyncHealthScoreAttribute(): int
```

---

### `findOrCreateForProperty`

Create or find sync status for a property and message type

```php
public function findOrCreateForProperty(int $propertyId, MessageType $messageType, array $attributes = []): self
```

---

### `getPropertyStats`

Get sync statistics for a property

```php
public function getPropertyStats(int $propertyId, int $days = 30): array
```

---

### `getNeedsAttention`

Get syncs that need attention (failed, long running, low success rate)

```php
public function getNeedsAttention(int|null $propertyId = null): array
```

---

### `getSystemHealthReport`

Get system-wide sync health report

```php
public function getSystemHealthReport(): array
```

---

### `getPropertyInfo`

Helper method to get property information safely (cross-database)
This method helps handle the cross-database relationship gracefully

```php
public function getPropertyInfo(): array|null
```

---

### `factory`

Get a new factory instance for the model.

```php
public function factory($count = null, $state = [])
```

**Returns:** TFactory - 

---

