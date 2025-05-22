# ReservationResponseXmlBuilder

**Full Class Name:** `App\TravelClick\Builders\ReservationResponseXmlBuilder`

**File:** `Builders/ReservationResponseXmlBuilder.php`

**Type:** Class

## Description

Extends the ReservationXmlBuilder with methods specific to reservation response messages
for the HTNG 2011B protocol.

## Methods

### `buildSuccessResponse`

Builds a success response for a reservation transaction.

```php
public function buildSuccessResponse(string $reservationId, string $confirmationNumber, string $hotelCode, string $message = null): string
```

**Parameters:**

- `$reservationId` (string): The reservation ID
- `$confirmationNumber` (string): The confirmation number
- `$hotelCode` (string): The hotel code
- `$message` (string|null): Optional success message

**Returns:** string - The XML response

---

### `buildErrorResponse`

Builds an error response for a reservation transaction.

```php
public function buildErrorResponse(string $messageId, string $hotelCode, string $errorMessage, string $errorCode = '450'): string
```

**Parameters:**

- `$messageId` (string): The message ID
- `$hotelCode` (string): The hotel code
- `$errorMessage` (string): The error message
- `$errorCode` (string): The error code (default: 450 - Application Error)

**Returns:** string - The XML response

---

