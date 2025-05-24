# ReservationSyncFailed

**Full Class Name:** `App\TravelClick\Events\ReservationSyncFailed`

**File:** `Events/ReservationSyncFailed.php`

**Type:** Class

## Description

Event fired when a reservation synchronization with TravelClick fails.

## Properties

### `$reservationData`

**Type:** `ReservationDataDto`

---

### `$exception`

**Type:** `Throwable`

---

### `$jobId`

**Type:** `string|null`

---

### `$attempts`

**Type:** `int`

---

### `$socket`

The socket ID for the user that raised the event.

---

## Methods

### `__construct`

Create a new event instance.

```php
public function __construct(ReservationDataDto $reservationData, Throwable $exception, string|null $jobId = null, int $attempts = 0)
```

**Parameters:**

- `$reservationData` (ReservationDataDto): The reservation data that failed to synchronize
- `$exception` (Throwable): The exception that caused the failure
- `$jobId` (string|null): The ID of the job that processed this sync (for tracking)
- `$attempts` (int): Number of attempts made before failing

---

### `getErrorDetails`

Get error details from the exception.

```php
public function getErrorDetails(): array
```

**Returns:** array - 

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
public function dispatchIf($boolean, ...$arguments)
```

**Parameters:**

- `$boolean` (bool): 

**Returns:** mixed - 

---

### `dispatchUnless`

Dispatch the event with the given arguments unless the given truth test passes.

```php
public function dispatchUnless($boolean, ...$arguments)
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
public function restoreModel($value)
```

**Parameters:**

- `$value` (\Illuminate\Contracts\Database\ModelIdentifier): 

**Returns:** \Illuminate\Database\Eloquent\Model - 

---

