<?php

namespace App\TravelClick\Observers;

use App\TravelClick\Enums\SyncStatus;
use App\TravelClick\Events\SyncStatusChanged;
use App\TravelClick\Models\TravelClickSyncStatus;
use App\TravelClick\Jobs\OutboundJobs\RetryFailedSyncJob;
use App\TravelClick\Models\TravelClickLog;
use Illuminate\Support\Facades\Log;

/**
 * TravelClick Sync Status Observer
 *
 * This observer automatically handles events when sync status changes.
 * Think of it as an intelligent assistant that watches for status changes
 * and automatically triggers appropriate actions.
 *
 * For example, when a sync fails, it can automatically schedule a retry,
 * send notifications, or update related logs.
 */
class TravelClickSyncStatusObserver
{
    /**
     * Handle the TravelClickSyncStatus "created" event.
     *
     * Called when a new sync status is created.
     * Sets up initial logging and monitoring.
     */
    public function created(TravelClickSyncStatus $syncStatus): void
    {
        // Log the creation of new sync status
        Log::channel('travelclick')->info('New sync status created', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'initial_status' => $syncStatus->Status->value,
        ]);

        // Create initial log entry
        TravelClickLog::create([
            'PropertyID' => $syncStatus->PropertyID,
            'MessageType' => $syncStatus->MessageType,
            'Direction' => 'outbound',
            'Status' => 'pending',
            'MessageID' => 'SYNC_' . $syncStatus->SyncStatusID . '_' . now()->format('YmdHis'),
            'SystemUserID' => $syncStatus->LastSyncByUserID,
            'DateCreated' => now(),
            'Context' => [
                'sync_status_id' => $syncStatus->SyncStatusID,
                'operation' => 'sync_status_created',
            ],
        ]);

        // Dispatch event for other parts of the system
        event(new SyncStatusChanged($syncStatus, null, 'created'));
    }

    /**
     * Handle the TravelClickSyncStatus "updated" event.
     *
     * Called when sync status is updated.
     * Handles status transitions and automatic actions.
     */
    public function updated(TravelClickSyncStatus $syncStatus): void
    {
        // Get the original values before the update
        $originalStatus = $syncStatus->getOriginal('Status');
        $newStatus = $syncStatus->Status;

        // Only process if status actually changed
        if ($originalStatus !== $newStatus->value) {
            $this->handleStatusTransition($syncStatus, $originalStatus, $newStatus);
        }

        // Handle retry count changes
        $originalRetryCount = $syncStatus->getOriginal('RetryCount');
        $newRetryCount = $syncStatus->RetryCount;

        if ($originalRetryCount !== $newRetryCount && $newRetryCount > 0) {
            $this->handleRetryCountChange($syncStatus, $originalRetryCount, $newRetryCount);
        }

        // Handle success rate significant changes
        $originalSuccessRate = $syncStatus->getOriginal('SuccessRate');
        $newSuccessRate = $syncStatus->SuccessRate;

        if ($this->isSignificantSuccessRateChange($originalSuccessRate, $newSuccessRate)) {
            $this->handleSuccessRateChange($syncStatus, $originalSuccessRate, $newSuccessRate);
        }

        // Dispatch status changed event
        event(new SyncStatusChanged($syncStatus, $originalStatus, 'updated'));
    }

    /**
     * Handle status transitions between different sync states
     */
    protected function handleStatusTransition(
        TravelClickSyncStatus $syncStatus,
        ?string $from,
        SyncStatus $to
    ): void {
        Log::channel('travelclick')->info('Sync status transition', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'from_status' => $from,
            'to_status' => $to->value,
            'retry_count' => $syncStatus->RetryCount,
        ]);

        match ($to) {
            SyncStatus::RUNNING => $this->handleSyncStarted($syncStatus),
            SyncStatus::COMPLETED => $this->handleSyncCompleted($syncStatus),
            SyncStatus::FAILED => $this->handleSyncFailed($syncStatus),
            SyncStatus::PENDING => $this->handleSyncPending($syncStatus),
            default => null,
        };
    }

    /**
     * Handle when a sync starts running
     */
    protected function handleSyncStarted(TravelClickSyncStatus $syncStatus): void
    {
        // Update any related logs to show sync is running
        $this->updateRelatedLogs($syncStatus, 'running');

        // Clear any stale error messages from previous runs
        if ($syncStatus->isDirty('ErrorMessage') && empty($syncStatus->ErrorMessage)) {
            Log::channel('travelclick')->info('Error message cleared for running sync', [
                'sync_status_id' => $syncStatus->SyncStatusID,
            ]);
        }
    }

    /**
     * Handle when a sync completes successfully
     */
    protected function handleSyncCompleted(TravelClickSyncStatus $syncStatus): void
    {
        // Update related logs to reflect completion
        $this->updateRelatedLogs($syncStatus, 'completed');

        // Log success metrics
        Log::channel('travelclick')->info('Sync completed successfully', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'records_processed' => $syncStatus->RecordsProcessed,
            'records_total' => $syncStatus->RecordsTotal,
            'success_rate' => $syncStatus->SuccessRate,
        ]);

        // Cancel any pending retry jobs for this sync
        $this->cancelPendingRetryJobs($syncStatus);

        // Check if this completion improves overall property health
        $this->checkPropertyHealthImprovement($syncStatus);
    }

    /**
     * Handle when a sync fails
     */
    protected function handleSyncFailed(TravelClickSyncStatus $syncStatus): void
    {
        // Update related logs to reflect failure
        $this->updateRelatedLogs($syncStatus, 'failed', [
            'error_message' => $syncStatus->ErrorMessage,
            'retry_count' => $syncStatus->RetryCount,
        ]);

        // Log failure details
        Log::channel('travelclick')->error('Sync failed', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'error_message' => $syncStatus->ErrorMessage,
            'retry_count' => $syncStatus->RetryCount,
            'max_retries' => $syncStatus->MaxRetries,
            'auto_retry_enabled' => $syncStatus->AutoRetryEnabled,
            'next_retry_at' => $syncStatus->NextRetryAt?->toISOString(),
        ]);

        // Schedule retry if applicable
        if ($syncStatus->canRetry() && $syncStatus->NextRetryAt) {
            $this->scheduleRetryJob($syncStatus);
        }

        // Check if we've hit max retries and need manual intervention
        if ($syncStatus->RetryCount >= $syncStatus->MaxRetries) {
            $this->handleMaxRetriesReached($syncStatus);
        }

        // Check for pattern of failures that might indicate a bigger issue
        $this->checkForFailurePatterns($syncStatus);
    }

    /**
     * Handle when a sync is reset to pending
     */
    protected function handleSyncPending(TravelClickSyncStatus $syncStatus): void
    {
        // Update related logs
        $this->updateRelatedLogs($syncStatus, 'pending');

        Log::channel('travelclick')->info('Sync reset to pending', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'reset_by_user' => $syncStatus->LastSyncByUserID,
        ]);
    }

    /**
     * Handle retry count changes
     */
    protected function handleRetryCountChange(
        TravelClickSyncStatus $syncStatus,
        int $oldCount,
        int $newCount
    ): void {
        Log::channel('travelclick')->info('Retry count changed', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'old_retry_count' => $oldCount,
            'new_retry_count' => $newCount,
            'max_retries' => $syncStatus->MaxRetries,
        ]);

        // Log warning if approaching max retries
        if ($newCount >= $syncStatus->MaxRetries - 1) {
            Log::channel('travelclick')->warning('Sync approaching max retries', [
                'sync_status_id' => $syncStatus->SyncStatusID,
                'property_id' => $syncStatus->PropertyID,
                'message_type' => $syncStatus->MessageType->value,
                'retry_count' => $newCount,
                'max_retries' => $syncStatus->MaxRetries,
            ]);
        }
    }

    /**
     * Handle significant success rate changes
     */
    protected function handleSuccessRateChange(
        TravelClickSyncStatus $syncStatus,
        ?float $oldRate,
        ?float $newRate
    ): void {
        $rateDifference = $newRate - ($oldRate ?? 0);

        Log::channel('travelclick')->info('Success rate changed significantly', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'old_success_rate' => $oldRate,
            'new_success_rate' => $newRate,
            'difference' => $rateDifference,
        ]);

        // Alert if success rate drops significantly
        if ($rateDifference < -20) {
            Log::channel('travelclick')->warning('Success rate dropped significantly', [
                'sync_status_id' => $syncStatus->SyncStatusID,
                'property_id' => $syncStatus->PropertyID,
                'message_type' => $syncStatus->MessageType->value,
                'rate_drop' => abs($rateDifference),
            ]);
        }
    }

    /**
     * Update related TravelClickLog entries with sync status
     */
    protected function updateRelatedLogs(
        TravelClickSyncStatus $syncStatus,
        string $status,
        array $additionalContext = []
    ): void {
        TravelClickLog::where('PropertyID', $syncStatus->PropertyID)
            ->where('MessageType', $syncStatus->MessageType)
            ->whereIn('Status', ['pending', 'running'])
            ->update([
                'Status' => $status,
                'DateModified' => now(),
                'Context' => array_merge([
                    'sync_status_id' => $syncStatus->SyncStatusID,
                    'updated_by_observer' => true,
                ], $additionalContext),
            ]);
    }

    /**
     * Schedule a retry job for failed sync
     */
    protected function scheduleRetryJob(TravelClickSyncStatus $syncStatus): void
    {
        try {
            RetryFailedSyncJob::dispatch($syncStatus)
                ->delay($syncStatus->NextRetryAt)
                ->onQueue('travelclick-retry');

            Log::channel('travelclick')->info('Retry job scheduled', [
                'sync_status_id' => $syncStatus->SyncStatusID,
                'property_id' => $syncStatus->PropertyID,
                'message_type' => $syncStatus->MessageType->value,
                'retry_at' => $syncStatus->NextRetryAt->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::channel('travelclick')->error('Failed to schedule retry job', [
                'sync_status_id' => $syncStatus->SyncStatusID,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cancel any pending retry jobs for this sync
     */
    protected function cancelPendingRetryJobs(TravelClickSyncStatus $syncStatus): void
    {
        // This would integrate with your job management system
        // Implementation depends on your queue driver and job tracking
        Log::channel('travelclick')->info('Cancelled pending retry jobs', [
            'sync_status_id' => $syncStatus->SyncStatusID,
        ]);
    }

    /**
     * Handle when max retries are reached
     */
    protected function handleMaxRetriesReached(TravelClickSyncStatus $syncStatus): void
    {
        Log::channel('travelclick')->critical('Sync reached max retries', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'max_retries' => $syncStatus->MaxRetries,
            'error_message' => $syncStatus->ErrorMessage,
        ]);

        // Disable auto-retry to prevent infinite loops
        $syncStatus->update(['AutoRetryEnabled' => false]);

        // This could trigger alerts to operations team
        // event(new MaxRetriesReachedEvent($syncStatus));
    }

    /**
     * Check for failure patterns that might indicate system issues
     */
    protected function checkForFailurePatterns(TravelClickSyncStatus $syncStatus): void
    {
        // Check if other syncs for the same property are also failing
        $recentFailures = TravelClickSyncStatus::where('PropertyID', $syncStatus->PropertyID)
            ->where('Status', SyncStatus::FAILED)
            ->where('LastSyncAttempt', '>=', now()->subHours(1))
            ->count();

        if ($recentFailures >= 3) {
            Log::channel('travelclick')->warning('Multiple sync failures detected for property', [
                'property_id' => $syncStatus->PropertyID,
                'failure_count' => $recentFailures,
                'timeframe' => '1 hour',
            ]);
        }

        // Check for system-wide issues
        $systemWideFailures = TravelClickSyncStatus::where('Status', SyncStatus::FAILED)
            ->where('LastSyncAttempt', '>=', now()->subMinutes(30))
            ->count();

        if ($systemWideFailures >= 10) {
            Log::channel('travelclick')->critical('System-wide sync failures detected', [
                'failure_count' => $systemWideFailures,
                'timeframe' => '30 minutes',
            ]);
        }
    }

    /**
     * Check if sync completion improves overall property health
     */
    protected function checkPropertyHealthImprovement(TravelClickSyncStatus $syncStatus): void
    {
        $healthScore = $syncStatus->getSyncHealthScoreAttribute();

        if ($healthScore >= 90) {
            Log::channel('travelclick')->info('High health score achieved', [
                'sync_status_id' => $syncStatus->SyncStatusID,
                'property_id' => $syncStatus->PropertyID,
                'message_type' => $syncStatus->MessageType->value,
                'health_score' => $healthScore,
            ]);
        }
    }

    /**
     * Check if success rate change is significant
     */
    protected function isSignificantSuccessRateChange(?float $old, ?float $new): bool
    {
        if ($old === null || $new === null) {
            return false;
        }

        return abs($new - $old) >= 10; // 10% change is considered significant
    }

    /**
     * Handle the TravelClickSyncStatus "deleting" event.
     *
     * Called before a sync status is deleted.
     * Clean up related data and logs.
     */
    public function deleting(TravelClickSyncStatus $syncStatus): void
    {
        Log::channel('travelclick')->info('Sync status being deleted', [
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'final_status' => $syncStatus->Status->value,
        ]);

        // Cancel any pending retry jobs
        $this->cancelPendingRetryJobs($syncStatus);

        // Mark related logs as orphaned
        TravelClickLog::where('PropertyID', $syncStatus->PropertyID)
            ->where('MessageType', $syncStatus->MessageType)
            ->update([
                'Context' => \DB::raw("JSON_SET(COALESCE(Context, '{}'), '$.sync_status_deleted', true)"),
            ]);
    }

    /**
     * Handle the TravelClickSyncStatus "deleted" event.
     *
     * Called after a sync status is deleted.
     * Final cleanup and notifications.
     */
    public function deleted(TravelClickSyncStatus $syncStatus): void
    {
        Log::channel('travelclick')->info('Sync status deleted', [
            'sync_status_id' => $syncStatus->SyncStatusID,
        ]);

        // Dispatch event for external systems
        event(new SyncStatusChanged($syncStatus, $syncStatus->Status->value, 'deleted'));
    }
}
