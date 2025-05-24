# XML Builders

## Overview

This namespace contains 5 classes/interfaces/enums.

## Table of Contents

- [InventoryXmlBuilder](#inventoryxmlbuilder) (Class)
- [RateXmlBuilder](#ratexmlbuilder) (Class)
- [ReservationResponseXmlBuilder](#reservationresponsexmlbuilder) (Class)
- [ReservationXmlBuilder](#reservationxmlbuilder) (Class)
- [XmlBuilder](#xmlbuilder) (Abstract Class)

## Complete API Reference

---

### InventoryXmlBuilder

**Type:** Class
**Full Name:** `App\TravelClick\Builders\InventoryXmlBuilder`

**Description:** XML Builder for HTNG 2011B Inventory Messages (OTA_HotelInvCountNotifRQ)

#### Methods

```php
public function __construct(SoapHeaderDto $soapHeaders, bool $validateXml = true, bool $formatOutput = false);
public function buildBatch(Spatie\LaravelData\DataCollection $inventoryCollection): string;
public function buildSingle(InventoryData $inventoryData): string;
public static function forCalculated(SoapHeaderDto $soapHeaders): self;
public static function forDirect(SoapHeaderDto $soapHeaders): self;
public static function forPropertyLevel(SoapHeaderDto $soapHeaders): self;
```

---

### RateXmlBuilder

**Type:** Class
**Full Name:** `App\TravelClick\Builders\RateXmlBuilder`

**Description:** XML Builder for Rate messages (OTA_HotelRateNotifRQ)

#### Methods

```php
public function __construct(MessageType $messageType, SoapHeaderDto $soapHeaders, RateOperationType $operationType, bool $isDeltaUpdate = true, bool $validateXml = true, bool $formatOutput = false);
public function withOperationType(RateOperationType $operationType): self;
public function withDeltaUpdate(bool $isDeltaUpdate = true): self;
public function getOperationType(): RateOperationType;
public function isDeltaUpdate(): bool;
public function getMaxRatePlansPerMessage(): int;
public function getLinkedRateConfig(): array;
public function buildWithValidation(array $messageData): string;
```

---

### ReservationResponseXmlBuilder

**Type:** Class
**Full Name:** `App\TravelClick\Builders\ReservationResponseXmlBuilder`

**Description:** Extends the ReservationXmlBuilder with methods specific to reservation response messages

#### Methods

```php
public function buildSuccessResponse(string $reservationId, string $confirmationNumber, string $hotelCode, string|null $message = null): string;
public function buildErrorResponse(string $messageId, string $hotelCode, string $errorMessage, string $errorCode = '450'): string;
```

---

### ReservationXmlBuilder

**Type:** Class
**Full Name:** `App\TravelClick\Builders\ReservationXmlBuilder`

**Description:** XML Builder for TravelClick HTNG 2011B reservation messages

#### Methods

```php
public function __construct(SoapHeaderDto $soapHeaders, bool $validateXml = true, bool $formatOutput = false);
public function buildReservationXml(ReservationDataDto $reservationData): string;
```

---

### XmlBuilder

**Type:** Abstract Class
**Full Name:** `App\TravelClick\Builders\XmlBuilder`

**Description:** Abstract base class for building HTNG 2011B XML messages

#### Methods

```php
public function __construct(MessageType $messageType, SoapHeaderDto $soapHeaders, bool $validateXml = true, bool $formatOutput = false);
public function build(array $messageData): string;
public function withValidation(bool $validate = true): self;
public function withFormatting(bool $format = true): self;
public function getMessageType(): MessageType;
public function getSoapHeaders(): SoapHeaderDto;
```

## Detailed Documentation

For detailed documentation of each class, see:

- [InventoryXmlBuilder](InventoryXmlBuilder.md)
- [RateXmlBuilder](RateXmlBuilder.md)
- [ReservationResponseXmlBuilder](ReservationResponseXmlBuilder.md)
- [ReservationXmlBuilder](ReservationXmlBuilder.md)
- [XmlBuilder](XmlBuilder.md)
