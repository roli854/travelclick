# InventoryResponseDto

**Full Class Name:** `App\TravelClick\DTOs\InventoryResponseDto`

**File:** `DTOs/InventoryResponseDto.php`

**Type:** Class

## Description

Data Transfer Object for TravelClick Inventory Response
This DTO extends the base SOAP response to include inventory-specific
information such as processed counts, room types, and date ranges.

## Methods

### `__construct`

Constructor for inventory response DTO

```php
public function __construct(string $messageId, bool $isSuccess, string $rawResponse, array $processedCounts = null, string $hotelCode = null, array $roomTypes = null, Carbon\Carbon|null $startDate = null, Carbon\Carbon|null $endDate = null, string $errorMessage = null, string $errorCode = null, array $warnings = null, Carbon\Carbon|null $timestamp = null, string $echoToken = null, array $headers = null, float $durationMs = null)
```

---

### `success`

Create a successful inventory response DTO

```php
public function success(string $messageId, string $rawResponse, string $echoToken = null, array $headers = null, float $durationMs = null, array $processedCounts = [], string $hotelCode = null, array $roomTypes = [], Carbon\Carbon|null $startDate = null, Carbon\Carbon|null $endDate = null, array $warnings = null): self
```

**Parameters:**

- `$messageId` (string): The original message ID for tracking
- `$rawResponse` (string): The raw XML response
- `$processedCounts` (array): The inventory counts that were processed
- `$hotelCode` (string|null): The hotel code in the response
- `$roomTypes` (array): The room types processed in the response
- `$startDate` (Carbon|null): The start date for the inventory update
- `$endDate` (Carbon|null): The end date for the inventory update
- `$echoToken` (string|null): The echo token from the response
- `$headers` (array|null): Any SOAP headers in the response
- `$durationMs` (float|null): The duration of the request in milliseconds
- `$warnings` (array|null): Any warnings in the response

**Returns:** static - 

---

### `failure`

Create a failed inventory response DTO

```php
public function failure(string $messageId, string $rawResponse, string $errorMessage, string $errorCode = null, array $warnings = null, float $durationMs = null): self
```

**Parameters:**

- `$messageId` (string): The original message ID for tracking
- `$rawResponse` (string): The raw XML response
- `$errorMessage` (string): The error message
- `$errorCode` (string|null): The error code
- `$warnings` (array|null): Any warnings in the response
- `$durationMs` (float|null): The duration of the request in milliseconds

**Returns:** static - 

---

### `getCountValue`

Get the processed counts for a specific count type

```php
public function getCountValue(App\TravelClick\Enums\CountType $countType, string $roomType = null): int
```

**Parameters:**

- `$countType` (CountType): The count type to retrieve
- `$roomType` (string|null): Optional room type filter

**Returns:** int|null - The count value or null if not present

---

### `getRoomTypes`

Get all processed room types

```php
public function getRoomTypes(): array
```

**Returns:** array - The room types processed in this response

---

### `getDateRange`

Get the date range for this inventory update

```php
public function getDateRange(): array
```

**Returns:** array{start: - ?Carbon, end: ?Carbon} The date range

---

### `getProcessedCounts`

Get all processed counts

```php
public function getProcessedCounts(): array
```

**Returns:** array - The processed counts data

---

### `hasRoomType`

Check if a specific room type was processed

```php
public function hasRoomType(string $roomType): bool
```

**Parameters:**

- `$roomType` (string): The room type code to check

**Returns:** bool - True if the room type was processed

---

### `getHotelCode`

Get the hotel code

```php
public function getHotelCode(): string
```

**Returns:** string|null - The hotel code

---

### `toArray`

Convert DTO to array for logging purposes
Extends parent method to include inventory-specific data

```php
public function toArray(): array
```

---

