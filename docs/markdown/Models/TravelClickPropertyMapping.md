# TravelClickPropertyMapping

**Full Class Name:** `App\TravelClick\Models\TravelClickPropertyMapping`

**File:** `Models/TravelClickPropertyMapping.php`

**Type:** Class

## Description

TravelClickPropertyMapping Model
This model manages the mapping between Centrium properties and TravelClick hotel codes.
It's like a translation dictionary that ensures both systems can communicate about
the same hotel using their respective identifiers.

## Properties

### `$timestamps`

Indicates if the model should be timestamped
We handle timestamps manually to match Centrium conventions

---

## Methods

### `property`

Get the Centrium property associated with this mapping

```php
public function property(): Illuminate\Database\Eloquent\Relations\BelongsTo
```

---

### `systemUser`

Get the system user who created this mapping

```php
public function systemUser(): Illuminate\Database\Eloquent\Relations\BelongsTo
```

---

### `lastModifiedByUser`

Get the system user who last modified this mapping

```php
public function lastModifiedByUser(): Illuminate\Database\Eloquent\Relations\BelongsTo
```

---

### `travelClickLogs`

Get all TravelClick logs for this property mapping

```php
public function travelClickLogs(): Illuminate\Database\Eloquent\Relations\HasMany
```

---

### `recentLogs`

Get recent TravelClick logs for this property

```php
public function recentLogs()
```

---

### `scopeActive`

Scope for active mappings only

```php
public function scopeActive(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
```

---

### `scopeWithSyncStatus`

Scope for mappings with specific sync status

```php
public function scopeWithSyncStatus(Illuminate\Database\Eloquent\Builder $query, SyncStatus $status): Illuminate\Database\Eloquent\Builder
```

---

### `scopeStaleSync`

Scope for mappings that haven't synced recently

```php
public function scopeStaleSync(Illuminate\Database\Eloquent\Builder $query, int $hours = 24): Illuminate\Database\Eloquent\Builder
```

---

### `scopeNeedsAttention`

Scope for mappings that need attention

```php
public function scopeNeedsAttention(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
```

---

### `scopeSearch`

Scope for searching by hotel code or property name

```php
public function scopeSearch(Illuminate\Database\Eloquent\Builder $query, string $search): Illuminate\Database\Eloquent\Builder
```

---

### `scopeByPropertyGroup`

Scope for mappings by property group

```php
public function scopeByPropertyGroup(Illuminate\Database\Eloquent\Builder $query, int $propertyGroupId): Illuminate\Database\Eloquent\Builder
```

---

### `getFormattedHotelCodeAttribute`

Get formatted hotel code for display

```php
public function getFormattedHotelCodeAttribute(): string
```

---

### `getIsSyncHealthyAttribute`

Check if sync is healthy (recent and successful)

```php
public function getIsSyncHealthyAttribute(): bool
```

---

### `getDaysSinceLastSyncAttribute`

Get days since last sync

```php
public function getDaysSinceLastSyncAttribute(): int
```

---

### `getSyncHealthStatusAttribute`

Get comprehensive sync health status

```php
public function getSyncHealthStatusAttribute(): array
```

---

### `createMapping`

Create a new property mapping with validation

```php
public function createMapping(array $data): self
```

---

### `findByHotelCode`

Find mapping by TravelClick hotel code

```php
public function findByHotelCode(string $hotelCode): self|null
```

---

### `findByPropertyId`

Find mapping by Centrium property ID

```php
public function findByPropertyId(int $propertyId): self|null
```

---

### `getNeedingSyncMappings`

Get all mappings that need sync

```php
public function getNeedingSyncMappings(): Illuminate\Database\Eloquent\Collection
```

---

### `markSyncStarted`

Mark sync as started

```php
public function markSyncStarted(): void
```

---

### `markSyncSuccess`

Mark sync as successful

```php
public function markSyncSuccess(string|null $notes = null): void
```

---

### `markSyncFailed`

Mark sync as failed

```php
public function markSyncFailed(string $error): void
```

---

### `markSyncError`

Mark sync as having errors but partially successful

```php
public function markSyncError(string $error): void
```

---

### `getConfigValue`

Get specific configuration value

```php
public function getConfigValue(string $key, $default = null)
```

---

### `setConfigValue`

Set specific configuration value

```php
public function setConfigValue(string $key, $value): void
```

---

### `updateConfiguration`

Update multiple configuration values

```php
public function updateConfiguration(array $config): void
```

---

### `resetConfiguration`

Reset configuration to defaults

```php
public function resetConfiguration(): void
```

---

### `getDefaultConfiguration`

Get default configuration structure

```php
public function getDefaultConfiguration(): array
```

---

### `deactivate`

Deactivate mapping with reason

```php
public function deactivate(string|null $reason = null): void
```

---

### `reactivate`

Reactivate mapping

```php
public function reactivate(): void
```

---

### `getSyncStatistics`

Get sync statistics summary

```php
public function getSyncStatistics(): array
```

---

### `getHealthReport`

Get mapping health report

```php
public function getHealthReport(): array
```

---

### `exportMappings`

Export mappings for external analysis

```php
public function exportMappings(array $propertyIds = []): array
```

---

### `bulkUpdateSyncStatus`

Bulk update sync status for multiple mappings

```php
public function bulkUpdateSyncStatus(array $mappingIds, SyncStatus $status, string|null $error = null): int
```

---

### `bulkDeactivate`

Bulk deactivate mappings

```php
public function bulkDeactivate(array $mappingIds, string|null $reason = null): int
```

---

### `cleanupInactiveMappings`

Cleanup inactive mappings older than specified days

```php
public function cleanupInactiveMappings(int $daysOld = 365): int
```

---

### `factory`

Get a new factory instance for the model.

```php
public function factory($count = null, $state = [])
```

**Returns:** TFactory - 

---

