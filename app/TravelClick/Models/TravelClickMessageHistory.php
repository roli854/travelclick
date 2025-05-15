<?php

namespace App\TravelClick\Models;

use App\Models\SystemUser;
use App\TravelClick\Enums\MessageDirection;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\ProcessingStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TravelClickMessageHistory Model
 *
 * This model represents a detailed history entry of all messages exchanged with TravelClick.
 * It's like a filing cabinet that keeps copies of all business correspondence for future reference.
 *
 * @property int $MessageHistoryID
 * @property string $MessageID
 * @property string|null $ParentMessageID
 * @property string|null $BatchID
 * @property string $MessageType
 * @property string $Direction
 * @property int $PropertyID
 * @property string|null $HotelCode
 * @property string $MessageXML
 * @property string $XmlHash
 * @property int|null $MessageSize
 * @property string $ProcessingStatus
 * @property array|null $ExtractedData
 * @property string|null $ProcessingNotes
 * @property Carbon|null $SentAt
 * @property Carbon|null $ReceivedAt
 * @property Carbon|null $ProcessedAt
 * @property int $SystemUserID
 * @property Carbon $DateCreated
 *
 * @property-read TravelClickLog $travelClickLog
 * @property-read TravelClickMessageHistory|null $parentMessage
 * @property-read Collection<TravelClickMessageHistory> $childMessages
 * @property-read SystemUser $systemUser
 * @property-read string $xml_preview
 * @property-read string $processing_time_display
 * @property-read bool $is_batch_message
 * @property-read array $message_summary
 */
class TravelClickMessageHistory extends Model
{
    use HasFactory;

    /**
     * The connection name for the model - using CentriumLog database
     */
    protected $connection = 'centriumLog';

    /**
     * The table associated with the model
     */
    protected $table = 'TravelClickMessageHistory';

    /**
     * The primary key for the model
     */
    protected $primaryKey = 'MessageHistoryID';

    /**
     * Indicates if the model should be timestamped
     * We handle timestamps manually to match Centrium conventions
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable
     */
    protected $fillable = [
        'MessageID',
        'ParentMessageID',
        'BatchID',
        'MessageType',
        'Direction',
        'PropertyID',
        'HotelCode',
        'MessageXML',
        'XmlHash',
        'MessageSize',
        'ProcessingStatus',
        'ExtractedData',
        'ProcessingNotes',
        'SentAt',
        'ReceivedAt',
        'ProcessedAt',
        'SystemUserID'
    ];

    /**
     * The attributes that should be cast
     */
    protected $casts = [
        'MessageType' => MessageType::class,
        'Direction' => MessageDirection::class,
        'ProcessingStatus' => ProcessingStatus::class,
        'ExtractedData' => 'array',
        'SentAt' => 'datetime',
        'ReceivedAt' => 'datetime',
        'ProcessedAt' => 'datetime',
        'DateCreated' => 'datetime',
        'MessageSize' => 'integer',
        'PropertyID' => 'integer',
        'SystemUserID' => 'integer'
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-set DateCreated when creating new records
        static::creating(function ($model) {
            $model->DateCreated = now();

            // Auto-generate XmlHash if MessageXML is provided
            if ($model->MessageXML && !$model->XmlHash) {
                $model->XmlHash = hash('sha256', $model->MessageXML);
            }

            // Calculate MessageSize if not provided
            if ($model->MessageXML && !$model->MessageSize) {
                $model->MessageSize = strlen($model->MessageXML);
            }
        });

        // Update XmlHash and MessageSize when XML is modified
        static::updating(function ($model) {
            if ($model->isDirty('MessageXML')) {
                $model->XmlHash = hash('sha256', $model->MessageXML);
                $model->MessageSize = strlen($model->MessageXML);
            }
        });
    }

    // =============================================================================
    // RELATIONSHIPS
    // =============================================================================

    /**
     * Get the main TravelClick log entry for this message
     */
    public function travelClickLog(): BelongsTo
    {
        return $this->belongsTo(TravelClickLog::class, 'MessageID', 'MessageID');
    }

    /**
     * Get the parent message if this is a response/follow-up
     */
    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(TravelClickMessageHistory::class, 'ParentMessageID', 'MessageID');
    }

    /**
     * Get all child messages (responses/follow-ups to this message)
     */
    public function childMessages(): HasMany
    {
        return $this->hasMany(TravelClickMessageHistory::class, 'ParentMessageID', 'MessageID');
    }

    /**
     * Get the system user who initiated/processed this message
     */
    public function systemUser(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'SystemUserID', 'SystemUserID');
    }

    /**
     * Get messages that are part of the same batch
     */
    public function batchMessages(): HasMany
    {
        return $this->hasMany(TravelClickMessageHistory::class, 'BatchID', 'BatchID')
            ->where('MessageHistoryID', '!=', $this->MessageHistoryID);
    }

    // =============================================================================
    // QUERY SCOPES
    // =============================================================================

    /**
     * Scope for messages of a specific type
     */
    public function scopeOfType(Builder $query, MessageType $messageType): Builder
    {
        return $query->where('MessageType', $messageType);
    }

    /**
     * Scope for messages in a specific direction
     */
    public function scopeDirection(Builder $query, MessageDirection $direction): Builder
    {
        return $query->where('Direction', $direction);
    }

    /**
     * Scope for messages from a specific property
     */
    public function scopeForProperty(Builder $query, int $propertyId): Builder
    {
        return $query->where('PropertyID', $propertyId);
    }

    /**
     * Scope for messages with specific processing status
     */
    public function scopeWithStatus(Builder $query, ProcessingStatus $status): Builder
    {
        return $query->where('ProcessingStatus', $status);
    }

    /**
     * Scope for messages created within a time period
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('DateCreated', '>=', now()->subHours($hours));
    }

    /**
     * Scope for messages that are part of a batch
     */
    public function scopeBatchMessages(Builder $query): Builder
    {
        return $query->whereNotNull('BatchID');
    }

    /**
     * Scope for messages that took longer than expected to process
     */
    public function scopeSlowMessages(Builder $query, int $thresholdSeconds = 30): Builder
    {
        return $query->whereNotNull('SentAt')
            ->whereNotNull('ReceivedAt')
            ->whereRaw("DATEDIFF(second, SentAt, ReceivedAt) > ?", [$thresholdSeconds]);
    }

    /**
     * Scope for messages with specific XML content
     */
    public function scopeContainingXml(Builder $query, string $xmlFragment): Builder
    {
        return $query->where('MessageXML', 'LIKE', '%' . $xmlFragment . '%');
    }

    /**
     * Scope for message threads (parent and all children)
     */
    public function scopeMessageThread(Builder $query, string $messageId): Builder
    {
        return $query->where(function ($q) use ($messageId) {
            $q->where('MessageID', $messageId)
                ->orWhere('ParentMessageID', $messageId);
        });
    }

    // =============================================================================
    // ATTRIBUTE ACCESSORS
    // =============================================================================

    /**
     * Get a preview of the XML content (first 200 characters)
     */
    public function getXmlPreviewAttribute(): string
    {
        if (!$this->MessageXML) {
            return '';
        }

        $preview = Str::limit(strip_tags($this->MessageXML), 200);
        return Str::squish($preview); // Remove extra whitespace
    }

    /**
     * Get a formatted display of processing time
     */
    public function getProcessingTimeDisplayAttribute(): string
    {
        if (!$this->SentAt || !$this->ReceivedAt) {
            return 'N/A';
        }

        $diffSeconds = $this->SentAt->diffInSeconds($this->ReceivedAt);

        if ($diffSeconds < 1) {
            return $this->SentAt->diffInMilliseconds($this->ReceivedAt) . 'ms';
        } elseif ($diffSeconds < 60) {
            return $diffSeconds . 's';
        } else {
            return $this->SentAt->diff($this->ReceivedAt)->format('%im %ss');
        }
    }

    /**
     * Check if this message is part of a batch operation
     */
    public function getIsBatchMessageAttribute(): bool
    {
        return !is_null($this->BatchID);
    }

    /**
     * Get a summary of the message for display purposes
     */
    public function getMessageSummaryAttribute(): array
    {
        return [
            'id' => $this->MessageID,
            'type' => $this->MessageType?->getDisplayName() ?? $this->MessageType,
            'direction' => $this->Direction?->getDisplayName() ?? $this->Direction,
            'hotel_code' => $this->HotelCode,
            'status' => $this->ProcessingStatus?->getDisplayName() ?? $this->ProcessingStatus,
            'size_kb' => $this->MessageSize ? round($this->MessageSize / 1024, 2) : null,
            'processing_time' => $this->processing_time_display,
            'created_at' => $this->DateCreated?->format('Y-m-d H:i:s'),
        ];
    }

    // =============================================================================
    // UTILITY METHODS
    // =============================================================================

    /**
     * Create a new message history entry with automatic XML hashing
     */
    public static function createEntry(array $data): self
    {
        // Ensure MessageID is provided
        if (!isset($data['MessageID'])) {
            $data['MessageID'] = (string) Str::uuid();
        }

        // Auto-extract key data from XML if ExtractedData is not provided
        if (isset($data['MessageXML']) && !isset($data['ExtractedData'])) {
            $data['ExtractedData'] = static::extractKeyDataFromXml($data['MessageXML']);
        }

        return static::create($data);
    }

    /**
     * Extract key data from XML for quick reference
     */
    public static function extractKeyDataFromXml(string $xml): array
    {
        $extracted = [];

        try {
            $simpleXml = simplexml_load_string($xml);

            if ($simpleXml) {
                // Extract common attributes
                $extracted['root_element'] = $simpleXml->getName();

                // Extract HotelCode if present
                if (isset($simpleXml['HotelCode'])) {
                    $extracted['hotel_code'] = (string) $simpleXml['HotelCode'];
                }

                // Extract EchoToken if present
                if (isset($simpleXml['EchoToken'])) {
                    $extracted['echo_token'] = (string) $simpleXml['EchoToken'];
                }

                // Extract TimeStamp if present
                if (isset($simpleXml['TimeStamp'])) {
                    $extracted['timestamp'] = (string) $simpleXml['TimeStamp'];
                }

                // Count child elements
                $extracted['element_count'] = count($simpleXml->children());
            }
        } catch (\Exception $e) {
            $extracted['extraction_error'] = $e->getMessage();
        }

        return $extracted;
    }

    /**
     * Mark message as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'SentAt' => now(),
            'ProcessingStatus' => ProcessingStatus::PROCESSING
        ]);
    }

    /**
     * Mark message as received with response
     */
    public function markAsReceived(string $responseXml = null): void
    {
        $updateData = [
            'ReceivedAt' => now(),
            'ProcessingStatus' => ProcessingStatus::RECEIVED
        ];

        if ($responseXml) {
            $updateData['MessageXML'] = $responseXml;
        }

        $this->update($updateData);
    }

    /**
     * Mark message as processed
     */
    public function markAsProcessed(string $notes = null): void
    {
        $updateData = [
            'ProcessedAt' => now(),
            'ProcessingStatus' => ProcessingStatus::PROCESSED
        ];

        if ($notes) {
            $updateData['ProcessingNotes'] = $notes;
        }

        $this->update($updateData);
    }

    /**
     * Mark message as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'ProcessingStatus' => ProcessingStatus::FAILED,
            'ProcessingNotes' => $error,
            'ProcessedAt' => now()
        ]);
    }

    /**
     * Find duplicate messages by XML hash
     */
    public static function findDuplicatesByHash(string $xmlHash): Collection
    {
        return static::where('XmlHash', $xmlHash)->get();
    }

    /**
     * Get message statistics for a property
     */
    public static function getMessageStats(int $propertyId, int $days = 7): array
    {
        $startDate = now()->subDays($days);

        $stats = static::where('PropertyID', $propertyId)
            ->where('DateCreated', '>=', $startDate)
            ->selectRaw('
                MessageType,
                Direction,
                ProcessingStatus,
                COUNT(*) as count,
                AVG(MessageSize) as avg_size,
                AVG(CASE
                    WHEN SentAt IS NOT NULL AND ReceivedAt IS NOT NULL
                    THEN DATEDIFF(second, SentAt, ReceivedAt)
                    ELSE NULL
                END) as avg_processing_seconds
            ')
            ->groupBy('MessageType', 'Direction', 'ProcessingStatus')
            ->get();

        // Calculate summary metrics
        $totalMessages = $stats->sum('count');
        $successfulMessages = $stats->where('ProcessingStatus', ProcessingStatus::PROCESSED)->sum('count');
        $successRate = $totalMessages > 0 ? ($successfulMessages / $totalMessages) * 100 : 0;

        return [
            'total_messages' => $totalMessages,
            'success_rate' => round($successRate, 2),
            'avg_processing_time_seconds' => round($stats->avg('avg_processing_seconds'), 2),
            'avg_message_size_kb' => round($stats->avg('avg_size') / 1024, 2),
            'by_type_direction_status' => $stats->toArray(),
            'period_days' => $days
        ];
    }

    /**
     * Get batch operation summary
     */
    public static function getBatchSummary(string $batchId): array
    {
        $messages = static::where('BatchID', $batchId)->get();

        if ($messages->isEmpty()) {
            return [];
        }

        $totalMessages = $messages->count();
        $processedCount = $messages->where('ProcessingStatus', ProcessingStatus::PROCESSED)->count();
        $failedCount = $messages->where('ProcessingStatus', ProcessingStatus::FAILED)->count();

        $startTime = $messages->min('SentAt');
        $endTime = $messages->max('ProcessedAt');

        return [
            'batch_id' => $batchId,
            'total_messages' => $totalMessages,
            'processed_count' => $processedCount,
            'failed_count' => $failedCount,
            'success_rate' => $totalMessages > 0 ? round(($processedCount / $totalMessages) * 100, 2) : 0,
            'start_time' => $startTime?->format('Y-m-d H:i:s'),
            'end_time' => $endTime?->format('Y-m-d H:i:s'),
            'duration_minutes' => $startTime && $endTime ? $startTime->diffInMinutes($endTime) : null,
            'messages_by_type' => $messages->groupBy('MessageType')->map->count()->toArray(),
            'messages_by_status' => $messages->groupBy('ProcessingStatus')->map->count()->toArray()
        ];
    }

    /**
     * Clean up old message history records
     */
    public static function cleanup(int $daysToKeep = 30): int
    {
        $cutoffDate = now()->subDays($daysToKeep);

        return static::where('DateCreated', '<', $cutoffDate)
            ->where('ProcessingStatus', ProcessingStatus::PROCESSED)
            ->delete();
    }

    /**
     * Export message history for analysis
     */
    public static function exportMessages(
        int $propertyId,
        Carbon $startDate,
        Carbon $endDate,
        array $messageTypes = []
    ): Collection {
        $query = static::where('PropertyID', $propertyId)
            ->whereBetween('DateCreated', [$startDate, $endDate])
            ->with(['travelClickLog', 'systemUser']);

        if (!empty($messageTypes)) {
            $query->whereIn('MessageType', $messageTypes);
        }

        return $query->orderBy('DateCreated')->get();
    }
}
