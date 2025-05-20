# RetryHelper

**Full Class Name:** `App\TravelClick\Support\RetryHelper`

**File:** `Support/RetryHelper.php`

**Type:** Class

## Description

Class RetryHelper
A helper class that manages retrying operations with configurable retry strategies
and circuit breaker pattern implementation.
This class orchestrates the retry process, using strategies to determine backoff
times and circuit breakers to prevent overwhelming failing services.

## Methods

### `__construct`

Constructor.

```php
public function __construct(array $config = [])
```

**Parameters:**

- `$config` (array): Optional configuration override (for testing)

---

### `registerStrategy`

Register a retry strategy for a specific operation type.

```php
public function registerStrategy(string $operationType, App\TravelClick\Support\Contracts\RetryStrategyInterface $strategy): self
```

**Parameters:**

- `$operationType` (string): The operation type (e.g., 'inventory', 'rates', 'reservations')
- `$strategy` (RetryStrategyInterface): The retry strategy to use

**Returns:** self - 

---

### `executeWithRetry`

Execute an operation with retry logic.

```php
public function executeWithRetry(callable $operation, string $operationType, string $serviceIdentifier = null): mixed
```

**Parameters:**

- `$operation` (callable): The operation to execute
- `$operationType` (string): The type of operation (for strategy selection)
- `$serviceIdentifier` (string|null): Optional service identifier (for circuit breaker)

**Returns:** mixed - The result of the operation

---

### `getStrategyForOperationType`

Get the retry strategy for a specific operation type.

```php
public function getStrategyForOperationType(string $operationType): App\TravelClick\Support\Contracts\RetryStrategyInterface
```

**Parameters:**

- `$operationType` (string): The operation type

**Returns:** RetryStrategyInterface - The retry strategy

---

