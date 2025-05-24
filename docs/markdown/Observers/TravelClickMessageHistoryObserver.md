# TravelClickMessageHistoryObserver

**Full Class Name:** `App\TravelClick\Observers\TravelClickMessageHistoryObserver`

**File:** `Observers/TravelClickMessageHistoryObserver.php`

**Type:** Class

## Description

TravelClickMessageHistoryObserver
This observer is like a detective who watches every message that goes through
the TravelClick system and takes notes about patterns, duplicates, and sync status.
Key responsibilities:
- Track message patterns and update sync status accordingly
- Detect and handle duplicate messages (deduplication)
- Fire SyncStatusChanged events when patterns indicate status changes
- Maintain sync health metrics based on message success/failure patterns
- Handle batch operation tracking

## Methods

### `created`

Handle the TravelClickMessageHistory "created" event.
When a new message is created, we need to:
1. Check for duplicates using XML hash
2. Update sync status if this is an outbound message
3. Cache the message for deduplication

```php
public function created(TravelClickMessageHistory $messageHistory): void
```

---

### `updated`

Handle the TravelClickMessageHistory "updated" event.
When a message is updated (usually status changes), we need to:
1. Update sync status based on the new processing status
2. Check if sync health needs to be recalculated
3. Fire appropriate events

```php
public function updated(TravelClickMessageHistory $messageHistory): void
```

---

### `deleted`

Handle the TravelClickMessageHistory "deleted" event.
Clean up any related cache entries and update metrics.

```php
public function deleted(TravelClickMessageHistory $messageHistory): void
```

---

