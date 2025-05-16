<?php

namespace App\TravelClick\Observers;

use App\TravelClick\Models\TravelClickErrorLog;
use App\TravelClick\Events\SyncStatusChanged;
use App\TravelClick\Models\TravelClickSyncStatus;
use App\TravelClick\Enums\ErrorType;
use App\TravelClick\Enums\SyncStatus as SyncStatusEnum;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;

/**
 * TravelClick Error Log Observer
 *
 * This observer handles events for the TravelClickErrorLog model.
 * It acts like a watchful supervisor that reacts to error events,
 * ensuring proper logging, status updates, and notifications.
 *
 * Think of this as an emergency response system - when an error occurs,
 * this observer springs into action to handle the aftermath.
 */
class TravelClickErrorLogObserver
{
    /**
     * Handle the TravelClickErrorLog "created" event.
     *
     * When a new error is logged, this method:
     * 1. Logs to Laravel's standard logging system
     * 2. Updates related sync status if applicable
     * 3. Triggers status change events
     * 4. Handles critical error escalation
     */
    public function created(TravelClickErrorLog $errorLog): void
    {
        // Log to Laravel's logging system for centralized monitoring
        $this->logToLaravel($errorLog, 'created');

        // Update related sync status if this error affects an ongoing sync
        $this->updateRelatedSyncStatus($errorLog);

        // Handle critical errors with special urgency
        if ($this->isCriticalError($errorLog)) {
            $this->handleCriticalError($errorLog);
        }

        // Fire sync status event if applicable
        $this->fireSyncStatusEvent($errorLog, 'created');
    }

    /**
     * Handle the TravelClickErrorLog "updated" event.
     *
     * When an error log is updated (usually when resolved),
     * this method tracks the resolution and updates related statuses.
     */
    public function updated(TravelClickErrorLog $errorLog): void
    {
        // Get the original values to detect what changed
        $original = $errorLog->getOriginal();

        // Check if the error was resolved
        if ($this->wasErrorResolved($errorLog, $original)) {
            $this->handleErrorResolution($errorLog);
        }

        // Log the update with relevant details
        $this->logToLaravel($errorLog, 'updated', [
            'changed_fields' => array_keys($errorLog->getDirty()),
            'was_resolved' => isset($errorLog->ResolvedAt) && !isset($original['ResolvedAt'])
        ]);

        // Update sync status if resolution affects ongoing operations
        $this->updateRelatedSyncStatusOnResolution($errorLog, $original);
    }

    /**
     * Handle the TravelClickErrorLog "deleted" event.
     *
     * Cleanup and logging when error records are removed.
     */
    public function deleted(TravelClickErrorLog $errorLog): void
    {
        // Log the deletion for audit trail
        $this->logToLaravel($errorLog, 'deleted');

        // Clean up any related status that might reference this error
        $this->cleanupRelatedStatuses($errorLog);
    }

    /**
     * Handle bulk operations efficiently.
     * This prevents the observer from firing for each individual record
     * during bulk operations, improving performance.
     */
    public function creating(TravelClickErrorLog $errorLog): void
    {
        // Ensure we have basic required fields before creation
        if (empty($errorLog->MessageID)) {
            $errorLog->MessageID = 'ERROR-' . uniqid();
        }

        if (empty($errorLog->DateCreated)) {
            $errorLog->DateCreated = now();
        }
    }

    /**
     * Log error events to Laravel's standard logging system.
     * This provides a unified view across all system logs.
     */
    private function logToLaravel(
        TravelClickErrorLog $errorLog,
        string $action,
        array $additionalContext = []
    ): void {
        $context = array_merge([
            'error_log_id' => $errorLog->ErrorLogID,
            'message_id' => $errorLog->MessageID,
            'job_id' => $errorLog->JobID,
            'error_type' => $errorLog->ErrorType->value,
            'severity' => $errorLog->Severity,
            'property_id' => $errorLog->PropertyID,
            'can_retry' => $errorLog->CanRetry,
            'requires_manual_intervention' => $errorLog->RequiresManualIntervention,
            'source_class' => $errorLog->SourceClass,
            'source_method' => $errorLog->SourceMethod,
            'action' => $action
        ], $additionalContext);

        // Log with appropriate level based on severity
        $logLevel = $this->mapSeverityToLogLevel($errorLog->Severity);

        Log::$logLevel("TravelClick Error Log {$action}", $context);

        // For critical errors, also log at error level regardless of severity
        if ($this->isCriticalError($errorLog)) {
            Log::error("CRITICAL TravelClick Error: {$errorLog->ErrorTitle}", $context);
        }
    }

    /**
     * Map error severity to appropriate logging level.
     */
    private function mapSeverityToLogLevel(string $severity): string
    {
        return match (strtolower($severity)) {
            'critical' => 'critical',
            'high' => 'error',
            'medium' => 'warning',
            'low' => 'info',
            default => 'debug'
        };
    }

    /**
     * Check if this is a critical error that needs immediate attention.
     */
    private function isCriticalError(TravelClickErrorLog $errorLog): bool
    {
        return $errorLog->Severity === 'critical' ||
            $errorLog->RequiresManualIntervention ||
            $errorLog->ErrorType === ErrorType::AUTHENTICATION ||
            $errorLog->ErrorType === ErrorType::CONNECTION;
    }

    /**
     * Handle critical errors with special escalation procedures.
     */
    private function handleCriticalError(TravelClickErrorLog $errorLog): void
    {
        // Log to alert systems
        Log::alert("CRITICAL TravelClick Error Requires Immediate Attention", [
            'error_log_id' => $errorLog->ErrorLogID,
            'error_title' => $errorLog->ErrorTitle,
            'property_id' => $errorLog->PropertyID,
            'error_type' => $errorLog->ErrorType->value,
            'message_id' => $errorLog->MessageID
        ]);

        // Here you could integrate with alerting systems like:
        // - Slack notifications
        // - PagerDuty alerts
        // - Email notifications to administrators
        // Example: Notification::route('slack', '#alerts')->notify(new CriticalErrorAlert($errorLog));
    }

    /**
     * Update related sync status when an error occurs.
     * This keeps the sync status in sync with actual error states.
     */
    private function updateRelatedSyncStatus(TravelClickErrorLog $errorLog): void
    {
        if (!$errorLog->MessageID) {
            return;
        }

        // Try to find related sync status by MessageID
        $syncStatus = TravelClickSyncStatus::where('MessageID', $errorLog->MessageID)
            ->orWhere('PropertyID', $errorLog->PropertyID)
            ->where('Status', '!=', SyncStatusEnum::COMPLETED)
            ->where('Status', '!=', SyncStatusEnum::FAILED)
            ->first();

        if (!$syncStatus) {
            return;
        }

        $previousStatus = $syncStatus->Status->value;

        // Update sync status based on error severity and type
        if ($this->isCriticalError($errorLog)) {
            $syncStatus->Status = SyncStatusEnum::FAILED;
            $syncStatus->ErrorMessage = $errorLog->ErrorMessage;
            $syncStatus->LastError = $errorLog->ErrorTitle;
        } else {
            $syncStatus->Status = SyncStatusEnum::ERROR;
            $syncStatus->RetryCount += 1;
            $syncStatus->LastError = $errorLog->ErrorTitle;

            // Set next retry time if error can be retried
            if ($errorLog->CanRetry && $errorLog->RecommendedRetryDelay) {
                $syncStatus->NextRetryAt = now()->addSeconds($errorLog->RecommendedRetryDelay);
            }
        }

        $syncStatus->save();

        // Fire sync status changed event
        Event::dispatch(new SyncStatusChanged(
            $syncStatus,
            $previousStatus,
            'updated',
            ['triggered_by_error_log' => $errorLog->ErrorLogID]
        ));
    }

    /**
     * Check if an error was resolved by comparing with original values.
     */
    private function wasErrorResolved(TravelClickErrorLog $errorLog, array $original): bool
    {
        return isset($errorLog->ResolvedAt) && !isset($original['ResolvedAt']);
    }

    /**
     * Handle error resolution - update related statuses and log resolution.
     */
    private function handleErrorResolution(TravelClickErrorLog $errorLog): void
    {
        Log::info("TravelClick Error Resolved", [
            'error_log_id' => $errorLog->ErrorLogID,
            'message_id' => $errorLog->MessageID,
            'resolved_by' => $errorLog->ResolvedByUserID,
            'resolution_notes' => $errorLog->ResolutionNotes,
            'time_to_resolution' => $errorLog->ResolvedAt->diffInMinutes($errorLog->DateCreated) . ' minutes'
        ]);
    }

    /**
     * Update sync status when errors are resolved.
     * This can potentially allow retries of previously failed operations.
     */
    private function updateRelatedSyncStatusOnResolution(
        TravelClickErrorLog $errorLog,
        array $original
    ): void {
        if (!$this->wasErrorResolved($errorLog, $original)) {
            return;
        }

        // Find related sync status
        $syncStatus = TravelClickSyncStatus::where('MessageID', $errorLog->MessageID)
            ->orWhere('PropertyID', $errorLog->PropertyID)
            ->where('Status', SyncStatusEnum::FAILED)
            ->first();

        if (!$syncStatus) {
            return;
        }

        // Check if this was the last blocking error
        $remainingErrors = TravelClickErrorLog::where('MessageID', $errorLog->MessageID)
            ->whereNull('ResolvedAt')
            ->where('RequiresManualIntervention', true)
            ->count();

        if ($remainingErrors === 0) {
            $previousStatus = $syncStatus->Status->value;
            $syncStatus->Status = SyncStatusEnum::RETRY_PENDING;
            $syncStatus->ErrorMessage = null;
            $syncStatus->LastError = null;
            $syncStatus->NextRetryAt = now()->addMinutes(5); // Short delay before retry
            $syncStatus->save();

            Event::dispatch(new SyncStatusChanged(
                $syncStatus,
                $previousStatus,
                'updated',
                ['triggered_by_error_resolution' => $errorLog->ErrorLogID]
            ));
        }
    }

    /**
     * Clean up related statuses when an error log is deleted.
     */
    private function cleanupRelatedStatuses(TravelClickErrorLog $errorLog): void
    {
        // Note: Be careful with this - usually error logs should not be deleted
        // but archived instead. This is here for completeness.
        Log::info("TravelClick Error Log Deleted", [
            'error_log_id' => $errorLog->ErrorLogID,
            'message_id' => $errorLog->MessageID,
            'error_type' => $errorLog->ErrorType->value
        ]);

        // Could update sync statuses that reference this error
        // but typically error logs should be archived, not deleted
    }

    /**
     * Fire sync status changed event if related sync status exists.
     */
    private function fireSyncStatusEvent(TravelClickErrorLog $errorLog, string $changeType): void
    {
        if (!class_exists(SyncStatusChanged::class)) {
            return;
        }

        // Find related sync status
        $syncStatus = TravelClickSyncStatus::where('MessageID', $errorLog->MessageID)->first();

        if ($syncStatus) {
            Event::dispatch(new SyncStatusChanged(
                $syncStatus,
                null,
                $changeType,
                [
                    'triggered_by_error_log' => $errorLog->ErrorLogID,
                    'error_severity' => $errorLog->Severity,
                    'error_type' => $errorLog->ErrorType->value
                ]
            ));
        }
    }

    /**
     * Handle bulk operations (for performance).
     * When doing bulk inserts/updates, we might want to disable
     * observer events to improve performance.
     */
    public static function withoutEvents(callable $callback)
    {
        return TravelClickErrorLog::withoutEvents($callback);
    }
}
