<?php

namespace App\TravelClick\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Class CircuitBreaker
 *
 * Implements the Circuit Breaker pattern to prevent operations from being executed
 * when a service is experiencing issues. This helps prevent cascading failures
 * and gives the external service time to recover.
 *
 * The circuit breaker has three states:
 * - Closed: Operations are executed normally
 * - Open: Operations are prevented from executing
 * - Half-open: A test operation is allowed to determine if the service has recovered
 */
class CircuitBreaker
{
    /**
     * @var string Unique identifier for the service/operation
     */
    private string $service;

    /**
     * @var int Number of failures before opening the circuit
     */
    private int $threshold;

    /**
     * @var int Time in seconds before attempting to reset the circuit
     */
    private int $resetTimeoutSeconds;

    /**
     * @var string Cache key for failure count
     */
    private string $failureCountKey;

    /**
     * @var string Cache key for circuit state
     */
    private string $circuitStateKey;

    /**
     * @var string Cache key for last failure time
     */
    private string $lastFailureTimeKey;

    /**
     * Constructor.
     *
     * @param string $service Unique identifier for the service/operation
     * @param int $threshold Number of failures before opening the circuit
     * @param int $resetTimeoutSeconds Time in seconds before attempting to reset the circuit
     */
    public function __construct(
        string $service,
        int $threshold = 5,
        int $resetTimeoutSeconds = 60
    ) {
        $this->service = $service;
        $this->threshold = $threshold;
        $this->resetTimeoutSeconds = $resetTimeoutSeconds;

        // Create cache keys for this specific service
        $this->failureCountKey = "circuit_breaker:{$service}:failure_count";
        $this->circuitStateKey = "circuit_breaker:{$service}:state";
        $this->lastFailureTimeKey = "circuit_breaker:{$service}:last_failure_time";
    }

    /**
     * Check if a request should be allowed based on the circuit state.
     *
     * @return bool True if the request is allowed, false otherwise
     */
    public function allowRequest(): bool
    {
        $state = Cache::get($this->circuitStateKey, 'closed');

        if ($state === 'closed') {
            // Circuit is closed, allow the request
            return true;
        }

        if ($state === 'open') {
            // Circuit is open, check if reset timeout has passed
            $lastFailureTime = Cache::get($this->lastFailureTimeKey, 0);
            $currentTime = time();

            if (($currentTime - $lastFailureTime) >= $this->resetTimeoutSeconds) {
                // Reset timeout has passed, transition to half-open
                Cache::put($this->circuitStateKey, 'half-open', now()->addMinutes(30));
                return true;
            }

            // Circuit is open and reset timeout has not passed
            return false;
        }

        // Circuit is half-open, allow a test request
        return true;
    }

    /**
     * Record a successful operation.
     *
     * @return void
     */
    public function recordSuccess(): void
    {
        $state = Cache::get($this->circuitStateKey, 'closed');

        if ($state === 'half-open') {
            // If we had a successful request in half-open state, close the circuit
            Cache::put($this->circuitStateKey, 'closed', now()->addMinutes(30));
        }

        // Reset failure count
        Cache::put($this->failureCountKey, 0, now()->addMinutes(30));
    }

    /**
     * Record a failed operation.
     *
     * @return void
     */
    public function recordFailure(): void
    {
        $state = Cache::get($this->circuitStateKey, 'closed');

        if ($state === 'half-open') {
            // If we had a failure in half-open state, reopen the circuit
            Cache::put($this->circuitStateKey, 'open', now()->addMinutes(30));
            Cache::put($this->lastFailureTimeKey, time(), now()->addMinutes(30));
            return;
        }

        // Increment failure count
        $failureCount = Cache::get($this->failureCountKey, 0) + 1;
        Cache::put($this->failureCountKey, $failureCount, now()->addMinutes(30));

        // Update last failure time
        Cache::put($this->lastFailureTimeKey, time(), now()->addMinutes(30));

        // If threshold is reached, open the circuit
        if ($failureCount >= $this->threshold) {
            Cache::put($this->circuitStateKey, 'open', now()->addMinutes(30));
        }
    }

    /**
     * Check if the circuit is currently open.
     *
     * @return bool True if the circuit is open, false otherwise
     */
    public function isOpen(): bool
    {
        return Cache::get($this->circuitStateKey, 'closed') === 'open';
    }

    /**
     * Manually reset the circuit state to closed.
     *
     * @return void
     */
    public function reset(): void
    {
        Cache::put($this->circuitStateKey, 'closed', now()->addMinutes(30));
        Cache::put($this->failureCountKey, 0, now()->addMinutes(30));
    }
}
