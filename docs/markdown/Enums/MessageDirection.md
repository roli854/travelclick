# MessageDirection

**Full Class Name:** `App\TravelClick\Enums\MessageDirection`

**File:** `Enums/MessageDirection.php`

**Type:** Enum

## Description

MessageDirection Enum for TravelClick Integration
Tracks whether a message is going to TravelClick (outbound) or coming from TravelClick (inbound).
This is essential for logging, monitoring, and organizing our message flow.
Like marking letters as "incoming mail" or "outgoing mail".

## Constants

### `OUTBOUND`

Messages sent from Centrium to TravelClick
Examples: Inventory updates, rate updates, new reservations

**Value:** `\App\TravelClick\Enums\MessageDirection::OUTBOUND`

---

### `INBOUND`

Messages received from TravelClick to Centrium
Examples: New reservations, reservation modifications, responses

**Value:** `\App\TravelClick\Enums\MessageDirection::INBOUND`

---

## Properties

### `$name`

**Type:** `string`

---

### `$value`

**Type:** `string`

---

## Methods

### `description`

Get human-readable description

```php
public function description(): string
```

---

### `opposite`

Get the opposite direction
Useful for response messages

```php
public function opposite(): self
```

---

### `getLogLevel`

Get appropriate log level for this direction

```php
public function getLogLevel(): string
```

---

### `getDefaultQueue`

Get default queue for this direction

```php
public function getDefaultQueue(): string
```

---

### `allowsMessageType`

Check if this direction allows specific message types

```php
public function allowsMessageType(MessageType $messageType): bool
```

---

### `getIcon`

Get icon for UI display

```php
public function getIcon(): string
```

---

### `getColor`

Get color for UI display

```php
public function getColor(): string
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

