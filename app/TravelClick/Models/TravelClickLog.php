<?php

namespace App\TravelClick\Models;

use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\SyncStatus;
use App\TravelClick\Enums\MessageDirection;
use App\TravelClick\Enums\ErrorType;
use App\Models\SystemUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * TravelClick Log Model
 *
 * This model represents the main audit log for all TravelClick operations.
 * It's like a detailed journal of every interaction with TravelClick.
 *
 * @property int $TravelClickLogID
 * @property string $MessageID
 * @property MessageDirection $Direction
 * @property MessageType $MessageType
 * @property int $PropertyID
 * @property string|null $HotelCode
 * @property string|null $RequestXML
 * @property string|null $ResponseXML
 * @property SyncStatus $Status
 * @property ErrorType|null $ErrorType
 * @property string|null $ErrorMessage
 * @property int $RetryCount
 * @property Carbon $StartedAt
 * @property Carbon|null $CompletedAt
 * @property int|null $DurationMs
 * @property int $SystemUserID
 * @property Carbon $DateCreated
 * @property Carbon $DateModified
 * @property array|null $Metadata
 * @property string|null $JobID
 */
class TravelClickLog extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     * Using CentriumLog database for all TravelClick logs.
     */
    protected $connection = 'centriumLog';

    /**
     * The table associated with the model.
     * Following Centrium naming convention with PascalCase.
     */
    protected $table = 'TravelClickLogs';

    /**
     * The primary key for the model.
     * Following Centrium convention of [TableName]ID
     */
    protected $primaryKey = 'TravelClickLogID';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'MessageID',
        'Direction',
        'MessageType',
        'PropertyID',
        'HotelCode',
        'RequestXML',
        'ResponseXML',
        'Status',
        'ErrorType',
        'ErrorMessage',
        'RetryCount',
        'StartedAt',
        'CompletedAt',
        'DurationMs',
        'SystemUserID',
        'Metadata',
        'JobID'
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'RetryCount' => 0,
        'SystemUserID' => 0,
        'Status' => 'pending'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'Direction' => MessageDirection::class,
        'MessageType' => MessageType::class,
        'Status' => SyncStatus::class,
        'ErrorType' => ErrorType::class,
        'Metadata' => 'array',
        'StartedAt' => 'datetime',
        'CompletedAt' => 'datetime',
        'DateCreated' => 'datetime',
        'DateModified' => 'datetime',
        'PropertyID' => 'integer',
        'SystemUserID' => 'integer',
        'RetryCount' => 'integer',
        'DurationMs' => 'integer'
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'RequestXML',   // Can be large, hide by default
        'ResponseXML'   // Can be large, hide by default
    ];

    /**
     * Laravel timestamp configuration.
     * Using custom timestamp field names following Centrium convention.
     */
    const CREATED_AT = 'DateCreated';
    const UPDATED_AT = 'DateModified';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically set timestamps when creating
        static::creating(function ($model) {
            if (is_null($model->DateCreated)) {
                $model->DateCreated = now();
            }
            if (is_null($model->StartedAt)) {
                $model->StartedAt = now();
            }
        });

        // Update DateModified when updating
        static::updating(function ($model) {
            $model->DateModified = now();
        });
    }

    /**
     * Get the system user who initiated this operation.
     */
    public function systemUser(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'SystemUserID', 'SystemUserID');
    }

    /**
     * Get the property this log entry relates to.
     * Note: This assumes you have a Property model in Centrium.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Property::class, 'PropertyID', 'PropertyID');
    }

    /**
     * Get all error logs associated with this operation.
     */
    public function errorLogs(): HasMany
    {
        return $this->hasMany(TravelClickErrorLog::class, 'MessageID', 'MessageID');
    }

    /**
     * Get message history entries for this log.
     */
    public function messageHistory(): HasMany
    {
        return $this->hasMany(TravelClickMessageHistory::class, 'MessageID', 'MessageID');
    }

    // Scopes for common queries

    /**
     * Scope to filter by direction (inbound/outbound).
     */
    public function scopeDirection(Builder $query, MessageDirection $direction): Builder
    {
        return $query->where('Direction', $direction);
    }

    /**
     * Scope to filter by message type.
     */
    public function scopeByType(Builder $query, MessageType $messageType): Builder
    {
        return $query->where('MessageType', $messageType);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus(Builder $query, SyncStatus $status): Builder
    {
        return $query->where('Status', $status);
    }

    /**
     * Scope to filter pending operations.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('Status', SyncStatus::PENDING);
    }

    /**
     * Scope to filter completed operations.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('Status', SyncStatus::COMPLETED);
    }

    /**
     * Scope to filter failed operations.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('Status', SyncStatus::FAILED);
    }

    /**
     * Scope to filter by property.
     */
    public function scopeForProperty(Builder $query, int $propertyId): Builder
    {
        return $query->where('PropertyID', $propertyId);
    }

    /**
     * Scope to filter by hotel code.
     */
    public function scopeForHotel(Builder $query, string $hotelCode): Builder
    {
        return $query->where('HotelCode', $hotelCode);
    }

    /**
     * Scope to filter recent logs.
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('DateCreated', '>=', now()->subHours($hours));
    }

    /**
     * Scope to filter operations with errors.
     */
    public function scopeWithErrors(Builder $query): Builder
    {
        return $query->whereNotNull('ErrorType')
            ->orWhereHas('errorLogs');
    }

    /**
     * Scope to filter operations that need retry.
     */
    public function scopeNeedsRetry(Builder $query): Builder
    {
        return $query->where('Status', SyncStatus::FAILED)
            ->where('RetryCount', '<', 3); // Assuming max 3 retries
    }

    /**
     * Scope to filter long-running operations.
     */
    public function scopeLongRunning(Builder $query, int $thresholdMs = 30000): Builder
    {
        return $query->where('DurationMs', '>', $thresholdMs);
    }

    // Accessor methods

    /**
     * Get the operation duration in a human-readable format.
     */
    public function getFormattedDurationAttribute(): string
    {
        if (is_null($this->DurationMs)) {
            return 'Pending';
        }

        if ($this->DurationMs < 1000) {
            return $this->DurationMs . 'ms';
        }

        return round($this->DurationMs / 1000, 2) . 's';
    }

    /**
     * Get status with color for UI display.
     */
    public function getStatusWithColorAttribute(): array
    {
        return [
            'status' => $this->Status->value,
            'label' => $this->Status->getLabel(),
            'color' => $this->Status->getColor()
        ];
    }

    /**
     * Get a summary of the operation for quick display.
     */
    public function getOperationSummaryAttribute(): string
    {
        $direction = ucfirst($this->Direction->value);
        $type = $this->MessageType->getLabel();
        $status = $this->Status->getLabel();

        return "{$direction} {$type} - {$status}";
    }

    /**
     * Check if this operation was successful.
     */
    public function getIsSuccessfulAttribute(): bool
    {
        return $this->Status === SyncStatus::COMPLETED;
    }

    /**
     * Check if this operation is still running.
     */
    public function getIsRunningAttribute(): bool
    {
        return in_array($this->Status, [SyncStatus::PENDING, SyncStatus::PROCESSING]);
    }

    /**
     * Check if operation has taken too long (more than expected).
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->is_running) {
            $runningTime = now()->diffInMinutes($this->StartedAt);
            return $runningTime > 5; // Consider overdue after 5 minutes
        }
        return false;
    }

    /**
     * Get XML content safely (truncated if too large).
     */
    public function getTruncatedRequestXmlAttribute(): string
    {
        if (is_null($this->RequestXML)) {
            return '';
        }

        return strlen($this->RequestXML) > 1000
            ? substr($this->RequestXML, 0, 1000) . '...'
            : $this->RequestXML;
    }

    /**
     * Get XML content safely (truncated if too large).
     */
    public function getTruncatedResponseXmlAttribute(): string
    {
        if (is_null($this->ResponseXML)) {
            return '';
        }

        return strlen($this->ResponseXML) > 1000
            ? substr($this->ResponseXML, 0, 1000) . '...'
            : $this->ResponseXML;
    }

    // Helper methods

    /**
     * Mark the operation as started.
     */
    public function markAsStarted(): bool
    {
        $this->Status = SyncStatus::PROCESSING;
        $this->StartedAt = now();
        return $this->save();
    }

    /**
     * Mark the operation as completed successfully.
     */
    public function markAsCompleted(string $responseXml = null): bool
    {
        $this->Status = SyncStatus::COMPLETED;
        $this->CompletedAt = now();

        if ($this->StartedAt) {
            $this->DurationMs = $this->StartedAt->diffInMilliseconds($this->CompletedAt);
        }

        if ($responseXml) {
            $this->ResponseXML = $responseXml;
        }

        return $this->save();
    }

    /**
     * Mark the operation as failed.
     */
    public function markAsFailed(ErrorType $errorType, string $errorMessage, string $responseXml = null): bool
    {
        $this->Status = SyncStatus::FAILED;
        $this->ErrorType = $errorType;
        $this->ErrorMessage = $errorMessage;
        $this->CompletedAt = now();

        if ($this->StartedAt) {
            $this->DurationMs = $this->StartedAt->diffInMilliseconds($this->CompletedAt);
        }

        if ($responseXml) {
            $this->ResponseXML = $responseXml;
        }

        return $this->save();
    }

    /**
     * Increment retry count.
     */
    public function incrementRetryCount(): bool
    {
        $this->RetryCount++;
        $this->Status = SyncStatus::PENDING; // Reset to pending for retry
        return $this->save();
    }

    /**
     * Add metadata to the log entry.
     */
    public function addMetadata(array $data): bool
    {
        $currentMetadata = $this->Metadata ?? [];
        $this->Metadata = array_merge($currentMetadata, $data);
        return $this->save();
    }

    /**
     * Create a new TravelClick log entry.
     */
    public static function createLog(
        string $messageId,
        MessageDirection $direction,
        MessageType $messageType,
        int $propertyId,
        string $hotelCode = null,
        string $requestXml = null,
        array $metadata = [],
        string $jobId = null,
        int $systemUserId = null
    ): self {
        return self::create([
            'MessageID' => $messageId,
            'Direction' => $direction,
            'MessageType' => $messageType,
            'PropertyID' => $propertyId,
            'HotelCode' => $hotelCode,
            'RequestXML' => $requestXml,
            'Status' => SyncStatus::PENDING,
            'StartedAt' => now(),
            'SystemUserID' => $systemUserId ?? auth()->id() ?? 0,
            'Metadata' => $metadata,
            'JobID' => $jobId
        ]);
    }

    /**
     * Get performance statistics for a property.
     */
    public static function getPerformanceStats(int $propertyId, int $days = 7): array
    {
        $query = self::forProperty($propertyId)
            ->where('DateCreated', '>=', now()->subDays($days));

        $total = $query->count();
        $completed = $query->where('Status', SyncStatus::COMPLETED)->count();
        $failed = $query->where('Status', SyncStatus::FAILED)->count();
        $pending = $query->where('Status', SyncStatus::PENDING)->count();

        $avgDuration = $query->whereNotNull('DurationMs')
            ->avg('DurationMs');

        // Get stats by message type
        $byType = $query->groupBy('MessageType')
            ->selectRaw('MessageType, COUNT(*) as count')
            ->pluck('count', 'MessageType')
            ->toArray();

        // Get hourly distribution
        $hourlyDistribution = $query->groupBy(\DB::raw('HOUR(DateCreated)'))
            ->selectRaw('HOUR(DateCreated) as hour, COUNT(*) as count')
            ->pluck('count', 'hour')
            ->toArray();

        return [
            'total_operations' => $total,
            'success_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
            'pending_operations' => $pending,
            'avg_duration_ms' => round($avgDuration ?? 0, 2),
            'by_message_type' => $byType,
            'hourly_distribution' => $hourlyDistribution,
            'period_days' => $days
        ];
    }

    /**
     * Get recent activity summary.
     */
    public static function getRecentActivity(int $hours = 24): array
    {
        $query = self::recent($hours);

        return [
            'total_operations' => $query->count(),
            'successful' => $query->completed()->count(),
            'failed' => $query->failed()->count(),
            'pending' => $query->pending()->count(),
            'inventory_operations' => $query->byType(MessageType::INVENTORY)->count(),
            'rate_operations' => $query->byType(MessageType::RATES)->count(),
            'reservation_operations' => $query->byType(MessageType::RESERVATION)->count(),
            'avg_duration_ms' => $query->whereNotNull('DurationMs')->avg('DurationMs') ?? 0
        ];
    }

    /**
     * Get logs that need attention (failed, overdue, etc.).
     */
    public static function getNeedsAttention(): Builder
    {
        return self::where(function ($query) {
            $query->failed()
                ->orWhere(function ($q) {
                    $q->pending()
                        ->where('StartedAt', '<', now()->subMinutes(5));
                });
        })->orderBy('DateCreated', 'desc');
    }

    /**
     * Clean up old log entries (for maintenance).
     */
    public static function cleanup(int $daysToKeep = 30): int
    {
        $cutoffDate = now()->subDays($daysToKeep);

        return self::where('DateCreated', '<', $cutoffDate)
            ->where('Status', '!=', SyncStatus::FAILED) // Keep failed logs longer
            ->delete();
    }

    /**
     * Get error pattern analysis.
     */
    public static function getErrorPatterns(int $propertyId = null, int $days = 7): array
    {
        $query = self::withErrors()
            ->where('DateCreated', '>=', now()->subDays($days));

        if ($propertyId) {
            $query->forProperty($propertyId);
        }

        $errorsByType = $query->groupBy('ErrorType')
            ->selectRaw('ErrorType, COUNT(*) as count')
            ->pluck('count', 'ErrorType')
            ->toArray();

        $errorsByMessage = $query->groupBy('MessageType')
            ->selectRaw('MessageType, COUNT(*) as count')
            ->pluck('count', 'MessageType')
            ->toArray();

        return [
            'by_error_type' => $errorsByType,
            'by_message_type' => $errorsByMessage,
            'total_errors' => array_sum($errorsByType),
            'period_days' => $days
        ];
    }
}
