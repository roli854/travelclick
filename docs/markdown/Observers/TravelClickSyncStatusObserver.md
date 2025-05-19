# TravelClickSyncStatusObserver

**Full Class Name:** `App\TravelClick\Observers\TravelClickSyncStatusObserver`

**File:** `Observers/TravelClickSyncStatusObserver.php`

**Type:** Class

## Description

TravelClickSyncStatusObserver
Handles events for TravelClickSyncStatus model.
This observer is like a security camera system for sync operations - it watches
every change and records important events for auditing and monitoring.
Key responsibilities:
- Track state transitions and duration calculations
- Dispatch SyncStatusChanged events for real-time updates
- Log important changes for auditing
- Calculate metrics for health monitoring

## Methods

### `creating`

Handle the TravelClickSyncStatus "creating" event.
Fired before a new sync status record is created.

```php
public function creating(App\TravelClick\Models\TravelClickSyncStatus $syncStatus): void
```

**Parameters:**

- `$syncStatus` (TravelClickSyncStatus): 

**Returns:** void - 

---

### `created`

Handle the TravelClickSyncStatus "created" event.
Fired after a new sync status record has been saved.

```php
public function created(App\TravelClick\Models\TravelClickSyncStatus $syncStatus): void
```

**Parameters:**

- `$syncStatus` (TravelClickSyncStatus): 

**Returns:** void - 

---

### `updating`

Handle the TravelClickSyncStatus "updating" event.
Fired before changes are saved to the database.

```php
public function updating(App\TravelClick\Models\TravelClickSyncStatus $syncStatus): void
```

**Parameters:**

- `$syncStatus` (TravelClickSyncStatus): 

**Returns:** void - 

---

### `updated`

Handle the TravelClickSyncStatus "updated" event.
Fired after changes have been saved to the database.

```php
public function updated(App\TravelClick\Models\TravelClickSyncStatus $syncStatus): void
```

**Parameters:**

- `$syncStatus` (TravelClickSyncStatus): 

**Returns:** void - 

---

### `deleted`

Handle the TravelClickSyncStatus "deleted" event.
Fired after a record has been deleted.

```php
public function deleted(App\TravelClick\Models\TravelClickSyncStatus $syncStatus): void
```

**Parameters:**

- `$syncStatus` (TravelClickSyncStatus): 

**Returns:** void - 

---

### `restored`

Handle the TravelClickSyncStatus "restored" event.
Fired after a soft-deleted record has been restored.

```php
public function restored(App\TravelClick\Models\TravelClickSyncStatus $syncStatus): void
```

**Parameters:**

- `$syncStatus` (TravelClickSyncStatus): 

**Returns:** void - 

---

### `forceDeleted`

Handle the TravelClickSyncStatus "forceDeleted" event.
Fired after a record has been permanently deleted.

```php
public function forceDeleted(App\TravelClick\Models\TravelClickSyncStatus $syncStatus): void
```

**Parameters:**

- `$syncStatus` (TravelClickSyncStatus): 

**Returns:** void - 

---

