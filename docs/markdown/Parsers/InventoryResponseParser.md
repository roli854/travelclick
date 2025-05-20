# InventoryResponseParser

**Full Class Name:** `App\TravelClick\Parsers\InventoryResponseParser`

**File:** `Parsers/InventoryResponseParser.php`

**Type:** Class

## Description

Parser for TravelClick Inventory Response Messages
This class extends the base SOAP parser to handle the specific structure
of inventory response messages (OTA_HotelInvCountNotifRS).
It extracts inventory counts, date ranges, and room type information.

## Methods

### `parse`

Parse a SOAP response into a structured InventoryResponseDto

```php
public function parse(string $messageId, string $rawResponse, float $durationMs = null, array $headers = []): App\TravelClick\DTOs\SoapResponseDto
```

**Parameters:**

- `$messageId` (string): The unique message identifier for tracking
- `$rawResponse` (string): The raw XML response from TravelClick
- `$durationMs` (?float): The time taken to receive the response in milliseconds
- `$headers` (array): Optional SOAP headers from the response

**Returns:** InventoryResponseDto - The parsed inventory response data

---

