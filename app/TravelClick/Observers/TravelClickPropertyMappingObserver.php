<?php

namespace App\TravelClick\Observers;

use App\TravelClick\Models\TravelClickPropertyMapping;
use App\TravelClick\Models\TravelClickPropertyConfig;
use App\TravelClick\Models\TravelClickLog;
use App\TravelClick\Enums\SyncStatus;
use App\TravelClick\Enums\MessageType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Observer for TravelClickPropertyMapping model
 *
 * This observer handles all model events for property mappings, ensuring
 * that related configurations are kept in sync and caches are invalidated
 * when necessary. It's like a vigilant supervisor who makes sure every
 * change to property mappings is properly recorded and synchronized.
 */
class TravelClickPropertyMappingObserver
{
    /**
     * Handle the "creating" event
     * This is triggered before a new mapping is saved to the database
     */
    public function creating(TravelClickPropertyMapping $mapping): void
    {
        try {
            // Log the creation attempt
            $this->logEvent('creating', $mapping, [
                'property_id' => $mapping->PropertyID,
                'hotel_code' => $mapping->TravelClickHotelCode
            ]);

            // Validate that the property doesn't already have an active mapping
            $existingMapping = TravelClickPropertyMapping::where('PropertyID', $mapping->PropertyID)
                ->where('IsActive', true)
                ->where('PropertyMappingID', '!=', $mapping->PropertyMappingID)
                ->first();

            if ($existingMapping) {
                throw new \InvalidArgumentException(
                    "Property {$mapping->PropertyID} already has an active TravelClick mapping"
                );
            }

            // Validate that the hotel code isn't already in use
            $existingCode = TravelClickPropertyMapping::where('TravelClickHotelCode', $mapping->TravelClickHotelCode)
                ->where('IsActive', true)
                ->where('PropertyMappingID', '!=', $mapping->PropertyMappingID)
                ->first();

            if ($existingCode) {
                throw new \InvalidArgumentException(
                    "Hotel code {$mapping->TravelClickHotelCode} is already in use by another property"
                );
            }
        } catch (\Exception $e) {
            Log::error('Error in TravelClickPropertyMapping creating observer', [
                'error' => $e->getMessage(),
                'mapping_data' => $mapping->toArray()
            ]);
            throw $e;
        }
    }

    /**
     * Handle the "created" event
     * This is triggered after a new mapping is successfully saved
     */
    public function created(TravelClickPropertyMapping $mapping): void
    {
        try {
            // Log the successful creation
            $this->logEvent('created', $mapping, [
                'mapping_id' => $mapping->PropertyMappingID,
                'property_id' => $mapping->PropertyID,
                'hotel_code' => $mapping->TravelClickHotelCode
            ]);

            // Create or update the corresponding PropertyConfig
            $this->syncPropertyConfig($mapping);

            // Invalidate related caches
            $this->invalidateRelatedCaches($mapping);

            // Create a log entry for the new mapping
            $this->createLogEntry($mapping, MessageType::MAPPING_CREATED, [
                'action' => 'created',
                'mapping_id' => $mapping->PropertyMappingID,
                'property_id' => $mapping->PropertyID,
                'hotel_code' => $mapping->TravelClickHotelCode,
                'is_active' => $mapping->IsActive
            ]);
        } catch (\Exception $e) {
            Log::error('Error in TravelClickPropertyMapping created observer', [
                'error' => $e->getMessage(),
                'mapping' => $mapping->toArray()
            ]);
        }
    }

    /**
     * Handle the "updating" event
     * This is triggered before a mapping is updated
     */
    public function updating(TravelClickPropertyMapping $mapping): void
    {
        try {
            // Log the update attempt
            $dirty = $mapping->getDirty();
            $this->logEvent('updating', $mapping, [
                'mapping_id' => $mapping->PropertyMappingID,
                'changes' => array_keys($dirty)
            ]);

            // Handle hotel code changes - validate uniqueness
            if ($mapping->isDirty('TravelClickHotelCode')) {
                $newHotelCode = $mapping->TravelClickHotelCode;
                $existingCode = TravelClickPropertyMapping::where('TravelClickHotelCode', $newHotelCode)
                    ->where('IsActive', true)
                    ->where('PropertyMappingID', '!=', $mapping->PropertyMappingID)
                    ->first();

                if ($existingCode) {
                    throw new \InvalidArgumentException(
                        "Hotel code {$newHotelCode} is already in use by another property"
                    );
                }
            }

            // Handle status changes - validate active state transitions
            if ($mapping->isDirty('IsActive')) {
                $newActiveState = $mapping->IsActive;

                // If activating, ensure no other mapping is active for this property
                if ($newActiveState) {
                    $existingActive = TravelClickPropertyMapping::where('PropertyID', $mapping->PropertyID)
                        ->where('IsActive', true)
                        ->where('PropertyMappingID', '!=', $mapping->PropertyMappingID)
                        ->first();

                    if ($existingActive) {
                        throw new \InvalidArgumentException(
                            "Cannot activate: Property {$mapping->PropertyID} already has an active mapping"
                        );
                    }
                }
            }

            // Store original values for logging after update
            $mapping->_originalValues = $mapping->getOriginal();
        } catch (\Exception $e) {
            Log::error('Error in TravelClickPropertyMapping updating observer', [
                'error' => $e->getMessage(),
                'mapping_id' => $mapping->PropertyMappingID,
                'dirty_fields' => $mapping->getDirty()
            ]);
            throw $e;
        }
    }

    /**
     * Handle the "updated" event
     * This is triggered after a mapping is successfully updated
     */
    public function updated(TravelClickPropertyMapping $mapping): void
    {
        try {
            $changes = $mapping->getChanges();
            $original = $mapping->_originalValues ?? [];

            // Log the successful update
            $this->logEvent('updated', $mapping, [
                'mapping_id' => $mapping->PropertyMappingID,
                'changes' => $changes
            ]);

            // Sync with PropertyConfig if relevant fields changed
            $configRelevantFields = [
                'TravelClickHotelCode',
                'IsActive',
                'MappingConfiguration'
            ];

            $hasConfigChanges = collect($configRelevantFields)
                ->some(fn($field) => array_key_exists($field, $changes));

            if ($hasConfigChanges) {
                $this->syncPropertyConfig($mapping);
            }

            // Handle specific field changes
            $this->handleSpecificChanges($mapping, $changes, $original);

            // Invalidate related caches
            $this->invalidateRelatedCaches($mapping);

            // Create log entry for significant changes
            $this->createUpdateLogEntry($mapping, $changes, $original);
        } catch (\Exception $e) {
            Log::error('Error in TravelClickPropertyMapping updated observer', [
                'error' => $e->getMessage(),
                'mapping_id' => $mapping->PropertyMappingID,
                'changes' => $mapping->getChanges()
            ]);
        }
    }

    /**
     * Handle the "deleting" event
     * This is triggered before a mapping is deleted
     */
    public function deleting(TravelClickPropertyMapping $mapping): void
    {
        try {
            // Log the deletion attempt
            $this->logEvent('deleting', $mapping, [
                'mapping_id' => $mapping->PropertyMappingID,
                'property_id' => $mapping->PropertyID,
                'hotel_code' => $mapping->TravelClickHotelCode
            ]);

            // Check if mapping has related logs that should be preserved
            $relatedLogsCount = TravelClickLog::where('PropertyID', $mapping->PropertyID)->count();

            if ($relatedLogsCount > 0) {
                Log::warning('Deleting mapping with existing logs', [
                    'mapping_id' => $mapping->PropertyMappingID,
                    'related_logs_count' => $relatedLogsCount
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error in TravelClickPropertyMapping deleting observer', [
                'error' => $e->getMessage(),
                'mapping_id' => $mapping->PropertyMappingID
            ]);
        }
    }

    /**
     * Handle the "deleted" event
     * This is triggered after a mapping is successfully deleted
     */
    public function deleted(TravelClickPropertyMapping $mapping): void
    {
        try {
            // Log the successful deletion
            $this->logEvent('deleted', $mapping, [
                'mapping_id' => $mapping->PropertyMappingID,
                'property_id' => $mapping->PropertyID,
                'hotel_code' => $mapping->TravelClickHotelCode
            ]);

            // Clean up related PropertyConfig if it exists
            $this->cleanupPropertyConfig($mapping);

            // Invalidate all related caches
            $this->invalidateRelatedCaches($mapping);

            // Create a log entry for the deletion
            $this->createLogEntry($mapping, MessageType::MAPPING_DELETED, [
                'action' => 'deleted',
                'mapping_id' => $mapping->PropertyMappingID,
                'property_id' => $mapping->PropertyID,
                'hotel_code' => $mapping->TravelClickHotelCode
            ]);
        } catch (\Exception $e) {
            Log::error('Error in TravelClickPropertyMapping deleted observer', [
                'error' => $e->getMessage(),
                'mapping_id' => $mapping->PropertyMappingID
            ]);
        }
    }

    /**
     * Handle the "restored" event (for soft deletes, if implemented)
     * This is triggered after a soft-deleted mapping is restored
     */
    public function restored(TravelClickPropertyMapping $mapping): void
    {
        try {
            // Log the restoration
            $this->logEvent('restored', $mapping, [
                'mapping_id' => $mapping->PropertyMappingID,
                'property_id' => $mapping->PropertyID,
                'hotel_code' => $mapping->TravelClickHotelCode
            ]);

            // Re-sync with PropertyConfig
            $this->syncPropertyConfig($mapping);

            // Invalidate related caches
            $this->invalidateRelatedCaches($mapping);
        } catch (\Exception $e) {
            Log::error('Error in TravelClickPropertyMapping restored observer', [
                'error' => $e->getMessage(),
                'mapping_id' => $mapping->PropertyMappingID
            ]);
        }
    }

    /**
     * Synchronize the mapping with TravelClickPropertyConfig
     * This ensures both models stay in sync when the mapping changes
     */
    protected function syncPropertyConfig(TravelClickPropertyMapping $mapping): void
    {
        try {
            // Find existing config or create new one
            $config = TravelClickPropertyConfig::where('property_id', $mapping->PropertyID)->first();

            if (!$config) {
                $config = new TravelClickPropertyConfig([
                    'property_id' => $mapping->PropertyID,
                    'is_active' => $mapping->IsActive,
                    'config' => []
                ]);
            }

            // Update the configuration with mapping data
            $configData = $config->config ?? [];
            $configData['hotel_code'] = $mapping->TravelClickHotelCode;
            $configData['hotel_name'] = $mapping->TravelClickHotelName;
            $configData['mapping_id'] = $mapping->PropertyMappingID;
            $configData['sync_status'] = $mapping->SyncStatus?->value;
            $configData['last_mapping_update'] = now()->toISOString();

            // Merge mapping configuration if it exists
            if ($mapping->MappingConfiguration) {
                $configData = array_merge($configData, $mapping->MappingConfiguration);
            }

            $config->config = $configData;
            $config->is_active = $mapping->IsActive;
            $config->save();

            Log::info('Successfully synced PropertyConfig with PropertyMapping', [
                'mapping_id' => $mapping->PropertyMappingID,
                'property_id' => $mapping->PropertyID,
                'config_id' => $config->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync PropertyConfig', [
                'error' => $e->getMessage(),
                'mapping_id' => $mapping->PropertyMappingID,
                'property_id' => $mapping->PropertyID
            ]);
        }
    }

    /**
     * Clean up PropertyConfig when mapping is deleted
     */
    protected function cleanupPropertyConfig(TravelClickPropertyMapping $mapping): void
    {
        try {
            $config = TravelClickPropertyConfig::where('property_id', $mapping->PropertyID)->first();

            if ($config) {
                // Instead of deleting, mark as inactive and remove mapping-specific data
                $configData = $config->config ?? [];
                unset($configData['mapping_id']);
                $configData['mapping_deleted_at'] = now()->toISOString();

                $config->config = $configData;
                $config->is_active = false;
                $config->save();

                Log::info('Cleaned up PropertyConfig after mapping deletion', [
                    'mapping_id' => $mapping->PropertyMappingID,
                    'property_id' => $mapping->PropertyID,
                    'config_id' => $config->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to cleanup PropertyConfig', [
                'error' => $e->getMessage(),
                'mapping_id' => $mapping->PropertyMappingID,
                'property_id' => $mapping->PropertyID
            ]);
        }
    }

    /**
     * Handle specific field changes with custom logic
     */
    protected function handleSpecificChanges(TravelClickPropertyMapping $mapping, array $changes, array $original): void
    {
        // Handle sync status changes
        if (array_key_exists('SyncStatus', $changes)) {
            $oldStatus = $original['SyncStatus'] ?? null;
            $newStatus = $changes['SyncStatus'];

            Log::info('Sync status changed', [
                'mapping_id' => $mapping->PropertyMappingID,
                'property_id' => $mapping->PropertyID,
                'from' => $oldStatus,
                'to' => $newStatus
            ]);

            // Clear certain caches when sync status changes
            if ($newStatus === SyncStatus::SUCCESS->value) {
                Cache::forget("travelclick.property.{$mapping->PropertyID}.sync_errors");
            }
        }

        // Handle activation/deactivation
        if (array_key_exists('IsActive', $changes)) {
            $isActivated = $changes['IsActive'] && !($original['IsActive'] ?? false);
            $isDeactivated = !$changes['IsActive'] && ($original['IsActive'] ?? true);

            if ($isActivated) {
                // Clear any error flags when reactivating
                if ($mapping->SyncStatus === SyncStatus::ERROR || $mapping->SyncStatus === SyncStatus::FAILED) {
                    $mapping->update(['SyncStatus' => SyncStatus::PENDING]);
                }
            }

            Log::info('Mapping active status changed', [
                'mapping_id' => $mapping->PropertyMappingID,
                'property_id' => $mapping->PropertyID,
                'activated' => $isActivated,
                'deactivated' => $isDeactivated
            ]);
        }

        // Handle hotel code changes
        if (array_key_exists('TravelClickHotelCode', $changes)) {
            $oldCode = $original['TravelClickHotelCode'] ?? null;
            $newCode = $changes['TravelClickHotelCode'];

            Log::info('Hotel code changed', [
                'mapping_id' => $mapping->PropertyMappingID,
                'property_id' => $mapping->PropertyID,
                'from' => $oldCode,
                'to' => $newCode
            ]);

            // Force sync when hotel code changes
            $mapping->update(['SyncStatus' => SyncStatus::PENDING]);
        }
    }

    /**
     * Invalidate relevant caches when mapping changes
     */
    protected function invalidateRelatedCaches(TravelClickPropertyMapping $mapping): void
    {
        $cachesToInvalidate = [
            // Property-specific caches
            "travelclick.property.{$mapping->PropertyID}.config",
            "travelclick.property.{$mapping->PropertyID}.mapping",
            "travelclick.property.{$mapping->PropertyID}.sync_status",

            // Hotel code specific caches
            "travelclick.hotel_code.{$mapping->TravelClickHotelCode}",

            // General caches
            "travelclick.mappings.active",
            "travelclick.mappings.pending_sync",
            "travelclick.mappings.by_property",
            "travelclick.mappings.by_hotel_code",
            "travelclick.sync.statistics",
            "travelclick.health.report",

            // Property groups and lists
            "travelclick.properties.mapped",
            "travelclick.properties.pending",
            "travelclick.properties.failed"
        ];

        foreach ($cachesToInvalidate as $cacheKey) {
            Cache::forget($cacheKey);
        }

        // Invalidate tagged caches
        Cache::tags([
            'travelclick.mappings',
            "travelclick.property.{$mapping->PropertyID}",
            'travelclick.sync'
        ])->flush();

        Log::debug('Invalidated caches for mapping change', [
            'mapping_id' => $mapping->PropertyMappingID,
            'property_id' => $mapping->PropertyID,
            'caches_cleared' => count($cachesToInvalidate)
        ]);
    }

    /**
     * Log events for debugging and audit purposes
     */
    protected function logEvent(string $event, TravelClickPropertyMapping $mapping, array $context = []): void
    {
        Log::info("PropertyMapping {$event} event", array_merge([
            'event' => $event,
            'mapping_id' => $mapping->PropertyMappingID ?? null,
            'property_id' => $mapping->PropertyID,
            'hotel_code' => $mapping->TravelClickHotelCode,
            'is_active' => $mapping->IsActive,
            'sync_status' => $mapping->SyncStatus?->value
        ], $context));
    }

    /**
     * Create a TravelClickLog entry for the mapping event
     */
    protected function createLogEntry(TravelClickPropertyMapping $mapping, MessageType $messageType, array $data): void
    {
        try {
            TravelClickLog::create([
                'PropertyID' => $mapping->PropertyID,
                'HotelCode' => $mapping->TravelClickHotelCode,
                'MessageType' => $messageType,
                'Direction' => 'Internal',
                'MessageID' => uniqid('mapping_', true),
                'RequestData' => json_encode($data),
                'ResponseData' => null,
                'ProcessingStatus' => 'Success',
                'ErrorMessage' => null,
                'ProcessedAt' => now(),
                'SystemUserID' => auth()->id() ?? $mapping->SystemUserID
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create log entry for mapping event', [
                'error' => $e->getMessage(),
                'mapping_id' => $mapping->PropertyMappingID,
                'message_type' => $messageType->value
            ]);
        }
    }

    /**
     * Create a detailed log entry for updates
     */
    protected function createUpdateLogEntry(TravelClickPropertyMapping $mapping, array $changes, array $original): void
    {
        // Only log significant changes to avoid noise
        $significantFields = [
            'IsActive',
            'TravelClickHotelCode',
            'SyncStatus',
            'MappingConfiguration'
        ];

        $significantChanges = array_intersect_key($changes, array_flip($significantFields));

        if (!empty($significantChanges)) {
            $this->createLogEntry($mapping, MessageType::MAPPING_UPDATED, [
                'action' => 'updated',
                'mapping_id' => $mapping->PropertyMappingID,
                'property_id' => $mapping->PropertyID,
                'changes' => $significantChanges,
                'original_values' => array_intersect_key($original, $significantChanges)
            ]);
        }
    }
}
