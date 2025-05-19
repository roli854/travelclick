# SyncStatusChanged

**Full Class Name:** `App\TravelClick\Events\SyncStatusChanged`

**File:** `Events/SyncStatusChanged.php`

**Type:** Class

## Description

SyncStatusChanged Event
This event is fired whenever a TravelClickSyncStatus changes state.
It's like sending a notification throughout the system that something
important happened with a sync operation.
This event can be listened to by multiple parts of the system:
- Logging systems to record the change
- Monitoring systems to update dashboards
- Notification systems to alert administrators
- Analytics systems to track patterns

## Methods

### `__construct`

Create a new event instance.

```php
public function __construct(App\TravelClick\Models\TravelClickSyncStatus $syncStatus, string $previousStatus = null, string $changeType = 'updated', array $context = [])
```

**Parameters:**

- `$syncStatus` (TravelClickSyncStatus): The sync status that changed
- `$previousStatus` (string|null): The previous status value
- `$changeType` (string): Type of change (created, updated, deleted)
- `$context` (array): Additional context about the change

---

### `broadcastOn`

Get the channels the event should broadcast on.
This allows real-time updates in dashboards and monitoring systems.

```php
public function broadcastOn(): array
```

**Returns:** array<int, - \Illuminate\Broadcasting\Channel>

---

### `broadcastAs`

The event's broadcast name.
This will be the event name that frontend JavaScript listens for.

```php
public function broadcastAs(): string
```

---

### `broadcastWith`

Get the data to broadcast.
This data will be sent to frontend clients via WebSocket.

```php
public function broadcastWith(): array
```

---

### `broadcastWhen`

Determine if this event should be broadcast.
Only broadcast certain types of changes to avoid spam.

```php
public function broadcastWhen(): bool
```

---

### `isStatusTransition`

Check if this is a status transition

```php
public function isStatusTransition(): bool
```

---

### `isFailure`

Check if this is a failure event

```php
public function isFailure(): bool
```

---

### `isCompletion`

Check if this is a completion event

```php
public function isCompletion(): bool
```

---

### `isCritical`

Check if this is a critical event (failure with max retries)

```php
public function isCritical(): bool
```

---

### `getDescription`

Get a human-readable description of the change

```php
public function getDescription(): string
```

---

### `getSeverity`

Get severity level for this event

```php
public function getSeverity(): string
```

---

### `getTags`

Get tags for filtering and organization

```php
public function getTags(): array
```

---

### `toArray`

Convert event to array for logging or storage

```php
public function toArray(): array
```

---

### `dispatch`

Dispatch the event with the given arguments.

```php
public function dispatch()
```

**Returns:** mixed - 

---

### `dispatchIf`

Dispatch the event with the given arguments if the given truth test passes.

```php
public function dispatchIf(mixed $boolean, mixed $arguments)
```

**Parameters:**

- `$boolean` (bool): 

**Returns:** mixed - 

---

### `dispatchUnless`

Dispatch the event with the given arguments unless the given truth test passes.

```php
public function dispatchUnless(mixed $boolean, mixed $arguments)
```

**Parameters:**

- `$boolean` (bool): 

**Returns:** mixed - 

---

### `broadcast`

Broadcast the event with the given arguments.

```php
public function broadcast()
```

**Returns:** \Illuminate\Broadcasting\PendingBroadcast - 

---

### `dontBroadcastToCurrentUser`

Exclude the current user from receiving the broadcast.

```php
public function dontBroadcastToCurrentUser()
```

**Returns:** $this - 

---

### `broadcastToEveryone`

Broadcast the event to everyone.

```php
public function broadcastToEveryone()
```

**Returns:** $this - 

---

### `__serialize`

Prepare the instance values for serialization.

```php
public function __serialize()
```

**Returns:** array - 

---

### `__unserialize`

Restore the model after serialization.

```php
public function __unserialize(array $values)
```

**Parameters:**

- `$values` (array): 

**Returns:** void - 

---

### `restoreModel`

Restore the model from the model identifier instance.

```php
public function restoreModel(mixed $value)
```

**Parameters:**

- `$value` (\Illuminate\Contracts\Database\ModelIdentifier): 

**Returns:** \Illuminate\Database\Eloquent\Model - 

---

