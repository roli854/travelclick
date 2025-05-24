# SoapResponseParser

**Full Class Name:** `App\TravelClick\Parsers\SoapResponseParser`

**File:** `Parsers/SoapResponseParser.php`

**Type:** Class

## Description

Base class for parsing SOAP responses from TravelClick
This class handles the common functionality for parsing SOAP responses,
extracting error information, and converting XML to structured data.
Specific message types should extend this class with their own parsing logic.

## Methods

### `parse`

Parse a SOAP response into a structured DTO

```php
public function parse(string $messageId, string $rawResponse, float|null $durationMs = null, array $headers = []): SoapResponseDto
```

**Parameters:**

- `$messageId` (string): The unique message identifier for tracking
- `$rawResponse` (string): The raw XML response from TravelClick
- `$durationMs` (?float): The time taken to receive the response in milliseconds
- `$headers` (array): Optional SOAP headers from the response

**Returns:** SoapResponseDto - The parsed response data

---

### `parseFromFault`

Create a parser from a SoapFault exception

```php
public function parseFromFault(string $messageId, SoapFault $fault, float|null $durationMs = null): SoapResponseDto
```

**Parameters:**

- `$messageId` (string): The unique message identifier for tracking
- `$fault` (SoapFault): The SOAP fault exception
- `$durationMs` (?float): The time taken before the fault occurred

**Returns:** SoapResponseDto - The parsed fault as a response DTO

---

