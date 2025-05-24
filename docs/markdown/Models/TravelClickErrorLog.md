# TravelClickErrorLog

**Full Class Name:** `App\TravelClick\Models\TravelClickErrorLog`

**File:** `Models/TravelClickErrorLog.php`

**Type:** Class

## Description

TravelClick Error Log Model
This model represents detailed error tracking for TravelClick operations.
It provides comprehensive error analysis, retry logic, and resolution tracking.

## Constants

### `CREATED_AT`

Laravel timestamp configuration.
Using custom created_at field name following Centrium convention.

**Value:** `'DateCreated'`

---

### `UPDATED_AT`

**Value:** `null`

---

## Methods

### `travelClickLog`

Get the travel click log that owns this error.

```php
public function travelClickLog(): Illuminate\Database\Eloquent\Relations\BelongsTo
```

---

### `resolvedByUser`

Get the system user who resolved this error.
This would typically relate to a SystemUser model in Centrium.

```php
public function resolvedByUser(): Illuminate\Database\Eloquent\Relations\BelongsTo
```

---

### `systemUser`

Get the system user who created this log entry.
This would typically relate to a SystemUser model in Centrium.

```php
public function systemUser(): Illuminate\Database\Eloquent\Relations\BelongsTo
```

---

### `scopeBySeverity`

Scope to filter by error severity.

```php
public function scopeBySeverity(Illuminate\Database\Eloquent\Builder $query, string $severity): Illuminate\Database\Eloquent\Builder
```

---

### `scopeCritical`

Scope to filter critical errors.

```php
public function scopeCritical(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
```

---

### `scopeUnresolved`

Scope to filter unresolved errors.

```php
public function scopeUnresolved(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
```

---

### `scopeRetryable`

Scope to filter errors that can be retried.

```php
public function scopeRetryable(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
```

---

### `scopeRequiresManualIntervention`

Scope to filter errors requiring manual intervention.

```php
public function scopeRequiresManualIntervention(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
```

---

### `scopeForProperty`

Scope to filter by property.

```php
public function scopeForProperty(Illuminate\Database\Eloquent\Builder $query, int $propertyId): Illuminate\Database\Eloquent\Builder
```

---

### `scopeByType`

Scope to filter by error type.

```php
public function scopeByType(Illuminate\Database\Eloquent\Builder $query, ErrorType $errorType): Illuminate\Database\Eloquent\Builder
```

---

### `scopeRecent`

Scope to filter recent errors.

```php
public function scopeRecent(Illuminate\Database\Eloquent\Builder $query, int $hours = 24): Illuminate\Database\Eloquent\Builder
```

---

### `getFormattedErrorTitleAttribute`

Get the formatted error title for display.

```php
public function getFormattedErrorTitleAttribute(): string
```

---

### `getShortErrorMessageAttribute`

Get a shortened version of the error message for listings.

```php
public function getShortErrorMessageAttribute(): string
```

---

### `getIsResolvedAttribute`

Check if this error is resolved.

```php
public function getIsResolvedAttribute(): bool
```

---

### `getTimeSinceErrorAttribute`

Get human-readable time since error occurred.

```php
public function getTimeSinceErrorAttribute(): string
```

---

### `getSeverityColorAttribute`

Get the severity color for UI display.

```php
public function getSeverityColorAttribute(): string
```

---

### `markAsResolved`

Mark this error as resolved by a user.

```php
public function markAsResolved(int $userId, string|null $notes = null): bool
```

---

### `isRecentError`

Check if this error occurred within the last X minutes.

```php
public function isRecentError(int $minutes = 5): bool
```

---

### `getSimilarErrors`

Get similar errors (same type and property).

```php
public function getSimilarErrors(int $limit = 5)
```

---

### `logError`

Create a comprehensive error log entry.

```php
public function logError(string $messageId, ErrorType $errorType, string $title, string $message, array $context = [], string $severity = 'medium', string|null $jobId = null, int $propertyId = 0, bool $canRetry = false, int|null $retryDelaySeconds = null, bool $requiresManualIntervention = false): self
```

---

### `getErrorStats`

Get error statistics for a property.

```php
public function getErrorStats(int $propertyId, int $days = 7): array
```

---

### `factory`

Get a new factory instance for the model.

```php
public function factory($count = null, $state = [])
```

**Returns:** TFactory - 

---

