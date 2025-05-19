# SoapRequestDto

**Full Class Name:** `App\TravelClick\DTOs\SoapRequestDto`

**File:** `DTOs/SoapRequestDto.php`

**Type:** Class

## Description

Data Transfer Object for SOAP Request
This DTO encapsulates all the data needed to make a SOAP request to TravelClick.
It provides a structured way to pass request data between components.

## Methods

### `__construct`

```php
public function __construct(string $messageId, string $action, string $xmlBody, string $hotelCode, array $headers = [], string $echoToken = null, string $version = '1.0', string $target = 'Production')
```

---

### `forInventory`

Create a new request DTO for inventory operations

```php
public function forInventory(string $messageId, string $xmlBody, string $hotelCode, string $echoToken = null): self
```

---

### `forRates`

Create a new request DTO for rate operations

```php
public function forRates(string $messageId, string $xmlBody, string $hotelCode, string $echoToken = null): self
```

---

### `forReservation`

Create a new request DTO for reservation operations

```php
public function forReservation(string $messageId, string $xmlBody, string $hotelCode, string $echoToken = null): self
```

---

### `getCompleteHeaders`

Get the complete headers array for the SOAP request

```php
public function getCompleteHeaders(): array
```

---

### `toArray`

Convert DTO to array for logging purposes

```php
public function toArray(): array
```

---

