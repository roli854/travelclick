# SchemaType

**Full Class Name:** `App\TravelClick\Enums\SchemaType`

**File:** `Enums/SchemaType.php`

**Type:** Enum

## Description

XSD Schema Types for HTNG 2011B Messages
Maps message types to their corresponding XSD schema files for validation.
Each schema type corresponds to a specific HTNG 2011B message structure.

## Constants

### `INVENTORY`

**Value:** `\App\TravelClick\Enums\SchemaType::INVENTORY`

---

### `RATES`

**Value:** `\App\TravelClick\Enums\SchemaType::RATES`

---

### `RESERVATION`

**Value:** `\App\TravelClick\Enums\SchemaType::RESERVATION`

---

### `RESTRICTIONS`

**Value:** `\App\TravelClick\Enums\SchemaType::RESTRICTIONS`

---

### `GROUP_BLOCK`

**Value:** `\App\TravelClick\Enums\SchemaType::GROUP_BLOCK`

---

## Properties

### `$name`

**Type:** `string`

---

### `$value`

**Type:** `string`

---

## Methods

### `getMessageType`

Get the corresponding MessageType for this schema

```php
public function getMessageType(): MessageType
```

---

### `fromMessageType`

Get schema from MessageType

```php
public function fromMessageType(MessageType $messageType): self
```

---

### `getSchemaPath`

Get the full path to the schema file

```php
public function getSchemaPath(): string
```

---

### `getNamespace`

Get the schema namespace

```php
public function getNamespace(): string
```

---

### `getRootElement`

Get the root element name for the schema

```php
public function getRootElement(): string
```

---

### `getOTAMessageName`

Get the corresponding OTA message name

```php
public function getOTAMessageName(): string
```

---

### `exists`

Check if schema file exists

```php
public function exists(): bool
```

---

### `getValidationRules`

Get schema validation rules specific to this type

```php
public function getValidationRules(): array
```

---

### `getAllSchemas`

Get all schema types

```php
public function getAllSchemas(): array
```

---

### `getPrimarySchemas`

Get primary schemas (most commonly used)

```php
public function getPrimarySchemas(): array
```

---

### `getBatchableSchemas`

Get schema types that support batching

```php
public function getBatchableSchemas(): array
```

---

### `getResponseSchemaName`

Get the corresponding response schema name (for validation)

```php
public function getResponseSchemaName(): string
```

---

### `supportsCountType`

Check if this schema supports the given count type (for inventory)

```php
public function supportsCountType(int $countType): bool
```

---

### `getValidationMessages`

Get validation error messages for this schema type

```php
public function getValidationMessages(): array
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

