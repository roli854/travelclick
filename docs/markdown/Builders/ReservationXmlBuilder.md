# ReservationXmlBuilder

**Full Class Name:** `App\TravelClick\Builders\ReservationXmlBuilder`

**File:** `Builders/ReservationXmlBuilder.php`

**Type:** Class

## Description

XML Builder for TravelClick HTNG 2011B reservation messages
This class builds OTA_HotelResNotifRQ XML messages for all types of
reservations (transient, travel agency, corporate, package, group,
and alternate payment) according to HTNG 2011B specifications.

## Methods

### `__construct`

Create a new ReservationXmlBuilder instance

```php
public function __construct(App\TravelClick\DTOs\SoapHeaderDto $soapHeaders, bool $validateXml = true, bool $formatOutput = false)
```

**Parameters:**

- `$soapHeaders` (SoapHeaderDto): 
- `$validateXml` (bool): 
- `$formatOutput` (bool): 

---

### `buildReservationXml`

Create XML message for a reservation

```php
public function buildReservationXml(App\TravelClick\DTOs\ReservationDataDto $reservationData): string
```

**Parameters:**

- `$reservationData` (ReservationDataDto): 

**Returns:** string - The complete XML message

---

