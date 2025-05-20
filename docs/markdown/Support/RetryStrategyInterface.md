# RetryStrategyInterface

**Full Class Name:** `App\TravelClick\Support\Contracts\RetryStrategyInterface`

**File:** `Support/Contracts/RetryStrategyInterface.php`

**Type:** Interface

## Description

Interface RetryStrategyInterface
This interface defines the contract for retry strategies in the TravelClick integration.
Different retry strategies (exponential backoff, linear backoff, etc.) will implement this
interface to provide consistent retry behavior while allowing strategy-specific calculations.

## Methods

### `calculateDelay`

Calculate delay in seconds before the next retry attempt.

```php
public function calculateDelay(int $attemptNumber): int
```

**Parameters:**

- `$attemptNumber` (int): The current attempt number (1-based index)

**Returns:** int - The delay in seconds before the next retry

---

### `shouldRetry`

Determine if a specific exception should be retried.
This allows strategies to decide which exceptions are transient and worth retrying.

```php
public function shouldRetry(Throwable $exception): bool
```

**Parameters:**

- `$exception` (\Throwable): The exception that was thrown

**Returns:** bool - True if the operation should be retried, false otherwise

---

### `getMaxAttempts`

Get the maximum number of retry attempts.

```php
public function getMaxAttempts(): int
```

**Returns:** int - The maximum number of retry attempts

---

