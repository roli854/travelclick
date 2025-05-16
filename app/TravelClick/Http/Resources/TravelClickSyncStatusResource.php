<?php

namespace App\TravelClick\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TravelClick Sync Status Resource
 *
 * Transforms TravelClickSyncStatus model into a JSON structure suitable for API responses.
 * Like a professional interpreter, this resource takes complex internal data and
 * presents it in a clean, consistent format that front-end applications love.
 */
class TravelClickSyncStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Basic identification
            'id' => $this->SyncStatusID,
            'property_id' => $this->PropertyID,
            'message_type' => [
                'value' => $this->MessageType->value,
                'label' => $this->MessageType->label(),
                'description' => $this->MessageType->description(),
            ],

            // Status with enhanced information
            'status' => [
                'value' => $this->Status->value,
                'label' => $this->Status->description(),
                'color' => $this->Status->getColor(),
                'icon' => $this->Status->getIcon(),
                'is_success' => $this->Status->isSuccess(),
                'is_failure' => $this->Status->isFailure(),
                'is_in_progress' => $this->Status->isInProgress(),
                'can_retry' => $this->Status->canRetry(),
                'requires_attention' => $this->Status->requiresAttention(),
            ],

            // Timing information
            'timing' => [
                'last_sync_attempt' => $this->formatTimestamp($this->LastSyncAttempt),
                'last_successful_sync' => $this->formatTimestamp($this->LastSuccessfulSync),
                'next_retry_at' => $this->formatTimestamp($this->NextRetryAt),
                'created_at' => $this->formatTimestamp($this->DateCreated),
                'updated_at' => $this->formatTimestamp($this->DateModified),
                'time_since_last_sync' => $this->getTimeSinceLastSync(),
                'time_since_last_success' => $this->getTimeSinceLastSuccess(),
            ],

            // Progress and metrics
            'metrics' => [
                'records_processed' => $this->RecordsProcessed,
                'records_total' => $this->RecordsTotal,
                'progress_percentage' => $this->getProgressPercentage(),
                'success_rate' => $this->formatPercentage($this->SuccessRate),
                'health_score' => $this->getSyncHealthScoreAttribute(),
            ],

            // Retry configuration and status
            'retry_config' => [
                'retry_count' => $this->RetryCount,
                'max_retries' => $this->MaxRetries,
                'auto_retry_enabled' => $this->AutoRetryEnabled,
                'can_retry' => $this->canRetry(),
                'is_overdue_for_retry' => $this->isOverdueForRetry(),
                'retry_in_progress' => $this->Status->value === 'retry_pending',
            ],

            // Error information (when applicable)
            'error' => $this->when($this->ErrorMessage, [
                'message' => $this->ErrorMessage,
                'last_error_at' => $this->LastSyncAttempt?->toISOString(),
            ]),

            // Context and metadata
            'metadata' => [
                'last_message_id' => $this->LastMessageID,
                'is_active' => $this->IsActive,
                'context' => $this->Context,
                'last_sync_by_user_id' => $this->LastSyncByUserID,
            ],

            // Related information (conditionally loaded)
            'property' => $this->whenLoaded('property', function () {
                return [
                    'id' => $this->property->PropertyID,
                    'name' => $this->property->Name,
                    'short_name' => $this->property->ShortName,
                    'reference' => $this->property->Reference,
                ];
            }),

            'last_sync_user' => $this->whenLoaded('lastSyncUser', function () {
                return [
                    'id' => $this->lastSyncUser->SystemUserID,
                    'username' => $this->lastSyncUser->UserName,
                    'email' => $this->lastSyncUser->Email,
                ];
            }),

            // Quick status assessment for dashboards
            'dashboard_summary' => [
                'status_color' => $this->getStatusColor(),
                'status_badge' => $this->getStatusBadge(),
                'priority_level' => $this->getPriorityLevel(),
                'needs_attention' => $this->needsAttention(),
                'health_indicator' => $this->getHealthIndicator(),
                'quick_actions' => $this->getQuickActions(),
            ],

            // Operational data
            'operations' => [
                'is_running' => $this->isRunning(),
                'has_failed' => $this->hasFailed(),
                'is_long_running' => $this->isLongRunning(),
                'needs_manual_intervention' => $this->needsManualIntervention(),
                'can_be_cancelled' => $this->canBeCancelled(),
            ],
        ];
    }

    /**
     * Format timestamp for consistent API output
     */
    private function formatTimestamp($timestamp): ?array
    {
        if (!$timestamp) {
            return null;
        }

        return [
            'datetime' => $timestamp->toISOString(),
            'human' => $timestamp->diffForHumans(),
            'formatted' => $timestamp->format('Y-m-d H:i:s T'),
            'unix' => $timestamp->unix(),
        ];
    }

    /**
     * Format percentage value
     */
    private function formatPercentage(?float $value): ?array
    {
        if ($value === null) {
            return null;
        }

        return [
            'value' => $value,
            'rounded' => round($value, 2),
            'display' => round($value, 2) . '%',
        ];
    }

    /**
     * Get status color with context
     */
    private function getStatusColor(): string
    {
        // Add additional logic for nuanced colors
        if ($this->Status->value === 'failed' && $this->RetryCount >= $this->MaxRetries) {
            return 'dark-red'; // Permanent failure
        }

        if ($this->Status->value === 'running' && $this->isLongRunning()) {
            return 'orange'; // Long running operation
        }

        return $this->Status->getColor();
    }

    /**
     * Get status badge style information
     */
    private function getStatusBadge(): array
    {
        return [
            'text' => $this->Status->description(),
            'color' => $this->getStatusColor(),
            'icon' => $this->Status->getIcon(),
            'pulse' => $this->Status->isInProgress(),
        ];
    }

    /**
     * Determine priority level for operations dashboard
     */
    private function getPriorityLevel(): string
    {
        $healthScore = $this->getSyncHealthScoreAttribute();

        if ($healthScore >= 90) {
            return 'low';
        } elseif ($healthScore >= 70) {
            return 'medium';
        } elseif ($healthScore >= 50) {
            return 'high';
        } else {
            return 'critical';
        }
    }

    /**
     * Check if sync needs attention
     */
    private function needsAttention(): bool
    {
        return $this->Status->requiresAttention() ||
               $this->getSyncHealthScoreAttribute() < 60 ||
               $this->isLongRunning() ||
               ($this->RetryCount > 0 && !$this->AutoRetryEnabled);
    }

    /**
     * Get health indicator for quick visual reference
     */
    private function getHealthIndicator(): array
    {
        $score = $this->getSyncHealthScoreAttribute();

        if ($score >= 90) {
            return ['status' => 'excellent', 'color' => 'green', 'icon' => 'heart'];
        } elseif ($score >= 80) {
            return ['status' => 'good', 'color' => 'blue', 'icon' => 'thumbs-up'];
        } elseif ($score >= 60) {
            return ['status' => 'fair', 'color' => 'yellow', 'icon' => 'alert-triangle'];
        } elseif ($score >= 40) {
            return ['status' => 'poor', 'color' => 'orange', 'icon' => 'alert-circle'];
        } else {
            return ['status' => 'critical', 'color' => 'red', 'icon' => 'x-circle'];
        }
    }

    /**
     * Get available quick actions for the current status
     */
    private function getQuickActions(): array
    {
        $actions = [];

        // Retry action
        if ($this->canRetry()) {
            $actions[] = [
                'action' => 'retry',
                'label' => 'Retry Sync',
                'icon' => 'refresh-cw',
                'color' => 'blue',
                'confirmation_required' => false,
            ];
        }

        // Force retry action (even if auto-retry is disabled)
        if ($this->hasFailed() && !$this->canRetry()) {
            $actions[] = [
                'action' => 'force_retry',
                'label' => 'Force Retry',
                'icon' => 'rotate-ccw',
                'color' => 'orange',
                'confirmation_required' => true,
            ];
        }

        // Cancel action for running syncs
        if ($this->isRunning()) {
            $actions[] = [
                'action' => 'cancel',
                'label' => 'Cancel Sync',
                'icon' => 'x',
                'color' => 'red',
                'confirmation_required' => true,
            ];
        }

        // Reset action for failed syncs
        if ($this->hasFailed()) {
            $actions[] = [
                'action' => 'reset',
                'label' => 'Reset Status',
                'icon' => 'refresh',
                'color' => 'purple',
                'confirmation_required' => true,
            ];
        }

        // Toggle auto-retry
        if ($this->AutoRetryEnabled) {
            $actions[] = [
                'action' => 'disable_auto_retry',
                'label' => 'Disable Auto-Retry',
                'icon' => 'pause',
                'color' => 'gray',
                'confirmation_required' => false,
            ];
        } else {
            $actions[] = [
                'action' => 'enable_auto_retry',
                'label' => 'Enable Auto-Retry',
                'icon' => 'play',
                'color' => 'green',
                'confirmation_required' => false,
            ];
        }

        // View logs action
        $actions[] = [
            'action' => 'view_logs',
            'label' => 'View Logs',
            'icon' => 'file-text',
            'color' => 'indigo',
            'confirmation_required' => false,
        ];

        return $actions;
    }

    /**
     * Check if sync is running longer than expected
     */
    private function isLongRunning(int $minutesThreshold = 30): bool
    {
        if (!$this->isRunning() || !$this->LastSyncAttempt) {
            return false;
        }

        return $this->LastSyncAttempt->diffInMinutes(now()) > $minutesThreshold;
    }

    /**
     * Check if manual intervention is needed
     */
    private function needsManualIntervention(): bool
    {
        return $this->Status->value === 'on_hold' ||
               ($this->hasFailed() && $this->RetryCount >= $this->MaxRetries) ||
               ($this->hasFailed() && !$this->AutoRetryEnabled) ||
               $this->isLongRunning(60); // Consider 1 hour+ as needing intervention
    }

    /**
     * Check if sync can be cancelled
     */
    private function canBeCancelled(): bool
    {
        return $this->Status->isInProgress() && $this->Status->value !== 'pending';
    }

    /**
     * Static method to create a collection resource with additional metadata
     */
    public static function collection($resource): TravelClickSyncStatusCollection
    {
        return new TravelClickSyncStatusCollection($resource);
    }

    /**
     * Add additional metadata when this resource is used in responses
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'generated_at' => now()->toISOString(),
                'version' => '1.0',
                'includes_property' => $this->resource->relationLoaded('property'),
                'includes_user' => $this->resource->relationLoaded('lastSyncUser'),
            ],
        ];
    }

    /**
     * Customize the HTTP response when this resource is served
     */
    public function withResponse(Request $request, $response): void
    {
        // Add custom headers for sync status
        $response->headers->set('X-Sync-Health-Score', $this->getSyncHealthScoreAttribute());
        $response->headers->set('X-Sync-Status', $this->Status->value);
        $response->headers->set('X-Needs-Attention', $this->needsAttention() ? 'true' : 'false');
    }
}