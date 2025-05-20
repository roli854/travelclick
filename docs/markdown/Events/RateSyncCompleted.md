# RateSyncCompleted

**Full Class Name:** `App\TravelClick\Events\RateSyncCompleted`

**File:** `Events/RateSyncCompleted.php`

**Type:** Class

## Methods

### `__construct`

Create a new event instance.

```php
public function __construct(string $hotelCode, App\TravelClick\Enums\RateOperationType $operationType, int $ratesProcessed, float $durationMs, string $trackingId = null)
```

**Parameters:**

- `$hotelCode` (string): 
- `$operationType` (RateOperationType): 
- `$ratesProcessed` (int): 
- `$durationMs` (float): 
- `$trackingId` (string|null): 

**Returns:** void - 

---

### `broadcastAs`

Get the event name.

```php
public function broadcastAs(): string
```

**Returns:** string - 

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

