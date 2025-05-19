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

## Methods

### `__construct`

```php
public function __construct(float $firstAdultRate, float $secondAdultRate, string $roomTypeCode, string $ratePlanCode, Carbon\Carbon|string $startDate, Carbon\Carbon|string $endDate, float $additionalAdultRate = null, float $additionalChildRate = null, string $currencyCode = null, bool $restrictedDisplayIndicator = null, bool $isCommissionable = null, string $ratePlanQualifier = null, string $marketCode = null, int $maxGuestApplicable = null, bool $isLinkedRate = false, string $masterRatePlanCode = null, float $linkedRateOffset = null, float $linkedRatePercentage = null)
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
public function equals(App\TravelClick\DTOs\RateData $other): bool
```

---

### `withDateRange`

Create a copy with modified dates
Useful for splitting rates across date ranges

```php
public function withDateRange(Carbon\Carbon $startDate, Carbon\Carbon $endDate): self
```

---

