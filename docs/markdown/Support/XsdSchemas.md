# XsdSchemas

**Full Class Name:** `App\TravelClick\Support\XsdSchemas`

**File:** `Support/XsdSchemas.php`

**Type:** Class

## Description

Registry for mapping HTNG 2011B message types to their corresponding XSD schema files.
This class provides a centralized way to locate and load XSD schemas for validation
of different message types in the TravelClick integration.

## Methods

### `getSchemaPath`

Get the file path for a specific message type's XSD schema

```php
public function getSchemaPath(MessageType $messageType): string
```

**Parameters:**

- `$messageType` (MessageType): The message type to get schema for

**Returns:** string - Full path to the XSD file

---

### `getSchemaContent`

Get the content of an XSD schema for a specific message type

```php
public function getSchemaContent(MessageType $messageType): string
```

**Parameters:**

- `$messageType` (MessageType): The message type to get schema content for

**Returns:** string - The XSD schema content

---

### `hasSchema`

Check if a schema exists for a specific message type

```php
public function hasSchema(MessageType $messageType): bool
```

**Parameters:**

- `$messageType` (MessageType): The message type to check

**Returns:** bool - True if schema exists and is readable

---

### `getAvailableMessageTypes`

Get all available message types that have corresponding XSD schemas

```php
public function getAvailableMessageTypes(): array
```

**Returns:** array<MessageType> - Array of message types with available schemas

---

### `clearCache`

Clear the schema cache

```php
public function clearCache(): void
```

**Returns:** void - 

---

### `validateSchemaAvailability`

Validate that all required schemas are present

```php
public function validateSchemaAvailability(): array
```

**Returns:** array<string> - Array of missing schema files

---

### `getSchemaStats`

Get schema validation statistics

```php
public function getSchemaStats(): array
```

**Returns:** array{total: - int, available: int, missing: int, percent_available: float}

---

