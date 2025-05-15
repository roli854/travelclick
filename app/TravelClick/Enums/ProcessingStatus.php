<?php

namespace App\TravelClick\Enums;

/**
 * ProcessingStatus Enum
 *
 * Represents the processing status of TravelClick messages.
 * It's like tracking the stages of a letter delivery system.
 */
enum ProcessingStatus: string
{
  case PENDING = 'pending';
  case PROCESSING = 'processing';
  case SENT = 'sent';
  case RECEIVED = 'received';
  case PROCESSED = 'processed';
  case FAILED = 'failed';

  /**
   * Get the display name for the UI
   */
  public function getDisplayName(): string
  {
    return match ($this) {
      self::PENDING => 'Pending',
      self::PROCESSING => 'Processing',
      self::SENT => 'Sent',
      self::RECEIVED => 'Received',
      self::PROCESSED => 'Processed',
      self::FAILED => 'Failed'
    };
  }

  /**
   * Get the color for UI representation
   */
  public function getColor(): string
  {
    return match ($this) {
      self::PENDING => '#FFA500',    // Orange
      self::PROCESSING => '#4169E1', // Royal Blue
      self::SENT => '#1E90FF',       // Dodger Blue
      self::RECEIVED => '#32CD32',   // Lime Green
      self::PROCESSED => '#00FF00',  // Green
      self::FAILED => '#FF0000'      // Red
    };
  }

  /**
   * Get the icon for UI representation
   */
  public function getIcon(): string
  {
    return match ($this) {
      self::PENDING => 'clock',
      self::PROCESSING => 'cog',
      self::SENT => 'paper-plane',
      self::RECEIVED => 'download',
      self::PROCESSED => 'check-circle',
      self::FAILED => 'exclamation-triangle'
    };
  }

  /**
   * Check if this status indicates a completed state
   */
  public function isCompleted(): bool
  {
    return in_array($this, [self::PROCESSED, self::FAILED]);
  }

  /**
   * Check if this status indicates success
   */
  public function isSuccessful(): bool
  {
    return $this === self::PROCESSED;
  }

  /**
   * Check if this status indicates failure
   */
  public function isFailed(): bool
  {
    return $this === self::FAILED;
  }

  /**
   * Check if this status indicates the message is in progress
   */
  public function isInProgress(): bool
  {
    return in_array($this, [self::PENDING, self::PROCESSING, self::SENT, self::RECEIVED]);
  }

  /**
   * Get the next logical status in the processing flow
   */
  public function getNextStatus(): ?self
  {
    return match ($this) {
      self::PENDING => self::PROCESSING,
      self::PROCESSING => self::SENT,
      self::SENT => self::RECEIVED,
      self::RECEIVED => self::PROCESSED,
      self::PROCESSED => null,
      self::FAILED => null
    };
  }

  /**
   * Get all statuses that can be filtered in queries
   */
  public static function getFilterableStatuses(): array
  {
    return [
      self::PENDING,
      self::PROCESSING,
      self::SENT,
      self::RECEIVED,
      self::PROCESSED,
      self::FAILED
    ];
  }

  /**
   * Get statuses that indicate active processing
   */
  public static function getActiveStatuses(): array
  {
    return [
      self::PENDING,
      self::PROCESSING,
      self::SENT,
      self::RECEIVED
    ];
  }

  /**
   * Get statuses that indicate completed processing
   */
  public static function getCompletedStatuses(): array
  {
    return [
      self::PROCESSED,
      self::FAILED
    ];
  }
}
