# SoapResponseDto

**Full Class Name:** `App\TravelClick\DTOs\SoapResponseDto`

**File:** `DTOs/SoapResponseDto.php`

**Type:** Class

## Description

Data Transfer Object for SOAP Response
This DTO encapsulates the response received from TravelClick SOAP calls.
It provides structured access to response data and metadata.

## Methods

### `__construct`

```php
public function __construct(string $messageId, bool $isSuccess, string $rawResponse, string $errorMessage = null, string $errorCode = null, array $warnings = null, Carbon\Carbon|null $timestamp = null, string $echoToken = null, array $headers = null, float $durationMs = null)
```

---

### `success`

Create a successful response DTO

```php
public function success(string $messageId, string $rawResponse, string $echoToken = null, array $headers = null, float $durationMs = null): self
```

---

### `failure`

Create a failed response DTO

```php
public function failure(string $messageId, string $rawResponse, string $errorMessage, string $errorCode = null, array $warnings = null, float $durationMs = null): self
```

---

### `fromSoapFault`

Create response from SoapFault

```php
public function fromSoapFault(string $messageId, SoapFault $fault, float $durationMs = null): self
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

