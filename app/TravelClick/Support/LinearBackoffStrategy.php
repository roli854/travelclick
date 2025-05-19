<?php

namespace App\TravelClick\Support;

use App\TravelClick\Support\Contracts\RetryStrategyInterface;
use App\TravelClick\Exceptions\SoapException;
use App\TravelClick\Exceptions\TravelClickConnectionException;

/**
 * Class LinearBackoffStrategy
 *
 * Implements a linear backoff strategy where each retry waits a fixed increment
 * longer than the previous one, up to a maximum delay value.
 *
 * This provides a more predictable retry pattern that may be suitable for
 * some types of TravelClick operations.
 */
class LinearBackoffStrategy implements RetryStrategyInterface
{
  /**
   * @var int Maximum number of retry attempts
   */
  private int $maxAttempts;

  /**
   * @var int Initial delay in seconds
   */
  private int $initialDelay;

  /**
   * @var int Increment in seconds for each retry
   */
  private int $increment;

  /**
   * @var int Maximum delay in seconds
   */
  private int $maxDelay;

  /**
   * @var array<class-string<\Throwable>> List of exception classes that are retryable
   */
  private array $retryableExceptions;

  /**
   * Constructor.
   *
   * @param int $maxAttempts Maximum number of attempts
   * @param int $initialDelay Initial delay in seconds
   * @param int $increment Increment in seconds for each retry
   * @param int $maxDelay Maximum delay in seconds
   * @param array<class-string<\Throwable>> $retryableExceptions List of exception classes that are retryable
   */
  public function __construct(
    int $maxAttempts = 3,
    int $initialDelay = 10,
    int $increment = 20,
    int $maxDelay = 300,
    array $retryableExceptions = []
  ) {
    $this->maxAttempts = $maxAttempts;
    $this->initialDelay = $initialDelay;
    $this->increment = $increment;
    $this->maxDelay = $maxDelay;

    // If no specific exceptions were provided, use common network-related exceptions
    $this->retryableExceptions = $retryableExceptions ?: [
      SoapException::class,
      TravelClickConnectionException::class,
      \SoapFault::class,
      \GuzzleHttp\Exception\ConnectException::class,
      \GuzzleHttp\Exception\ServerException::class,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDelay(int $attemptNumber): int
  {
    if ($attemptNumber <= 0) {
      return 0;
    }

    // Calculate linear delay: initialDelay + (increment * (attemptNumber - 1))
    $delay = $this->initialDelay + ($this->increment * ($attemptNumber - 1));

    // Apply small jitter (randomness) to prevent synchronized retries
    // Add/subtract up to 10% of the increment
    $jitter = $this->increment * 0.1;
    $delay = $delay - $jitter + (mt_rand() / mt_getrandmax() * $jitter * 2);

    // Ensure we don't exceed max delay
    return (int) min($delay, $this->maxDelay);
  }

  /**
   * {@inheritdoc}
   */
  public function shouldRetry(\Throwable $exception): bool
  {
    // Check if the exception is of a retryable type
    foreach ($this->retryableExceptions as $retryableException) {
      if ($exception instanceof $retryableException) {
        return true;
      }
    }

    // Special handling for specific HTTP status codes in responses
    if (method_exists($exception, 'getResponse') && $exception->getResponse()) {
      $statusCode = $exception->getResponse()->getStatusCode();

      // 5xx server errors and specific 4xx errors are typically retryable
      return $statusCode >= 500 || in_array($statusCode, [408, 429]);
    }

    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxAttempts(): int
  {
    return $this->maxAttempts;
  }
}
