# Events

## Overview

This namespace contains 5 classes/interfaces/enums.

## Table of Contents

- [RateSyncCompleted](#ratesynccompleted) (Class)
- [RateSyncFailed](#ratesyncfailed) (Class)
- [ReservationSyncCompleted](#reservationsynccompleted) (Class)
- [ReservationSyncFailed](#reservationsyncfailed) (Class)
- [SyncStatusChanged](#syncstatuschanged) (Class)

## Complete API Reference

---

### RateSyncCompleted

**Type:** Class
**Full Name:** `App\TravelClick\Events\RateSyncCompleted`

#### Properties

```php
public readonly string $hotelCode;
public readonly RateOperationType $operationType;
public readonly int $ratesProcessed;
public readonly float $durationMs;
public readonly string|null $trackingId;
public $socket;
```

#### Methods

```php
public function __construct(string $hotelCode, RateOperationType $operationType, int $ratesProcessed, float $durationMs, string|null $trackingId = null);
public function broadcastAs(): string;
public static function dispatch();
public static function dispatchIf($boolean, ...$arguments);
public static function dispatchUnless($boolean, ...$arguments);
public static function broadcast();
public function dontBroadcastToCurrentUser();
public function broadcastToEveryone();
public function __serialize();
public function __unserialize(array $values);
public function restoreModel($value);
```

---

### RateSyncFailed

**Type:** Class
**Full Name:** `App\TravelClick\Events\RateSyncFailed`

#### Properties

```php
public readonly string $hotelCode;
public readonly RateOperationType $operationType;
public readonly string $errorMessage;
public readonly string $exceptionClass;
public readonly string|null $trackingId;
public $socket;
```

#### Methods

```php
public function __construct(string $hotelCode, RateOperationType $operationType, string $errorMessage, string $exceptionClass, string|null $trackingId = null);
public function broadcastAs(): string;
public static function dispatch();
public static function dispatchIf($boolean, ...$arguments);
public static function dispatchUnless($boolean, ...$arguments);
public static function broadcast();
public function dontBroadcastToCurrentUser();
public function broadcastToEveryone();
public function __serialize();
public function __unserialize(array $values);
public function restoreModel($value);
```

---

### ReservationSyncCompleted

**Type:** Class
**Full Name:** `App\TravelClick\Events\ReservationSyncCompleted`

**Description:** Event fired when a reservation is successfully synchronized with TravelClick.

#### Properties

```php
public readonly ReservationDataDto $reservationData;
public readonly SoapResponseDto $response;
public readonly string|null $confirmationNumber;
public readonly string|null $jobId;
public $socket;
```

#### Methods

```php
public function __construct(ReservationDataDto $reservationData, SoapResponseDto $response, string|null $confirmationNumber = null, string|null $jobId = null);
public static function dispatch();
public static function dispatchIf($boolean, ...$arguments);
public static function dispatchUnless($boolean, ...$arguments);
public static function broadcast();
public function dontBroadcastToCurrentUser();
public function broadcastToEveryone();
public function __serialize();
public function __unserialize(array $values);
public function restoreModel($value);
```

---

### ReservationSyncFailed

**Type:** Class
**Full Name:** `App\TravelClick\Events\ReservationSyncFailed`

**Description:** Event fired when a reservation synchronization with TravelClick fails.

#### Properties

```php
public readonly ReservationDataDto $reservationData;
public readonly Throwable $exception;
public readonly string|null $jobId;
public readonly int $attempts;
public $socket;
```

#### Methods

```php
public function __construct(ReservationDataDto $reservationData, Throwable $exception, string|null $jobId = null, int $attempts = 0);
public function getErrorDetails(): array;
public static function dispatch();
public static function dispatchIf($boolean, ...$arguments);
public static function dispatchUnless($boolean, ...$arguments);
public static function broadcast();
public function dontBroadcastToCurrentUser();
public function broadcastToEveryone();
public function __serialize();
public function __unserialize(array $values);
public function restoreModel($value);
```

---

### SyncStatusChanged

**Type:** Class
**Full Name:** `App\TravelClick\Events\SyncStatusChanged`

**Description:** SyncStatusChanged Event

#### Properties

```php
public TravelClickSyncStatus $syncStatus;
public string|null $previousStatus;
public string $changeType;
public array $context;
public $socket;
```

#### Methods

```php
public function __construct(TravelClickSyncStatus $syncStatus, string|null $previousStatus = null, string $changeType = 'updated', array $context = []);
public function broadcastOn(): array;
public function broadcastAs(): string;
public function broadcastWith(): array;
public function broadcastWhen(): bool;
public function isStatusTransition(): bool;
public function isFailure(): bool;
public function isCompletion(): bool;
public function isCritical(): bool;
public function getDescription(): string;
public function getSeverity(): string;
public function getTags(): array;
public function toArray(): array;
public function toWebhook(): array;
public function toNotification(): array;
public static function dispatch();
public static function dispatchIf($boolean, ...$arguments);
public static function dispatchUnless($boolean, ...$arguments);
public static function broadcast();
public function dontBroadcastToCurrentUser();
public function broadcastToEveryone();
public function __serialize();
public function __unserialize(array $values);
public function restoreModel($value);
```

## Detailed Documentation

For detailed documentation of each class, see:

- [RateSyncCompleted](RateSyncCompleted.md)
- [RateSyncFailed](RateSyncFailed.md)
- [ReservationSyncCompleted](ReservationSyncCompleted.md)
- [ReservationSyncFailed](ReservationSyncFailed.md)
- [SyncStatusChanged](SyncStatusChanged.md)
