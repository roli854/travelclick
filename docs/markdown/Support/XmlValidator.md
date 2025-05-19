# XmlValidator

**Full Class Name:** `App\TravelClick\Support\XmlValidator`

**File:** `Support/XmlValidator.php`

**Type:** Class

## Description

Advanced XML validator with support for HTNG 2011B schema validation
This class provides both basic XML structure validation and XSD schema validation
for TravelClick HTNG 2011B messages.

## Methods

### `validateXmlStructure`

Validate XML string for basic structure and well-formedness

```php
public function validateXmlStructure(string $xml): bool
```

**Parameters:**

- `$xml` (string): The XML string to validate

**Returns:** bool - True if XML is valid

---

### `validateAgainstSchema`

Validate XML against HTNG 2011B XSD schema

```php
public function validateAgainstSchema(string $xml, App\TravelClick\Enums\MessageType $messageType): bool
```

**Parameters:**

- `$xml` (string): The XML string to validate
- `$messageType` (MessageType): The message type to validate against

**Returns:** bool - True if XML is valid against schema

---

### `validate`

Validate XML with automatic message type detection

```php
public function validate(string $xml): bool
```

**Parameters:**

- `$xml` (string): The XML string to validate

**Returns:** bool - True if XML is valid

---

### `getValidationInfo`

Get validation statistics for all available schemas

```php
public function getValidationInfo(): array
```

**Returns:** array{schemas: - array, stats: array}

---

### `validateXsdSchema`

Validate XSD schema file itself

```php
public function validateXsdSchema(string $xsdPath): bool
```

**Parameters:**

- `$xsdPath` (string): Path to XSD file

**Returns:** bool - True if XSD is valid

---

