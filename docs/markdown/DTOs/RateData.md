# RateData

**Full Class Name:** `App\TravelClick\DTOs\RateData`

**File:** `DTOs/RateData.php`

**Type:** Class

## Description

Rate data structure for TravelClick HTNG 2011B integration
This DTO represents a single rate structure that can be sent to TravelClick.
Think of it as a "rate card" that contains all the pricing information
for a specific room type and date range.
Key requirements from HTNG 2011B specification:
- Must include rates for 1st and 2nd adults (mandatory for certification)
- Can include additional adult and child rates
- Supports various optional attributes for advanced rate management

## Properties

### `$firstAdultRate`

Rate for the first adult (mandatory)
This is the base rate that must always be present

**Type:** `float`

---

### `$secondAdultRate`

Rate for the second adult (mandatory)
Required for certification even if same as first adult

**Type:** `float`

---

### `$additionalAdultRate`

Rate for additional adults beyond the second (optional)
Used when 3rd, 4th+ adults have the same additional rate

**Type:** `float|null`

---

### `$additionalChildRate`

Rate for additional children (optional)
Applied to children beyond included occupancy

**Type:** `float|null`

---

### `$currencyCode`

Currency code (ISO 3166 format)
Default pulled from configuration if not specified

**Type:** `string`

---

### `$startDate`

Start date for this rate (inclusive)

**Type:** `Carbon\Carbon`

---

### `$endDate`

End date for this rate (inclusive)

**Type:** `Carbon\Carbon`

---

### `$roomTypeCode`

Room type code this rate applies to

**Type:** `string`

---

### `$ratePlanCode`

Rate plan code this rate belongs to

**Type:** `string`

---

### `$restrictedDisplayIndicator`

Whether this rate has restricted display (optional)
Used for special rates that shouldn't be publicly shown

**Type:** `bool|null`

---

### `$isCommissionable`

Whether this rate is commissionable (optional)
Important for travel agent bookings

**Type:** `bool|null`

---

### `$ratePlanQualifier`

Rate plan qualifier (optional)
Additional categorization for the rate

**Type:** `string|null`

---

### `$marketCode`

Market code (optional)
Associates rate with specific market segment

**Type:** `string|null`

---

### `$maxGuestApplicable`

Maximum number of guests this rate applies to (optional)
Helps TravelClick understand occupancy limits

**Type:** `int|null`

---

### `$isLinkedRate`

Whether this is a linked rate (derived from master)
Linked rates should not be sent if external system handles them

**Type:** `bool`

---

### `$masterRatePlanCode`

Master rate plan code if this is a linked rate

**Type:** `string|null`

---

### `$linkedRateOffset`

Offset amount for linked rates (can be positive or negative)

**Type:** `float|null`

---

### `$linkedRatePercentage`

Offset percentage for linked rates (e.g., -10 for 10% discount)

**Type:** `float|null`

---

## Methods

### `__construct`

```php
public function __construct(float $firstAdultRate, float $secondAdultRate, string $roomTypeCode, string $ratePlanCode, Carbon\Carbon|string $startDate, Carbon\Carbon|string $endDate, float|null $additionalAdultRate = null, float|null $additionalChildRate = null, string|null $currencyCode = null, bool|null $restrictedDisplayIndicator = null, bool|null $isCommissionable = null, string|null $ratePlanQualifier = null, string|null $marketCode = null, int|null $maxGuestApplicable = null, bool $isLinkedRate = false, string|null $masterRatePlanCode = null, float|null $linkedRateOffset = null, float|null $linkedRatePercentage = null)
```

---

### `fromArray`

Create RateData from array
Useful when receiving data from API requests or database

```php
public function fromArray(array $data): self
```

---

### `toArray`

Convert to array format suitable for XML building

```php
public function toArray(): array
```

---

### `toXmlAttributes`

Convert to XML attributes for RatePlan element

```php
public function toXmlAttributes(): array
```

---

### `isValidForDate`

Check if this rate is valid for the given date

```php
public function isValidForDate(Carbon\Carbon $date): bool
```

---

### `getRateForGuests`

Get the rate amount for a specific number of guests
Useful for calculations and validations

```php
public function getRateForGuests(int $guests): float
```

---

### `equals`

Check if this rate equals another rate (for deduplication)

```php
public function equals(RateData $other): bool
```

---

### `withDateRange`

Create a copy with modified dates
Useful for splitting rates across date ranges

```php
public function withDateRange(Carbon\Carbon $startDate, Carbon\Carbon $endDate): self
```

---

