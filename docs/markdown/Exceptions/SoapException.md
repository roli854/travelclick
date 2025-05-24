# SoapException

**Full Class Name:** `App\TravelClick\Exceptions\SoapException`

**File:** `Exceptions/SoapException.php`

**Type:** Class

## Description

Base exception for all SOAP-related errors in TravelClick integration
This exception provides a base for all SOAP communication errors,
with additional context for debugging and monitoring.

## Properties

### `$messageId`

**Type:** `string|null`

---

### `$soapFaultCode`

**Type:** `string|null`

---

### `$soapFaultString`

**Type:** `string|null`

---

### `$context`

**Type:** `array|null`

---

## Methods

### `__construct`

```php
public function __construct(string $message, string|null $messageId = null, string|null $soapFaultCode = null, string|null $soapFaultString = null, array|null $context = null, int $code = 0, Throwable|null $previous = null)
```

---

### `fromSoapFault`

Create exception from SoapFault

```php
public function fromSoapFault(SoapFault $fault, string|null $messageId = null, array|null $context = null): self
```

---

### `getContext`

Get exception context for logging

```php
public function getContext(): array
```

---

### `isFaultCode`

Check if this is a specific type of SOAP fault

```php
public function isFaultCode(string $code): bool
```

---

### `isConnectionError`

Check if this is a connection-related error

```php
public function isConnectionError(): bool
```

---

### `isAuthenticationError`

Check if this is an authentication error

```php
public function isAuthenticationError(): bool
```

---

