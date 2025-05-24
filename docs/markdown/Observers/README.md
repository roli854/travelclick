# Model Observers

## Overview

This namespace contains 4 classes/interfaces/enums.

## Table of Contents

- [TravelClickErrorLogObserver](#travelclickerrorlogobserver) (Class)
- [TravelClickMessageHistoryObserver](#travelclickmessagehistoryobserver) (Class)
- [TravelClickPropertyMappingObserver](#travelclickpropertymappingobserver) (Class)
- [TravelClickSyncStatusObserver](#travelclicksyncstatusobserver) (Class)

## Complete API Reference

---

### TravelClickErrorLogObserver

**Type:** Class
**Full Name:** `App\TravelClick\Observers\TravelClickErrorLogObserver`

**Description:** TravelClick Error Log Observer

#### Methods

```php
public function created(TravelClickErrorLog $errorLog): void;
public function updated(TravelClickErrorLog $errorLog): void;
public function deleted(TravelClickErrorLog $errorLog): void;
public function creating(TravelClickErrorLog $errorLog): void;
public static function withoutEvents(callable $callback);
```

---

### TravelClickMessageHistoryObserver

**Type:** Class
**Full Name:** `App\TravelClick\Observers\TravelClickMessageHistoryObserver`

**Description:** TravelClickMessageHistoryObserver

#### Methods

```php
public function created(TravelClickMessageHistory $messageHistory): void;
public function updated(TravelClickMessageHistory $messageHistory): void;
public function deleted(TravelClickMessageHistory $messageHistory): void;
```

---

### TravelClickPropertyMappingObserver

**Type:** Class
**Full Name:** `App\TravelClick\Observers\TravelClickPropertyMappingObserver`

**Description:** Observer for TravelClickPropertyMapping model

#### Methods

```php
public function creating(TravelClickPropertyMapping $mapping): void;
public function created(TravelClickPropertyMapping $mapping): void;
public function updating(TravelClickPropertyMapping $mapping): void;
public function updated(TravelClickPropertyMapping $mapping): void;
public function deleting(TravelClickPropertyMapping $mapping): void;
public function deleted(TravelClickPropertyMapping $mapping): void;
public function restored(TravelClickPropertyMapping $mapping): void;
```

---

### TravelClickSyncStatusObserver

**Type:** Class
**Full Name:** `App\TravelClick\Observers\TravelClickSyncStatusObserver`

**Description:** TravelClickSyncStatusObserver

#### Methods

```php
public function creating(TravelClickSyncStatus $syncStatus): void;
public function created(TravelClickSyncStatus $syncStatus): void;
public function updating(TravelClickSyncStatus $syncStatus): void;
public function updated(TravelClickSyncStatus $syncStatus): void;
public function deleted(TravelClickSyncStatus $syncStatus): void;
public function restored(TravelClickSyncStatus $syncStatus): void;
public function forceDeleted(TravelClickSyncStatus $syncStatus): void;
```

## Detailed Documentation

For detailed documentation of each class, see:

- [TravelClickErrorLogObserver](TravelClickErrorLogObserver.md)
- [TravelClickMessageHistoryObserver](TravelClickMessageHistoryObserver.md)
- [TravelClickPropertyMappingObserver](TravelClickPropertyMappingObserver.md)
- [TravelClickSyncStatusObserver](TravelClickSyncStatusObserver.md)
