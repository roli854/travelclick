<?php

namespace App\TravelClick\Events;

use App\TravelClick\Models\TravelClickSyncStatus;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SyncStatusChanged Event
 *
 * This event is fired whenever a TravelClickSyncStatus changes state.
 * It's like sending a notification throughout the system that something
 * important happened with a sync operation.
 *
 * This event can be listened to by multiple parts of the system:
 * - Logging systems to record the change
 * - Monitoring systems to update dashboards
 * - Notification systems to alert administrators
 * - Analytics systems to track patterns
 */
class SyncStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The sync status that changed
     */
    public TravelClickSyncStatus $syncStatus;

    /**
     * The previous status (null if created)
     */
    public ?string $previousStatus;

    /**
     * The type of change (created, updated, deleted)
     */
    public string $changeType;

    /**
     * Additional context about the change
     */
    public array $context;

    /**
     * Create a new event instance.
     *
     * @param TravelClickSyncStatus $syncStatus The sync status that changed
     * @param string|null $previousStatus The previous status value
     * @param string $changeType Type of change (created, updated, deleted)
     * @param array $context Additional context about the change
     */
    public function __construct(
        TravelClickSyncStatus $syncStatus,
        ?string $previousStatus = null,
        string $changeType = 'updated',
        array $context = []
    ) {
        $this->syncStatus = $syncStatus;
        $this->previousStatus = $previousStatus;
        $this->changeType = $changeType;
        $this->context = array_merge([
            'timestamp' => now()->toISOString(),
            'sync_status_id' => $syncStatus->SyncStatusID,
            'property_id' => $syncStatus->PropertyID,
            'message_type' => $syncStatus->MessageType->value,
            'current_status' => $syncStatus->Status->value,
        ], $context);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * This allows real-time updates in dashboards and monitoring systems.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Broadcast to all users monitoring TravelClick system
            new Channel('travelclick-sync-status'),

            // Broadcast to users monitoring specific property
            new PrivateChannel('travelclick-property-' . $this->syncStatus->PropertyID),

            // Broadcast to admin users for system-wide monitoring
            new PresenceChannel('travelclick-admin'),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * This will be the event name that frontend JavaScript listens for.
     */
    public function broadcastAs(): string
    {
        return 'sync.status.changed';
    }

    /**
     * Get the data to broadcast.
     *
     * This data will be sent to frontend clients via WebSocket.
     */
    public function broadcastWith(): array
    {
        return [
            'sync_status_id' => $this->syncStatus->SyncStatusID,
            'property_id' => $this->syncStatus->PropertyID,
            'message_type' => $this->syncStatus->MessageType->label(),
            'status' => [
                'current' => $this->syncStatus->Status->value,
                'previous' => $this->previousStatus,
                'label' => $this->syncStatus->Status->label(),
                'color' => $this->syncStatus->Status->color(),
                'icon' => $this->syncStatus->Status->icon(),
            ],
            'change_type' => $this->changeType,
            'metrics' => [
                'success_rate' => $this->syncStatus->SuccessRate,
                'health_score' => $this->syncStatus->getSyncHealthScoreAttribute(),
                'retry_count' => $this->syncStatus->RetryCount,
                'max_retries' => $this->syncStatus->MaxRetries,
                'records_processed' => $this->syncStatus->RecordsProcessed,
                'records_total' => $this->syncStatus->RecordsTotal,
                'progress_percentage' => $this->syncStatus->getProgressPercentage(),
            ],
            'timestamps' => [
                'last_sync_attempt' => $this->syncStatus->LastSyncAttempt?->toISOString(),
                'last_successful_sync' => $this->syncStatus->LastSuccessfulSync?->toISOString(),
                'next_retry_at' => $this->syncStatus->NextRetryAt?->toISOString(),
            ],
            'context' => $this->context,
        ];
    }

    /**
     * Determine if this event should be broadcast.
     *
     * Only broadcast certain types of changes to avoid spam.
     */
    public function broadcastWhen(): bool
    {
        // Always broadcast status changes and creation
        if (in_array($this->changeType, ['created', 'deleted'])) {
            return true;
        }

        // For updates, only broadcast if status actually changed
        if ($this->changeType === 'updated') {
            return $this->previousStatus !== $this->syncStatus->Status->value;
        }

        return false;
    }

    /**
     * Check if this is a status transition
     */
    public function isStatusTransition(): bool
    {
        return $this->previousStatus !== null &&
            $this->previousStatus !== $this->syncStatus->Status->value;
    }

    /**
     * Check if this is a failure event
     */
    public function isFailure(): bool
    {
        return $this->syncStatus->Status->value === 'failed';
    }

    /**
     * Check if this is a completion event
     */
    public function isCompletion(): bool
    {
        return $this->syncStatus->Status->value === 'completed';
    }

    /**
     * Check if this is a critical event (failure with max retries)
     */
    public function isCritical(): bool
    {
        return $this->isFailure() &&
            $this->syncStatus->RetryCount >= $this->syncStatus->MaxRetries;
    }

    /**
     * Get a human-readable description of the change
     */
    public function getDescription(): string
    {
        $propertyId = $this->syncStatus->PropertyID;
        $messageType = $this->syncStatus->MessageType->label();
        $currentStatus = $this->syncStatus->Status->label();

        switch ($this->changeType) {
            case 'created':
                return "New {$messageType} sync created for Property {$propertyId}";

            case 'updated':
                if ($this->isStatusTransition()) {
                    $previousLabel = $this->previousStatus ?
                        ucfirst(str_replace('_', ' ', $this->previousStatus)) :
                        'Unknown';
                    return "{$messageType} sync for Property {$propertyId} changed from {$previousLabel} to {$currentStatus}";
                }
                return "{$messageType} sync for Property {$propertyId} updated";

            case 'deleted':
                return "{$messageType} sync for Property {$propertyId} deleted";

            default:
                return "{$messageType} sync for Property {$propertyId} changed";
        }
    }

    /**
     * Get severity level for this event
     */
    public function getSeverity(): string
    {
        if ($this->isCritical()) {
            return 'critical';
        }

        if ($this->isFailure()) {
            return 'error';
        }

        if ($this->syncStatus->getSyncHealthScoreAttribute() < 70) {
            return 'warning';
        }

        return 'info';
    }

    /**
     * Get tags for filtering and organization
     */
    public function getTags(): array
    {
        $tags = [
            'sync',
            'property:' . $this->syncStatus->PropertyID,
            'message_type:' . $this->syncStatus->MessageType->value,
            'status:' . $this->syncStatus->Status->value,
            'severity:' . $this->getSeverity(),
        ];

        if ($this->isStatusTransition()) {
            $tags[] = 'transition';
            $tags[] = 'from:' . $this->previousStatus;
        }

        if ($this->syncStatus->RetryCount > 0) {
            $tags[] = 'retry:' . $this->syncStatus->RetryCount;
        }

        return $tags;
    }

    /**
     * Convert event to array for logging or storage
     */
    public function toArray(): array
    {
        return [
            'event_type' => 'sync_status_changed',
            'sync_status_id' => $this->syncStatus->SyncStatusID,
            'property_id' => $this->syncStatus->PropertyID,
            'message_type' => $this->syncStatus->MessageType->value,
            'previous_status' => $this->previousStatus,
            'current_status' => $this->syncStatus->Status->value,
            'change_type' => $this->changeType,
            'description' => $this->getDescription(),
            'severity' => $this->getSeverity(),
            'tags' => $this->getTags(),
            'metrics' => [
                'success_rate' => $this->syncStatus->SuccessRate,
                'health_score' => $this->syncStatus->getSyncHealthScoreAttribute(),
                'retry_count' => $this->syncStatus->RetryCount,
                'records_processed' => $this->syncStatus->RecordsProcessed,
                'records_total' => $this->syncStatus->RecordsTotal,
            ],
            'context' => $this->context,
            'timestamp' => $this->context['timestamp'],
        ];
    }

    /**
     * Get data formatted for webhook delivery
     */
    public function toWebhook(): array
    {
        return array_merge($this->toArray(), [
            'webhook_id' => uniqid('whk_', true),
            'webhook_timestamp' => now()->timestamp,
            'webhook_signature' => hash_hmac('sha256', json_encode($this->toArray()), config('app.key')),
        ]);
    }

    /**
     * Get notification data for various channels
     */
    public function toNotification(): array
    {
        $baseInfo = [
            'title' => $this->getNotificationTitle(),
            'body' => $this->getDescription(),
            'severity' => $this->getSeverity(),
            'property_id' => $this->syncStatus->PropertyID,
            'sync_status_id' => $this->syncStatus->SyncStatusID,
        ];

        return [
            'database' => $baseInfo,
            'mail' => array_merge($baseInfo, [
                'details_url' => route('travelclick.sync.show', $this->syncStatus->SyncStatusID),
            ]),
            'slack' => array_merge($baseInfo, [
                'color' => $this->syncStatus->Status->getColor(),
                'icon' => $this->syncStatus->Status->getIcon(),
            ]),
        ];
    }

    /**
     * Get notification title based on event type
     */
    private function getNotificationTitle(): string
    {
        if ($this->isCritical()) {
            return '🚨 Critical Sync Failure';
        }

        if ($this->isFailure()) {
            return '⚠️ Sync Failed';
        }

        if ($this->isCompletion()) {
            return '✅ Sync Completed';
        }

        return 'Sync Status Updated';
    }
}
