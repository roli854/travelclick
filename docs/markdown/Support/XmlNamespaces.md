# XmlNamespaces

**Full Class Name:** `App\TravelClick\Support\XmlNamespaces`

**File:** `Support/XmlNamespaces.php`

**Type:** Class

## Description

XML Namespaces manager for HTNG 2011B Interface
This class centralizes all namespace definitions and provides utilities
for working with XML namespaces in TravelClick integration.
Like having a directory of all the "languages" (namespaces) that
different parts of the XML document speak.

## Constants

### `SOAP_ENVELOPE`

SOAP envelope namespace - the "wrapper" for all SOAP messages

**Value:** `'http://www.w3.org/2003/05/soap-envelope'`

---

### `WS_ADDRESSING`

WS-Addressing namespace - for routing and addressing SOAP messages

**Value:** `'http://www.w3.org/2005/08/addressing'`

---

### `WS_SECURITY`

WS-Security namespace - for authentication and security headers

**Value:** `'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd'`

---

### `HTN_SERVICE`

HTN namespace - TravelClick/iHotelier specific namespace

**Value:** `'http://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService'`

---

### `OTA_2003`

OTA namespace - OpenTravel Alliance standard namespace

**Value:** `'http://www.opentravel.org/OTA/2003/05'`

---

### `XSI`

XML Schema Instance namespace - for XML schema references

**Value:** `'http://www.w3.org/2001/XMLSchema-instance'`

---

### `XSD`

XML Schema namespace - for XML schema definitions

**Value:** `'http://www.w3.org/2001/XMLSchema'`

---

### `PREFIX_MAPPING`

Standard namespace prefixes mapping
Like having standard abbreviations for long department names

**Value:** `[...]`

---

## Methods

### `getStandardNamespaces`

Get all standard namespaces for HTNG messages

```php
public function getStandardNamespaces(): array
```

**Returns:** array<string, - string> Array of prefix => namespace URI

---

### `getSoapEnvelopeNamespaces`

Get namespaces for SOAP envelope

```php
public function getSoapEnvelopeNamespaces(): array
```

**Returns:** array<string, - string>

---

### `getOtaNamespaces`

Get namespaces for OTA message bodies

```php
public function getOtaNamespaces(): array
```

**Returns:** array<string, - string>

---

### `buildNamespaceAttributes`

Build namespace attributes for XML elements

```php
public function buildNamespaceAttributes(array $namespaces): array
```

**Returns:** array<string, - string> Array of xmlns attributes

---

### `getSoapEnvelopeAttributes`

Get complete namespace attributes for a SOAP envelope

```php
public function getSoapEnvelopeAttributes(): array
```

**Returns:** array<string, - string>

---

### `getOtaSchemaLocation`

Get schema location for OTA messages

```php
public function getOtaSchemaLocation(): string
```

**Returns:** string - 

---

### `isValidPrefix`

Validate that a namespace prefix is recognized

```php
public function isValidPrefix(string $prefix): bool
```

**Parameters:**

- `$prefix` (string): 

**Returns:** bool - 

---

### `getNamespaceByPrefix`

Get namespace URI by prefix

```php
public function getNamespaceByPrefix(string $prefix): string|null
```

**Parameters:**

- `$prefix` (string): 

**Returns:** string|null - 

---

### `getDefaultNamespaceForMessageType`

Get default namespace for different message types

```php
public function getDefaultNamespaceForMessageType(string $messageType): string
```

**Parameters:**

- `$messageType` (string): ('inventory', 'rate', 'reservation', 'restriction', 'group')

**Returns:** string - 

---

### `getCompleteNamespaceContext`

Build complete namespace context for XML builders
Combines all necessary namespaces for a complete HTNG message

```php
public function getCompleteNamespaceContext(bool $includeSoapNamespaces = true): array
```

**Parameters:**

- `$includeSoapNamespaces` (bool): Whether to include SOAP-specific namespaces

**Returns:** array<string, - string>

---

