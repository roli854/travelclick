# RateSync

**Full Class Name:** `App\TravelClick\Facades\RateSync`

**File:** `Facades/RateSync.php`

**Type:** Class

## Description



## Methods

### `dispatch`

Dispatch a new rate sync job.

```php
public function dispatch(Illuminate\Support\Collection|array $rates, string $hotelCode, RateOperationType $operationType = \App\TravelClick\Enums\RateOperationType::RATE_UPDATE, bool $isDeltaUpdate = true, int $batchSize = 0, string|null $trackingId = null): void
```

**Parameters:**

- `$hotelCode` (string): 
- `$operationType` (RateOperationType): 
- `$isDeltaUpdate` (bool): 
- `$batchSize` (int): 
- `$trackingId` (string|null): 

**Returns:** void - 

---

### `dispatchSync`

Dispatch a new rate sync job synchronously.

```php
public function dispatchSync(Illuminate\Support\Collection|array $rates, string $hotelCode, RateOperationType $operationType = \App\TravelClick\Enums\RateOperationType::RATE_UPDATE, bool $isDeltaUpdate = true, int $batchSize = 0, string|null $trackingId = null): void
```

**Parameters:**

- `$hotelCode` (string): 
- `$operationType` (RateOperationType): 
- `$isDeltaUpdate` (bool): 
- `$batchSize` (int): 
- `$trackingId` (string|null): 

**Returns:** void - 

---

