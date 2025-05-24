# SoapService

**Full Class Name:** `App\TravelClick\Services\SoapService`

**File:** `Services/SoapService.php`

**Type:** Class

## Description

Service for handling all SOAP communications with TravelClick
OPTIMIZED: Now uses SoapClientFactory with automatic header injection
via the SoapHeaders class for proper WSSE authentication following
TravelClick HTNG 2011B specifications.

## Methods

### `__construct`

```php
public function __construct(SoapClientFactory|null $clientFactory = null)
```

---

### `sendRequest`

Send a SOAP request to TravelClick

```php
public function sendRequest(SoapRequestDto $request): SoapResponseDto
```

---

### `updateInventory`

Update inventory at TravelClick

```php
public function updateInventory(string $xml, string $hotelCode): SoapResponseDto
```

---

### `updateRates`

Update rates at TravelClick

```php
public function updateRates(string $xml, string $hotelCode): SoapResponseDto
```

---

### `sendReservation`

Send reservation to TravelClick

```php
public function sendReservation(string $xml, string $hotelCode): SoapResponseDto
```

---

### `testConnection`

Test connection to TravelClick

```php
public function testConnection(): bool
```

---

### `getClient`

Get the current SOAP client instance

```php
public function getClient(): SoapClient
```

---

### `isConnected`

Check if the service is currently connected

```php
public function isConnected(): bool
```

---

### `getLastRequestId`

Get last request ID for debugging

```php
public function getLastRequestId(): string
```

---

### `getConfigSummary`

Get current configuration summary

```php
public function getConfigSummary(): array
```

---

### `reconnect`

Force reconnection by clearing cached client

```php
public function reconnect(): void
```

---

### `__destruct`

Cleanup resources

```php
public function __destruct()
```

---

