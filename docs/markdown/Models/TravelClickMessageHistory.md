# TravelClickMessageHistory

**Full Class Name:** `App\TravelClick\Models\TravelClickMessageHistory`

**File:** `Models/TravelClickMessageHistory.php`

**Type:** Class

## Description

TravelClickMessageHistory Model
This model represents a detailed history entry of all messages exchanged with TravelClick.
It's like a filing cabinet that keeps copies of all business correspondence for future reference.

## Properties

### `$timestamps`

Indicates if the model should be timestamped
We handle timestamps manually to match Centrium conventions

---

## Methods

### `travelClickLog`

Get the main TravelClick log entry for this message

```php
public function travelClickLog(): Illuminate\Database\Eloquent\Relations\BelongsTo
```

---

### `parentMessage`

Get the parent message if this is a response/follow-up

```php
public function parentMessage(): Illuminate\Database\Eloquent\Relations\BelongsTo
```

---

### `childMessages`

Get all child messages (responses/follow-ups to this message)

```php
public function childMessages(): Illuminate\Database\Eloquent\Relations\HasMany
```

---

### `systemUser`

Get the system user who initiated/processed this message

```php
public function systemUser(): Illuminate\Database\Eloquent\Relations\BelongsTo
```

---

### `batchMessages`

Get messages that are part of the same batch

```php
public function batchMessages(): Illuminate\Database\Eloquent\Relations\HasMany
```

---

### `scopeOfType`

Scope for messages of a specific type

```php
public function scopeOfType(Illuminate\Database\Eloquent\Builder $query, MessageType $messageType): Illuminate\Database\Eloquent\Builder
```

---

### `scopeDirection`

Scope for messages in a specific direction

```php
public function scopeDirection(Illuminate\Database\Eloquent\Builder $query, MessageDirection $direction): Illuminate\Database\Eloquent\Builder
```

---

### `scopeForProperty`

Scope for messages from a specific property

```php
public function scopeForProperty(Illuminate\Database\Eloquent\Builder $query, int $propertyId): Illuminate\Database\Eloquent\Builder
```

---

### `scopeWithStatus`

Scope for messages with specific processing status

```php
public function scopeWithStatus(Illuminate\Database\Eloquent\Builder $query, ProcessingStatus $status): Illuminate\Database\Eloquent\Builder
```

---

### `scopeRecent`

Scope for messages created within a time period

```php
public function scopeRecent(Illuminate\Database\Eloquent\Builder $query, int $hours = 24): Illuminate\Database\Eloquent\Builder
```

---

### `scopeBatchMessages`

Scope for messages that are part of a batch

```php
public function scopeBatchMessages(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
```

---

### `scopeSlowMessages`

Scope for messages that took longer than expected to process

```php
public function scopeSlowMessages(Illuminate\Database\Eloquent\Builder $query, int $thresholdSeconds = 30): Illuminate\Database\Eloquent\Builder
```

---

### `scopeContainingXml`

Scope for messages with specific XML content

```php
public function scopeContainingXml(Illuminate\Database\Eloquent\Builder $query, string $xmlFragment): Illuminate\Database\Eloquent\Builder
```

---

### `scopeMessageThread`

Scope for message threads (parent and all children)

```php
public function scopeMessageThread(Illuminate\Database\Eloquent\Builder $query, string $messageId): Illuminate\Database\Eloquent\Builder
```

---

### `getXmlPreviewAttribute`

Get a preview of the XML content (first 200 characters)

```php
public function getXmlPreviewAttribute(): string
```

---

### `getProcessingTimeDisplayAttribute`

Get a formatted display of processing time

```php
public function getProcessingTimeDisplayAttribute(): string
```

---

### `getIsBatchMessageAttribute`

Check if this message is part of a batch operation

```php
public function getIsBatchMessageAttribute(): bool
```

---

### `getMessageSummaryAttribute`

Get a summary of the message for display purposes

```php
public function getMessageSummaryAttribute(): array
```

---

### `createEntry`

Create a new message history entry with automatic XML hashing

```php
public function createEntry(array $data): self
```

---

### `extractKeyDataFromXml`

Extract key data from XML for quick reference

```php
public function extractKeyDataFromXml(string $xml): array
```

---

### `markAsSent`

Mark message as sent

```php
public function markAsSent(): void
```

---

### `markAsReceived`

Mark message as received with response

```php
public function markAsReceived(string|null $responseXml = null): void
```

---

### `markAsProcessed`

Mark message as processed

```php
public function markAsProcessed(string|null $notes = null): void
```

---

### `markAsFailed`

Mark message as failed

```php
public function markAsFailed(string $error): void
```

---

### `findDuplicatesByHash`

Find duplicate messages by XML hash

```php
public function findDuplicatesByHash(string $xmlHash): Illuminate\Database\Eloquent\Collection
```

---

### `getMessageStats`

Get message statistics for a property

```php
public function getMessageStats(int $propertyId, int $days = 7): array
```

---

### `getBatchSummary`

Get batch operation summary

```php
public function getBatchSummary(string $batchId): array
```

---

### `cleanup`

Clean up old message history records

```php
public function cleanup(int $daysToKeep = 30): int
```

---

### `exportMessages`

Export message history for analysis

```php
public function exportMessages(int $propertyId, Carbon\Carbon $startDate, Carbon\Carbon $endDate, array $messageTypes = []): Illuminate\Database\Eloquent\Collection
```

---

### `factory`

Get a new factory instance for the model.

```php
public function factory($count = null, $state = [])
```

**Returns:** TFactory - 

---

