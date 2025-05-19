# SoapLogger

**Full Class Name:** `App\TravelClick\Support\SoapLogger`

**File:** `Support/SoapLogger.php`

**Type:** Class

## Description

SoapLogger - Comprehensive logging for TravelClick SOAP operations
This class provides detailed logging for all SOAP operations with TravelClick,
including performance metrics, error handling, and audit trails.
Features:
- Detailed request/response logging
- Performance timing measurement
- Error classification and handling
- Integration with Laravel logging
- Configurable log levels for debugging
- XML storage with size optimization

## Methods

### `__construct`

Constructor - Initialize SOAP logger with configuration

```php
public function __construct(array $config = [])
```

**Parameters:**

- `$config` (array): Optional configuration override

---

### `create`

Create logger instance from Laravel configuration

```php
public function create(): self
```

**Returns:** self - 

---

### `logRequestStart`

Log the start of a SOAP operation

```php
public function logRequestStart(App\TravelClick\DTOs\SoapRequestDto $request, int $propertyId = null, string $jobId = null): App\TravelClick\Models\TravelClickLog
```

**Parameters:**

- `$request` (SoapRequestDto): SOAP request DTO
- `$propertyId` (int|null): Property ID for the operation
- `$jobId` (string|null): Associated job ID

**Returns:** TravelClickLog - Created log entry

---

### `logResponseSuccess`

Log successful SOAP response

```php
public function logResponseSuccess(App\TravelClick\Models\TravelClickLog $log, App\TravelClick\DTOs\SoapResponseDto $response): App\TravelClick\Models\TravelClickLog
```

**Parameters:**

- `$log` (TravelClickLog): Existing log entry
- `$response` (SoapResponseDto): SOAP response DTO

**Returns:** TravelClickLog - Updated log entry

---

### `logResponseFailure`

Log failed SOAP response or error

```php
public function logResponseFailure(App\TravelClick\Models\TravelClickLog $log, App\TravelClick\DTOs\SoapResponseDto|null $response = null, Throwable|null $exception = null): App\TravelClick\Models\TravelClickLog
```

**Parameters:**

- `$log` (TravelClickLog): Existing log entry
- `$response` (SoapResponseDto|null): SOAP response DTO (if available)
- `$exception` (Throwable|null): Exception that occurred

**Returns:** TravelClickLog - Updated log entry

---

### `logWarnings`

Log warnings from TravelClick response

```php
public function logWarnings(App\TravelClick\Models\TravelClickLog $log, array $warnings): void
```

**Parameters:**

- `$log` (TravelClickLog): Log entry
- `$warnings` (array): Array of warning messages

---

### `logPerformanceMetrics`

Log operation performance metrics

```php
public function logPerformanceMetrics(App\TravelClick\Models\TravelClickLog $log, array $metrics = []): void
```

**Parameters:**

- `$log` (TravelClickLog): Log entry
- `$metrics` (array): Additional performance metrics

---

### `logDebug`

Log debug information (only in debug mode)

```php
public function logDebug(string $message, array $context = [], string $messageId = null): void
```

**Parameters:**

- `$message` (string): Debug message
- `$context` (array): Additional context
- `$messageId` (string|null): Associated message ID

---

### `getPerformanceStats`

Get performance statistics for recent operations

```php
public function getPerformanceStats(int $minutes = 60): array
```

**Parameters:**

- `$minutes` (int): Number of minutes to look back

**Returns:** array - Performance statistics

---

### `cleanupLogs`

Clean up old log entries for maintenance

```php
public function cleanupLogs(int $daysToKeep = 30): array
```

**Parameters:**

- `$daysToKeep` (int): Number of days to keep logs

**Returns:** array - Cleanup results

---

### `generateOperationReport`

Generate comprehensive operation report

```php
public function generateOperationReport(string $messageId): array
```

**Parameters:**

- `$messageId` (string): Message ID to report on

**Returns:** array - Operation report

---

### `forOperation`

Create a scoped logger for a specific operation

```php
public function forOperation(string $operationType, string $messageId): self
```

**Parameters:**

- `$operationType` (string): Type of operation
- `$messageId` (string): Message ID

**Returns:** SoapLogger - New logger instance with operation context

---

