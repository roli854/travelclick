# MessageType

**Full Class Name:** `App\TravelClick\Enums\MessageType`

**File:** `Enums/MessageType.php`

**Type:** Enum

## Description

MessageType Enum for TravelClick Integration
Defines all the different types of messages we can send to or receive from TravelClick.
Each message type has specific XML structures and processing requirements.
Think of these as different types of letters you might send:
- Inventory: "Here's our room availability"
- Rates: "Here are our prices"
- Reservation: "We have a new booking"
- etc.

## Constants

### `INVENTORY`

Inventory Messages - OTA_HotelInvCountNotifRQ
Used to update room availability and counts

**Value:** `\App\TravelClick\Enums\MessageType::INVENTORY`

---

### `RATES`

Rate Messages - OTA_HotelRateNotifRQ
Used to update room rates and pricing

**Value:** `\App\TravelClick\Enums\MessageType::RATES`

---

### `RESERVATION`

Reservation Messages - OTA_HotelResNotifRQ
Used to send new reservations, modifications, or cancellations

**Value:** `\App\TravelClick\Enums\MessageType::RESERVATION`

---

### `RESTRICTIONS`

Restriction Messages - OTA_HotelAvailNotifRQ
Used to send availability restrictions (stop sale, min/max stay, etc.)

**Value:** `\App\TravelClick\Enums\MessageType::RESTRICTIONS`

---

### `GROUP_BLOCK`

Group Block Messages - OTA_HotelInvBlockNotifRQ
Used to create, modify, or cancel group allocations

**Value:** `\App\TravelClick\Enums\MessageType::GROUP_BLOCK`

---

### `RESPONSE`

Response Messages - Various response types
Used for acknowledgments and error responses

**Value:** `\App\TravelClick\Enums\MessageType::RESPONSE`

---

### `UNKNOWN`

**Value:** `\App\TravelClick\Enums\MessageType::UNKNOWN`

---

## Properties

### `$name`

**Type:** `string`

---

### `$value`

**Type:** `string`

---

## Methods

### `getOTAMessageName`

Get the OTA message name for XML

```php
public function getOTAMessageName(): string
```

---

### `getQueueName`

Get the queue name for this message type

```php
public function getQueueName(): string
```

---

### `getTimeout`

Get timeout for this message type (in seconds)

```php
public function getTimeout(): int
```

---

### `getBatchSize`

Get batch size for this message type

```php
public function getBatchSize(): int
```

---

### `isEnabled`

Check if this message type is enabled in config

```php
public function isEnabled(): bool
```

---

### `getPriority`

Get priority level (1 = highest, 10 = lowest)

```php
public function getPriority(): int
```

---

### `description`

Get human-readable description

```php
public function description(): string
```

---

### `outboundTypes`

Get all outbound message types

```php
public function outboundTypes(): array
```

---

### `inboundTypes`

Get all inbound message types

```php
public function inboundTypes(): array
```

---

### `cases`

```php
public function cases(): array
```

---

### `from`

```php
public function from(string|int $value): static
```

---

### `tryFrom`

```php
public function tryFrom(string|int $value): static|null
```

---

