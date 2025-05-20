# CircuitBreaker

**Full Class Name:** `App\TravelClick\Support\CircuitBreaker`

**File:** `Support/CircuitBreaker.php`

**Type:** Class

## Description

Class CircuitBreaker
Implements the Circuit Breaker pattern to prevent operations from being executed
when a service is experiencing issues. This helps prevent cascading failures
and gives the external service time to recover.
The circuit breaker has three states:
- Closed: Operations are executed normally
- Open: Operations are prevented from executing
- Half-open: A test operation is allowed to determine if the service has recovered

## Methods

### `__construct`

Constructor.

```php
public function __construct(string $service, int $threshold = 5, int $resetTimeoutSeconds = 60)
```

**Parameters:**

- `$service` (string): Unique identifier for the service/operation
- `$threshold` (int): Number of failures before opening the circuit
- `$resetTimeoutSeconds` (int): Time in seconds before attempting to reset the circuit

---

### `allowRequest`

Check if a request should be allowed based on the circuit state.

```php
public function allowRequest(): bool
```

**Returns:** bool - True if the request is allowed, false otherwise

---

### `recordSuccess`

Record a successful operation.

```php
public function recordSuccess(): void
```

**Returns:** void - 

---

### `recordFailure`

Record a failed operation.

```php
public function recordFailure(): void
```

**Returns:** void - 

---

### `isOpen`

Check if the circuit is currently open.

```php
public function isOpen(): bool
```

**Returns:** bool - True if the circuit is open, false otherwise

---

### `reset`

Manually reset the circuit state to closed.

```php
public function reset(): void
```

**Returns:** void - 

---

