# ValidationRulesHelper

**Full Class Name:** `App\TravelClick\Support\ValidationRulesHelper`

**File:** `Support/ValidationRulesHelper.php`

**Type:** Class

## Description

ValidationRulesHelper
Centralized helper class for common validation logic specific to HTNG 2011B.
Provides utility methods for parsing and validating dates, codes, ranges, and business rules.
This class complements BusinessRulesValidator by providing reusable validation methods
that can be used across different parts of the TravelClick integration.

## Constants

### `HTNG_DATE_FORMAT`

HTNG date format for XML messages

**Value:** `'Y-m-d'`

---

### `HTNG_DATETIME_FORMAT`

**Value:** `'Y-m-d\TH:i:s'`

---

### `HTNG_DATETIME_WITH_TZ_FORMAT`

**Value:** `'Y-m-d\TH:i:s.u\Z'`

---

### `MAX_DATE_RANGE_DAYS`

Maximum allowed date range for most operations (in days)

**Value:** `365`

---

### `MAX_FUTURE_BOOKING_YEARS`

Maximum allowed future booking date (in years)

**Value:** `2`

---

### `HOTEL_CODE_PATTERN`

Hotel code pattern (typically 6 digits)

**Value:** `'/^\d{6}$/'`

---

### `ROOM_TYPE_CODE_PATTERN`

Room type code pattern (3-10 alphanumeric characters)

**Value:** `'/^[A-Z0-9]{3,10}$/'`

---

### `RATE_PLAN_CODE_PATTERN`

Rate plan code pattern (3-20 alphanumeric characters with possible hyphens)

**Value:** `'/^[A-Z0-9\-]{3,20}$/'`

---

## Methods

### `validateAndParseHtngDate`

Validate and parse HTNG date string

```php
public function validateAndParseHtngDate(string $dateString, bool $allowNull = false): array
```

**Parameters:**

- `$dateString` (string): Date string to validate
- `$allowNull` (bool): Whether null/empty values are allowed

**Returns:** array{valid: - bool, date: ?Carbon, error: ?string}

---

### `validateDateRange`

Validate date range

```php
public function validateDateRange(string $startDate, string $endDate, array $options = []): array
```

**Parameters:**

- `$startDate` (string): Start date string
- `$endDate` (string): End date string
- `$options` (array): Validation options

**Returns:** array{valid: - bool, start: ?Carbon, end: ?Carbon, errors: array}

---

### `validateHotelCode`

Validate hotel code format

```php
public function validateHotelCode(string $hotelCode): array
```

**Parameters:**

- `$hotelCode` (string): Hotel code to validate

**Returns:** array{valid: - bool, error: ?string}

---

### `validateRoomTypeCode`

Validate room type code format

```php
public function validateRoomTypeCode(string $roomTypeCode): array
```

**Parameters:**

- `$roomTypeCode` (string): Room type code to validate

**Returns:** array{valid: - bool, error: ?string}

---

### `validateRatePlanCode`

Validate rate plan code format

```php
public function validateRatePlanCode(string $ratePlanCode): array
```

**Parameters:**

- `$ratePlanCode` (string): Rate plan code to validate

**Returns:** array{valid: - bool, error: ?string}

---

### `validateCurrencyCode`

Validate currency code (ISO 4217)

```php
public function validateCurrencyCode(string $currencyCode): array
```

**Parameters:**

- `$currencyCode` (string): Currency code to validate

**Returns:** array{valid: - bool, error: ?string}

---

### `validateNumericRange`

Validate numeric range

```php
public function validateNumericRange(int|float $value, int|float $min, int|float $max, string $fieldName = 'Value'): array
```

**Parameters:**

- `$value` (int|float): Value to validate
- `$min` (int|float): Minimum allowed value
- `$max` (int|float): Maximum allowed value
- `$fieldName` (string): Field name for error messages

**Returns:** array{valid: - bool, error: ?string}

---

### `validateCountTypeAndValue`

Validate count type and count value combination

```php
public function validateCountTypeAndValue(CountType $countType, int $count): array
```

**Parameters:**

- `$countType` (CountType): Count type enum
- `$count` (int): Count value

**Returns:** array{valid: - bool, error: ?string}

---

### `validateOccupancy`

Validate guest count and occupancy rules

```php
public function validateOccupancy(int $adults, int $children = 0, int $infants = 0, array $roomTypeRules = []): array
```

**Parameters:**

- `$adults` (int): Number of adults
- `$children` (int): Number of children
- `$infants` (int): Number of infants
- `$roomTypeRules` (array): Room type specific rules

**Returns:** array{valid: - bool, errors: array}

---

### `validateRateAmounts`

Validate rate amounts and guest pricing logic

```php
public function validateRateAmounts(array $guestAmounts): array
```

**Parameters:**

- `$guestAmounts` (array): Array of guest amounts

**Returns:** array{valid: - bool, errors: array}

---

### `validateMessageId`

Validate message ID format (UUID)

```php
public function validateMessageId(string $messageId): array
```

**Parameters:**

- `$messageId` (string): Message ID to validate

**Returns:** array{valid: - bool, error: ?string}

---

### `validateEmail`

Validate email address format

```php
public function validateEmail(string $email, bool $required = true): array
```

**Parameters:**

- `$email` (string): Email address to validate
- `$required` (bool): Whether email is required

**Returns:** array{valid: - bool, error: ?string}

---

### `validatePhone`

Validate phone number format

```php
public function validatePhone(string $phone, bool $required = true): array
```

**Parameters:**

- `$phone` (string): Phone number to validate
- `$required` (bool): Whether phone is required

**Returns:** array{valid: - bool, error: ?string}

---

### `validateBatchSize`

Validate batch size for processing

```php
public function validateBatchSize(int $batchSize, int $maxBatchSize = 1000): array
```

**Parameters:**

- `$batchSize` (int): Batch size to validate
- `$maxBatchSize` (int): Maximum allowed batch size

**Returns:** array{valid: - bool, error: ?string}

---

### `formatDateForHtng`

Format date for HTNG XML

```php
public function formatDateForHtng(Carbon\CarbonInterface $date, bool $includeTime = false): string
```

**Parameters:**

- `$date` (CarbonInterface): Date to format
- `$includeTime` (bool): Whether to include time

**Returns:** string - Formatted date string

---

### `sanitizeForXml`

Sanitize text for XML content

```php
public function sanitizeForXml(string $text, int $maxLength = 255): string
```

**Parameters:**

- `$text` (string): Text to sanitize
- `$maxLength` (int): Maximum allowed length

**Returns:** string - Sanitized text

---

### `createValidationResult`

Create standardized validation result array

```php
public function createValidationResult(bool $valid, array|string|null $errors = null, array|string|null $warnings = null): array
```

**Parameters:**

- `$valid` (bool): Whether validation passed
- `$errors` (string|array|null): Error message(s)
- `$warnings` (string|array|null): Warning message(s)

**Returns:** array{valid: - bool, errors: array, warnings: array}

---

