# MessageIdGenerator

**Full Class Name:** `App\TravelClick\Support\MessageIdGenerator`

**File:** `Support/MessageIdGenerator.php`

**Type:** Class

## Description

MessageIdGenerator - Generates unique identifiers for SOAP messages
This class creates standardized, traceable message IDs for all communications
with TravelClick PMS Connect. These IDs are critical for:
- Message tracing and debugging
- Correlation of requests/responses
- Prevention of duplicate message processing
- Auditing and compliance

## Methods

### `generate`

Generate a unique message ID for a specific hotel and message type

```php
public function generate(mixed $hotelId, App\TravelClick\Enums\MessageType $messageType, string $prefix = null): string
```

**Parameters:**

- `$hotelId` (string|int): The hotel identifier
- `$messageType` (MessageType): The type of message being sent
- `$prefix` (string|null): Optional prefix for the ID

**Returns:** string - The generated message ID

---

### `generateWithTimestamp`

Generate a timestamp-based message ID for traceability
Includes a timestamp component for easier chronological tracing

```php
public function generateWithTimestamp(mixed $hotelId, App\TravelClick\Enums\MessageType $messageType): string
```

**Parameters:**

- `$hotelId` (string|int): The hotel identifier
- `$messageType` (MessageType): The type of message being sent

**Returns:** string - The generated message ID with timestamp

---

### `generateIdempotent`

Generate a deterministic (idempotent) message ID based on payload
This ensures that identical requests generate the same ID,
helping prevent duplicate processing

```php
public function generateIdempotent(mixed $hotelId, App\TravelClick\Enums\MessageType $messageType, string $payload): string
```

**Parameters:**

- `$hotelId` (string|int): The hotel identifier
- `$messageType` (MessageType): The type of message being sent
- `$payload` (string): The message payload used for deterministic generation

**Returns:** string - The deterministic message ID

---

### `parseMessageId`

Parse a message ID into its component parts

```php
public function parseMessageId(string $messageId): array
```

**Parameters:**

- `$messageId` (string): The message ID to parse

**Returns:** array{hotel_id: - string, message_type: string, uuid: string} The components of the message ID

---

### `isValid`

Check if a message ID is valid

```php
public function isValid(string $messageId): bool
```

**Parameters:**

- `$messageId` (string): The message ID to validate

**Returns:** bool - True if the message ID is valid, false otherwise

---

### `extractHotelId`

Extract the hotel ID from a message ID

```php
public function extractHotelId(string $messageId): string
```

**Parameters:**

- `$messageId` (string): The message ID

**Returns:** string|null - The hotel ID or null if invalid

---

### `extractMessageType`

Extract the message type from a message ID

```php
public function extractMessageType(string $messageId): string
```

**Parameters:**

- `$messageId` (string): The message ID

**Returns:** string|null - The message type or null if invalid

---

