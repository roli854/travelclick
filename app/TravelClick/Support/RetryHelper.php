<?php

namespace App\TravelClick\Support;

use App\TravelClick\Support\Contracts\RetryStrategyInterface;
use App\TravelClick\Support\ExponentialBackoffStrategy;
use App\TravelClick\Support\LinearBackoffStrategy;
use App\TravelClick\Support\CircuitBreaker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

/**
 * Class RetryHelper
 *
 * A helper class that manages retrying operations with configurable retry strategies
 * and circuit breaker pattern implementation.
 *
 * This class orchestrates the retry process, using strategies to determine backoff
 * times and circuit breakers to prevent overwhelming failing services.
 */
class RetryHelper
{
  /**
   * @var array<string, RetryStrategyInterface> Registered strategies by operation type
   */
  private array $strategies = [];

  /**
   * @var array<string, CircuitBreaker> Circuit breakers by service identifier
   */
  private array $circuitBreakers = [];

  /**
   * @var array Default configuration from travelclick.php
   */
  private array $defaultConfiguration;

  /**
   * Constructor.
   *
   * @param array $config Optional configuration override (for testing)
   */
  public function __construct(array $config = [])
  {
    $this->defaultConfiguration = $config ?: Config::get('travelclick.retry_policy', [
      'max_attempts' => 3,
      'backoff_strategy' => 'exponential',
      'initial_delay_seconds' => 10,
      'max_delay_seconds' => 300,
      'multiplier' => 2,
    ]);
  }

  /**
   * Register a retry strategy for a specific operation type.
   *
   * @param string $operationType The operation type (e.g., 'inventory', 'rates', 'reservations')
   * @param RetryStrategyInterface $strategy The retry strategy to use
   * @return self
   */
  public function registerStrategy(string $operationType, RetryStrategyInterface $strategy): self
  {
    $this->strategies[$operationType] = $strategy;
    return $this;
  }

  /**
   * Execute an operation with retry logic.
   *
   * @param callable $operation The operation to execute
   * @param string $operationType The type of operation (for strategy selection)
   * @param string|null $serviceIdentifier Optional service identifier (for circuit breaker)
   * @return mixed The result of the operation
   * @throws \Exception When all retry attempts fail or circuit is open
   */
  public function executeWithRetry(callable $operation, string $operationType, string $serviceIdentifier = null): mixed
  {
    $strategy = $this->getStrategyForOperationType($operationType);
    $circuitBreaker = $serviceIdentifier ? $this->getCircuitBreaker($serviceIdentifier) : null;

    // Check if circuit breaker is open
    if ($circuitBreaker && !$circuitBreaker->allowRequest()) {
      $errorMessage = "Circuit is open for service '{$serviceIdentifier}'. Preventing operation.";
      Log::warning($errorMessage);
      throw new \RuntimeException($errorMessage);
    }

    $attempt = 0;
    $lastException = null;

    do {
      $attempt++;

      try {
        $result = $operation();

        // Record success in circuit breaker
        if ($circuitBreaker) {
          $circuitBreaker->recordSuccess();
        }

        $this->handleSuccess($serviceIdentifier);

        return $result;
      } catch (\Throwable $exception) {
        $lastException = $exception;

        // Record failure in circuit breaker
        if ($circuitBreaker) {
          $circuitBreaker->recordFailure();
        }

        $this->handleFailure($exception, $serviceIdentifier);

        // If the exception is not retryable, rethrow immediately
        if (!$strategy->shouldRetry($exception)) {
          throw $exception;
        }

        // If we've reached the max attempts, rethrow the last exception
        if ($attempt >= $strategy->getMaxAttempts()) {
          $errorMessage = "Failed after {$attempt} attempts. Last error: " . $exception->getMessage();
          Log::error($errorMessage, ['exception' => $exception]);
          throw $exception;
        }

        // Calculate delay for next attempt
        $delay = $strategy->calculateDelay($attempt);

        Log::info("Retrying operation (attempt {$attempt} of {$strategy->getMaxAttempts()}) after {$delay}s delay.", [
          'operation_type' => $operationType,
          'service' => $serviceIdentifier,
          'exception' => $exception->getMessage(),
        ]);

        // Wait before retrying
        $this->sleep($delay);
      }
    } while ($attempt < $strategy->getMaxAttempts());

    // We should never reach here due to the exception in the loop above,
    // but just in case...
    throw $lastException ?: new \RuntimeException('All retry attempts failed');
  }

  /**
   * Get the retry strategy for a specific operation type.
   *
   * @param string $operationType The operation type
   * @return RetryStrategyInterface The retry strategy
   */
  public function getStrategyForOperationType(string $operationType): RetryStrategyInterface
  {
    // Return registered strategy if exists
    if (isset($this->strategies[$operationType])) {
      return $this->strategies[$operationType];
    }

    // Create and register a new strategy based on configuration
    $strategy = $this->createDefaultStrategy($operationType);
    $this->registerStrategy($operationType, $strategy);

    return $strategy;
  }

  /**
   * Create a default strategy based on configuration.
   *
   * @param string $operationType The operation type
   * @return RetryStrategyInterface The created strategy
   */
  protected function createDefaultStrategy(string $operationType): RetryStrategyInterface
  {
    // Get operation specific configuration if available
    $operationConfig = Config::get("travelclick.message_types.{$operationType}", []);

    // Merge with default configuration
    $config = array_merge($this->defaultConfiguration, $operationConfig);

    // Determine which strategy class to use
    $strategyType = $config['backoff_strategy'] ?? 'exponential';

    if ($strategyType === 'linear') {
      return new LinearBackoffStrategy(
        $config['max_attempts'] ?? 3,
        $config['initial_delay_seconds'] ?? 10,
        $config['increment_seconds'] ?? 20,
        $config['max_delay_seconds'] ?? 300
      );
    }

    // Default to exponential
    return new ExponentialBackoffStrategy(
      $config['max_attempts'] ?? 3,
      $config['initial_delay_seconds'] ?? 10,
      $config['max_delay_seconds'] ?? 300,
      $config['multiplier'] ?? 2.0
    );
  }

  /**
   * Get or create a circuit breaker for a service.
   *
   * @param string $serviceIdentifier The service identifier
   * @return CircuitBreaker The circuit breaker
   */
  protected function getCircuitBreaker(string $serviceIdentifier): CircuitBreaker
  {
    if (!isset($this->circuitBreakers[$serviceIdentifier])) {
      $threshold = Config::get('travelclick.circuit_breaker.threshold', 5);
      $resetTimeout = Config::get('travelclick.circuit_breaker.reset_timeout', 60);

      $this->circuitBreakers[$serviceIdentifier] = new CircuitBreaker(
        $serviceIdentifier,
        $threshold,
        $resetTimeout
      );
    }

    return $this->circuitBreakers[$serviceIdentifier];
  }

  /**
   * Sleep for a specified number of seconds.
   * Extracted to a separate method to allow easier testing.
   *
   * @param int $seconds Number of seconds to sleep
   * @return void
   */
  protected function sleep(int $seconds): void
  {
    if ($seconds > 0) {
      sleep($seconds);
    }
  }

  /**
   * Handle successful operation.
   *
   * @param string|null $serviceIdentifier Optional service identifier
   * @return void
   */
  protected function handleSuccess(string $serviceIdentifier = null): void
  {
    // Add any success handling logic here (metrics, logging, etc.)
    Log::debug("Operation successful", [
      'service' => $serviceIdentifier,
    ]);
  }

  /**
   * Handle failed operation.
   *
   * @param \Throwable $exception The exception that was thrown
   * @param string|null $serviceIdentifier Optional service identifier
   * @return void
   */
  protected function handleFailure(\Throwable $exception, string $serviceIdentifier = null): void
  {
    // Add any failure handling logic here (metrics, logging, etc.)
    Log::warning("Operation failed: " . $exception->getMessage(), [
      'service' => $serviceIdentifier,
      'exception' => get_class($exception),
    ]);
  }
}
