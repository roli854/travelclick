# ErrorResponseParser

**Full Class Name:** `App\TravelClick\Parsers\ErrorResponseParser`

**File:** `Parsers/ErrorResponseParser.php`

**Type:** Class

## Description

Specialized parser for error responses from TravelClick SOAP API
This class extends the base SoapResponseParser to provide more detailed
error analysis, categorization, and structured information for debugging.
It specializes in extracting error codes, messages, and mapping them to
appropriate error types for better error handling and recovery.

## Methods

### `parseError`

Parse an error response from TravelClick

```php
public function parseError(string $messageId, string $rawResponse, float|null $durationMs = null): SoapResponseDto
```

**Parameters:**

- `$messageId` (string): The unique message identifier for tracking
- `$rawResponse` (string): The raw XML response from TravelClick
- `$durationMs` (?float): The time taken to receive the response

**Returns:** SoapResponseDto - The parsed response

---

### `parseFromFault`

Parse a SoapFault exception with enhanced error details

```php
public function parseFromFault(string $messageId, SoapFault $fault, float|null $durationMs = null): SoapResponseDto
```

**Parameters:**

- `$messageId` (string): The unique message identifier for tracking
- `$fault` (SoapFault): The SOAP fault exception
- `$durationMs` (?float): The time taken before the fault occurred

**Returns:** SoapResponseDto - Enhanced error response

---

### `categorizeError`

Categorize an error based on its code and message

```php
public function categorizeError(string $errorCode, string $errorMessage): ErrorType
```

**Parameters:**

- `$errorCode` (string): The error code
- `$errorMessage` (string): The error message

**Returns:** ErrorType - The categorized error type

---

### `categorizeFromException`

Categorize an error from an exception

```php
public function categorizeFromException(Throwable $exception): ErrorType
```

**Parameters:**

- `$exception` (Throwable): The exception to categorize

**Returns:** ErrorType - The categorized error type

---

### `getHtngErrorDescription`

Map HTNG error code to human readable description

```php
public function getHtngErrorDescription(string $errorCode): string
```

**Parameters:**

- `$errorCode` (string): The HTNG error code

**Returns:** string - The description or the original code if not found

---

### `isRetryableError`

Check if an error is retryable based on its type

```php
public function isRetryableError(string $errorCode, string $errorMessage): bool
```

**Parameters:**

- `$errorCode` (string): The error code
- `$errorMessage` (string): The error message

**Returns:** bool - True if the error is retryable

---

### `getRetryDelay`

Get recommended retry delay for an error in seconds

```php
public function getRetryDelay(string $errorCode, string $errorMessage): int
```

**Parameters:**

- `$errorCode` (string): The error code
- `$errorMessage` (string): The error message

**Returns:** int - The recommended delay in seconds

---

