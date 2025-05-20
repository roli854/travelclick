# TravelClickHelper

**Full Class Name:** `App\TravelClick\Support\TravelClickHelper`

**File:** `Support/TravelClickHelper.php`

**Type:** Class

## Description

TravelClick Helper
Provides utility functions for the TravelClick integration.
This class contains common helper methods that are used across
different parts of the TravelClick integration.

## Methods

### `isValidHotelCode`

Validates a hotel code according to TravelClick requirements.
Hotel codes must follow specific patterns defined in the config,
typically alphanumeric with specific length constraints.

```php
public function isValidHotelCode(string $hotelCode): bool
```

**Parameters:**

- `$hotelCode` (string): The hotel code to validate

**Returns:** bool - True if the hotel code is valid, false otherwise

---

### `formatDateForHtng`

Format a date to HTNG format (YYYY-MM-DD).

```php
public function formatDateForHtng(Carbon\Carbon|string $date): string
```

**Parameters:**

- `$date` (Carbon|string): The date to format

**Returns:** string - The formatted date string

---

### `formatDateTimeForHtng`

Format a datetime to HTNG format (YYYY-MM-DDThh:mm:ss).

```php
public function formatDateTimeForHtng(Carbon\Carbon|string $dateTime): string
```

**Parameters:**

- `$dateTime` (Carbon|string): The datetime to format

**Returns:** string - The formatted datetime string

---

### `parseDateFromHtng`

Parse a date from HTNG format to a Carbon instance.

```php
public function parseDateFromHtng(string $htngDate): Carbon\Carbon
```

**Parameters:**

- `$htngDate` (string): The date in HTNG format (YYYY-MM-DD)

**Returns:** Carbon - The parsed Carbon instance

---

### `parseDateTimeFromHtng`

Parse a datetime from HTNG format to a Carbon instance.

```php
public function parseDateTimeFromHtng(string $htngDateTime): Carbon\Carbon
```

**Parameters:**

- `$htngDateTime` (string): The datetime in HTNG format (YYYY-MM-DDThh:mm:ss)

**Returns:** Carbon - The parsed Carbon instance

---

### `mapSourceOfBusiness`

Map a source of business to the corresponding TravelClick code.
TravelClick expects specific codes for different booking sources,
this function maps our internal codes to their expected values.

```php
public function mapSourceOfBusiness(string $source): string
```

**Parameters:**

- `$source` (string): The internal source of business

**Returns:** string|null - The mapped TravelClick code, or null if not found

---

### `getAllSourceOfBusinessMappings`

Get all available source of business mappings.

```php
public function getAllSourceOfBusinessMappings(): array
```

**Returns:** array - Array of source of business mappings

---

### `generateWsseHeaders`

Generate WSSE headers for TravelClick SOAP authentication.
WSSE (Web Services Security) is used by TravelClick for secure
SOAP message exchange. This implements WSSE Username Token Profile.

```php
public function generateWsseHeaders(string $username = null, string $password = null): array
```

**Parameters:**

- `$username` (string|null): Optional username (defaults to config)
- `$password` (string|null): Optional password (defaults to config)

**Returns:** array - The WSSE headers array

---

### `getRequestTimestamp`

Generate a timestamp for TravelClick requests.

```php
public function getRequestTimestamp(): string
```

**Returns:** string - The timestamp in HTNG format

---

### `generateEchoToken`

Generate a unique echo token for message tracing.
Echo tokens are used to track messages through the system.
They should be unique for each message sent.

```php
public function generateEchoToken(): string
```

**Returns:** string - The generated echo token

---

### `getActiveEndpoint`

Get the active TravelClick endpoint based on environment.

```php
public function getActiveEndpoint(): string
```

**Returns:** string - The active endpoint URL

---

### `getWsdlUrl`

Get the WSDL URL for SOAP operations.

```php
public function getWsdlUrl(): string
```

**Returns:** string - The WSDL URL

---

### `normalizeRoomTypeCode`

Normalize a room type code to TravelClick format.

```php
public function normalizeRoomTypeCode(string $roomTypeCode): string
```

**Parameters:**

- `$roomTypeCode` (string): The room type code to normalize

**Returns:** string - The normalized room type code

---

### `normalizeRatePlanCode`

Normalize a rate plan code to TravelClick format.

```php
public function normalizeRatePlanCode(string $ratePlanCode): string
```

**Parameters:**

- `$ratePlanCode` (string): The rate plan code to normalize

**Returns:** string - The normalized rate plan code

---

### `getXmlElementName`

Convert a MessageType enum to its XML element name.

```php
public function getXmlElementName(App\TravelClick\Enums\MessageType $messageType): string
```

**Parameters:**

- `$messageType` (MessageType): The message type

**Returns:** string - The XML element name

---

### `getXmlDeclaration`

Build the XML version and encoding declaration.

```php
public function getXmlDeclaration(): string
```

**Returns:** string - The XML declaration

---

