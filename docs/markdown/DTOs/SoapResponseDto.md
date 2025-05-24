# SoapResponseDto

**Full Class Name:** `App\TravelClick\DTOs\SoapResponseDto`

**File:** `DTOs/SoapResponseDto.php`

**Type:** Class

## Description

Data Transfer Object for SOAP Response
This DTO encapsulates the response received from TravelClick SOAP calls.
It provides structured access to response data and metadata.

## Properties

### `$messageId`

**Type:** `string`

---

### `$isSuccess`

**Type:** `bool`

---

### `$rawResponse`

**Type:** `string`

---

### `$errorMessage`

**Type:** `string|null`

---

### `$errorCode`

**Type:** `string|null`

---

### `$warnings`

**Type:** `array|null`

---

### `$timestamp`

**Type:** `Carbon\Carbon|null`

---

### `$echoToken`

**Type:** `string|null`

---

### `$headers`

**Type:** `array|null`

---

### `$durationMs`

**Type:** `float|null`

---

## Methods

### `__construct`

```php
public function __construct(string $messageId, bool $isSuccess, string $rawResponse, string|null $errorMessage = null, string|null $errorCode = null, array|null $warnings = null, Carbon\Carbon|null $timestamp = null, string|null $echoToken = null, array|null $headers = null, float|null $durationMs = null)
```

---

### `success`

Create a successful response DTO

```php
public function success(string $messageId, string $rawResponse, string|null $echoToken = null, array|null $headers = null, float|null $durationMs = null): self
```

---

### `failure`

Create a failed response DTO

```php
public function failure(string $messageId, string $rawResponse, string $errorMessage, string|null $errorCode = null, array|null $warnings = null, float|null $durationMs = null): self
```

---

### `fromSoapFault`

Create response from SoapFault

```php
public function fromSoapFault(string $messageId, SoapFault $fault, float|null $durationMs = null): self
```

---

### `hasWarnings`

Check if response contains warnings

```php
public function hasWarnings(): bool
```

---

### `getWarningsAsString`

Get warnings as a formatted string

```php
public function getWarningsAsString(): string
```

---

### `getFormattedDuration`

Get formatted duration for logging

```php
public function getFormattedDuration(): string
```

---

### `toArray`

Convert DTO to array for logging purposes

```php
public function toArray(): array
```

---

### `getLogContext`

Get log context for detailed logging

```php
public function getLogContext(): array
```

---

