# ReservationResponseDto

**Full Class Name:** `App\TravelClick\DTOs\ReservationResponseDto`

**File:** `DTOs/ReservationResponseDto.php`

**Type:** Class

## Description

Specialized DTO for Reservation SOAP responses
Extends the base SoapResponseDto to include reservation-specific data payload

## Methods

### `__construct`

Create a new ReservationResponseDto instance

```php
public function __construct(string $messageId, bool $isSuccess, string $rawResponse, array|null $payload = null, string|null $errorMessage = null, string|null $errorCode = null, array|null $warnings = null, Carbon\Carbon|null $timestamp = null, string|null $echoToken = null, array|null $headers = null, float|null $durationMs = null)
```

---

### `successWithPayload`

Create a successful reservation response
This method name is different from the parent to avoid LSP violation

```php
public function successWithPayload(string $messageId, string $rawResponse, array $payload, string|null $echoToken = null, array|null $headers = null, float|null $durationMs = null): self
```

---

### `success`

Create a successful response DTO
Implementation to maintain compatibility with parent class

```php
public function success(string $messageId, string $rawResponse, string|null $echoToken = null, array|null $headers = null, float|null $durationMs = null): self
```

---

### `fromSoapResponse`

Create a reservation response from base SoapResponseDto

```php
public function fromSoapResponse(SoapResponseDto $response, array|null $payload = null): self
```

---

### `getPayload`

Get the reservation payload data

```php
public function getPayload(): array|null
```

---

### `withPayload`

Set or update the payload data

```php
public function withPayload(array $payload): self
```

---

### `hasPayload`

Check if payload is available

```php
public function hasPayload(): bool
```

---

### `toArray`

Convert DTO to array for logging purposes
Extends parent method to include payload information

```php
public function toArray(): array
```

---

