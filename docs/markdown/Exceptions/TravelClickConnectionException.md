# TravelClickConnectionException

**Full Class Name:** `App\TravelClick\Exceptions\TravelClickConnectionException`

**File:** `Exceptions/TravelClickConnectionException.php`

**Type:** Class

## Description

Exception thrown when there are connection issues with TravelClick
This exception is thrown specifically for network-related issues,
timeouts, and other connection problems with the TravelClick service.

## Properties

### `$timeoutSeconds`

**Type:** `int|null`

---

### `$endpoint`

**Type:** `string|null`

---

## Methods

### `__construct`

```php
public function __construct(string $message, string|null $messageId = null, int|null $timeoutSeconds = null, string|null $endpoint = null, Throwable|null $previous = null)
```

---

### `timeout`

Create exception for connection timeout

```php
public function timeout(int $timeoutSeconds, string $endpoint, string|null $messageId = null): self
```

---

### `unreachable`

Create exception for network unreachable

```php
public function unreachable(string $endpoint, string|null $messageId = null, string|null $details = null): self
```

---

### `sslError`

Create exception for SSL/TLS issues

```php
public function sslError(string $sslError, string $endpoint, string|null $messageId = null): self
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

