# SoapServiceInterface

**Full Class Name:** `App\TravelClick\Services\Contracts\SoapServiceInterface`

**File:** `Services/Contracts/SoapServiceInterface.php`

**Type:** Interface

## Description

Interface for TravelClick SOAP service operations
This interface defines the contract for all SOAP communications with TravelClick.
It ensures consistent method signatures and proper error handling across implementations.

## Methods

### `sendRequest`

Send a SOAP request to TravelClick

```php
public function sendRequest(App\TravelClick\DTOs\SoapRequestDto $request): App\TravelClick\DTOs\SoapResponseDto
```

**Parameters:**

- `$request` (SoapRequestDto): The SOAP request data transfer object

**Returns:** SoapResponseDto - The parsed SOAP response

---

### `updateInventory`

Update inventory at TravelClick

```php
public function updateInventory(string $xml, string $hotelCode): App\TravelClick\DTOs\SoapResponseDto
```

**Parameters:**

- `$xml` (string): The inventory XML message
- `$hotelCode` (string): The hotel code

**Returns:** SoapResponseDto - 

---

### `updateRates`

Update rates at TravelClick

```php
public function updateRates(string $xml, string $hotelCode): App\TravelClick\DTOs\SoapResponseDto
```

**Parameters:**

- `$xml` (string): The rates XML message
- `$hotelCode` (string): The hotel code

**Returns:** SoapResponseDto - 

---

### `sendReservation`

Send reservation to TravelClick

```php
public function sendReservation(string $xml, string $hotelCode): App\TravelClick\DTOs\SoapResponseDto
```

**Parameters:**

- `$xml` (string): The reservation XML message
- `$hotelCode` (string): The hotel code

**Returns:** SoapResponseDto - 

---

### `testConnection`

Test connection to TravelClick

```php
public function testConnection(): bool
```

**Returns:** bool - True if connection is successful

---

### `getClient`

Get the current SOAP client instance

```php
public function getClient(): SoapClient
```

**Returns:** \SoapClient - 

---

### `isConnected`

Check if the service is currently connected

```php
public function isConnected(): bool
```

**Returns:** bool - 

---

