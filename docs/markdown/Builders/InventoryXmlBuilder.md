# InventoryXmlBuilder

**Full Class Name:** `App\TravelClick\Builders\InventoryXmlBuilder`

**File:** `Builders/InventoryXmlBuilder.php`

**Type:** Class

## Description

XML Builder for HTNG 2011B Inventory Messages (OTA_HotelInvCountNotifRQ)
This builder handles the construction of inventory update messages for TravelClick.
It supports both "calculated" and "not-calculated" inventory methods as defined
in the TravelClick documentation.
Key concepts:
- Not Calculated (CountType=2): Sets inventory directly with available room count
- Calculated (CountTypes 4,5,6,99): TravelClick calculates availability from sold counts
- Property-level vs Room-level: Can update entire property or specific room types
Think of this as a translator that converts your inventory data into the exact
XML format that TravelClick expects, with all necessary validations and structure.

## Methods

### `__construct`

Constructor

```php
public function __construct(App\TravelClick\DTOs\SoapHeaderDto $soapHeaders, bool $validateXml = true, bool $formatOutput = false)
```

---

### `buildBatch`

Build multiple inventory records into a single XML message

```php
public function buildBatch(Spatie\LaravelData\DataCollection $inventoryCollection): string
```

**Returns:** string - Complete XML message

---

### `buildSingle`

Build a single inventory record into XML message

```php
public function buildSingle(App\TravelClick\DTOs\InventoryData $inventoryData): string
```

**Parameters:**

- `$inventoryData` (InventoryData): 

**Returns:** string - Complete XML message

---

### `forCalculated`

Create a new builder instance for calculated inventory

```php
public function forCalculated(App\TravelClick\DTOs\SoapHeaderDto $soapHeaders): self
```

**Parameters:**

- `$soapHeaders` (SoapHeaderDto): 

**Returns:** self - 

---

### `forDirect`

Create a new builder instance for direct (not-calculated) inventory

```php
public function forDirect(App\TravelClick\DTOs\SoapHeaderDto $soapHeaders): self
```

**Parameters:**

- `$soapHeaders` (SoapHeaderDto): 

**Returns:** self - 

---

### `forPropertyLevel`

Create a new builder instance for property-level inventory

```php
public function forPropertyLevel(App\TravelClick\DTOs\SoapHeaderDto $soapHeaders): self
```

**Parameters:**

- `$soapHeaders` (SoapHeaderDto): 

**Returns:** self - 

---

