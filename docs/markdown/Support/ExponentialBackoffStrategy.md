# ExponentialBackoffStrategy

**Full Class Name:** `App\TravelClick\Support\ExponentialBackoffStrategy`

**File:** `Support/ExponentialBackoffStrategy.php`

**Type:** Class

## Description

Class ExponentialBackoffStrategy
Implements an exponential backoff strategy where each retry waits exponentially
longer than the previous one, up to a maximum delay value.
This helps prevent overwhelming the TravelClick services during temporary issues
while still retrying operations in a controlled manner.

## Methods

### `__construct`

Constructor.

```php
public function __construct(int $maxAttempts = 3, int $initialDelay = 10, int $maxDelay = 300, float $multiplier = 2.0, array $retryableExceptions = [])
```

**Parameters:**

- `$maxAttempts` (int): Maximum number of attempts
- `$initialDelay` (int): Initial delay in seconds
- `$maxDelay` (int): Maximum delay in seconds
- `$multiplier` (float): Multiplier for each subsequent delay calculation
- `$retryableExceptions` (array<class-string<\Throwable>>): List of exception classes that are retryable

---

### `calculateDelay`

{@inheritdoc}

```php
public function calculateDelay(int $attemptNumber): int
```

---

### `shouldRetry`

{@inheritdoc}

```php
public function shouldRetry(Throwable $exception): bool
```

---

### `getMaxAttempts`

{@inheritdoc}

```php
public function getMaxAttempts(): int
```

---

