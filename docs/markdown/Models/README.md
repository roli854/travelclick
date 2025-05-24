# Models
## Overview
This namespace contains 6 classes/interfaces/enums.
## Table of Contents
- [TravelClickErrorLog](#travelclickerrorlog) (Class)
- [TravelClickLog](#travelclicklog) (Class)
- [TravelClickMessageHistory](#travelclickmessagehistory) (Class)
- [TravelClickPropertyConfig](#travelclickpropertyconfig) (Class)
- [TravelClickPropertyMapping](#travelclickpropertymapping) (Class)
- [TravelClickSyncStatus](#travelclicksyncstatus) (Class)
## Complete API Reference
---
### TravelClickErrorLog
**Type:** Class
**Full Name:** `App\TravelClick\Models\TravelClickErrorLog`
**Description:** TravelClick Error Log Model
#### Constants
```php
public const CREATED_AT = 'DateCreated';
public const UPDATED_AT = null;
```
#### Methods
```php
public function travelClickLog(): Illuminate\Database\Eloquent\Relations\BelongsTo;
public function resolvedByUser(): Illuminate\Database\Eloquent\Relations\BelongsTo;
public function systemUser(): Illuminate\Database\Eloquent\Relations\BelongsTo;
public function scopeBySeverity(Illuminate\Database\Eloquent\Builder $query, string $severity): Illuminate\Database\Eloquent\Builder;
public function scopeCritical(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder;
public function scopeUnresolved(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder;
public function scopeRetryable(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder;
public function scopeRequiresManualIntervention(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder;
public function scopeForProperty(Illuminate\Database\Eloquent\Builder $query, int $propertyId): Illuminate\Database\Eloquent\Builder;
public function scopeByType(Illuminate\Database\Eloquent\Builder $query, ErrorType $errorType): Illuminate\Database\Eloquent\Builder;
public function scopeRecent(Illuminate\Database\Eloquent\Builder $query, int $hours = 24): Illuminate\Database\Eloquent\Builder;
public function getFormattedErrorTitleAttribute(): string;
public function getShortErrorMessageAttribute(): string;
public function getIsResolvedAttribute(): bool;
public function getTimeSinceErrorAttribute(): string;
public function getSeverityColorAttribute(): string;
public function markAsResolved(int $userId, string|null $notes = null): bool;
public function isRecentError(int $minutes = 5): bool;
public function getSimilarErrors(int $limit = 5);
public static function logError(string $messageId, ErrorType $errorType, string $title, string $message, array $context = [], string $severity = 'medium', string|null $jobId = null, int $propertyId = 0, bool $canRetry = false, int|null $retryDelaySeconds = null, bool $requiresManualIntervention = false): self;
public static function getErrorStats(int $propertyId, int $days = 7): array;
public static function factory($count = null, $state = []);
```
---
### TravelClickLog
**Type:** Class
**Full Name:** `App\TravelClick\Models\TravelClickLog`
**Description:** TravelClick Log Model
#### Constants
```php
public const CREATED_AT = 'DateCreated';
public const UPDATED_AT = 'DateModified';
```
#### Methods
```php
public function systemUser(): Illuminate\Database\Eloquent\Relations\BelongsTo;
public function property(): Illuminate\Database\Eloquent\Relations\BelongsTo;
public function errorLogs(): Illuminate\Database\Eloquent\Relations\HasMany;
public function messageHistory(): Illuminate\Database\Eloquent\Relations\HasMany;
public function scopeDirection(Illuminate\Database\Eloquent\Builder $query, MessageDirection $direction): Illuminate\Database\Eloquent\Builder;
public function scopeByType(Illuminate\Database\Eloquent\Builder $query, MessageType $messageType): Illuminate\Database\Eloquent\Builder;
public function scopeByStatus(Illuminate\Database\Eloquent\Builder $query, SyncStatus $status): Illuminate\Database\Eloquent\Builder;
public function scopePending(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder;
public function scopeCompleted(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder;
public function scopeFailed(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder;
public function scopeForProperty(Illuminate\Database\Eloquent\Builder $query, int $propertyId): Illuminate\Database\Eloquent\Builder;
public function scopeForHotel(Illuminate\Database\Eloquent\Builder $query, string $hotelCode): Illuminate\Database\Eloquent\Builder;
public function scopeRecent(Illuminate\Database\Eloquent\Builder $query, int $hours = 24): Illuminate\Database\Eloquent\Builder;
public function scopeWithErrors(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder;
public function scopeNeedsRetry(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder;
public function scopeLongRunning(Illuminate\Database\Eloquent\Builder $query, int $thresholdMs = 30000): Illuminate\Database\Eloquent\Builder;
public function getFormattedDurationAttribute(): string;
public function getStatusWithColorAttribute(): array;
public function getOperationSummaryAttribute(): string;
public function getIsSuccessfulAttribute(): bool;
public function getIsRunningAttribute(): bool;
public function getIsOverdueAttribute(): bool;
public function getTruncatedRequestXmlAttribute(): string;
public function getTruncatedResponseXmlAttribute(): string;
public function markAsStarted(): bool;
public function markAsCompleted(string|null $responseXml = null): bool;
public function markAsFailed(ErrorType $errorType, string $errorMessage, string|null $responseXml = null): bool;
public function incrementRetryCount(): bool;
public function addMetadata(array $data): bool;
public static function createLog(string $messageId, MessageDirection $direction, MessageType $messageType, int $propertyId, string|null $hotelCode = null, string|null $requestXml = null, array $metadata = [], string|null $jobId = null, int|null $systemUserId = null): self;
public static function getPerformanceStats(int $propertyId, int $days = 7): array;
public static function getRecentActivity(int $hours = 24): array;
public static function getNeedsAttention(): Illuminate\Database\Eloquent\Builder;
public static function cleanup(int $daysToKeep = 30): int;
public static function getErrorPatterns(int|null $propertyId = null, int $days = 7): array;
public static function factory($count = null, $state = []);
```
---
### TravelClickMessageHistory
**Type:** Class
**Full Name:** `App\TravelClick\Models\TravelClickMessageHistory`
**Description:** TravelClickMessageHistory Model
#### Properties
```php
public $timestamps;
```
#### Methods
```php
public function travelClickLog(): Illuminate\Database\Eloquent\Relations\BelongsTo;
public function parentMessage(): Illuminate\Database\Eloquent\Relations\BelongsTo;
public function childMessages(): Illuminate\Database\Eloquent\Relations\HasMany;
public function systemUser(): Illuminate\Database\Eloquent\Relations\BelongsTo;
public function batchMessages(): Illuminate\Database\Eloquent\Relations\HasMany;
public function scopeOfType(Illuminate\Database\Eloquent\Builder $query, MessageType $messageType): Illuminate\Database\Eloquent\Builder;
public function scopeDirection(Illuminate\Database\Eloquent\Builder $query, MessageDirection $direction): Illuminate\Database\Eloquent\Builder;
public function scopeForProperty(Illuminate\Database\Eloquent\Builder $query, int $propertyId): Illuminate\Database\Eloquent\Builder;
public function scopeWithStatus(Illuminate\Database\Eloquent\Builder $query, ProcessingStatus $status): Illuminate\Database\Eloquent\Builder;
public function scopeRecent(Illuminate\Database\Eloquent\Builder $query, int $hours = 24): Illuminate\Database\Eloquent\Builder;
public function scopeBatchMessages(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder;
public function scopeSlowMessages(Illuminate\Database\Eloquent\Builder $query, int $thresholdSeconds = 30): Illuminate\Database\Eloquent\Builder;
public function scopeContainingXml(Illuminate\Database\Eloquent\Builder $query, string $xmlFragment): Illuminate\Database\Eloquent\Builder;
public function scopeMessageThread(Illuminate\Database\Eloquent\Builder $query, string $messageId): Illuminate\Database\Eloquent\Builder;
public function getXmlPreviewAttribute(): string;
public function getProcessingTimeDisplayAttribute(): string;
public function getIsBatchMessageAttribute(): bool;
public function getMessageSummaryAttribute(): array;
public static function createEntry(array $data): self;
public static function extractKeyDataFromXml(string $xml): array;
public function markAsSent(): void;
public function markAsReceived(string|null $responseXml = null): void;
public function markAsProcessed(string|null $notes = null): void;
public function markAsFailed(string $error): void;
public static function findDuplicatesByHash(string $xmlHash): Illuminate\Database\Eloquent\Collection;
public static function getMessageStats(int $propertyId, int $days = 7): array;
public static function getBatchSummary(string $batchId): array;
public static function cleanup(int $daysToKeep = 30): int;
public static function exportMessages(int $propertyId, Carbon\Carbon $startDate, Carbon\Carbon $endDate, array $messageTypes = []): Illuminate\Database\Eloquent\Collection;
public static function factory($count = null, $state = []);
```
---
### TravelClickPropertyConfig
**Type:** Class
**Full Name:** `App\TravelClick\Models\TravelClickPropertyConfig`
**Description:** TravelClick Property Configuration Model
#### Methods
```php
public function property(): Illuminate\Database\Eloquent\Relations\BelongsTo;
public function scopeActive($query);
public function scopeNeedsSync($query, int $hoursThreshold = 24);
public function scopeForProperties($query, array $propertyIds);
public function getConfigValue(string $key, mixed $default = null): mixed;
public function setConfigValue(string $key, mixed $value): self;
public function hasRequiredFields(): bool;
public function markAsSynced(): self;
public function getConfigForLogging(): array;
public function mergeConfig(array $newConfig): self;
public function validateConfig(): array;
public static function getDueForHealthCheck(int $intervalHours = 6): Illuminate\Support\Collection;
public function updateHealthCheck(bool $healthy = true): self;
public function isHealthy(): bool;
public function getLastHealthCheck(): Carbon\Carbon|null;
public function forceDelete();
public static function forceDestroy($ids);
public static function factory($count = null, $state = []);
public static function bootSoftDeletes();
public function initializeSoftDeletes();
public function forceDeleteQuietly();
public function restore();
public function restoreQuietly();
public function trashed();
public static function softDeleted($callback);
public static function restoring($callback);
public static function restored($callback);
public static function forceDeleting($callback);
public static function forceDeleted($callback);
public function isForceDeleting();
public function getDeletedAtColumn();
public function getQualifiedDeletedAtColumn();
```
---
### TravelClickPropertyMapping
**Type:** Class
**Full Name:** `App\TravelClick\Models\TravelClickPropertyMapping`
**Description:** TravelClickPropertyMapping Model
#### Properties
```php
public $timestamps;
```
#### Methods
```php
public function property(): Illuminate\Database\Eloquent\Relations\BelongsTo;
public function systemUser(): Illuminate\Database\Eloquent\Relations\BelongsTo;
public function lastModifiedByUser(): Illuminate\Database\Eloquent\Relations\BelongsTo;
public function travelClickLogs(): Illuminate\Database\Eloquent\Relations\HasMany;
public function recentLogs();
public function scopeActive(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder;
public function scopeWithSyncStatus(Illuminate\Database\Eloquent\Builder $query, SyncStatus $status): Illuminate\Database\Eloquent\Builder;
public function scopeStaleSync(Illuminate\Database\Eloquent\Builder $query, int $hours = 24): Illuminate\Database\Eloquent\Builder;
public function scopeNeedsAttention(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder;
public function scopeSearch(Illuminate\Database\Eloquent\Builder $query, string $search): Illuminate\Database\Eloquent\Builder;
public function scopeByPropertyGroup(Illuminate\Database\Eloquent\Builder $query, int $propertyGroupId): Illuminate\Database\Eloquent\Builder;
public function getFormattedHotelCodeAttribute(): string;
public function getIsSyncHealthyAttribute(): bool;
public function getDaysSinceLastSyncAttribute(): int;
public function getSyncHealthStatusAttribute(): array;
public static function createMapping(array $data): self;
public static function findByHotelCode(string $hotelCode): self|null;
public static function findByPropertyId(int $propertyId): self|null;
public static function getNeedingSyncMappings(): Illuminate\Database\Eloquent\Collection;
public function markSyncStarted(): void;
public function markSyncSuccess(string|null $notes = null): void;
public function markSyncFailed(string $error): void;
public function markSyncError(string $error): void;
public function getConfigValue(string $key, $default = null);
public function setConfigValue(string $key, $value): void;
public function updateConfiguration(array $config): void;
public function resetConfiguration(): void;
public static function getDefaultConfiguration(): array;
public function deactivate(string|null $reason = null): void;
public function reactivate(): void;
public static function getSyncStatistics(): array;
public static function getHealthReport(): array;
public static function exportMappings(array $propertyIds = []): array;
public static function bulkUpdateSyncStatus(array $mappingIds, SyncStatus $status, string|null $error = null): int;
public static function bulkDeactivate(array $mappingIds, string|null $reason = null): int;
public static function cleanupInactiveMappings(int $daysOld = 365): int;
public static function factory($count = null, $state = []);
```
---
### TravelClickSyncStatus
**Type:** Class
**Full Name:** `App\TravelClick\Models\TravelClickSyncStatus`
**Description:** TravelClick Sync Status Model
#### Properties
```php
public $timestamps;
```
#### Methods
```php
public function property(): Illuminate\Database\Eloquent\Relations\BelongsTo;
public function lastSyncUser(): Illuminate\Database\Eloquent\Relations\BelongsTo;
public function travelClickLogs(): Illuminate\Database\Eloquent\Relations\HasMany;
public function recentErrorLogs(): Illuminate\Database\Eloquent\Relations\HasMany;
public function scopeActive(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder;
public function scopeForProperty(Illuminate\Database\Eloquent\Builder $query, int $propertyId): Illuminate\Database\Eloquent\Builder;
public function scopeOfType(Illuminate\Database\Eloquent\Builder $query, MessageType $messageType): Illuminate\Database\Eloquent\Builder;
public function scopeWithStatus(Illuminate\Database\Eloquent\Builder $query, SyncStatus $status): Illuminate\Database\Eloquent\Builder;
public function scopeNeedsRetry(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder;
public function scopeLongRunning(Illuminate\Database\Eloquent\Builder $query, int $minutes = 30): Illuminate\Database\Eloquent\Builder;
public function scopeRecentFailures(Illuminate\Database\Eloquent\Builder $query, int $hours = 24): Illuminate\Database\Eloquent\Builder;
public function scopeLowSuccessRate(Illuminate\Database\Eloquent\Builder $query, float $threshold = 80.0): Illuminate\Database\Eloquent\Builder;
public function isRunning(): bool;
public function hasFailed(): bool;
public function canRetry(): bool;
public function isOverdueForRetry(): bool;
public function getProgressPercentage(): int;
public function getTimeSinceLastSync(): string;
public function getTimeSinceLastSuccess(): string;
public function markAsStarted(int|null $totalRecords = null, int|null $userId = null): self;
public function updateProgress(int $processed, string|null $messageId = null): self;
public function markAsCompleted(int|null $finalProcessed = null, array $context = []): self;
public function markAsFailed(string $errorMessage, array $context = []): self;
public function resetForRetry(int|null $userId = null): self;
public function disableAutoRetry(): self;
public function enableAutoRetry(): self;
public function scheduleNextRetry(int|null $delayMinutes = null): self;
public function getStatusWithColorAttribute(): array;
public function getOperationsSummaryAttribute(): array;
public function getSyncHealthScoreAttribute(): int;
public static function findOrCreateForProperty(int $propertyId, MessageType $messageType, array $attributes = []): self;
public static function getPropertyStats(int $propertyId, int $days = 30): array;
public static function getNeedsAttention(int|null $propertyId = null): array;
public static function getSystemHealthReport(): array;
public function getPropertyInfo(): array|null;
public static function factory($count = null, $state = []);
```
## Detailed Documentation
For detailed documentation of each class, see:
- [TravelClickErrorLog](TravelClickErrorLog.md)
- [TravelClickLog](TravelClickLog.md)
- [TravelClickMessageHistory](TravelClickMessageHistory.md)
- [TravelClickPropertyConfig](TravelClickPropertyConfig.md)
- [TravelClickPropertyMapping](TravelClickPropertyMapping.md)
- [TravelClickSyncStatus](TravelClickSyncStatus.md)