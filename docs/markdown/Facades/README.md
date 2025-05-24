# Facades

## Overview

This namespace contains 1 classes/interfaces/enums.

## Table of Contents

- [RateSync](#ratesync) (Class)

## Complete API Reference

---

### RateSync

**Type:** Class
**Full Name:** `App\TravelClick\Facades\RateSync`

#### Methods

```php
public static function dispatch(Illuminate\Support\Collection|array $rates, string $hotelCode, RateOperationType $operationType = \App\TravelClick\Enums\RateOperationType::RATE_UPDATE, bool $isDeltaUpdate = true, int $batchSize = 0, string|null $trackingId = null): void;
public static function dispatchSync(Illuminate\Support\Collection|array $rates, string $hotelCode, RateOperationType $operationType = \App\TravelClick\Enums\RateOperationType::RATE_UPDATE, bool $isDeltaUpdate = true, int $batchSize = 0, string|null $trackingId = null): void;
```

## Detailed Documentation

For detailed documentation of each class, see:

- [RateSync](RateSync.md)
