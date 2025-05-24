# TravelClickAuthenticationException

**Full Class Name:** `App\TravelClick\Exceptions\TravelClickAuthenticationException`

**File:** `Exceptions/TravelClickAuthenticationException.php`

**Type:** Class

## Description

Exception thrown when authentication with TravelClick fails
This exception is thrown specifically for authentication issues,
including invalid credentials, expired tokens, or authorization failures.

## Properties

### `$username`

**Type:** `string|null`

---

### `$authenticationMethod`

**Type:** `string|null`

---

### `$faultDetail`

**Type:** `string|null`

---

## Methods

### `__construct`

```php
public function __construct(string $message, string|null $messageId = null, string|null $username = null, string|null $authenticationMethod = 'WSSE', string|null $faultDetail = null, Throwable|null $previous = null)
```

---

### `invalidCredentials`

Create exception for invalid credentials

```php
public function invalidCredentials(string $username, string|null $messageId = null, string|null $details = null): self
```

---

### `expiredCredentials`

Create exception for expired credentials

```php
public function expiredCredentials(string $username, string|null $messageId = null): self
```

---

### `insufficientPermissions`

Create exception for insufficient permissions

```php
public function insufficientPermissions(string $username, string $requiredPermission, string|null $messageId = null): self
```

---

### `serviceUnavailable`

Create exception for authentication service unavailable

```php
public function serviceUnavailable(string|null $messageId = null, string|null $details = null): self
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

Get troubleshooting steps specific to authentication

```php
public function getTroubleshootingSteps(): array
```

---

### `getCredentialValidationSteps`

Get credential validation recommendations

```php
public function getCredentialValidationSteps(): array
```

---

### `getSecurityNote`

Security note for logging

```php
public function getSecurityNote(): string
```

---

