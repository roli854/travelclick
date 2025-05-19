# XmlBuilder

**Full Class Name:** `App\TravelClick\Builders\XmlBuilder`

**File:** `Builders/XmlBuilder.php`

**Type:** Class

## Description

Abstract base class for building HTNG 2011B XML messages
This class provides common functionality for all XML builders in the TravelClick
integration. It handles SOAP envelope structure, namespaces, headers, and
validation. Think of it as the foundation blueprint that all specific
message builders will use to construct their XML.

## Methods

### `__construct`

```php
public function __construct(App\TravelClick\Enums\MessageType $messageType, App\TravelClick\DTOs\SoapHeaderDto $soapHeaders, bool $validateXml = true, bool $formatOutput = false)
```

---

### `build`

Build the complete XML message

```php
public function build(array $messageData): string
```

**Returns:** string - The complete XML message

---

### `withValidation`

Set whether to validate the built XML

```php
public function withValidation(bool $validate = true): self
```

**Parameters:**

- `$validate` (bool): 

**Returns:** self - 

---

### `withFormatting`

Set whether to format the output XML

```php
public function withFormatting(bool $format = true): self
```

**Parameters:**

- `$format` (bool): 

**Returns:** self - 

---

### `getMessageType`

Get the message type this builder handles

```php
public function getMessageType(): App\TravelClick\Enums\MessageType
```

**Returns:** MessageType - 

---

### `getSoapHeaders`

Get the SOAP headers

```php
public function getSoapHeaders(): App\TravelClick\DTOs\SoapHeaderDto
```

**Returns:** SoapHeaderDto - 

---

