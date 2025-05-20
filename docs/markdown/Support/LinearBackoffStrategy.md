# LinearBackoffStrategy

**Full Class Name:** `App\TravelClick\Support\LinearBackoffStrategy`

**File:** `Support/LinearBackoffStrategy.php`

**Type:** Class

## Description

Class LinearBackoffStrategy
Implements a linear backoff strategy where each retry waits a fixed increment
longer than the previous one, up to a maximum delay value.
This provides a more predictable retry pattern that may be suitable for
some types of TravelClick operations.

## Methods

### `__construct`

Constructor.

```php
public function __construct(int $maxAttempts = 3, int $initialDelay = 10, int $increment = 20, int $maxDelay = 300, array $retryableExceptions = [])
```

**Parameters:**

- `$maxAttempts` (int): Maximum number of attempts
- `$initialDelay` (int): Initial delay in seconds
- `$increment` (int): Increment in seconds for each retry
- `$maxDelay` (int): Maximum delay in seconds
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

