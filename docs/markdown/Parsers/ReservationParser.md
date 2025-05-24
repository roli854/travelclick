# ReservationParser

**Full Class Name:** `App\TravelClick\Parsers\ReservationParser`

**File:** `Parsers/ReservationParser.php`

**Type:** Class

## Description

Parser for TravelClick reservation responses
Extends the base SoapResponseParser to handle specific parsing logic for
reservation-related messages. Extracts reservation details like confirmation numbers,
guest profiles, room information, and payment details.

## Methods

### `parse`

Parse a reservation response into a structured format
This method extends the base parse method to extract detailed reservation
information after the general SOAP response is processed.

```php
public function parse(string $messageId, string $rawResponse, float|null $durationMs = null, array $headers = []): ReservationResponseDto
```

**Parameters:**

- `$messageId` (string): The unique message identifier for tracking
- `$rawResponse` (string): The raw XML response from TravelClick
- `$durationMs` (?float): The time taken to receive the response in milliseconds
- `$headers` (array): Optional SOAP headers from the response

**Returns:** ReservationResponseDto - The parsed response data with reservation details

---

