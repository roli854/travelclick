# RatePlanData

**Full Class Name:** `App\TravelClick\DTOs\RatePlanData`

**File:** `DTOs/RatePlanData.php`

**Type:** Class

## Description

Rate plan data structure for TravelClick HTNG 2011B integration
This DTO represents a complete rate plan that can contain multiple rates
across different date ranges and room types. Think of it as a "rate book"
that contains all the pricing information for a specific rate plan.
A rate plan might have:
- Different rates for different dates (seasonal pricing)
- Different rates for different room types
- Linked rates derived from a master rate

## Methods

### `__construct`

```php
public function __construct(string $ratePlanCode, string $hotelCode, App\TravelClick\Enums\RateOperationType $operationType, Illuminate\Support\Collection|array $rates, string $ratePlanName = null, string $currencyCode = null, bool $isLinkedRate = false, string $masterRatePlanCode = null, int $maxGuestApplicable = null, bool $isCommissionable = null, array $marketCodes = [], bool $isDeltaUpdate = true, Carbon\Carbon|null $lastModified = null)
```

---

### `fromArray`

Create RatePlanData from array

```php
public function fromArray(array $data): self
```

---

### `toArray`

Convert to array for XML building

```php
public function toArray(): array
```

---

### `getRatesForRoomType`

Get rates for a specific room type

```php
public function getRatesForRoomType(string $roomTypeCode): Illuminate\Support\Collection
```

---

### `getRatesForDate`

Get rates valid for a specific date

```php
public function getRatesForDate(Carbon\Carbon $date): Illuminate\Support\Collection
```

---

### `hasRatesForRoomType`

Check if rate plan has rates for specific room type

```php
public function hasRatesForRoomType(string $roomTypeCode): bool
```

---

### `getCurrencies`

Get all unique currencies used in rates

```php
public function getCurrencies(): Illuminate\Support\Collection
```

---

### `isValidForCertification`

Check if rate plan is valid for certification
(must have rates with 1st and 2nd adult rates)

```php
public function isValidForCertification(): bool
```

---

### `splitByDateRanges`

Split rate plan into multiple plans by date range
Useful for batch processing with size limits

```php
public function splitByDateRanges(int $maxDaysPerPlan = 30): Illuminate\Support\Collection
```

---

### `filterLinkedRatesIfNeeded`

Filter out linked rates if external system handles them
According to HTNG spec, only send master rates if external system manages linking

```php
public function filterLinkedRatesIfNeeded(bool $externalSystemHandlesLinkedRates = false): self
```

---

