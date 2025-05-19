# ValidationService

**Full Class Name:** `App\TravelClick\Services\ValidationService`

**File:** `Services/ValidationService.php`

**Type:** Class

## Description

Complete validation service implementing ValidationServiceInterface
Provides comprehensive validation for HTNG 2011B messages including:
- SOAP message validation with DTOs
- XML structure validation against schemas
- Business rule validation per message type
- Property-specific rule validation
- Data sanitization and cleaning

## Methods

### `__construct`

```php
public function __construct(App\TravelClick\Support\ValidationRulesHelper|null $rulesHelper = null, App\TravelClick\Support\BusinessRulesValidator|null $businessRulesValidator = null)
```

---

### `validateSoapMessage`

{@inheritDoc}

```php
public function validateSoapMessage(App\TravelClick\DTOs\SoapRequestDto|App\TravelClick\DTOs\SoapResponseDto $message, App\TravelClick\Enums\MessageType $messageType): array
```

---

### `validateXmlStructure`

{@inheritDoc}

```php
public function validateXmlStructure(string $xml, string $schemaType): array
```

---

### `validateInventoryData`

{@inheritDoc}

```php
public function validateInventoryData(array $inventoryData, string $propertyId): array
```

---

### `validateRateData`

{@inheritDoc}

```php
public function validateRateData(array $rateData, string $propertyId): array
```

---

### `validateReservationData`

{@inheritDoc}

```php
public function validateReservationData(array $reservationData, App\TravelClick\Enums\ReservationType $reservationType, string $propertyId): array
```

---

### `validateGroupBlockData`

{@inheritDoc}

```php
public function validateGroupBlockData(array $groupData, string $propertyId): array
```

---

### `validateInventoryCounts`

{@inheritDoc}

```php
public function validateInventoryCounts(array $inventoryCounts, string $inventoryMethod): array
```

---

### `sanitizeData`

{@inheritDoc}

```php
public function sanitizeData(array $data, array $rules = []): array
```

---

### `validateDateRange`

{@inheritDoc}

```php
public function validateDateRange(string $startDate, string $endDate, array $constraints = []): array
```

---

### `validatePropertyRules`

{@inheritDoc}

```php
public function validatePropertyRules(string $propertyId, array $data, string $operation): array
```

---

### `validateRequiredFields`

{@inheritDoc}

```php
public function validateRequiredFields(array $data, App\TravelClick\Enums\MessageType $messageType, array $optionalFields = []): array
```

---

### `validateBusinessLogic`

{@inheritDoc}

```php
public function validateBusinessLogic(array $data, string $operationType, App\TravelClick\Enums\MessageType $messageType): array
```

---

### `validateSoapHeaders`

{@inheritDoc}

```php
public function validateSoapHeaders(array $headers, string $propertyId): array
```

---

### `getValidationRules`

{@inheritDoc}

```php
public function getValidationRules(App\TravelClick\Enums\MessageType $messageType, string $operation = 'create'): array
```

---

### `allValidationsPassed`

{@inheritDoc}

```php
public function allValidationsPassed(array $validationResults): bool
```

---

### `getValidationErrors`

{@inheritDoc}

```php
public function getValidationErrors(array $validationResults): array
```

---

