<?php

namespace App\TravelClick\Support\Contracts;

/**
 * Interface RetryStrategyInterface
 *
 * This interface defines the contract for retry strategies in the TravelClick integration.
 * Different retry strategies (exponential backoff, linear backoff, etc.) will implement this
 * interface to provide consistent retry behavior while allowing strategy-specific calculations.
 */
interface RetryStrategyInterface
{
  /**
   * Calculate delay in seconds before the next retry attempt.
   *
   * @param int $attemptNumber The current attempt number (1-based index)
   * @return int The delay in seconds before the next retry
   */
  public function calculateDelay(int $attemptNumber): int;

  /**
   * Determine if a specific exception should be retried.
   * This allows strategies to decide which exceptions are transient and worth retrying.
   *
   * @param \Throwable $exception The exception that was thrown
   * @return bool True if the operation should be retried, false otherwise
   */
  public function shouldRetry(\Throwable $exception): bool;

  /**
   * Get the maximum number of retry attempts.
   *
   * @return int The maximum number of retry attempts
   */
  public function getMaxAttempts(): int;
}
