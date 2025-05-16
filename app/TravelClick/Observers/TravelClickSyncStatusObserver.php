<?php

namespace App\TravelClick\Observers;

use App\TravelClick\Events\SyncStatusChanged;
use App\TravelClick\Models\TravelClickSyncStatus;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * TravelClickSyncStatusObserver
 *
 * Handles events for TravelClickSyncStatus model.
 * This observer is like a security camera system for sync operations - it watches
 * every change and records important events for auditing and monitoring.
 *
 * Key responsibilities:
 * - Track state transitions and duration calculations
 * - Dispatch SyncStatusChanged events for real-time updates
 * - Log important changes for auditing
 * - Calculate metrics for health monitoring
 */
class TravelClickSyncStatusObserver
{
    /**
     * Handle the TravelClickSyncStatus "creating" event.
     * Fired before a new sync status record is created.
     *
     * @param TravelClickSyncStatus $syncStatus
     * @return void
     */
    public function creating(TravelClickSyncStatus $syncStatus): void
    {
        // Log the creation of new sync status
        Log::info('Creating new sync status record', [
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType?->value,
            'status' => $syncStatus->Status?->value,
            'context' => 'sync_status_observer',
        ]);

        // Ensure DateCreated is set if not already
        if (!$syncStatus->DateCreated) {
            $syncStatus->DateCreated = now();
        }

        // Initialize context array if empty
        if (empty($syncStatus->Context)) {
            $syncStatus->Context = [
                'created_at' => now()->toISOString(),
                'initial_status' => $syncStatus->Status?->value,
            ];
        }
    }

    /**
     * Handle the TravelClickSyncStatus "created" event.
     * Fired after a new sync status record has been saved.
     *
     * @param TravelClickSyncStatus $syncStatus
     * @return void
     */
    public function created(TravelClickSyncStatus $syncStatus): void
    {
        // Dispatch SyncStatusChanged event for real-time updates
        event(new SyncStatusChanged(
            $syncStatus,
            null, // No previous status for new records
            'created',
            [
                'created_by_observer' => true,
                'initial_status' => $syncStatus->Status->value,
            ]
        ));

        // Log successful creation with detailed context
        Log::info('Sync status record created successfully', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'status' => $syncStatus->Status->value,
            'context' => 'sync_status_observer',
        ]);

        // Create metrics tracking if this is the first sync for this combination
        $this->trackInitialMetrics($syncStatus);
    }

    /**
     * Handle the TravelClickSyncStatus "updating" event.
     * Fired before changes are saved to the database.
     *
     * @param TravelClickSyncStatus $syncStatus
     * @return void
     */
    public function updating(TravelClickSyncStatus $syncStatus): void
    {
        // Set DateModified automatically
        $syncStatus->DateModified = now();

        // Get the original values to track what changed
        $originalValues = $syncStatus->getOriginal();
        $changes = $syncStatus->getDirty();

        // Store original status for event dispatching
        $syncStatus->_original_status = $originalValues['Status'] ?? null;

        // Calculate duration if status is changing
        if (array_key_exists('Status', $changes)) {
            $this->calculateTransitionDuration($syncStatus, $originalValues);
        }

        // Update context with change information
        $this->updateContextWithChanges($syncStatus, $changes, $originalValues);

        // Log the update attempt
        Log::debug('Updating sync status record', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'changes' => $changes,
            'context' => 'sync_status_observer',
        ]);
    }

    /**
     * Handle the TravelClickSyncStatus "updated" event.
     * Fired after changes have been saved to the database.
     *
     * @param TravelClickSyncStatus $syncStatus
     * @return void
     */
    public function updated(TravelClickSyncStatus $syncStatus): void
    {
        // Get the original status that was stored during updating
        $originalStatus = $syncStatus->_original_status ?? null;
        $currentStatus = $syncStatus->Status->value;

        // Check if status actually changed
        $statusChanged = $originalStatus && $originalStatus !== $currentStatus;

        // Dispatch SyncStatusChanged event
        event(new SyncStatusChanged(
            $syncStatus,
            $originalStatus,
            'updated',
            [
                'status_changed' => $statusChanged,
                'updated_by_observer' => true,
                'changes' => $syncStatus->getChanges(),
            ]
        ));

        // Log significant changes
        if ($statusChanged) {
            $this->logStatusTransition($syncStatus, $originalStatus, $currentStatus);
        }

        // Update health metrics after status changes
        if ($statusChanged && $syncStatus->isSuccess()) {
            $this->updateSuccessMetrics($syncStatus);
        } elseif ($statusChanged && $syncStatus->isFailure()) {
            $this->updateFailureMetrics($syncStatus);
        }

        // Clean up temporary attributes
        unset($syncStatus->_original_status);
    }

    /**
     * Handle the TravelClickSyncStatus "deleted" event.
     * Fired after a record has been deleted.
     *
     * @param TravelClickSyncStatus $syncStatus
     * @return void
     */
    public function deleted(TravelClickSyncStatus $syncStatus): void
    {
        // Dispatch SyncStatusChanged event for deletion
        event(new SyncStatusChanged(
            $syncStatus,
            $syncStatus->Status->value,
            'deleted',
            [
                'deleted_by_observer' => true,
                'final_status' => $syncStatus->Status->value,
                'final_health_score' => $syncStatus->getSyncHealthScoreAttribute(),
            ]
        ));

        // Log the deletion with context
        Log::warning('Sync status record deleted', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'final_status' => $syncStatus->Status->value,
            'context' => 'sync_status_observer',
        ]);

        // Archive important metrics before deletion
        $this->archiveDeletedSyncMetrics($syncStatus);
    }

    /**
     * Handle the TravelClickSyncStatus "restored" event.
     * Fired after a soft-deleted record has been restored.
     *
     * @param TravelClickSyncStatus $syncStatus
     * @return void
     */
    public function restored(TravelClickSyncStatus $syncStatus): void
    {
        // Dispatch SyncStatusChanged event for restoration
        event(new SyncStatusChanged(
            $syncStatus,
            null,
            'restored',
            [
                'restored_by_observer' => true,
                'restored_at' => now()->toISOString(),
            ]
        ));

        // Log the restoration
        Log::info('Sync status record restored', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'context' => 'sync_status_observer',
        ]);
    }

    /**
     * Handle the TravelClickSyncStatus "forceDeleted" event.
     * Fired after a record has been permanently deleted.
     *
     * @param TravelClickSyncStatus $syncStatus
     * @return void
     */
    public function forceDeleted(TravelClickSyncStatus $syncStatus): void
    {
        // Log permanent deletion
        Log::warning('Sync status record permanently deleted', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'context' => 'sync_status_observer',
        ]);

        // Archive metrics before permanent deletion
        $this->archiveDeletedSyncMetrics($syncStatus, true);
    }

    /**
     * Calculate and store transition duration information.
     *
     * @param TravelClickSyncStatus $syncStatus
     * @param array $originalValues
     * @return void
     */
    private function calculateTransitionDuration(
        TravelClickSyncStatus $syncStatus,
        array $originalValues
    ): void {
        $context = $syncStatus->Context ?? [];
        $originalStatus = $originalValues['Status'] ?? null;
        $newStatus = $syncStatus->Status->value;

        // Calculate duration since last status change
        $lastStatusChangeKey = 'last_status_change_at';
        if (isset($context[$lastStatusChangeKey])) {
            $lastChangeAt = Carbon::parse($context[$lastStatusChangeKey]);
            $duration = $lastChangeAt->diffInSeconds(now());

            // Store duration for the previous status
            $durationKey = "duration_in_{$originalStatus}_seconds";
            $context[$durationKey] = $duration;

            // Also store human-readable duration
            $context["duration_in_{$originalStatus}_human"] = $lastChangeAt->diffForHumans(now(), true);
        }

        // Update context with new status change time
        $context[$lastStatusChangeKey] = now()->toISOString();
        $context['status_transitions'][] = [
            'from' => $originalStatus,
            'to' => $newStatus,
            'at' => now()->toISOString(),
        ];

        $syncStatus->Context = $context;
    }

    /**
     * Update context with information about what changed.
     *
     * @param TravelClickSyncStatus $syncStatus
     * @param array $changes
     * @param array $originalValues
     * @return void
     */
    private function updateContextWithChanges(
        TravelClickSyncStatus $syncStatus,
        array $changes,
        array $originalValues
    ): void {
        $context = $syncStatus->Context ?? [];

        // Track what fields changed
        $context['last_update'] = [
            'timestamp' => now()->toISOString(),
            'changed_fields' => array_keys($changes),
            'change_summary' => $this->generateChangeSummary($changes, $originalValues),
        ];

        // Increment update counter
        $context['update_count'] = ($context['update_count'] ?? 0) + 1;

        $syncStatus->Context = $context;
    }

    /**
     * Generate a human-readable summary of changes.
     *
     * @param array $changes
     * @param array $originalValues
     * @return array
     */
    private function generateChangeSummary(array $changes, array $originalValues): array
    {
        $summary = [];

        foreach ($changes as $field => $newValue) {
            $oldValue = $originalValues[$field] ?? null;

            $summary[$field] = [
                'from' => $oldValue,
                'to' => $newValue,
                'changed_at' => now()->toISOString(),
            ];
        }

        return $summary;
    }

    /**
     * Log detailed information about status transitions.
     *
     * @param TravelClickSyncStatus $syncStatus
     * @param string|null $originalStatus
     * @param string $currentStatus
     * @return void
     */
    private function logStatusTransition(
        TravelClickSyncStatus $syncStatus,
        ?string $originalStatus,
        string $currentStatus
    ): void {
        $logLevel = $this->getLogLevelForTransition($originalStatus, $currentStatus);
        $message = "Sync status transition: {$originalStatus} → {$currentStatus}";

        Log::log($logLevel, $message, [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'from_status' => $originalStatus,
            'to_status' => $currentStatus,
            'retry_count' => $syncStatus->RetryCount,
            'health_score' => $syncStatus->getSyncHealthScoreAttribute(),
            'success_rate' => $syncStatus->SuccessRate,
            'context' => 'sync_status_transition',
        ]);
    }

    /**
     * Determine appropriate log level based on status transition.
     *
     * @param string|null $fromStatus
     * @param string $toStatus
     * @return string
     */
    private function getLogLevelForTransition(?string $fromStatus, string $toStatus): string
    {
        // Critical: Transitions to permanent failure
        if ($toStatus === 'failed_permanent') {
            return 'critical';
        }

        // Error: Transitions to failure states
        if (in_array($toStatus, ['failed', 'error'])) {
            return 'error';
        }

        // Warning: Transitions to problematic states
        if (in_array($toStatus, ['on_hold', 'partial'])) {
            return 'warning';
        }

        // Info: Transitions to success or normal operation
        if (in_array($toStatus, ['completed', 'success', 'running', 'processing'])) {
            return 'info';
        }

        // Debug: All other transitions
        return 'debug';
    }

    /**
     * Track metrics for initial sync creation.
     *
     * @param TravelClickSyncStatus $syncStatus
     * @return void
     */
    private function trackInitialMetrics(TravelClickSyncStatus $syncStatus): void
    {
        // Log creation of sync for this property/message type combination
        Log::info('New sync tracking started', [
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'initial_status' => $syncStatus->Status->value,
            'auto_retry_enabled' => $syncStatus->AutoRetryEnabled,
            'max_retries' => $syncStatus->MaxRetries,
            'context' => 'sync_metrics',
        ]);
    }

    /**
     * Update metrics when sync completes successfully.
     *
     * @param TravelClickSyncStatus $syncStatus
     * @return void
     */
    private function updateSuccessMetrics(TravelClickSyncStatus $syncStatus): void
    {
        // Calculate completion duration if we have context
        $context = $syncStatus->Context ?? [];
        $duration = null;

        if (isset($context['started_at'])) {
            $startedAt = Carbon::parse($context['started_at']);
            $duration = $startedAt->diffInSeconds(now());
        }

        // Log successful completion
        Log::info('Sync completed successfully', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'records_processed' => $syncStatus->RecordsProcessed,
            'records_total' => $syncStatus->RecordsTotal,
            'success_rate' => $syncStatus->SuccessRate,
            'completion_duration_seconds' => $duration,
            'retry_count_final' => $syncStatus->RetryCount,
            'context' => 'sync_success_metrics',
        ]);
    }

    /**
     * Update metrics when sync fails.
     *
     * @param TravelClickSyncStatus $syncStatus
     * @return void
     */
    private function updateFailureMetrics(TravelClickSyncStatus $syncStatus): void
    {
        // Determine failure severity
        $severity = $syncStatus->RetryCount >= $syncStatus->MaxRetries ? 'permanent' : 'retry_available';

        // Log failure with context
        Log::error('Sync operation failed', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'error_message' => $syncStatus->ErrorMessage,
            'retry_count' => $syncStatus->RetryCount,
            'max_retries' => $syncStatus->MaxRetries,
            'severity' => $severity,
            'auto_retry_enabled' => $syncStatus->AutoRetryEnabled,
            'next_retry_at' => $syncStatus->NextRetryAt?->toISOString(),
            'success_rate' => $syncStatus->SuccessRate,
            'context' => 'sync_failure_metrics',
        ]);
    }

    /**
     * Archive important metrics before deletion.
     *
     * @param TravelClickSyncStatus $syncStatus
     * @param bool $permanent
     * @return void
     */
    private function archiveDeletedSyncMetrics(
        TravelClickSyncStatus $syncStatus,
        bool $permanent = false
    ): void {
        $archiveData = [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'final_status' => $syncStatus->Status->value,
            'total_retries' => $syncStatus->RetryCount,
            'final_success_rate' => $syncStatus->SuccessRate,
            'final_health_score' => $syncStatus->getSyncHealthScoreAttribute(),
            'last_successful_sync' => $syncStatus->LastSuccessfulSync?->toISOString(),
            'last_sync_attempt' => $syncStatus->LastSyncAttempt?->toISOString(),
            'records_processed' => $syncStatus->RecordsProcessed,
            'records_total' => $syncStatus->RecordsTotal,
            'context' => $syncStatus->Context,
            'deletion_type' => $permanent ? 'permanent' : 'soft',
            'archived_at' => now()->toISOString(),
        ];

        // Log the archived metrics
        Log::info('Sync metrics archived before deletion', [
            'archive_data' => $archiveData,
            'context' => 'sync_metrics_archive',
        ]);

        // Store in a more permanent location if needed
        // This could be a separate table, file, or external service
        // For now, we'll use structured logging
    }
}
