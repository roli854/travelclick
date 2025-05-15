<?php

namespace App\TravelClick\Enums;

/**
 * SyncStatus Enum for TravelClick Integration
 *
 * Tracks the status of synchronization operations between Centrium and TravelClick.
 * This helps us know exactly where each piece of data stands in the sync process.
 *
 * Think of this like tracking a package shipment:
 * - Pending: Ready to ship
 * - Processing: In transit
 * - Completed: Delivered successfully
 * - Failed: Delivery failed
 * - etc.
 */
enum SyncStatus: string
{
/**
     * Data is ready to be synchronized but hasn't started yet
     */
    case PENDING = 'pending';

/**
     * Synchronization is currently in progress
     */
    case PROCESSING = 'processing';

/**
     * Synchronization completed successfully
     */
    case COMPLETED = 'completed';

/**
     * Synchronization failed but can be retried
     */
    case FAILED = 'failed';

/**
     * Synchronization failed and will not be retried
     */
    case FAILED_PERMANENT = 'failed_permanent';

/**
     * Synchronization was cancelled by user or system
     */
    case CANCELLED = 'cancelled';

/**
     * Waiting for retry after a failed attempt
     */
    case RETRY_PENDING = 'retry_pending';

/**
     * Partial success - some items succeeded, some failed
     */
    case PARTIAL = 'partial';

/**
     * Synchronization is on hold (manual intervention needed)
     */
    case ON_HOLD = 'on_hold';

/**
     * Data is marked for deletion/cleanup
     */
    case MARKED_FOR_DELETION = 'marked_for_deletion';

    case SUCCESS = 'success';

    case ERROR = 'error';

    case INACTIVE = 'inactive';

    case RUNNING = 'running';
    /**
     * Get human-readable description
     */
    public function description(): string
    {
        return match ($this) {
            self::PENDING => 'Ready to synchronize',
            self::PROCESSING => 'Synchronization in progress',
            self::COMPLETED => 'Successfully synchronized',
            self::FAILED => 'Failed - can be retried',
            self::FAILED_PERMANENT => 'Failed permanently - no more retries',
            self::CANCELLED => 'Synchronization cancelled',
            self::RETRY_PENDING => 'Waiting for retry',
            self::PARTIAL => 'Partially synchronized',
            self::ON_HOLD => 'On hold - needs attention',
            self::MARKED_FOR_DELETION => 'Marked for deletion',
            self::SUCCESS => 'Successfully synchronized',
            self::ERROR => 'Error occurred during synchronization',
            self::INACTIVE => 'Inactive - not in use',
            self::RUNNING => 'Currently running',
        };
    }

    /**
     * Check if this status indicates success
     */
    public function isSuccess(): bool
    {
        return $this === self::COMPLETED || $this === self::SUCCESS;
    }

    /**
     * Check if this status indicates failure
     */
    public function isFailure(): bool
    {
        return in_array($this, [
            self::FAILED,
            self::FAILED_PERMANENT,
            self::CANCELLED,
            self::ERROR
        ]);
    }

    /**
     * Check if this status indicates the sync is in progress
     */
    public function isInProgress(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::PROCESSING,
            self::RETRY_PENDING,
            self::RUNNING
        ]);
    }

    /**
     * Check if this status can be retried
     */
    public function canRetry(): bool
    {
        return in_array($this, [
            self::FAILED,
            self::PARTIAL,
            self::ERROR
        ]);
    }

    /**
     * Check if this status requires attention
     */
    public function requiresAttention(): bool
    {
        return in_array($this, [
            self::FAILED_PERMANENT,
            self::PARTIAL,
            self::ON_HOLD,
            self::ERROR
        ]);
    }

    /**
     * Get the next logical status after a failed attempt
     */
    public function getNextRetryStatus(int $attemptCount, int $maxAttempts): self
    {
        if ($attemptCount >= $maxAttempts) {
            return self::FAILED_PERMANENT;
        }

        return self::RETRY_PENDING;
    }

    /**
     * Get color for UI display (useful for dashboards)
     */
    public function getColor(): string
    {
        return match ($this) {
            self::COMPLETED, self::SUCCESS => 'green',
            self::PROCESSING, self::PENDING, self::RETRY_PENDING, self::RUNNING => 'blue',
            self::FAILED, self::FAILED_PERMANENT, self::ERROR => 'red',
            self::CANCELLED => 'gray',
            self::PARTIAL => 'orange',
            self::ON_HOLD => 'yellow',
            self::MARKED_FOR_DELETION => 'purple',
        };
    }

    /**
     * Get icon for UI display
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::COMPLETED, self::SUCCESS => 'check-circle',
            self::PROCESSING, self::RUNNING => 'clock',
            self::PENDING => 'time',
            self::FAILED, self::ERROR => 'x-circle',
            self::FAILED_PERMANENT => 'alert-circle',
            self::CANCELLED => 'x',
            self::RETRY_PENDING => 'refresh-cw',
            self::PARTIAL => 'alert-triangle',
            self::ON_HOLD => 'pause-circle',
            self::MARKED_FOR_DELETION => 'trash-2',
        };
    }

    /**
     * Get all statuses that indicate completion (success or permanent failure)
     */
    public static function finalStatuses(): array
    {
        return [
            self::COMPLETED,
            self::FAILED_PERMANENT,
            self::CANCELLED,
            self::ERROR,
            self::SUCCESS,
        ];
    }

    /**
     * Get all statuses that indicate active processing
     */
    public static function activeStatuses(): array
    {
        return [
            self::PENDING,
            self::PROCESSING,
            self::RETRY_PENDING,
            self::RUNNING,
        ];
    }
}
