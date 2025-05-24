# UpdateInventoryJob

**Full Class Name:** `App\TravelClick\Jobs\OutboundJobs\UpdateInventoryJob`

**File:** `Jobs/OutboundJobs/UpdateInventoryJob.php`

**Type:** Class

## Description

Job for sending inventory updates to TravelClick via HTNG 2011B
This job handles sending inventory data to TravelClick using their SOAP API.
It supports both "delta" (incremental changes) and "overlay" (full sync) modes,
as well as property-level and room-level inventory messages.
Features:
- Automatic retry on transient failures with exponential backoff
- Circuit breaker pattern to prevent overwhelming failing services
- Comprehensive logging of all operations
- Support for both calculated and direct inventory methods
- Batch processing to prevent timeouts with large volumes

## Properties

### `$tries`

The number of times the job may be attempted.

**Type:** `int`

---

### `$backoff`

The number of seconds to wait before retrying the job.
This is overridden by our RetryHelper, but is required for Laravel.

**Type:** `int`

---

### `$maxExceptions`

The maximum number of unhandled exceptions to allow before failing.

**Type:** `int`

---

### `$timeout`

The timeout in seconds for the job.

**Type:** `int`

---

### `$failOnTimeout`

Indicate if the job should be marked as failed on timeout.

**Type:** `bool`

---

### `$job`

The underlying queue job instance.

---

### `$connection`

The name of the connection the job should be sent to.

---

### `$queue`

The name of the queue the job should be sent to.

---

### `$delay`

The number of seconds before the job should be made available.

---

### `$afterCommit`

Indicates whether the job should be dispatched after all database transactions have committed.

---

### `$middleware`

The middleware the job should be dispatched through.

---

### `$chained`

The jobs that should run if this job is successful.

---

### `$chainConnection`

The name of the connection the chain should be sent to.

---

### `$chainQueue`

The name of the queue the chain should be sent to.

---

### `$chainCatchCallbacks`

The callbacks to be executed on chain failure.

---

## Methods

### `__construct`

Create a new job instance.

```php
public function __construct(Spatie\LaravelData\DataCollection|App\TravelClick\DTOs\InventoryData $inventoryData, string $hotelCode, bool $isOverlay = false, bool $highPriority = false, int|null $propertyId = null)
```

**Parameters:**

- `$hotelCode` (string): The hotel code in TravelClick
- `$isOverlay` (bool): Whether this is a full overlay (vs. delta update)
- `$highPriority` (bool): Whether this job has high priority
- `$propertyId` (int|null): Optional property ID for logging

---

### `middleware`

Get the middleware the job should pass through.

```php
public function middleware(): array
```

**Returns:** array - 

---

### `handle`

Execute the job.

```php
public function handle(SoapService $soapService, RetryHelper $retryHelper): void
```

**Parameters:**

- `$soapService` (SoapService): 
- `$retryHelper` (RetryHelper): 

**Returns:** void - 

---

### `delta`

Create a job for delta inventory update

```php
public function delta(Spatie\LaravelData\DataCollection|App\TravelClick\DTOs\InventoryData $inventoryData, string $hotelCode, int|null $propertyId = null): self
```

**Parameters:**

- `$hotelCode` (string): 
- `$propertyId` (int|null): 

**Returns:** self - 

---

### `overlay`

Create a job for overlay (full sync) inventory update

```php
public function overlay(Spatie\LaravelData\DataCollection|App\TravelClick\DTOs\InventoryData $inventoryData, string $hotelCode, int|null $propertyId = null, bool $highPriority = true): self
```

**Parameters:**

- `$hotelCode` (string): 
- `$propertyId` (int|null): 
- `$highPriority` (bool): 

**Returns:** self - 

---

### `urgent`

Create an urgent job for critical inventory updates

```php
public function urgent(Spatie\LaravelData\DataCollection|App\TravelClick\DTOs\InventoryData $inventoryData, string $hotelCode, int|null $propertyId = null): self
```

**Parameters:**

- `$hotelCode` (string): 
- `$propertyId` (int|null): 

**Returns:** self - 

---

### `dispatch`

Dispatch the job with the given arguments.

```php
public function dispatch(...$arguments)
```

**Returns:** \Illuminate\Foundation\Bus\PendingDispatch - 

---

### `dispatchIf`

Dispatch the job with the given arguments if the given truth test passes.

```php
public function dispatchIf($boolean, ...$arguments)
```

**Parameters:**

- `$boolean` (bool|\Closure): 

**Returns:** \Illuminate\Foundation\Bus\PendingDispatch|\Illuminate\Support\Fluent - 

---

### `dispatchUnless`

Dispatch the job with the given arguments unless the given truth test passes.

```php
public function dispatchUnless($boolean, ...$arguments)
```

**Parameters:**

- `$boolean` (bool|\Closure): 

**Returns:** \Illuminate\Foundation\Bus\PendingDispatch|\Illuminate\Support\Fluent - 

---

### `dispatchSync`

Dispatch a command to its appropriate handler in the current process.
Queueable jobs will be dispatched to the "sync" queue.

```php
public function dispatchSync(...$arguments)
```

**Returns:** mixed - 

---

### `dispatchAfterResponse`

Dispatch a command to its appropriate handler after the current process.

```php
public function dispatchAfterResponse(...$arguments)
```

**Returns:** mixed - 

---

### `withChain`

Set the jobs that should run if this job is successful.

```php
public function withChain($chain)
```

**Parameters:**

- `$chain` (array): 

**Returns:** \Illuminate\Foundation\Bus\PendingChain - 

---

### `attempts`

Get the number of times the job has been attempted.

```php
public function attempts()
```

**Returns:** int - 

---

### `delete`

Delete the job from the queue.

```php
public function delete()
```

**Returns:** void - 

---

### `fail`

Fail the job from the queue.

```php
public function fail($exception = null)
```

**Parameters:**

- `$exception` (\Throwable|string|null): 

**Returns:** void - 

---

### `release`

Release the job back into the queue after (n) seconds.

```php
public function release($delay = 0)
```

**Parameters:**

- `$delay` (\DateTimeInterface|\DateInterval|int): 

**Returns:** void - 

---

### `withFakeQueueInteractions`

Indicate that queue interactions like fail, delete, and release should be faked.

```php
public function withFakeQueueInteractions()
```

**Returns:** $this - 

---

### `assertDeleted`

Assert that the job was deleted from the queue.

```php
public function assertDeleted()
```

**Returns:** $this - 

---

### `assertNotDeleted`

Assert that the job was not deleted from the queue.

```php
public function assertNotDeleted()
```

**Returns:** $this - 

---

### `assertFailed`

Assert that the job was manually failed.

```php
public function assertFailed()
```

**Returns:** $this - 

---

### `assertFailedWith`

Assert that the job was manually failed with a specific exception.

```php
public function assertFailedWith($exception)
```

**Parameters:**

- `$exception` (\Throwable|string): 

**Returns:** $this - 

---

### `assertNotFailed`

Assert that the job was not manually failed.

```php
public function assertNotFailed()
```

**Returns:** $this - 

---

### `assertReleased`

Assert that the job was released back onto the queue.

```php
public function assertReleased($delay = null)
```

**Parameters:**

- `$delay` (\DateTimeInterface|\DateInterval|int): 

**Returns:** $this - 

---

### `assertNotReleased`

Assert that the job was not released back onto the queue.

```php
public function assertNotReleased()
```

**Returns:** $this - 

---

### `setJob`

Set the base queue job instance.

```php
public function setJob(Illuminate\Contracts\Queue\Job $job)
```

**Parameters:**

- `$job` (\Illuminate\Contracts\Queue\Job): 

**Returns:** $this - 

---

### `onConnection`

Set the desired connection for the job.

```php
public function onConnection($connection)
```

**Parameters:**

- `$connection` (\UnitEnum|string|null): 

**Returns:** $this - 

---

### `onQueue`

Set the desired queue for the job.

```php
public function onQueue($queue)
```

**Parameters:**

- `$queue` (\UnitEnum|string|null): 

**Returns:** $this - 

---

### `allOnConnection`

Set the desired connection for the chain.

```php
public function allOnConnection($connection)
```

**Parameters:**

- `$connection` (\UnitEnum|string|null): 

**Returns:** $this - 

---

### `allOnQueue`

Set the desired queue for the chain.

```php
public function allOnQueue($queue)
```

**Parameters:**

- `$queue` (\UnitEnum|string|null): 

**Returns:** $this - 

---

### `delay`

Set the desired delay in seconds for the job.

```php
public function delay($delay)
```

**Parameters:**

- `$delay` (\DateTimeInterface|\DateInterval|array|int|null): 

**Returns:** $this - 

---

### `withoutDelay`

Set the delay for the job to zero seconds.

```php
public function withoutDelay()
```

**Returns:** $this - 

---

### `afterCommit`

Indicate that the job should be dispatched after all database transactions have committed.

```php
public function afterCommit()
```

**Returns:** $this - 

---

### `beforeCommit`

Indicate that the job should not wait until database transactions have been committed before dispatching.

```php
public function beforeCommit()
```

**Returns:** $this - 

---

### `through`

Specify the middleware the job should be dispatched through.

```php
public function through($middleware)
```

**Parameters:**

- `$middleware` (array|object): 

**Returns:** $this - 

---

### `chain`

Set the jobs that should run if this job is successful.

```php
public function chain($chain)
```

**Parameters:**

- `$chain` (array): 

**Returns:** $this - 

---

### `prependToChain`

Prepend a job to the current chain so that it is run after the currently running job.

```php
public function prependToChain($job)
```

**Parameters:**

- `$job` (mixed): 

**Returns:** $this - 

---

### `appendToChain`

Append a job to the end of the current chain.

```php
public function appendToChain($job)
```

**Parameters:**

- `$job` (mixed): 

**Returns:** $this - 

---

### `dispatchNextJobInChain`

Dispatch the next job on the chain.

```php
public function dispatchNextJobInChain()
```

**Returns:** void - 

---

### `invokeChainCatchCallbacks`

Invoke all of the chain's failed job callbacks.

```php
public function invokeChainCatchCallbacks($e)
```

**Parameters:**

- `$e` (\Throwable): 

**Returns:** void - 

---

### `assertHasChain`

Assert that the job has the given chain of jobs attached to it.

```php
public function assertHasChain($expectedChain)
```

**Parameters:**

- `$expectedChain` (array): 

**Returns:** void - 

---

### `assertDoesntHaveChain`

Assert that the job has no remaining chained jobs.

```php
public function assertDoesntHaveChain()
```

**Returns:** void - 

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

