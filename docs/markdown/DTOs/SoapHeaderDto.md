# SoapHeaderDto

**Full Class Name:** `App\TravelClick\DTOs\SoapHeaderDto`

**File:** `DTOs/SoapHeaderDto.php`

**Type:** Class

## Description

Data Transfer Object for SOAP headers required by HTNG 2011B interface
This DTO encapsulates all the standard SOAP headers needed for communicating
with TravelClick's PMS Connect service, including authentication and addressing.

## Properties

### `$messageId`

**Type:** `string`

---

### `$to`

**Type:** `string`

---

### `$replyTo`

**Type:** `string`

---

### `$action`

**Type:** `string`

---

### `$from`

**Type:** `string`

---

### `$hotelCode`

**Type:** `string`

---

### `$username`

**Type:** `string`

---

### `$password`

**Type:** `string`

---

### `$timeStamp`

**Type:** `string|null`

---

### `$echoToken`

**Type:** `string|null`

---

## Methods

### `__construct`

```php
public function __construct(string $messageId, string $to, string $replyTo, string $action, string $from, string $hotelCode, string $username, string $password, string|null $timeStamp = null, string|null $echoToken = null)
```

---

### `create`

Create a SoapHeaderDto instance with common defaults for TravelClick

```php
public function create(string $action, string $hotelCode, string $username, string $password, string|null $endpoint = null, string|null $replyToEndpoint = null): self
```

**Parameters:**

- `$action` (string): The SOAP action being performed
- `$hotelCode` (string): The hotel code for this property
- `$username` (string): Authentication username
- `$password` (string): Authentication password
- `$endpoint` (string|null): Custom endpoint override
- `$replyToEndpoint` (string|null): Custom reply-to endpoint

**Returns:** self - 

---

### `forInventory`

Create SoapHeaderDto for inventory operations

```php
public function forInventory(string $hotelCode, string $username, string $password): self
```

**Parameters:**

- `$hotelCode` (string): 
- `$username` (string): 
- `$password` (string): 

**Returns:** self - 

---

### `forRates`

Create SoapHeaderDto for rate operations

```php
public function forRates(string $hotelCode, string $username, string $password): self
```

**Parameters:**

- `$hotelCode` (string): 
- `$username` (string): 
- `$password` (string): 

**Returns:** self - 

---

### `forReservation`

Create SoapHeaderDto for reservation operations

```php
public function forReservation(string $hotelCode, string $username, string $password): self
```

**Parameters:**

- `$hotelCode` (string): 
- `$username` (string): 
- `$password` (string): 

**Returns:** self - 

---

### `forGroup`

Create SoapHeaderDto for group operations

```php
public function forGroup(string $hotelCode, string $username, string $password): self
```

**Parameters:**

- `$hotelCode` (string): 
- `$username` (string): 
- `$password` (string): 

**Returns:** self - 

---

### `toSoapHeaders`

Generate SOAP headers array for use with SOAP client

```php
public function toSoapHeaders(): array
```

**Returns:** array<string, - mixed>

---

### `toNamespacedHeaders`

Generate headers array with proper namespace prefixes

```php
public function toNamespacedHeaders(): array
```

**Returns:** array<string, - mixed>

---

### `toArray`

Get headers in array format suitable for XML building

```php
public function toArray(): array
```

**Returns:** array<string, - mixed>

---

### `validate`

Validate that all required fields are present and valid

```php
public function validate(): bool
```

**Returns:** bool - 

---

### `fromConfig`

Create SoapHeaderDto from configuration

```php
public function fromConfig(string $action, array|null $overrides = null): self
```

**Parameters:**

- `$action` (string): 

**Returns:** self - 

---

