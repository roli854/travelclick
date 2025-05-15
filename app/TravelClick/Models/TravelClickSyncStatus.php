<?php

namespace App\TravelClick\Models;

use App\Models\SystemUser;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\SyncStatus as SyncStatusEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\LaravelData\Data;

/**
 * TravelClick Sync Status Model
 *
 * Tracks the synchronization status for each property and message type.
 * This model is like a "status board" that shows the current state of all
 * sync operations, helping identify what needs attention and what's running smoothly.
 *
 * @property int $SyncStatusID
 * @property int $PropertyID
 * @property string $MessageType
 * @property string $Status
 * @property Carbon|null $LastSyncAttempt
 * @property Carbon|null $LastSuccessfulSync
 * @property int $RetryCount
 * @property int $MaxRetries
 * @property Carbon|null $NextRetryAt
 * @property string|null $ErrorMessage
 * @property string|null $LastMessageID
 * @property int|null $RecordsProcessed
 * @property int|null $RecordsTotal
 * @property float|null $SuccessRate
 * @property bool $IsActive
 * @property bool $AutoRetryEnabled
 * @property int|null $LastSyncByUserID
 * @property Carbon $DateCreated
 * @property Carbon|null $DateModified
 * @property string|null $Context
 */
class TravelClickSyncStatus extends Model
{
    use HasFactory;

    /**
     * Database connection - uses CentriumLog database
     */
    protected $connection = 'centriumLog';

    /**
     * Table name following Centrium conventions
     */
    protected $table = 'TravelClickSyncStatus';

    /**
     * Primary key following Centrium conventions
     */
    protected $primaryKey = 'SyncStatusID';

    /**
     * Disable Laravel timestamps as we use Centrium conventions
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable
     */
    protected $fillable = [
        'PropertyID',
        'MessageType',
        'Status',
        'LastSyncAttempt',
        'LastSuccessfulSync',
        'RetryCount',
        'MaxRetries',
        'NextRetryAt',
        'ErrorMessage',
        'LastMessageID',
        'RecordsProcessed',
        'RecordsTotal',
        'SuccessRate',
        'IsActive',
        'AutoRetryEnabled',
        'LastSyncByUserID',
        'Context',
    ];

    /**
     * The attributes that should be cast to native types
     */
    protected $casts = [
        'PropertyID' => 'integer',
        'MessageType' => MessageType::class,
        'Status' => SyncStatusEnum::class,
        'LastSyncAttempt' => 'datetime',
        'LastSuccessfulSync' => 'datetime',
        'NextRetryAt' => 'datetime',
        'RetryCount' => 'integer',
        'MaxRetries' => 'integer',
        'RecordsProcessed' => 'integer',
        'RecordsTotal' => 'integer',
        'SuccessRate' => 'float',
        'IsActive' => 'boolean',
        'AutoRetryEnabled' => 'boolean',
        'LastSyncByUserID' => 'integer',
        'DateCreated' => 'datetime',
        'DateModified' => 'datetime',
        'Context' => 'json',
    ];

    /**
     * Default values for new records
     */
    protected $attributes = [
        'RetryCount' => 0,
        'MaxRetries' => 3,
        'SuccessRate' => 0.0,
        'IsActive' => true,
        'AutoRetryEnabled' => true,
    ];

    /**
     * Boot method - handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // Set DateCreated automatically on creation
        static::creating(function ($model) {
            $model->DateCreated = now();
        });

        // Set DateModified automatically on updates
        static::updating(function ($model) {
            $model->DateModified = now();
        });
    }

    /**
     * Relationship: User who performed the last sync
     */
    public function lastSyncUser(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'LastSyncByUserID', 'SystemUserID');
    }

    /**
     * Relationship: Related TravelClick logs
     */
    public function travelClickLogs(): HasMany
    {
        return $this->hasMany(TravelClickLog::class, 'PropertyID', 'PropertyID')
            ->where('MessageType', $this->MessageType);
    }

    /**
     * Relationship: Recent error logs for this sync
     */
    public function recentErrorLogs(): HasMany
    {
        return $this->hasMany(TravelClickErrorLog::class, 'PropertyID', 'PropertyID')
            ->where('ErrorContext->message_type', $this->MessageType)
            ->where('DateCreated', '>=', now()->subDays(7));
    }

    /**
     * Scope: Active sync statuses only
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('IsActive', true);
    }

    /**
     * Scope: Filter by specific property
     */
    public function scopeForProperty(Builder $query, int $propertyId): Builder
    {
        return $query->where('PropertyID', $propertyId);
    }

    /**
     * Scope: Filter by message type
     */
    public function scopeOfType(Builder $query, MessageType $messageType): Builder
    {
        return $query->where('MessageType', $messageType);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeWithStatus(Builder $query, SyncStatusEnum $status): Builder
    {
        return $query->where('Status', $status);
    }

    /**
     * Scope: Records that need retry
     */
    public function scopeNeedsRetry(Builder $query): Builder
    {
        return $query->where('Status', SyncStatusEnum::FAILED)
            ->where('AutoRetryEnabled', true)
            ->where('RetryCount', '<', 'MaxRetries')
            ->where(function ($q) {
                $q->whereNull('NextRetryAt')
                    ->orWhere('NextRetryAt', '<=', now());
            });
    }

    /**
     * Scope: Long running syncs
     */
    public function scopeLongRunning(Builder $query, int $minutes = 30): Builder
    {
        return $query->where('Status', SyncStatusEnum::RUNNING)
            ->where('LastSyncAttempt', '<=', now()->subMinutes($minutes));
    }

    /**
     * Scope: Failed syncs in the last period
     */
    public function scopeRecentFailures(Builder $query, int $hours = 24): Builder
    {
        return $query->where('Status', SyncStatusEnum::FAILED)
            ->where('LastSyncAttempt', '>=', now()->subHours($hours));
    }

    /**
     * Scope: Sync statuses with low success rate
     */
    public function scopeLowSuccessRate(Builder $query, float $threshold = 80.0): Builder
    {
        return $query->where('SuccessRate', '<', $threshold)
            ->whereNotNull('LastSuccessfulSync');
    }

    /**
     * Check if sync is currently running
     */
    public function isRunning(): bool
    {
        return $this->Status === SyncStatusEnum::RUNNING;
    }

    /**
     * Check if sync has failed
     */
    public function hasFailed(): bool
    {
        return $this->Status === SyncStatusEnum::FAILED;
    }

    /**
     * Check if sync can be retried
     */
    public function canRetry(): bool
    {
        return $this->hasFailed() &&
            $this->AutoRetryEnabled &&
            $this->RetryCount < $this->MaxRetries;
    }

    /**
     * Check if sync is overdue for a retry
     */
    public function isOverdueForRetry(): bool
    {
        return $this->canRetry() &&
            ($this->NextRetryAt === null || $this->NextRetryAt <= now());
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): int
    {
        if ($this->RecordsTotal === null || $this->RecordsTotal === 0) {
            return 0;
        }

        return min(100, (int) round(($this->RecordsProcessed ?? 0) / $this->RecordsTotal * 100));
    }

    /**
     * Get formatted duration since last sync attempt
     */
    public function getTimeSinceLastSync(): string
    {
        if (!$this->LastSyncAttempt) {
            return 'Never';
        }

        return $this->LastSyncAttempt->diffForHumans();
    }

    /**
     * Get formatted duration since last successful sync
     */
    public function getTimeSinceLastSuccess(): string
    {
        if (!$this->LastSuccessfulSync) {
            return 'Never';
        }

        return $this->LastSuccessfulSync->diffForHumans();
    }

    /**
     * Mark sync as started
     */
    public function markAsStarted(int $totalRecords = null, int $userId = null): self
    {
        $this->update([
            'Status' => SyncStatusEnum::RUNNING,
            'LastSyncAttempt' => now(),
            'RecordsProcessed' => 0,
            'RecordsTotal' => $totalRecords,
            'LastSyncByUserID' => $userId,
            'Context' => array_merge($this->Context ?? [], [
                'started_at' => now()->toISOString(),
                'total_records' => $totalRecords,
            ]),
        ]);

        return $this;
    }

    /**
     * Update progress during sync
     */
    public function updateProgress(int $processed, string $messageId = null): self
    {
        $updates = [
            'RecordsProcessed' => $processed,
            'LastMessageID' => $messageId,
        ];

        // Calculate success rate if we have historical data
        if ($this->RecordsTotal && $processed > 0) {
            $successRate = ($processed / $this->RecordsTotal) * 100;
            $updates['SuccessRate'] = round($successRate, 2);
        }

        $this->update($updates);

        return $this;
    }

    /**
     * Mark sync as completed successfully
     */
    public function markAsCompleted(int $finalProcessed = null, array $context = []): self
    {
        $processed = $finalProcessed ?? $this->RecordsProcessed ?? 0;
        $total = $this->RecordsTotal ?? $processed;

        $this->update([
            'Status' => SyncStatusEnum::COMPLETED,
            'LastSuccessfulSync' => now(),
            'RecordsProcessed' => $processed,
            'SuccessRate' => $total > 0 ? round(($processed / $total) * 100, 2) : 100.0,
            'RetryCount' => 0, // Reset retry count on success
            'NextRetryAt' => null,
            'ErrorMessage' => null,
            'Context' => array_merge($this->Context ?? [], $context, [
                'completed_at' => now()->toISOString(),
                'final_success_rate' => round(($processed / $total) * 100, 2),
            ]),
        ]);

        return $this;
    }

    /**
     * Mark sync as failed
     */
    public function markAsFailed(string $errorMessage, array $context = []): self
    {
        $retryCount = $this->RetryCount + 1;
        $nextRetryAt = null;

        // Calculate next retry time if auto-retry is enabled
        if ($this->AutoRetryEnabled && $retryCount <= $this->MaxRetries) {
            $delayMinutes = min(60, pow(2, $retryCount - 1) * 5); // Exponential backoff
            $nextRetryAt = now()->addMinutes($delayMinutes);
        }

        $this->update([
            'Status' => SyncStatusEnum::FAILED,
            'ErrorMessage' => $errorMessage,
            'RetryCount' => $retryCount,
            'NextRetryAt' => $nextRetryAt,
            'Context' => array_merge($this->Context ?? [], $context, [
                'failed_at' => now()->toISOString(),
                'retry_count' => $retryCount,
                'next_retry_at' => $nextRetryAt?->toISOString(),
            ]),
        ]);

        return $this;
    }

    /**
     * Reset for manual retry
     */
    public function resetForRetry(int $userId = null): self
    {
        $this->update([
            'Status' => SyncStatusEnum::PENDING,
            'NextRetryAt' => null,
            'ErrorMessage' => null,
            'RecordsProcessed' => 0,
            'LastSyncByUserID' => $userId,
            'Context' => array_merge($this->Context ?? [], [
                'manually_reset_at' => now()->toISOString(),
                'reset_by_user_id' => $userId,
            ]),
        ]);

        return $this;
    }

    /**
     * Disable auto-retry
     */
    public function disableAutoRetry(): self
    {
        $this->update([
            'AutoRetryEnabled' => false,
            'NextRetryAt' => null,
        ]);

        return $this;
    }

    /**
     * Enable auto-retry
     */
    public function enableAutoRetry(): self
    {
        $this->update(['AutoRetryEnabled' => true]);

        // Schedule next retry if currently failed
        if ($this->hasFailed() && $this->canRetry()) {
            $this->scheduleNextRetry();
        }

        return $this;
    }

    /**
     * Schedule next retry attempt
     */
    public function scheduleNextRetry(int $delayMinutes = null): self
    {
        if (!$delayMinutes) {
            $delayMinutes = min(60, pow(2, $this->RetryCount) * 5);
        }

        $this->update([
            'NextRetryAt' => now()->addMinutes($delayMinutes),
        ]);

        return $this;
    }

    /**
     * Get status with color for UI display
     */
    public function getStatusWithColorAttribute(): array
    {
        return [
            'status' => $this->Status->value,
            'label' => $this->Status->label(),
            'color' => $this->Status->color(),
            'icon' => $this->Status->icon(),
        ];
    }

    /**
     * Get operations summary for dashboard
     */
    public function getOperationsSummaryAttribute(): array
    {
        return [
            'property_id' => $this->PropertyID,
            'message_type' => $this->MessageType->label(),
            'status' => $this->Status->label(),
            'progress' => $this->getProgressPercentage(),
            'success_rate' => $this->SuccessRate,
            'last_sync' => $this->getTimeSinceLastSync(),
            'last_success' => $this->getTimeSinceLastSuccess(),
            'can_retry' => $this->canRetry(),
            'retry_count' => $this->RetryCount,
            'max_retries' => $this->MaxRetries,
        ];
    }

    /**
     * Get sync health score (0-100)
     * Based on success rate, retry count, and time since last success
     */
    public function getSyncHealthScoreAttribute(): int
    {
        $score = 100;

        // Penalize low success rate
        if ($this->SuccessRate !== null) {
            $score = min($score, $this->SuccessRate);
        }

        // Penalize high retry count
        if ($this->RetryCount > 0) {
            $penalty = min(30, $this->RetryCount * 10);
            $score -= $penalty;
        }

        // Penalize old last successful sync
        if ($this->LastSuccessfulSync) {
            $daysSinceSuccess = $this->LastSuccessfulSync->diffInDays(now());
            if ($daysSinceSuccess > 1) {
                $penalty = min(40, $daysSinceSuccess * 5);
                $score -= $penalty;
            }
        } else {
            $score -= 50; // Heavy penalty for never synced
        }

        // Penalize failed status
        if ($this->hasFailed()) {
            $score -= 20;
        }

        return max(0, (int) $score);
    }

    /**
     * Create or find sync status for a property and message type
     */
    public static function findOrCreateForProperty(
        int $propertyId,
        MessageType $messageType,
        array $attributes = []
    ): self {
        return static::firstOrCreate(
            [
                'PropertyID' => $propertyId,
                'MessageType' => $messageType,
            ],
            array_merge([
                'Status' => SyncStatusEnum::PENDING,
                'MaxRetries' => 3,
                'IsActive' => true,
                'AutoRetryEnabled' => true,
            ], $attributes)
        );
    }

    /**
     * Get sync statistics for a property
     */
    public static function getPropertyStats(int $propertyId, int $days = 30): array
    {
        $syncs = static::forProperty($propertyId)
            ->where('LastSyncAttempt', '>=', now()->subDays($days))
            ->get();

        $totalSyncs = $syncs->count();
        $successfulSyncs = $syncs->where('Status', SyncStatusEnum::COMPLETED)->count();
        $failedSyncs = $syncs->where('Status', SyncStatusEnum::FAILED)->count();
        $runningSyncs = $syncs->where('Status', SyncStatusEnum::RUNNING)->count();

        $avgSuccessRate = $syncs->where('SuccessRate', '>', 0)->avg('SuccessRate') ?? 0;
        $avgHealthScore = $syncs->avg(function ($sync) {
            return $sync->getSyncHealthScoreAttribute();
        });

        return [
            'total_syncs' => $totalSyncs,
            'successful_syncs' => $successfulSyncs,
            'failed_syncs' => $failedSyncs,
            'running_syncs' => $runningSyncs,
            'success_rate_overall' => $totalSyncs > 0 ? round(($successfulSyncs / $totalSyncs) * 100, 2) : 0,
            'average_success_rate' => round($avgSuccessRate, 2),
            'average_health_score' => round($avgHealthScore, 2),
            'by_message_type' => $syncs->groupBy('MessageType')->map(function ($group, $type) {
                return [
                    'count' => $group->count(),
                    'success_rate' => round($group->avg('SuccessRate') ?? 0, 2),
                    'health_score' => round($group->avg(function ($sync) {
                        return $sync->getSyncHealthScoreAttribute();
                    }), 2),
                ];
            }),
        ];
    }

    /**
     * Get syncs that need attention (failed, long running, low success rate)
     */
    public static function getNeedsAttention(int $propertyId = null): array
    {
        $query = static::query();

        if ($propertyId) {
            $query->forProperty($propertyId);
        }

        return [
            'failed_syncs' => $query->clone()->withStatus(SyncStatusEnum::FAILED)->get(),
            'long_running' => $query->clone()->longRunning(30)->get(),
            'low_success_rate' => $query->clone()->lowSuccessRate(80)->get(),
            'needs_retry' => $query->clone()->needsRetry()->get(),
        ];
    }

    /**
     * Get system-wide sync health report
     */
    public static function getSystemHealthReport(): array
    {
        $allSyncs = static::active()->get();
        $totalSyncs = $allSyncs->count();

        $healthStats = [
            'healthy' => $allSyncs->filter(fn($sync) => $sync->getSyncHealthScoreAttribute() >= 80)->count(),
            'warning' => $allSyncs->filter(fn($sync) => $sync->getSyncHealthScoreAttribute() >= 60 && $sync->getSyncHealthScoreAttribute() < 80)->count(),
            'critical' => $allSyncs->filter(fn($sync) => $sync->getSyncHealthScoreAttribute() < 60)->count(),
        ];

        $overallHealth = $totalSyncs > 0 ? $allSyncs->avg(function ($sync) {
            return $sync->getSyncHealthScoreAttribute();
        }) : 0;

        return [
            'overall_health_score' => round($overallHealth, 2),
            'total_syncs' => $totalSyncs,
            'health_distribution' => $healthStats,
            'status_distribution' => [
                'completed' => $allSyncs->where('Status', SyncStatusEnum::COMPLETED)->count(),
                'running' => $allSyncs->where('Status', SyncStatusEnum::RUNNING)->count(),
                'failed' => $allSyncs->where('Status', SyncStatusEnum::FAILED)->count(),
                'pending' => $allSyncs->where('Status', SyncStatusEnum::PENDING)->count(),
            ],
            'retry_statistics' => [
                'needs_retry' => $allSyncs->filter(fn($sync) => $sync->canRetry())->count(),
                'max_retries_reached' => $allSyncs->filter(fn($sync) => $sync->RetryCount >= $sync->MaxRetries)->count(),
                'auto_retry_disabled' => $allSyncs->where('AutoRetryEnabled', false)->count(),
            ],
        ];
    }
}
