# TravelClickConnectionException

**Full Class Name:** `App\TravelClick\Exceptions\TravelClickConnectionException`

**File:** `Exceptions/TravelClickConnectionException.php`

**Type:** Class

## Description

Exception thrown when there are connection issues with TravelClick
This exception is thrown specifically for network-related issues,
timeouts, and other connection problems with the TravelClick service.

## Methods

### `__construct`

```php
public function __construct(string $message, string $messageId = null, int $timeoutSeconds = null, string $endpoint = null, Throwable|null $previous = null)
```

---

### `timeout`

Create exception for connection timeout

```php
public function timeout(int $timeoutSeconds, string $endpoint, string $messageId = null): self
```

---

### `unreachable`

Create exception for network unreachable

```php
public function unreachable(string $endpoint, string $messageId = null, string $details = null): self
```

---

### `sslError`

Create exception for SSL/TLS issues

```php
public function sslError(string $sslError, string $endpoint, string $messageId = null): self
```

---

### `isRetryable`

Check if error is retryable

```php
public function isRetryable(): bool
```

---

### `getSuggestedRetryDelay`

Get suggested retry delay in seconds

```php
public function getSuggestedRetryDelay(): int
```

---

### `getTroubleshootingSteps`

Get troubleshooting suggestions

```php
public function getTroubleshootingSteps(): array
```

---

