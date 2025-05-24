# ProcessReservationCancellationJob

**Full Class Name:** `App\TravelClick\Jobs\InboundJobs\ProcessReservationCancellationJob`

**File:** `Jobs/InboundJobs/ProcessReservationCancellationJob.php`

**Type:** Class

## Description

Job to process reservation cancellations received from TravelClick
This job handles the entire cancellation workflow:
1. Validates the cancellation data
2. Updates the booking status in Centrium
3. Releases inventory back to available pool
4. Applies any cancellation policies/fees
5. Sends cancellation confirmations
6. Records the transaction in history

## Properties

### `$tries`

The number of times the job may be attempted.

---

### `$backoff`

The number of seconds to wait before retrying the job.

---

### `$deleteWhenMissingModels`

Delete the job if its models no longer exist.

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
public function __construct(ReservationDataDto $reservationData)
```

**Parameters:**

- `$reservationData` (ReservationDataDto): The reservation data containing cancellation information

**Returns:** void - 

---

### `handle`

Execute the job.

```php
public function handle(ReservationService $reservationService): void
```

**Parameters:**

- `$reservationService` (ReservationService): The service handling reservation operations

**Returns:** void - 

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

