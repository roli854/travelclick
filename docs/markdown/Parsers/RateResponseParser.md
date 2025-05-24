# RateResponseParser

**Full Class Name:** `App\TravelClick\Parsers\RateResponseParser`

**File:** `Parsers/RateResponseParser.php`

**Type:** Class

## Description

Parser specialized in handling and interpreting TravelClick rate responses.
This parser extends the base SoapResponseParser to provide rate-specific
parsing functionality, extracting detailed information about rate plans,
room types, pricing, and linked rates.
It handles all rate operation types defined in the RateOperationType enum:
- Rate Update (mandatory)
- Rate Creation (optional)
- Inactive Rate (optional)
- Remove Room Types (optional)
- Full Sync (special operation)
- Delta Update (recommended for routine updates)

## Methods

### `parse`

Parse a SOAP response related to rate operations
This method extends the base parser functionality to extract rate-specific
information from the response.

```php
public function parse(string $messageId, string $rawResponse, float|null $durationMs = null, array $headers = []): SoapResponseDto
```

**Parameters:**

- `$messageId` (string): The unique message identifier for tracking
- `$rawResponse` (string): The raw XML response from TravelClick
- `$durationMs` (?float): The time taken to receive the response in milliseconds
- `$headers` (array): Optional SOAP headers from the response

**Returns:** SoapResponseDto - The parsed response data enriched with rate information

---

