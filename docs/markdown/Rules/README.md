# Validation Rules

## Overview

This namespace contains 5 classes/interfaces/enums.

## Table of Contents

- [ValidCountType](#validcounttype) (Class)
- [ValidCurrencyCode](#validcurrencycode) (Class)
- [ValidHtngDate](#validhtngdate) (Class)
- [ValidHtngDateRange](#validhtngdaterange) (Class)
- [ValidRoomType](#validroomtype) (Class)

## Complete API Reference

---

### ValidCountType

**Type:** Class
**Full Name:** `App\TravelClick\Rules\ValidCountType`

**Description:** ValidCountType - Custom validation rule for HTNG 2011B CountType values

#### Methods

```php
public function __construct(bool $validateCalculated = true, bool $allowMultiple = true);
public function validate(string $attribute, mixed $value, Closure $fail): void;
public static function nonCalculated(bool $allowMultiple = true): self;
public static function single(bool $validateCalculated = true): self;
public static function calculated(): self;
```

---

### ValidCurrencyCode

**Type:** Class
**Full Name:** `App\TravelClick\Rules\ValidCurrencyCode`

**Description:** Validates currency codes according to ISO 4217 standard

#### Methods

```php
public function passes($attribute, $value): bool;
public function message(): string;
public static function getSupportedCurrencies(): array;
public static function isSupported(string $currency): bool;
public static function getCurrencyInfo(string $currency): array;
```

---

### ValidHtngDate

**Type:** Class
**Full Name:** `App\TravelClick\Rules\ValidHtngDate`

**Description:** ValidHtngDate

#### Methods

```php
public function __construct(array $options = []);
public function validate(string $attribute, mixed $value, Closure $fail): void;
public static function forArrival(): static;
public static function forDeparture(): static;
public static function forBooking(): static;
public static function forCancellationCutoff(): static;
public static function forInventorySync(): static;
public static function withBlackoutDates(array $blackoutDates, array $additionalOptions = []): static;
public static function withWeekendRestrictions(bool $excludeWeekends = true, array $additionalOptions = []): static;
public static function withAllowedDays(array $allowedDays, array $additionalOptions = []): static;
public function message(): string;
```

---

### ValidHtngDateRange

**Type:** Class
**Full Name:** `App\TravelClick\Rules\ValidHtngDateRange`

**Description:** ValidHtngDateRange

#### Methods

```php
public function __construct(array $options = []);
public function validate(string $attribute, mixed $value, Closure $fail): void;
public static function forInventory(): static;
public static function forRates(): static;
public static function forReservation(): static;
public static function forGroupBlock(): static;
public static function forRestrictions(): static;
public static function withBlackoutDates(array $blackoutDates, array $additionalOptions = []): static;
public static function withWeekendRestrictions(bool $excludeWeekends = true, array $additionalOptions = []): static;
public static function withAllowedDays(array $allowedDays, array $additionalOptions = []): static;
public static function withMinimumStay(int $minStayDays, array $additionalOptions = []): static;
public static function withMaxAdvanceBooking(int $maxAdvanceDays, array $additionalOptions = []): static;
public function message(): string;
```

---

### ValidRoomType

**Type:** Class
**Full Name:** `App\TravelClick\Rules\ValidRoomType`

#### Methods

```php
public function __construct(int|null $propertyId = null);
public function setData(array $data): static;
public function validate(string $attribute, mixed $value, Closure $fail): void;
public static function forProperty(int $propertyId): static;
public function message(): string;
```

## Detailed Documentation

For detailed documentation of each class, see:

- [ValidCountType](ValidCountType.md)
- [ValidCurrencyCode](ValidCurrencyCode.md)
- [ValidHtngDate](ValidHtngDate.md)
- [ValidHtngDateRange](ValidHtngDateRange.md)
- [ValidRoomType](ValidRoomType.md)
