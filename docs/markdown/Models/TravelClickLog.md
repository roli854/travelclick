# TravelClickLog

**Full Class Name:** `App\TravelClick\Models\TravelClickLog`

**File:** `Models/TravelClickLog.php`

**Type:** Class

## Description

TravelClick Log Model
This model represents the main audit log for all TravelClick operations.
It's like a detailed journal of every interaction with TravelClick.

## Constants

### `CREATED_AT`

Laravel timestamp configuration.
Using custom timestamp field names following Centrium convention.

**Value:** `'DateCreated'`

---

### `UPDATED_AT`

**Value:** `'DateModified'`

---

## Methods

### `systemUser`

Get the system user who initiated this operation.

```php
public function systemUser(): Illuminate\Database\Eloquent\Relations\BelongsTo
```

---

### `property`

Get the property this log entry relates to.
Note: This assumes you have a Property model in Centrium.

```php
public function property(): Illuminate\Database\Eloquent\Relations\BelongsTo
```

---

### `errorLogs`

Get all error logs associated with this operation.

```php
public function errorLogs(): Illuminate\Database\Eloquent\Relations\HasMany
```

---

### `messageHistory`

Get message history entries for this log.

```php
public function messageHistory(): Illuminate\Database\Eloquent\Relations\HasMany
```

---

### `scopeDirection`

Scope to filter by direction (inbound/outbound).

```php
public function scopeDirection(Illuminate\Database\Eloquent\Builder $query, MessageDirection $direction): Illuminate\Database\Eloquent\Builder
```

---

### `scopeByType`

Scope to filter by message type.

```php
public function scopeByType(Illuminate\Database\Eloquent\Builder $query, MessageType $messageType): Illuminate\Database\Eloquent\Builder
```

---

### `scopeByStatus`

Scope to filter by status.

```php
public function scopeByStatus(Illuminate\Database\Eloquent\Builder $query, SyncStatus $status): Illuminate\Database\Eloquent\Builder
```

---

### `scopePending`

Scope to filter pending operations.

```php
public function scopePending(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
```

---

### `scopeCompleted`

Scope to filter completed operations.

```php
public function scopeCompleted(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
```

---

### `scopeFailed`

Scope to filter failed operations.

```php
public function scopeFailed(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
```

---

### `scopeForProperty`

Scope to filter by property.

```php
public function scopeForProperty(Illuminate\Database\Eloquent\Builder $query, int $propertyId): Illuminate\Database\Eloquent\Builder
```

---

### `scopeForHotel`

Scope to filter by hotel code.

```php
public function scopeForHotel(Illuminate\Database\Eloquent\Builder $query, string $hotelCode): Illuminate\Database\Eloquent\Builder
```

---

### `scopeRecent`

Scope to filter recent logs.

```php
public function scopeRecent(Illuminate\Database\Eloquent\Builder $query, int $hours = 24): Illuminate\Database\Eloquent\Builder
```

---

### `scopeWithErrors`

Scope to filter operations with errors.

```php
public function scopeWithErrors(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
```

---

### `scopeNeedsRetry`

Scope to filter operations that need retry.

```php
public function scopeNeedsRetry(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
```

---

### `scopeLongRunning`

Scope to filter long-running operations.

```php
public function scopeLongRunning(Illuminate\Database\Eloquent\Builder $query, int $thresholdMs = 30000): Illuminate\Database\Eloquent\Builder
```

---

### `getFormattedDurationAttribute`

Get the operation duration in a human-readable format.

```php
public function getFormattedDurationAttribute(): string
```

---

### `getStatusWithColorAttribute`

Get status with color for UI display.

```php
public function getStatusWithColorAttribute(): array
```

---

### `getOperationSummaryAttribute`

Get a summary of the operation for quick display.

```php
public function getOperationSummaryAttribute(): string
```

---

### `getIsSuccessfulAttribute`

Check if this operation was successful.

```php
public function getIsSuccessfulAttribute(): bool
```

---

### `getIsRunningAttribute`

Check if this operation is still running.

```php
public function getIsRunningAttribute(): bool
```

---

### `getIsOverdueAttribute`

Check if operation has taken too long (more than expected).

```php
public function getIsOverdueAttribute(): bool
```

---

### `getTruncatedRequestXmlAttribute`

Get XML content safely (truncated if too large).

```php
public function getTruncatedRequestXmlAttribute(): string
```

---

### `getTruncatedResponseXmlAttribute`

Get XML content safely (truncated if too large).

```php
public function getTruncatedResponseXmlAttribute(): string
```

---

### `markAsStarted`

Mark the operation as started.

```php
public function markAsStarted(): bool
```

---

### `markAsCompleted`

Mark the operation as completed successfully.

```php
public function markAsCompleted(string|null $responseXml = null): bool
```

---

### `markAsFailed`

Mark the operation as failed.

```php
public function markAsFailed(ErrorType $errorType, string $errorMessage, string|null $responseXml = null): bool
```

---

### `incrementRetryCount`

Increment retry count.

```php
public function incrementRetryCount(): bool
```

---

### `addMetadata`

Add metadata to the log entry.

```php
public function addMetadata(array $data): bool
```

---

### `createLog`

Create a new TravelClick log entry.

```php
public function createLog(string $messageId, MessageDirection $direction, MessageType $messageType, int $propertyId, string|null $hotelCode = null, string|null $requestXml = null, array $metadata = [], string|null $jobId = null, int|null $systemUserId = null): self
```

---

### `getPerformanceStats`

Get performance statistics for a property.

```php
public function getPerformanceStats(int $propertyId, int $days = 7): array
```

---

### `getRecentActivity`

Get recent activity summary.

```php
public function getRecentActivity(int $hours = 24): array
```

---

### `getNeedsAttention`

Get logs that need attention (failed, overdue, etc.).

```php
public function getNeedsAttention(): Illuminate\Database\Eloquent\Builder
```

---

### `cleanup`

Clean up old log entries (for maintenance).

```php
public function cleanup(int $daysToKeep = 30): int
```

---

### `getErrorPatterns`

Get error pattern analysis.

```php
public function getErrorPatterns(int|null $propertyId = null, int $days = 7): array
```

---

### `factory`

Get a new factory instance for the model.

```php
public function factory($count = null, $state = [])
```

**Returns:** TFactory - 

---

