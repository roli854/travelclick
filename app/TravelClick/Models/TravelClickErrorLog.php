<?php

namespace App\TravelClick\Models;

use App\TravelClick\Enums\ErrorType;
use App\Models\SystemUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * TravelClick Error Log Model
 *
 * This model represents detailed error tracking for TravelClick operations.
 * It provides comprehensive error analysis, retry logic, and resolution tracking.
 *
 * @property int $ErrorLogID
 * @property string $MessageID
 * @property string|null $JobID
 * @property ErrorType $ErrorType
 * @property string|null $ErrorCode
 * @property string $Severity
 * @property string $ErrorTitle
 * @property string $ErrorMessage
 * @property string|null $StackTrace
 * @property array|null $Context
 * @property string|null $SourceClass
 * @property string|null $SourceMethod
 * @property int|null $SourceLine
 * @property bool $CanRetry
 * @property int|null $RecommendedRetryDelay
 * @property string|null $RecoveryNotes
 * @property bool $RequiresManualIntervention
 * @property Carbon|null $ResolvedAt
 * @property int|null $ResolvedByUserID
 * @property string|null $ResolutionNotes
 * @property int $PropertyID
 * @property int $SystemUserID
 * @property Carbon $DateCreated
 */
class TravelClickErrorLog extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     * Using CentriumLog database for all error logs.
     */
    protected $connection = 'centriumLog';

    /**
     * The table associated with the model.
     * Following Centrium naming convention with PascalCase.
     */
    protected $table = 'TravelClickErrorLogs';

    /**
     * The primary key for the model.
     * Following Centrium convention of [TableName]ID
     */
    protected $primaryKey = 'ErrorLogID';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'MessageID',
        'JobID',
        'ErrorType',
        'ErrorCode',
        'Severity',
        'ErrorTitle',
        'ErrorMessage',
        'StackTrace',
        'Context',
        'SourceClass',
        'SourceMethod',
        'SourceLine',
        'CanRetry',
        'RecommendedRetryDelay',
        'RecoveryNotes',
        'RequiresManualIntervention',
        'ResolvedAt',
        'ResolvedByUserID',
        'ResolutionNotes',
        'PropertyID',
        'SystemUserID'
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'CanRetry' => false,
        'RequiresManualIntervention' => false,
        'SystemUserID' => 0,
        'Severity' => 'medium'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'ErrorType' => ErrorType::class,
        'Context' => 'array',
        'CanRetry' => 'boolean',
        'RequiresManualIntervention' => 'boolean',
        'ResolvedAt' => 'datetime',
        'DateCreated' => 'datetime',
        'SourceLine' => 'integer',
        'RecommendedRetryDelay' => 'integer',
        'PropertyID' => 'integer',
        'SystemUserID' => 'integer',
        'ResolvedByUserID' => 'integer'
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'StackTrace'  // Hide sensitive stack trace information
    ];

    /**
     * Laravel timestamp configuration.
     * Using custom created_at field name following Centrium convention.
     */
    const CREATED_AT = 'DateCreated';
    const UPDATED_AT = null; // No updated_at timestamp in this table

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically set DateCreated when creating
        static::creating(function ($model) {
            if (is_null($model->DateCreated)) {
                $model->DateCreated = now();
            }
        });
    }

    /**
     * Get the travel click log that owns this error.
     */
    public function travelClickLog(): BelongsTo
    {
        return $this->belongsTo(TravelClickLog::class, 'MessageID', 'MessageID');
    }

    /**
     * Get the system user who resolved this error.
     * This would typically relate to a SystemUser model in Centrium.
     */
    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'ResolvedByUserID', 'SystemUserID');
    }

    /**
     * Get the system user who created this log entry.
     * This would typically relate to a SystemUser model in Centrium.
     */
    public function systemUser(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'SystemUserID', 'SystemUserID');
    }

    // Scopes for common queries

    /**
     * Scope to filter by error severity.
     */
    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where('Severity', $severity);
    }

    /**
     * Scope to filter critical errors.
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('Severity', 'critical');
    }

    /**
     * Scope to filter unresolved errors.
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('ResolvedAt');
    }

    /**
     * Scope to filter errors that can be retried.
     */
    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('CanRetry', true);
    }

    /**
     * Scope to filter errors requiring manual intervention.
     */
    public function scopeRequiresManualIntervention(Builder $query): Builder
    {
        return $query->where('RequiresManualIntervention', true);
    }

    /**
     * Scope to filter by property.
     */
    public function scopeForProperty(Builder $query, int $propertyId): Builder
    {
        return $query->where('PropertyID', $propertyId);
    }

    /**
     * Scope to filter by error type.
     */
    public function scopeByType(Builder $query, ErrorType $errorType): Builder
    {
        return $query->where('ErrorType', $errorType);
    }

    /**
     * Scope to filter recent errors.
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('DateCreated', '>=', now()->subHours($hours));
    }

    // Accessor methods

    /**
     * Get the formatted error title for display.
     */
    public function getFormattedErrorTitleAttribute(): string
    {
        return "[{$this->ErrorType->value}] {$this->ErrorTitle}";
    }

    /**
     * Get a shortened version of the error message for listings.
     */
    public function getShortErrorMessageAttribute(): string
    {
        return strlen($this->ErrorMessage) > 100
            ? substr($this->ErrorMessage, 0, 100) . '...'
            : $this->ErrorMessage;
    }

    /**
     * Check if this error is resolved.
     */
    public function getIsResolvedAttribute(): bool
    {
        return !is_null($this->ResolvedAt);
    }

    /**
     * Get human-readable time since error occurred.
     */
    public function getTimeSinceErrorAttribute(): string
    {
        return $this->DateCreated->diffForHumans();
    }

    /**
     * Get the severity color for UI display.
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->Severity) {
            'critical' => '#FF0000', // Red
            'high' => '#FF6600',     // Orange
            'medium' => '#FFCC00',   // Yellow
            'low' => '#66FF66',      // Green
            default => '#CCCCCC'     // Gray
        };
    }

    // Helper methods

    /**
     * Mark this error as resolved by a user.
     */
    public function markAsResolved(int $userId, string $notes = null): bool
    {
        $this->ResolvedAt = now();
        $this->ResolvedByUserID = $userId;

        if ($notes) {
            $this->ResolutionNotes = $notes;
        }

        return $this->save();
    }

    /**
     * Check if this error occurred within the last X minutes.
     */
    public function isRecentError(int $minutes = 5): bool
    {
        return $this->DateCreated > now()->subMinutes($minutes);
    }

    /**
     * Get similar errors (same type and property).
     */
    public function getSimilarErrors(int $limit = 5)
    {
        return self::where('ErrorType', $this->ErrorType)
            ->where('PropertyID', $this->PropertyID)
            ->where('ErrorLogID', '!=', $this->ErrorLogID)
            ->orderBy('DateCreated', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Create a comprehensive error log entry.
     */
    public static function logError(
        string $messageId,
        ErrorType $errorType,
        string $title,
        string $message,
        array $context = [],
        string $severity = 'medium',
        string $jobId = null,
        int $propertyId = 0,
        bool $canRetry = false,
        int $retryDelaySeconds = null,
        bool $requiresManualIntervention = false
    ): self {
        // Extract stack trace information
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[1] ?? null;

        $errorLog = new self([
            'MessageID' => $messageId,
            'JobID' => $jobId,
            'ErrorType' => $errorType,
            'ErrorCode' => $context['error_code'] ?? null,
            'Severity' => $severity,
            'ErrorTitle' => $title,
            'ErrorMessage' => $message,
            'StackTrace' => json_encode($trace),
            'Context' => $context,
            'SourceClass' => $caller['class'] ?? null,
            'SourceMethod' => $caller['function'] ?? null,
            'SourceLine' => $caller['line'] ?? null,
            'CanRetry' => $canRetry,
            'RecommendedRetryDelay' => $retryDelaySeconds,
            'RequiresManualIntervention' => $requiresManualIntervention,
            'PropertyID' => $propertyId,
            'SystemUserID' => auth()->id() ?? 0
        ]);

        $errorLog->save();

        // Log to Laravel's standard logging system as well
        logger($severity)->info("TravelClick Error Logged", [
            'error_log_id' => $errorLog->ErrorLogID,
            'message_id' => $messageId,
            'error_type' => $errorType->value,
            'title' => $title
        ]);

        return $errorLog;
    }

    /**
     * Get error statistics for a property.
     */
    public static function getErrorStats(int $propertyId, int $days = 7): array
    {
        $query = self::forProperty($propertyId)
            ->where('DateCreated', '>=', now()->subDays($days));

        $total = $query->count();
        $bySeverity = $query->groupBy('Severity')
            ->selectRaw('Severity, COUNT(*) as count')
            ->pluck('count', 'Severity')
            ->toArray();

        $byType = $query->groupBy('ErrorType')
            ->selectRaw('ErrorType, COUNT(*) as count')
            ->pluck('count', 'ErrorType')
            ->toArray();

        $resolved = $query->whereNotNull('ResolvedAt')->count();
        $requiresIntervention = $query->where('RequiresManualIntervention', true)
            ->whereNull('ResolvedAt')
            ->count();

        return [
            'total_errors' => $total,
            'resolved_percentage' => $total > 0 ? round(($resolved / $total) * 100, 2) : 0,
            'by_severity' => $bySeverity,
            'by_type' => $byType,
            'requires_intervention' => $requiresIntervention,
            'recent_critical' => self::forProperty($propertyId)
                ->critical()
                ->recent(24)
                ->count()
        ];
    }
}
