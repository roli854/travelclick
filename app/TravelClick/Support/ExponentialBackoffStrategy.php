<?php

namespace App\TravelClick\Support;

use App\TravelClick\Support\Contracts\RetryStrategyInterface;
use App\TravelClick\Exceptions\SoapException;
use App\TravelClick\Exceptions\TravelClickConnectionException;

/**
 * Class ExponentialBackoffStrategy
 *
 * Implements an exponential backoff strategy where each retry waits exponentially
 * longer than the previous one, up to a maximum delay value.
 *
 * This helps prevent overwhelming the TravelClick services during temporary issues
 * while still retrying operations in a controlled manner.
 */
class ExponentialBackoffStrategy implements RetryStrategyInterface
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
   * @var int Maximum delay in seconds
   */
  private int $maxDelay;

  /**
   * @var float Multiplier for exponential backoff calculation
   */
  private float $multiplier;

  /**
   * @var array<class-string<\Throwable>> List of exception classes that are retryable
   */
  private array $retryableExceptions;

  /**
   * Constructor.
   *
   * @param int $maxAttempts Maximum number of attempts
   * @param int $initialDelay Initial delay in seconds
   * @param int $maxDelay Maximum delay in seconds
   * @param float $multiplier Multiplier for each subsequent delay calculation
   * @param array<class-string<\Throwable>> $retryableExceptions List of exception classes that are retryable
   */
  public function __construct(
    int $maxAttempts = 3,
    int $initialDelay = 10,
    int $maxDelay = 300,
    float $multiplier = 2.0,
    array $retryableExceptions = []
  ) {
    $this->maxAttempts = $maxAttempts;
    $this->initialDelay = $initialDelay;
    $this->maxDelay = $maxDelay;
    $this->multiplier = $multiplier;

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

    // Calculate exponential delay: initialDelay * (multiplier ^ (attemptNumber - 1))
    $delay = $this->initialDelay * pow($this->multiplier, $attemptNumber - 1);

    // Apply jitter (randomness) to prevent thundering herd problem
    // Add/subtract up to 20% of the calculated delay
    $jitter = $delay * 0.2;
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
