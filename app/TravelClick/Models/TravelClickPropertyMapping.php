<?php

namespace App\TravelClick\Models;

use App\Models\Property;
use App\Models\SystemUser;
use App\TravelClick\Enums\SyncStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * TravelClickPropertyMapping Model
 *
 * This model manages the mapping between Centrium properties and TravelClick hotel codes.
 * It's like a translation dictionary that ensures both systems can communicate about
 * the same hotel using their respective identifiers.
 *
 * @property int $PropertyMappingID
 * @property int $PropertyID
 * @property string $TravelClickHotelCode
 * @property string|null $TravelClickHotelName
 * @property bool $IsActive
 * @property array|null $MappingConfiguration
 * @property string|null $Notes
 * @property Carbon|null $LastSyncAt
 * @property string $SyncStatus
 * @property string|null $LastSyncError
 * @property int $SystemUserID
 * @property Carbon $DateCreated
 * @property Carbon|null $LastModifiedAt
 * @property int|null $LastModifiedBy
 *
 * @property-read Property $property
 * @property-read SystemUser $systemUser
 * @property-read SystemUser|null $lastModifiedByUser
 * @property-read Collection<TravelClickLog> $travelClickLogs
 * @property-read string $formatted_hotel_code
 * @property-read bool $is_sync_healthy
 * @property-read int $days_since_last_sync
 * @property-read array $sync_health_status
 */
class TravelClickPropertyMapping extends Model
{
    use HasFactory;

    /**
     * The connection name for the model - using CentriumLog database
     */
    protected $connection = 'centriumLog';

    /**
     * The table associated with the model
     */
    protected $table = 'TravelClickPropertyMapping';

    /**
     * The primary key for the model
     */
    protected $primaryKey = 'PropertyMappingID';

    /**
     * Indicates if the model should be timestamped
     * We handle timestamps manually to match Centrium conventions
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable
     */
    protected $fillable = [
        'PropertyID',
        'TravelClickHotelCode',
        'TravelClickHotelName',
        'IsActive',
        'MappingConfiguration',
        'Notes',
        'LastSyncAt',
        'SyncStatus',
        'LastSyncError',
        'SystemUserID',
        'LastModifiedBy'
    ];

    /**
     * The attributes that should be cast
     */
    protected $casts = [
        'PropertyID' => 'integer',
        'IsActive' => 'boolean',
        'MappingConfiguration' => 'array',
        'LastSyncAt' => 'datetime',
        'SyncStatus' => SyncStatus::class,
        'DateCreated' => 'datetime',
        'LastModifiedAt' => 'datetime',
        'SystemUserID' => 'integer',
        'LastModifiedBy' => 'integer'
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
            $model->SyncStatus = $model->SyncStatus ?? SyncStatus::PENDING;
            $model->IsActive = $model->IsActive ?? true;
        });

        // Update LastModifiedAt when updating
        static::updating(function ($model) {
            $model->LastModifiedAt = now();

            // If user is authenticated, set LastModifiedBy
            if (auth()->check()) {
                $model->LastModifiedBy = auth()->id();
            }
        });
    }

    // =============================================================================
    // RELATIONSHIPS
    // =============================================================================

    /**
     * Get the Centrium property associated with this mapping
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'PropertyID', 'PropertyID');
    }

    /**
     * Get the system user who created this mapping
     */
    public function systemUser(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'SystemUserID', 'SystemUserID');
    }

    /**
     * Get the system user who last modified this mapping
     */
    public function lastModifiedByUser(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'LastModifiedBy', 'SystemUserID');
    }

    /**
     * Get all TravelClick logs for this property mapping
     */
    public function travelClickLogs(): HasMany
    {
        return $this->hasMany(TravelClickLog::class, 'PropertyID', 'PropertyID');
    }

    /**
     * Get recent TravelClick logs for this property
     */
    public function recentLogs()
    {
        return $this->travelClickLogs()
            ->where('DateCreated', '>=', now()->subDays(7))
            ->orderBy('DateCreated', 'desc');
    }

    // =============================================================================
    // QUERY SCOPES
    // =============================================================================

    /**
     * Scope for active mappings only
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('IsActive', true);
    }

    /**
     * Scope for mappings with specific sync status
     */
    public function scopeWithSyncStatus(Builder $query, SyncStatus $status): Builder
    {
        return $query->where('SyncStatus', $status);
    }

    /**
     * Scope for mappings that haven't synced recently
     */
    public function scopeStaleSync(Builder $query, int $hours = 24): Builder
    {
        return $query->where(function ($q) use ($hours) {
            $q->whereNull('LastSyncAt')
                ->orWhere('LastSyncAt', '<', now()->subHours($hours));
        });
    }

    /**
     * Scope for mappings that need attention
     */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('SyncStatus', SyncStatus::ERROR)
                ->orWhere('SyncStatus', SyncStatus::FAILED);
        });
    }

    /**
     * Scope for searching by hotel code or property name
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('TravelClickHotelCode', 'like', "%{$search}%")
                ->orWhere('TravelClickHotelName', 'like', "%{$search}%")
                ->orWhereHas('property', function ($propertyQuery) use ($search) {
                    $propertyQuery->where('Name', 'like', "%{$search}%")
                        ->orWhere('Reference', 'like', "%{$search}%");
                });
        });
    }

    /**
     * Scope for mappings by property group
     */
    public function scopeByPropertyGroup(Builder $query, int $propertyGroupId): Builder
    {
        return $query->whereHas('property', function ($propertyQuery) use ($propertyGroupId) {
            $propertyQuery->where('PropertyGroupID', $propertyGroupId);
        });
    }

    // =============================================================================
    // ATTRIBUTE ACCESSORS
    // =============================================================================

    /**
     * Get formatted hotel code for display
     */
    public function getFormattedHotelCodeAttribute(): string
    {
        return strtoupper($this->TravelClickHotelCode);
    }

    /**
     * Check if sync is healthy (recent and successful)
     */
    public function getIsSyncHealthyAttribute(): bool
    {
        if (!$this->LastSyncAt) {
            return false;
        }

        $isRecent = $this->LastSyncAt->isAfter(now()->subHours(24));
        $isSuccessful = in_array($this->SyncStatus, [
            SyncStatus::SUCCESS,
            SyncStatus::PROCESSING
        ]);

        return $isRecent && $isSuccessful;
    }

    /**
     * Get days since last sync
     */
    public function getDaysSinceLastSyncAttribute(): int
    {
        if (!$this->LastSyncAt) {
            return -1; // Never synced
        }

        return (int) $this->LastSyncAt->diffInDays(now());
    }

    /**
     * Get comprehensive sync health status
     */
    public function getSyncHealthStatusAttribute(): array
    {
        $daysSinceSync = $this->days_since_last_sync;

        if ($daysSinceSync === -1) {
            $healthLevel = 'never-synced';
            $healthMessage = 'Never synchronized';
            $healthColor = '#FF9500'; // Orange
        } elseif ($this->SyncStatus === SyncStatus::ERROR || $this->SyncStatus === SyncStatus::FAILED) {
            $healthLevel = 'error';
            $healthMessage = 'Sync errors detected';
            $healthColor = '#FF0000'; // Red
        } elseif ($daysSinceSync > 7) {
            $healthLevel = 'stale';
            $healthMessage = "Last sync {$daysSinceSync} days ago";
            $healthColor = '#FF9500'; // Orange
        } elseif ($daysSinceSync > 1) {
            $healthLevel = 'warning';
            $healthMessage = "Last sync {$daysSinceSync} days ago";
            $healthColor = '#FFFF00'; // Yellow
        } else {
            $healthLevel = 'healthy';
            $healthMessage = 'Recently synchronized';
            $healthColor = '#00FF00'; // Green
        }

        return [
            'level' => $healthLevel,
            'message' => $healthMessage,
            'color' => $healthColor,
            'days_since_sync' => $daysSinceSync,
            'last_sync' => $this->LastSyncAt?->format('Y-m-d H:i:s'),
            'sync_status' => $this->SyncStatus?->getDisplayName(),
            'has_error' => !empty($this->LastSyncError)
        ];
    }

    // =============================================================================
    // STATIC FACTORY METHODS
    // =============================================================================

    /**
     * Create a new property mapping with validation
     */
    public static function createMapping(array $data): self
    {
        // Validate required fields
        if (empty($data['PropertyID'])) {
            throw new ValidationException('PropertyID is required');
        }

        if (empty($data['TravelClickHotelCode'])) {
            throw new ValidationException('TravelClickHotelCode is required');
        }

        // Check for existing mapping
        $existingMapping = static::where('PropertyID', $data['PropertyID'])->first();
        if ($existingMapping && $existingMapping->IsActive) {
            throw new ValidationException("Property {$data['PropertyID']} already has an active TravelClick mapping");
        }

        // Check for duplicate hotel code
        $duplicateCode = static::where('TravelClickHotelCode', $data['TravelClickHotelCode'])
            ->where('IsActive', true)
            ->first();
        if ($duplicateCode) {
            throw new ValidationException("Hotel code {$data['TravelClickHotelCode']} is already in use");
        }

        // Set defaults
        $data['IsActive'] = $data['IsActive'] ?? true;
        $data['SyncStatus'] = $data['SyncStatus'] ?? SyncStatus::PENDING;
        $data['SystemUserID'] = $data['SystemUserID'] ?? auth()->id() ?? 1;

        return static::create($data);
    }

    /**
     * Find mapping by TravelClick hotel code
     */
    public static function findByHotelCode(string $hotelCode): ?self
    {
        return static::where('TravelClickHotelCode', $hotelCode)
            ->where('IsActive', true)
            ->first();
    }

    /**
     * Find mapping by Centrium property ID
     */
    public static function findByPropertyId(int $propertyId): ?self
    {
        return static::where('PropertyID', $propertyId)
            ->where('IsActive', true)
            ->first();
    }

    /**
     * Get all mappings that need sync
     */
    public static function getNeedingSyncMappings(): Collection
    {
        return static::active()
            ->where(function ($query) {
                $query->where('SyncStatus', SyncStatus::PENDING)
                    ->orWhere('SyncStatus', SyncStatus::ERROR)
                    ->orWhere('LastSyncAt', '<', now()->subHours(24));
            })
            ->with('property')
            ->get();
    }

    // =============================================================================
    // SYNC STATUS MANAGEMENT
    // =============================================================================

    /**
     * Mark sync as started
     */
    public function markSyncStarted(): void
    {
        $this->update([
            'SyncStatus' => SyncStatus::PROCESSING,
            'LastSyncError' => null
        ]);
    }

    /**
     * Mark sync as successful
     */
    public function markSyncSuccess(string $notes = null): void
    {
        $updateData = [
            'SyncStatus' => SyncStatus::SUCCESS,
            'LastSyncAt' => now(),
            'LastSyncError' => null
        ];

        if ($notes) {
            $updateData['Notes'] = $notes;
        }

        $this->update($updateData);
    }

    /**
     * Mark sync as failed
     */
    public function markSyncFailed(string $error): void
    {
        $this->update([
            'SyncStatus' => SyncStatus::FAILED,
            'LastSyncError' => $error,
            'LastSyncAt' => now()
        ]);
    }

    /**
     * Mark sync as having errors but partially successful
     */
    public function markSyncError(string $error): void
    {
        $this->update([
            'SyncStatus' => SyncStatus::ERROR,
            'LastSyncError' => $error,
            'LastSyncAt' => now()
        ]);
    }

    // =============================================================================
    // CONFIGURATION MANAGEMENT
    // =============================================================================

    /**
     * Get specific configuration value
     */
    public function getConfigValue(string $key, $default = null)
    {
        return data_get($this->MappingConfiguration, $key, $default);
    }

    /**
     * Set specific configuration value
     */
    public function setConfigValue(string $key, $value): void
    {
        $config = $this->MappingConfiguration ?? [];
        data_set($config, $key, $value);
        $this->update(['MappingConfiguration' => $config]);
    }

    /**
     * Update multiple configuration values
     */
    public function updateConfiguration(array $config): void
    {
        $currentConfig = $this->MappingConfiguration ?? [];
        $newConfig = array_merge($currentConfig, $config);
        $this->update(['MappingConfiguration' => $newConfig]);
    }

    /**
     * Reset configuration to defaults
     */
    public function resetConfiguration(): void
    {
        $this->update(['MappingConfiguration' => static::getDefaultConfiguration()]);
    }

    /**
     * Get default configuration structure
     */
    public static function getDefaultConfiguration(): array
    {
        return [
            'sync_inventory' => true,
            'sync_rates' => true,
            'sync_restrictions' => true,
            'sync_reservations' => true,
            'sync_group_blocks' => false,
            'batch_size' => 50,
            'retry_attempts' => 3,
            'retry_delay_seconds' => 60,
            'timeout_seconds' => 30,
            'notification_emails' => [],
            'custom_room_type_mappings' => [],
            'custom_rate_plan_mappings' => [],
            'exclude_room_types' => [],
            'exclude_rate_plans' => []
        ];
    }

    // =============================================================================
    // DEACTIVATION AND REACTIVATION
    // =============================================================================

    /**
     * Deactivate mapping with reason
     */
    public function deactivate(string $reason = null): void
    {
        $this->update([
            'IsActive' => false,
            'Notes' => $reason ? "Deactivated: {$reason}" : 'Deactivated',
            'SyncStatus' => SyncStatus::INACTIVE
        ]);
    }

    /**
     * Reactivate mapping
     */
    public function reactivate(): void
    {
        // Check for conflicts before reactivating
        $existingActive = static::where('PropertyID', $this->PropertyID)
            ->where('IsActive', true)
            ->where('PropertyMappingID', '!=', $this->PropertyMappingID)
            ->first();

        if ($existingActive) {
            throw new ValidationException('Cannot reactivate: Another active mapping exists for this property');
        }

        $existingCode = static::where('TravelClickHotelCode', $this->TravelClickHotelCode)
            ->where('IsActive', true)
            ->where('PropertyMappingID', '!=', $this->PropertyMappingID)
            ->first();

        if ($existingCode) {
            throw new ValidationException('Cannot reactivate: Hotel code is already in use');
        }

        $this->update([
            'IsActive' => true,
            'SyncStatus' => SyncStatus::PENDING,
            'LastSyncError' => null
        ]);
    }

    // =============================================================================
    // REPORTING AND ANALYTICS
    // =============================================================================

    /**
     * Get sync statistics summary
     */
    public static function getSyncStatistics(): array
    {
        $stats = static::selectRaw('
            COUNT(*) as total_mappings,
            COUNT(CASE WHEN IsActive = 1 THEN 1 END) as active_mappings,
            COUNT(CASE WHEN SyncStatus = ? THEN 1 END) as successful_syncs,
            COUNT(CASE WHEN SyncStatus IN (?, ?) THEN 1 END) as failed_syncs,
            COUNT(CASE WHEN LastSyncAt IS NULL THEN 1 END) as never_synced,
            COUNT(CASE WHEN LastSyncAt < ? THEN 1 END) as stale_syncs,
            AVG(CASE WHEN LastSyncAt IS NOT NULL THEN DATEDIFF(day, LastSyncAt, GETDATE()) END) as avg_days_since_sync
        ', [
            SyncStatus::SUCCESS->value,
            SyncStatus::ERROR->value,
            SyncStatus::FAILED->value,
            now()->subDays(7)
        ])->first();

        return [
            'total_mappings' => $stats->total_mappings,
            'active_mappings' => $stats->active_mappings,
            'successful_syncs' => $stats->successful_syncs,
            'failed_syncs' => $stats->failed_syncs,
            'never_synced' => $stats->never_synced,
            'stale_syncs' => $stats->stale_syncs,
            'success_rate' => $stats->active_mappings > 0
                ? round(($stats->successful_syncs / $stats->active_mappings) * 100, 2)
                : 0,
            'avg_days_since_sync' => round($stats->avg_days_since_sync ?? 0, 1)
        ];
    }

    /**
     * Get mapping health report
     */
    public static function getHealthReport(): array
    {
        $mappings = static::active()->with(['property'])->get();

        $healthCategories = [
            'healthy' => 0,
            'warning' => 0,
            'stale' => 0,
            'error' => 0,
            'never-synced' => 0
        ];

        $detailedReport = [];

        foreach ($mappings as $mapping) {
            $health = $mapping->sync_health_status;
            $healthCategories[$health['level']]++;

            if (in_array($health['level'], ['error', 'stale', 'never-synced'])) {
                $detailedReport[] = [
                    'property_name' => $mapping->property->Name,
                    'hotel_code' => $mapping->TravelClickHotelCode,
                    'health_level' => $health['level'],
                    'health_message' => $health['message'],
                    'last_sync' => $health['last_sync'],
                    'last_error' => $mapping->LastSyncError
                ];
            }
        }

        return [
            'summary' => $healthCategories,
            'total_mappings' => $mappings->count(),
            'health_percentage' => $mappings->count() > 0
                ? round(($healthCategories['healthy'] / $mappings->count()) * 100, 2)
                : 0,
            'issues_requiring_attention' => $detailedReport,
            'generated_at' => now()->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Export mappings for external analysis
     */
    public static function exportMappings(array $propertyIds = []): array
    {
        $query = static::with(['property', 'systemUser']);

        if (!empty($propertyIds)) {
            $query->whereIn('PropertyID', $propertyIds);
        }

        return $query->get()->map(function ($mapping) {
            return [
                'property_id' => $mapping->PropertyID,
                'property_name' => $mapping->property->Name,
                'property_reference' => $mapping->property->Reference,
                'hotel_code' => $mapping->TravelClickHotelCode,
                'hotel_name' => $mapping->TravelClickHotelName,
                'is_active' => $mapping->IsActive,
                'sync_status' => $mapping->SyncStatus?->getDisplayName(),
                'last_sync' => $mapping->LastSyncAt?->format('Y-m-d H:i:s'),
                'days_since_sync' => $mapping->days_since_last_sync,
                'last_error' => $mapping->LastSyncError,
                'configuration' => $mapping->MappingConfiguration,
                'notes' => $mapping->Notes,
                'created_by' => $mapping->systemUser->UserName ?? 'Unknown',
                'date_created' => $mapping->DateCreated->format('Y-m-d H:i:s')
            ];
        })->toArray();
    }

    // =============================================================================
    // BULK OPERATIONS
    // =============================================================================

    /**
     * Bulk update sync status for multiple mappings
     */
    public static function bulkUpdateSyncStatus(array $mappingIds, SyncStatus $status, string $error = null): int
    {
        $updateData = [
            'SyncStatus' => $status,
            'LastSyncAt' => now()
        ];

        if ($error && in_array($status, [SyncStatus::ERROR, SyncStatus::FAILED])) {
            $updateData['LastSyncError'] = $error;
        } elseif ($status === SyncStatus::SUCCESS) {
            $updateData['LastSyncError'] = null;
        }

        return static::whereIn('PropertyMappingID', $mappingIds)->update($updateData);
    }

    /**
     * Bulk deactivate mappings
     */
    public static function bulkDeactivate(array $mappingIds, string $reason = null): int
    {
        return static::whereIn('PropertyMappingID', $mappingIds)
            ->update([
                'IsActive' => false,
                'SyncStatus' => SyncStatus::INACTIVE,
                'Notes' => $reason ? "Bulk deactivated: {$reason}" : 'Bulk deactivated',
                'LastModifiedAt' => now(),
                'LastModifiedBy' => auth()->id()
            ]);
    }

    /**
     * Cleanup inactive mappings older than specified days
     */
    public static function cleanupInactiveMappings(int $daysOld = 365): int
    {
        return static::where('IsActive', false)
            ->where('DateCreated', '<', now()->subDays($daysOld))
            ->delete();
    }
}
