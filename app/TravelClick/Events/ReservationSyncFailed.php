<?php

namespace App\TravelClick\Events;

use App\TravelClick\DTOs\ReservationDataDto;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Event fired when a reservation synchronization with TravelClick fails.
 */
class ReservationSyncFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param ReservationDataDto $reservationData The reservation data that failed to synchronize
     * @param Throwable $exception The exception that caused the failure
     * @param string|null $jobId The ID of the job that processed this sync (for tracking)
     * @param int $attempts Number of attempts made before failing
     */
    public function __construct(
        public readonly ReservationDataDto $reservationData,
        public readonly Throwable $exception,
        public readonly ?string $jobId = null,
        public readonly int $attempts = 0
    ) {}

    /**
     * Get error details from the exception.
     *
     * @return array
     */
    public function getErrorDetails(): array
    {
        return [
            'message' => $this->exception->getMessage(),
            'exception_class' => get_class($this->exception),
            'code' => $this->exception->getCode(),
            'file' => $this->exception->getFile(),
            'line' => $this->exception->getLine(),
            'attempts' => $this->attempts,
            'hotel_code' => $this->reservationData->hotelCode,
            'reservation_id' => $this->reservationData->reservationId,
            'reservation_type' => $this->reservationData->reservationType->value,
        ];
    }
}
