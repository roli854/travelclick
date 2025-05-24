# SoapClientFactory

**Full Class Name:** `App\TravelClick\Support\SoapClientFactory`

**File:** `Support/SoapClientFactory.php`

**Type:** Class

## Description

Factory for creating SOAP clients configured for TravelClick
OPTIMIZED: Now integrates with SoapHeaders class for proper WSSE authentication
following TravelClick HTNG 2011B specifications.

## Methods

### `__construct`

```php
public function __construct(string $wsdl, string $username, string $password, string $hotelCode, array $options = [])
```

---

### `create`

Create a new SOAP client instance with proper headers

```php
public function create(): SoapClient
```

---

### `createWithHeaders`

Create client and automatically inject headers for a specific operation

```php
public function createWithHeaders(string $messageId, string $action = 'HTNG2011B_SubmitRequest'): SoapClient
```

---

### `injectHeaders`

Inject TravelClick headers using SoapHeaders class

```php
public function injectHeaders(SoapClient $client, string $messageId, string $action = 'HTNG2011B_SubmitRequest'): void
```

---

### `createWithOptions`

Create a client with custom options

```php
public function createWithOptions(array $customOptions): SoapClient
```

---

### `createForTesting`

Create a client optimized for testing

```php
public function createForTesting(): SoapClient
```

---

### `validateConfiguration`

Validate configuration before creating client

```php
public function validateConfiguration(): bool
```

---

### `fromConfig`

Create factory from Laravel configuration

```php
public function fromConfig(array|null $config = null): self
```

---

### `testConnection`

Test connection to TravelClick

```php
public function testConnection(): bool
```

---

### `getConfigSummary`

Get configuration summary for debugging

```php
public function getConfigSummary(): array
```

---

