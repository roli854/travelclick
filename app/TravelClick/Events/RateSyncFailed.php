<?php

namespace App\TravelClick\Events;

use App\TravelClick\Enums\RateOperationType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RateSyncFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $hotelCode
     * @param  RateOperationType  $operationType
     * @param  string  $errorMessage
     * @param  string  $exceptionClass
     * @param  string|null  $trackingId
     * @return void
     */
    public function __construct(
        public readonly string $hotelCode,
        public readonly RateOperationType $operationType,
        public readonly string $errorMessage,
        public readonly string $exceptionClass,
        public readonly ?string $trackingId = null
    ) {}

    /**
     * Get the event name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'travelclick.rate.sync.failed';
    }
}
