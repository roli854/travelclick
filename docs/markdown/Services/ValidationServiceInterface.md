# ValidationServiceInterface

**Full Class Name:** `App\TravelClick\Services\Contracts\ValidationServiceInterface`

**File:** `Services/Contracts/ValidationServiceInterface.php`

**Type:** Interface

## Description

ValidationService Interface
Provides comprehensive validation for all TravelClick/HTNG 2011B operations.
This service ensures data integrity, business logic compliance, and XML structure validation.

## Methods

### `validateSoapMessage`

Validate complete SOAP message (request or response)

```php
public function validateSoapMessage(App\TravelClick\DTOs\SoapRequestDto|App\TravelClick\DTOs\SoapResponseDto $message, MessageType $messageType): array
```

**Parameters:**

- `$message` (SoapRequestDto|SoapResponseDto): The SOAP message to validate
- `$messageType` (MessageType): The expected message type

**Returns:** array<string, - mixed> Validation results

---

### `validateXmlStructure`

Validate XML structure against HTNG 2011B schema

```php
public function validateXmlStructure(string $xml, string $schemaType): array
```

**Parameters:**

- `$xml` (string): The XML to validate
- `$schemaType` (string): The schema type (inventory, rate, reservation, etc.)

**Returns:** array<string, - mixed> Validation results with details

---

### `validateInventoryData`

Validate inventory data according to business rules

```php
public function validateInventoryData(array $inventoryData, string $propertyId): array
```

**Parameters:**

- `$propertyId` (string): The property ID for context

**Returns:** array<string, - mixed> Validation results

---

### `validateRateData`

Validate rate data according to business rules

```php
public function validateRateData(array $rateData, string $propertyId): array
```

**Parameters:**

- `$propertyId` (string): The property ID for context

**Returns:** array<string, - mixed> Validation results

---

### `validateReservationData`

Validate reservation data according to business rules

```php
public function validateReservationData(array $reservationData, ReservationType $reservationType, string $propertyId): array
```

**Parameters:**

- `$reservationType` (ReservationType): The type of reservation
- `$propertyId` (string): The property ID for context

**Returns:** array<string, - mixed> Validation results

---

### `validateGroupBlockData`

Validate group block data according to business rules

```php
public function validateGroupBlockData(array $groupData, string $propertyId): array
```

**Parameters:**

- `$propertyId` (string): The property ID for context

**Returns:** array<string, - mixed> Validation results

---

### `validateInventoryCounts`

Validate inventory count types and values

```php
public function validateInventoryCounts(array $inventoryCounts, string $inventoryMethod): array
```

**Parameters:**

- `$inventoryMethod` (string): Either 'calculated' or 'not_calculated'

**Returns:** array<string, - mixed> Validation results

---

### `sanitizeData`

Sanitize and clean data for safe processing

```php
public function sanitizeData(array $data, array $rules = []): array
```

**Parameters:**

- `$rules` (array<string>): Sanitization rules to apply

**Returns:** array<string, - mixed> Sanitized data

---

### `validateDateRange`

Validate date ranges and formats

```php
public function validateDateRange(string $startDate, string $endDate, array $constraints = []): array
```

**Parameters:**

- `$startDate` (string): Start date string
- `$endDate` (string): End date string

**Returns:** array<string, - mixed> Validation results

---

### `validatePropertyRules`

Validate property-specific business rules

```php
public function validatePropertyRules(string $propertyId, array $data, string $operation): array
```

**Parameters:**

- `$propertyId` (string): The property ID
- `$operation` (string): Operation type (inventory, rate, reservation)

**Returns:** array<string, - mixed> Validation results

---

### `validateRequiredFields`

Validate required fields based on message type

```php
public function validateRequiredFields(array $data, MessageType $messageType, array $optionalFields = []): array
```

**Parameters:**

- `$messageType` (MessageType): The message type
- `$optionalFields` (array<string>): Optional fields that can be omitted

**Returns:** array<string, - mixed> Validation results

---

### `validateBusinessLogic`

Validate business logic for specific HTNG operations

```php
public function validateBusinessLogic(array $data, string $operationType, MessageType $messageType): array
```

**Parameters:**

- `$operationType` (string): The operation type (create, modify, cancel)
- `$messageType` (MessageType): The message type

**Returns:** array<string, - mixed> Validation results

---

### `validateSoapHeaders`

Validate SOAP headers and authentication data

```php
public function validateSoapHeaders(array $headers, string $propertyId): array
```

**Parameters:**

- `$propertyId` (string): The property ID for context

**Returns:** array<string, - mixed> Validation results

---

### `getValidationRules`

Get validation rules for a specific message type

```php
public function getValidationRules(MessageType $messageType, string $operation = 'create'): array
```

**Parameters:**

- `$messageType` (MessageType): The message type
- `$operation` (string): The operation (create, modify, cancel)

**Returns:** array<string, - mixed> Array of validation rules

---

### `allValidationsPassed`

Check if data passes all validations

```php
public function allValidationsPassed(array $validationResults): bool
```

**Returns:** bool - True if all validations pass

---

### `getValidationErrors`

Get formatted validation errors

```php
public function getValidationErrors(array $validationResults): array
```

**Returns:** array<string> - Array of formatted error messages

---

