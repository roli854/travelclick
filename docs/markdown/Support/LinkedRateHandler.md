# LinkedRateHandler

**Full Class Name:** `App\TravelClick\Support\LinkedRateHandler`

**File:** `Support/LinkedRateHandler.php`

**Type:** Class

## Description

Linked Rate Handler for TravelClick HTNG 2011B Integration
This handler manages the complex logic around linked rates - rates that are derived
from other "master" rates using offsets or percentages. Think of it as a calculator
that knows how to derive AAA rates from BAR rates, or corporate rates from rack rates.
In hotel terminology:
- BAR (Best Available Rate) might be the master at $200/night
- AAA rate (linked) could be BAR - 10% = $180/night
- Corporate rate (linked) could be BAR - $20 = $180/night
Key responsibilities:
- Apply linked rate calculations (offset/percentage)
- Identify master vs derived rate relationships
- Handle configuration for external system linking
- Provide utilities for linked rate management

## Methods

### `__construct`

```php
public function __construct()
```

---

### `applyLinkedRateCalculations`

Apply linked rate calculations to a collection of rates
This method processes a collection of rates and calculates the actual values
for linked rates based on their master rates. It's like having a spreadsheet
that automatically updates derived formulas when master values change.

```php
public function applyLinkedRateCalculations(Illuminate\Support\Collection $rates, RateOperationType $operationType): Illuminate\Support\Collection
```

**Parameters:**

- `$rates` (Collection<RateData>): Collection of rates including masters and linked
- `$operationType` (RateOperationType): The operation being performed

**Returns:** Collection<RateData> - Rates with linked rate calculations applied

---

### `shouldSendLinkedRates`

Determine whether to send linked rates based on configuration
According to HTNG spec, if the external system handles linked rates
(like the PMS calculating AAA rates from BAR rates automatically),
then we should only send master rates to avoid duplication.

```php
public function shouldSendLinkedRates(RateOperationType $operationType): bool
```

**Parameters:**

- `$operationType` (RateOperationType): The operation being performed

**Returns:** bool - True if should send linked rates, false if filter them out

---

### `filterLinkedRatesIfNeeded`

Filter a rate plan removing linked rates if needed
This is a convenience method that leverages the existing logic in RatePlanData
but adds the business logic for determining when to filter.

```php
public function filterLinkedRatesIfNeeded(RatePlanData $ratePlan, RateOperationType $operationType): RatePlanData
```

**Parameters:**

- `$ratePlan` (RatePlanData): The rate plan to potentially filter
- `$operationType` (RateOperationType): The operation being performed

**Returns:** RatePlanData - Rate plan with linked rates filtered if appropriate

---

### `getRequiredMasterRates`

Identify all master rate dependencies for a set of linked rates
This method analyzes linked rates and returns a list of master rate plan codes
that need to be included to satisfy all dependencies. It's like mapping out
a family tree to understand which ancestors are needed.

```php
public function getRequiredMasterRates(Illuminate\Support\Collection $linkedRates): Illuminate\Support\Collection
```

**Parameters:**

- `$linkedRates` (Collection<RateData>): Collection of linked rates

**Returns:** Collection<string> - Collection of required master rate plan codes

---

### `validateLinkedRateDependencies`

Validate linked rate relationships across multiple rate plans
This method ensures that if you're sending linked rates, their master rates
are either included in the same batch or already exist in TravelClick.

```php
public function validateLinkedRateDependencies(Illuminate\Support\Collection $ratePlans, RateOperationType $operationType): void
```

**Parameters:**

- `$ratePlans` (Collection<RatePlanData>): Collection of rate plans
- `$operationType` (RateOperationType): The operation being performed

---

### `getLinkedRateSummary`

Get linked rate information for debugging/logging
Provides a summary of linked rate relationships useful for troubleshooting
and operational visibility.

```php
public function getLinkedRateSummary(Illuminate\Support\Collection $rates): array
```

**Parameters:**

- `$rates` (Collection<RateData>): Collection of rates to analyze

**Returns:** array - Summary of linked rate information

---

### `calculateLinkedRateFromMaster`

Create a new rate data with linked rate calculations applied
This method creates a copy of a linked rate with calculated values based on
the master rate. It's like having a formula in Excel that automatically
updates when the reference cell changes.

```php
public function calculateLinkedRateFromMaster(RateData $linkedRate, RateData $masterRate): RateData
```

**Parameters:**

- `$linkedRate` (RateData): The linked rate to calculate
- `$masterRate` (RateData): The master rate to base calculations on

**Returns:** RateData - New rate with calculated values

---

### `validateMasterRate`

Validate that a rate qualifies as a proper master rate
Master rates should be complete rates (not linked themselves) with
valid pricing for both adults.

```php
public function validateMasterRate(RateData $rate): void
```

**Parameters:**

- `$rate` (RateData): The rate to validate as master

---

### `externalSystemHandlesLinkedRates`

Get configuration for external system handling of linked rates

```php
public function externalSystemHandlesLinkedRates(): bool
```

**Returns:** bool - True if external system handles linked rates

---

### `getLinkedRateStrategy`

Get recommended strategy for handling linked rates
Provides a recommendation based on configuration and operation type.

```php
public function getLinkedRateStrategy(RateOperationType $operationType): array
```

**Parameters:**

- `$operationType` (RateOperationType): The operation being performed

**Returns:** array - Strategy recommendation with explanation

---

