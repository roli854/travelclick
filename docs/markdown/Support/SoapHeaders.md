# SoapHeaders

**Full Class Name:** `App\TravelClick\Support\SoapHeaders`

**File:** `Support/SoapHeaders.php`

**Type:** Class

## Description

SoapHeaders - Manages WSSE authentication headers for TravelClick integration
This class generates WS-Security headers required by TravelClick's HTNG 2011B interface.
It implements UsernameToken authentication with timestamp and nonce for security.
Based on TravelClick HTNG 2011B specification requirements:
- MessageID for tracking
- WS-Addressing headers (To, From, Action, ReplyTo)
- WS-Security headers with UsernameToken
- Timestamp and Nonce for replay attack prevention

## Methods

### `__construct`

Constructor

```php
public function __construct(string $username, string $password, string $hotelCode, string $endpoint)
```

**Parameters:**

- `$username` (string): TravelClick username
- `$password` (string): TravelClick password
- `$hotelCode` (string): Hotel identification code
- `$endpoint` (string): TravelClick endpoint URL

---

### `createHeaders`

Create complete SOAP headers for TravelClick request

```php
public function createHeaders(string $messageId, string $action = 'HTNG2011B_SubmitRequest'): string
```

**Parameters:**

- `$messageId` (string): Unique message identifier
- `$action` (string): SOAP action (defaults to HTNG2011B_SubmitRequest)

**Returns:** string - Complete XML headers string

---

### `fromConfig`

Create headers from Laravel configuration

```php
public function fromConfig(array $config, string $messageId, string $action = 'HTNG2011B_SubmitRequest'): string
```

**Parameters:**

- `$config` (array): TravelClick configuration array
- `$messageId` (string): Unique message identifier
- `$action` (string): SOAP action

**Returns:** string - Complete XML headers string

---

### `create`

Create headers with environment auto-detection

```php
public function create(string $messageId, string $action = 'HTNG2011B_SubmitRequest'): string
```

**Parameters:**

- `$messageId` (string): Unique message identifier
- `$action` (string): SOAP action

**Returns:** string - Complete XML headers string

---

### `generateMessageId`

Generate message ID with timestamp and unique suffix

```php
public function generateMessageId(string $prefix = 'MSG'): string
```

**Parameters:**

- `$prefix` (string): Message prefix (e.g., 'INV', 'RATE', 'RES')

**Returns:** string - Unique message ID

---

### `forOperation`

Create headers for specific operation types with validation

```php
public function forOperation(string $operationType, string|null $messageId = null): array
```

**Parameters:**

- `$operationType` (string): Type of operation (inventory, rates, reservation, etc.)
- `$messageId` (string|null): Optional message ID (will be generated if null)

**Returns:** array - [headers, messageId] tuple

---

### `createHeadersWithNamespaces`

Create headers with custom namespace declarations

```php
public function createHeadersWithNamespaces(string $messageId, array $customNamespaces = [], string $action = 'HTNG2011B_SubmitRequest'): string
```

**Parameters:**

- `$messageId` (string): Unique message identifier
- `$customNamespaces` (array): Additional namespaces to declare
- `$action` (string): SOAP action

**Returns:** string - Complete XML headers string

---

### `validateHeaders`

Validate headers format for debugging

```php
public function validateHeaders(string $headers): bool
```

**Parameters:**

- `$headers` (string): Headers XML string

**Returns:** bool - True if valid XML structure

---

