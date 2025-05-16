<?php

namespace App\TravelClick\Observers;

use App\TravelClick\Models\TravelClickMessageHistory;
use App\TravelClick\Models\TravelClickSyncStatus;
use App\TravelClick\Events\SyncStatusChanged;
use App\TravelClick\Enums\ProcessingStatus;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\MessageDirection;
use App\TravelClick\Enums\SyncStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * TravelClickMessageHistoryObserver
 *
 * This observer is like a detective who watches every message that goes through
 * the TravelClick system and takes notes about patterns, duplicates, and sync status.
 *
 * Key responsibilities:
 * - Track message patterns and update sync status accordingly
 * - Detect and handle duplicate messages (deduplication)
 * - Fire SyncStatusChanged events when patterns indicate status changes
 * - Maintain sync health metrics based on message success/failure patterns
 * - Handle batch operation tracking
 */
class TravelClickMessageHistoryObserver
{
    /**
     * Cache key prefix for message deduplication
     */
    private const DEDUP_CACHE_PREFIX = 'travelclick_dedup_';

    /**
     * Cache TTL for deduplication (24 hours in seconds)
     */
    private const DEDUP_CACHE_TTL = 86400;

    /**
     * Threshold for considering a sync as failing (failure rate %)
     */
    private const FAILURE_THRESHOLD = 30;

    /**
     * Number of recent messages to analyze for sync status
     */
    private const ANALYSIS_MESSAGE_COUNT = 50;

    /**
     * Handle the TravelClickMessageHistory "created" event.
     *
     * When a new message is created, we need to:
     * 1. Check for duplicates using XML hash
     * 2. Update sync status if this is an outbound message
     * 3. Cache the message for deduplication
     */
    public function created(TravelClickMessageHistory $messageHistory): void
    {
        try {
            // Log the message creation
            Log::info('New TravelClick message created', [
                'message_id' => $messageHistory->MessageID,
                'type' => $messageHistory->MessageType?->value,
                'direction' => $messageHistory->Direction?->value,
                'property_id' => $messageHistory->PropertyID,
                'xml_hash' => $messageHistory->XmlHash,
            ]);

            // 1. Handle deduplication
            $this->handleDeduplication($messageHistory);

            // 2. Update sync status based on message direction
            if ($messageHistory->Direction === MessageDirection::OUTBOUND) {
                $this->updateSyncStatusOnOutbound($messageHistory);
            }

            // 3. Cache for future deduplication checks
            $this->cacheMessageForDeduplication($messageHistory);

            // 4. Check if this is part of a batch and update batch status
            if ($messageHistory->BatchID) {
                $this->updateBatchMetrics($messageHistory);
            }
        } catch (\Exception $e) {
            Log::error('Error in TravelClickMessageHistoryObserver::created', [
                'message_id' => $messageHistory->MessageID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle the TravelClickMessageHistory "updated" event.
     *
     * When a message is updated (usually status changes), we need to:
     * 1. Update sync status based on the new processing status
     * 2. Check if sync health needs to be recalculated
     * 3. Fire appropriate events
     */
    public function updated(TravelClickMessageHistory $messageHistory): void
    {
        try {
            // Check what changed
            $changedFields = $messageHistory->getDirty();

            // If processing status changed, update sync status
            if (isset($changedFields['ProcessingStatus'])) {
                $this->updateSyncStatusOnProcessingChange($messageHistory);
            }

            // If the message was marked as processed or failed, analyze sync health
            if (in_array($messageHistory->ProcessingStatus, [
                ProcessingStatus::PROCESSED,
                ProcessingStatus::FAILED
            ])) {
                $this->analyzeSyncHealth($messageHistory);
            }

            // Update batch metrics if this is part of a batch
            if ($messageHistory->BatchID) {
                $this->updateBatchMetrics($messageHistory);
            }
        } catch (\Exception $e) {
            Log::error('Error in TravelClickMessageHistoryObserver::updated', [
                'message_id' => $messageHistory->MessageID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle the TravelClickMessageHistory "deleted" event.
     *
     * Clean up any related cache entries and update metrics.
     */
    public function deleted(TravelClickMessageHistory $messageHistory): void
    {
        try {
            // Clear deduplication cache for this message
            $cacheKey = $this->getDedupCacheKey($messageHistory->XmlHash);
            Cache::forget($cacheKey);

            // Log the deletion
            Log::info('TravelClick message deleted', [
                'message_id' => $messageHistory->MessageID,
                'type' => $messageHistory->MessageType?->value,
                'property_id' => $messageHistory->PropertyID,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in TravelClickMessageHistoryObserver::deleted', [
                'message_id' => $messageHistory->MessageID,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle message deduplication logic
     */
    private function handleDeduplication(TravelClickMessageHistory $messageHistory): void
    {
        // Check if we've seen this exact XML before
        $cacheKey = $this->getDedupCacheKey($messageHistory->XmlHash);
        $existingMessageId = Cache::get($cacheKey);

        if ($existingMessageId && $existingMessageId !== $messageHistory->MessageID) {
            // Found a duplicate - log it and mark the message
            Log::warning('Duplicate TravelClick message detected', [
                'current_message_id' => $messageHistory->MessageID,
                'existing_message_id' => $existingMessageId,
                'xml_hash' => $messageHistory->XmlHash,
                'property_id' => $messageHistory->PropertyID,
            ]);

            // Add a note about the duplication
            $messageHistory->ProcessingNotes = (string) ($messageHistory->ProcessingNotes
                ? $messageHistory->ProcessingNotes . '; '
                : '') . "Potential duplicate of message {$existingMessageId}";
            $messageHistory->saveQuietly(); // Save without triggering observers again

            // Update sync status to indicate duplicate issue
            $this->handleDuplicateMessage($messageHistory);
        }
    }

    /**
     * Cache message for future deduplication checks
     */
    private function cacheMessageForDeduplication(TravelClickMessageHistory $messageHistory): void
    {
        $cacheKey = $this->getDedupCacheKey($messageHistory->XmlHash);
        Cache::put($cacheKey, $messageHistory->MessageID, self::DEDUP_CACHE_TTL);
    }

    /**
     * Generate cache key for deduplication
     */
    private function getDedupCacheKey(string $xmlHash): string
    {
        return self::DEDUP_CACHE_PREFIX . $xmlHash;
    }

    /**
     * Update sync status when an outbound message is created
     */
    private function updateSyncStatusOnOutbound(TravelClickMessageHistory $messageHistory): void
    {
        $syncStatus = $this->findOrCreateSyncStatus($messageHistory);

        if ($syncStatus) {
            $previousStatus = $syncStatus->Status->value;

            // Increment records total and update status to processing
            $syncStatus->increment('RecordsTotal');

            if ($syncStatus->Status === SyncStatus::IDLE) {
                $syncStatus->update([
                    'Status' => SyncStatus::PROCESSING,
                    'LastSyncAttempt' => now(),
                ]);

                // Fire event for status change
                event(new SyncStatusChanged(
                    $syncStatus,
                    $previousStatus,
                    'updated',
                    ['trigger' => 'outbound_message_created']
                ));
            }
        }
    }

    /**
     * Update sync status when processing status changes
     */
    private function updateSyncStatusOnProcessingChange(TravelClickMessageHistory $messageHistory): void
    {
        $syncStatus = $this->findOrCreateSyncStatus($messageHistory);

        if (!$syncStatus) {
            return;
        }

        $previousSyncStatus = $syncStatus->Status->value;

        switch ($messageHistory->ProcessingStatus) {
            case ProcessingStatus::PROCESSED:
                $syncStatus->increment('RecordsProcessed');
                $syncStatus->update([
                    'LastSuccessfulSync' => now(),
                    'ConsecutiveFailures' => 0,
                ]);
                break;

            case ProcessingStatus::FAILED:
                $syncStatus->increment('ConsecutiveFailures');
                $syncStatus->increment('RetryCount');

                // Check if we've exceeded max retries
                if ($syncStatus->RetryCount >= $syncStatus->MaxRetries) {
                    $syncStatus->update([
                        'Status' => SyncStatus::FAILED,
                        'ErrorMessage' => $messageHistory->ProcessingNotes,
                    ]);
                } else {
                    // Calculate next retry time with exponential backoff
                    $backoffMinutes = min(60, pow(2, $syncStatus->RetryCount - 1) * 5);
                    $syncStatus->update([
                        'Status' => SyncStatus::RETRYING,
                        'NextRetryAt' => now()->addMinutes($backoffMinutes),
                    ]);
                }
                break;
        }

        // Recalculate success rate
        $this->updateSuccessRate($syncStatus);

        // Fire event if sync status actually changed
        if ($syncStatus->Status->value !== $previousSyncStatus) {
            event(new SyncStatusChanged(
                $syncStatus,
                $previousSyncStatus,
                'updated',
                ['trigger' => 'processing_status_change']
            ));
        }
    }

    /**
     * Analyze sync health based on recent message patterns
     */
    private function analyzeSyncHealth(TravelClickMessageHistory $messageHistory): void
    {
        $syncStatus = $this->findOrCreateSyncStatus($messageHistory);

        if (!$syncStatus) {
            return;
        }

        // Get recent messages for this sync type
        $recentMessages = TravelClickMessageHistory::where('PropertyID', $messageHistory->PropertyID)
            ->where('MessageType', $messageHistory->MessageType)
            ->where('Direction', MessageDirection::OUTBOUND)
            ->latest('DateCreated')
            ->limit(self::ANALYSIS_MESSAGE_COUNT)
            ->get();

        if ($recentMessages->count() < 5) {
            // Not enough data to analyze health
            return;
        }

        // Calculate failure rate in recent messages
        $failedCount = $recentMessages->where('ProcessingStatus', ProcessingStatus::FAILED)->count();
        $failureRate = ($failedCount / $recentMessages->count()) * 100;

        $previousStatus = $syncStatus->Status->value;
        $newStatus = null;

        // Determine new status based on failure rate
        if ($failureRate >= self::FAILURE_THRESHOLD) {
            if ($syncStatus->Status !== SyncStatus::DEGRADED && $syncStatus->Status !== SyncStatus::FAILED) {
                $newStatus = SyncStatus::DEGRADED;
            }
        } elseif ($failureRate < 5 && $syncStatus->Status === SyncStatus::DEGRADED) {
            // Health improved - move back to processing or idle
            $activeMessages = $recentMessages->where('ProcessingStatus', ProcessingStatus::PROCESSING)->count();
            $newStatus = $activeMessages > 0 ? SyncStatus::PROCESSING : SyncStatus::IDLE;
        }

        // Update status if needed
        if ($newStatus && $newStatus !== $syncStatus->Status) {
            $syncStatus->update(['Status' => $newStatus]);

            event(new SyncStatusChanged(
                $syncStatus,
                $previousStatus,
                'updated',
                [
                    'trigger' => 'health_analysis',
                    'failure_rate' => $failureRate,
                    'analyzed_messages' => $recentMessages->count(),
                ]
            ));
        }
    }

    /**
     * Handle duplicate message scenario
     */
    private function handleDuplicateMessage(TravelClickMessageHistory $messageHistory): void
    {
        $syncStatus = $this->findOrCreateSyncStatus($messageHistory);

        if ($syncStatus) {
            // Increment a custom field for tracking duplicates
            // This would require adding a DuplicateCount field to the sync status table
            $syncStatus->update([
                'ErrorMessage' => 'Duplicate messages detected',
            ]);

            // Log this as a potential issue
            Log::warning('Multiple duplicate messages detected for sync', [
                'sync_status_id' => $syncStatus->SyncStatusID,
                'property_id' => $syncStatus->PropertyID,
                'message_type' => $syncStatus->MessageType->value,
            ]);
        }
    }

    /**
     * Update batch operation metrics
     */
    private function updateBatchMetrics(TravelClickMessageHistory $messageHistory): void
    {
        if (!$messageHistory->BatchID) {
            return;
        }

        // Get all messages in this batch
        $batchMessages = TravelClickMessageHistory::where('BatchID', $messageHistory->BatchID)->get();

        $totalMessages = $batchMessages->count();
        $processedMessages = $batchMessages->where('ProcessingStatus', ProcessingStatus::PROCESSED)->count();
        $failedMessages = $batchMessages->where('ProcessingStatus', ProcessingStatus::FAILED)->count();
        $processingMessages = $batchMessages->where('ProcessingStatus', ProcessingStatus::PROCESSING)->count();

        // Log batch progress
        Log::info('Batch operation progress', [
            'batch_id' => $messageHistory->BatchID,
            'total' => $totalMessages,
            'processed' => $processedMessages,
            'failed' => $failedMessages,
            'processing' => $processingMessages,
            'progress_percentage' => $totalMessages > 0 ? round(($processedMessages / $totalMessages) * 100, 2) : 0,
        ]);

        // If batch is complete (all messages either processed or failed)
        if ($processedMessages + $failedMessages === $totalMessages) {
            Log::info('Batch operation completed', [
                'batch_id' => $messageHistory->BatchID,
                'success_rate' => $totalMessages > 0 ? round(($processedMessages / $totalMessages) * 100, 2) : 0,
            ]);
        }
    }

    /**
     * Find or create sync status for the message
     */
    private function findOrCreateSyncStatus(TravelClickMessageHistory $messageHistory): ?TravelClickSyncStatus
    {
        if (!$messageHistory->MessageType) {
            return null;
        }

        return TravelClickSyncStatus::firstOrCreate(
            [
                'PropertyID' => $messageHistory->PropertyID,
                'MessageType' => $messageHistory->MessageType,
            ],
            [
                'Status' => SyncStatus::IDLE,
                'RecordsTotal' => 0,
                'RecordsProcessed' => 0,
                'SuccessRate' => 100.0,
                'ConsecutiveFailures' => 0,
                'RetryCount' => 0,
                'MaxRetries' => 3,
                'SystemUserID' => $messageHistory->SystemUserID,
            ]
        );
    }

    /**
     * Update success rate for sync status
     */
    private function updateSuccessRate(TravelClickSyncStatus $syncStatus): void
    {
        $total = $syncStatus->RecordsTotal;
        $processed = $syncStatus->RecordsProcessed;

        if ($total > 0) {
            $successRate = ($processed / $total) * 100;
            $syncStatus->update(['SuccessRate' => round($successRate, 2)]);
        }
    }
}
