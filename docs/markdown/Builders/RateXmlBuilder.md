# RateXmlBuilder

**Full Class Name:** `App\TravelClick\Builders\RateXmlBuilder`

**File:** `Builders/RateXmlBuilder.php`

**Type:** Class

## Description

XML Builder for Rate messages (OTA_HotelRateNotifRQ)
This builder constructs HTNG 2011B compliant rate notification messages
for sending to TravelClick. Think of it as a specialized architect who
knows exactly how to design rate messages that TravelClick understands.
Key responsibilities:
- Build OTA_HotelRateNotifRQ messages for different operation types
- Handle linked rates according to external system capabilities
- Support both delta updates and full synchronization
- Validate rate structures before building XML
- Apply business rules specific to rate operations
Usage example:
```php
$builder = new RateXmlBuilder(
MessageType::RATES,
$soapHeaders,
RateOperationType::RATE_UPDATE
);
$xml = $builder->build(['rate_plans' => [$ratePlan1, $ratePlan2]]);
```

## Methods

### `__construct`

```php
public function __construct(App\TravelClick\Enums\MessageType $messageType, App\TravelClick\DTOs\SoapHeaderDto $soapHeaders, App\TravelClick\Enums\RateOperationType $operationType, bool $isDeltaUpdate = true, bool $validateXml = true, bool $formatOutput = false)
```

---

### `withOperationType`

Set operation type for the builder
Allows changing the operation type after instantiation, useful for
builders that handle multiple operation types.

```php
public function withOperationType(App\TravelClick\Enums\RateOperationType $operationType): self
```

**Parameters:**

- `$operationType` (RateOperationType): The operation type to set

**Returns:** self - 

---

### `withDeltaUpdate`

Set delta update mode

```php
public function withDeltaUpdate(bool $isDeltaUpdate = true): self
```

**Parameters:**

- `$isDeltaUpdate` (bool): Whether to use delta updates

**Returns:** self - 

---

### `getOperationType`

Get the operation type

```php
public function getOperationType(): App\TravelClick\Enums\RateOperationType
```

**Returns:** RateOperationType - 

---

### `isDeltaUpdate`

Check if delta updates are enabled

```php
public function isDeltaUpdate(): bool
```

**Returns:** bool - 

---

### `getMaxRatePlansPerMessage`

Get the maximum rate plans per message for current operation

```php
public function getMaxRatePlansPerMessage(): int
```

**Returns:** int - 

---

### `getLinkedRateConfig`

Get linked rate configuration summary
Useful for debugging and understanding how linked rates will be handled.

```php
public function getLinkedRateConfig(): array
```

**Returns:** array<string, - mixed> Linked rate configuration

---

### `buildWithValidation`

Build rate message with comprehensive validation and error handling
This is the main entry point with additional safety measures and
detailed error reporting for rate-specific issues.

```php
public function buildWithValidation(array $messageData): string
```

**Returns:** string - The complete XML message

---

