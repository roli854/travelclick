# TravelClickPropertyConfig

**Full Class Name:** `App\TravelClick\Models\TravelClickPropertyConfig`

**File:** `Models/TravelClickPropertyConfig.php`

**Type:** Class

## Description

TravelClick Property Configuration Model
Stores property-specific configurations for TravelClick integration.
Each property can have its own credentials, endpoints, and feature flags.

## Methods

### `property`

Get the property that owns this configuration

```php
public function property(): Illuminate\Database\Eloquent\Relations\BelongsTo
```

---

### `scopeActive`

Scope to get only active configurations

```php
public function scopeActive(mixed $query)
```

---

### `scopeNeedsSync`

Scope to get configurations that need sync

```php
public function scopeNeedsSync(mixed $query, int $hoursThreshold = 24)
```

---

### `scopeForProperties`

Scope to get configurations for specific properties

```php
public function scopeForProperties(mixed $query, array $propertyIds)
```

---

### `getConfigValue`

Get a specific configuration value

```php
public function getConfigValue(string $key, mixed $default = null): mixed
```

---

### `setConfigValue`

Set a specific configuration value

```php
public function setConfigValue(string $key, mixed $value): self
```

---

### `hasRequiredFields`

Check if configuration has required fields

```php
public function hasRequiredFields(): bool
```

---

### `markAsSynced`

Mark configuration as synced

```php
public function markAsSynced(): self
```

---

### `getConfigForLogging`

Get configuration with masked credentials for logging

```php
public function getConfigForLogging(): array
```

---

### `mergeConfig`

Merge with another configuration array

```php
public function mergeConfig(array $newConfig): self
```

---

### `validateConfig`

Validate configuration structure

```php
public function validateConfig(): array
```

---

### `getDueForHealthCheck`

Get property configurations that are due for health check

```php
public function getDueForHealthCheck(int $intervalHours = 6): Illuminate\Support\Collection
```

---

### `updateHealthCheck`

Update last health check timestamp

```php
public function updateHealthCheck(bool $healthy = true): self
```

---

### `isHealthy`

Check if configuration is healthy

```php
public function isHealthy(): bool
```

---

### `getLastHealthCheck`

Get last health check time

```php
public function getLastHealthCheck(): Carbon\Carbon|null
```

---

### `forceDelete`

Force a hard delete on a soft deleted model.

```php
public function forceDelete()
```

**Returns:** bool|null - 

---

### `forceDestroy`

Destroy the models for the given IDs.

```php
public function forceDestroy(mixed $ids)
```

**Parameters:**

- `$ids` (\Illuminate\Support\Collection|array|int|string): 

**Returns:** int - 

---

### `factory`

Get a new factory instance for the model.

```php
public function factory(mixed $count = null, mixed $state = [])
```

**Returns:** TFactory - 

---

### `bootSoftDeletes`

Boot the soft deleting trait for a model.

```php
public function bootSoftDeletes()
```

**Returns:** void - 

---

### `initializeSoftDeletes`

Initialize the soft deleting trait for an instance.

```php
public function initializeSoftDeletes()
```

**Returns:** void - 

---

### `forceDeleteQuietly`

Force a hard delete on a soft deleted model without raising any events.

```php
public function forceDeleteQuietly()
```

**Returns:** bool|null - 

---

### `restore`

Restore a soft-deleted model instance.

```php
public function restore()
```

**Returns:** bool - 

---

### `restoreQuietly`

Restore a soft-deleted model instance without raising any events.

```php
public function restoreQuietly()
```

**Returns:** bool - 

---

### `trashed`

Determine if the model instance has been soft-deleted.

```php
public function trashed()
```

**Returns:** bool - 

---

### `softDeleted`

Register a "softDeleted" model event callback with the dispatcher.

```php
public function softDeleted(mixed $callback)
```

**Parameters:**

- `$callback` (\Illuminate\Events\QueuedClosure|callable|class-string): 

**Returns:** void - 

---

### `restoring`

Register a "restoring" model event callback with the dispatcher.

```php
public function restoring(mixed $callback)
```

**Parameters:**

- `$callback` (\Illuminate\Events\QueuedClosure|callable|class-string): 

**Returns:** void - 

---

### `restored`

Register a "restored" model event callback with the dispatcher.

```php
public function restored(mixed $callback)
```

**Parameters:**

- `$callback` (\Illuminate\Events\QueuedClosure|callable|class-string): 

**Returns:** void - 

---

### `forceDeleting`

Register a "forceDeleting" model event callback with the dispatcher.

```php
public function forceDeleting(mixed $callback)
```

**Parameters:**

- `$callback` (\Illuminate\Events\QueuedClosure|callable|class-string): 

**Returns:** void - 

---

### `forceDeleted`

Register a "forceDeleted" model event callback with the dispatcher.

```php
public function forceDeleted(mixed $callback)
```

**Parameters:**

- `$callback` (\Illuminate\Events\QueuedClosure|callable|class-string): 

**Returns:** void - 

---

### `isForceDeleting`

Determine if the model is currently force deleting.

```php
public function isForceDeleting()
```

**Returns:** bool - 

---

### `getDeletedAtColumn`

Get the name of the "deleted at" column.

```php
public function getDeletedAtColumn()
```

**Returns:** string - 

---

### `getQualifiedDeletedAtColumn`

Get the fully qualified "deleted at" column.

```php
public function getQualifiedDeletedAtColumn()
```

**Returns:** string - 

---

