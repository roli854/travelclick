<?php

namespace App\TravelClick\Events;

use App\TravelClick\DTOs\ReservationDataDto;
use App\TravelClick\DTOs\SoapResponseDto;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a reservation is successfully synchronized with TravelClick.
 */
class ReservationSyncCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param ReservationDataDto $reservationData The reservation data that was synchronized
     * @param SoapResponseDto $response The response received from TravelClick
     * @param string|null $confirmationNumber The confirmation number from TravelClick (if available)
     * @param string|null $jobId The ID of the job that processed this sync (for tracking)
     */
    public function __construct(
        public readonly ReservationDataDto $reservationData,
        public readonly SoapResponseDto $response,
        public readonly ?string $confirmationNumber = null,
        public readonly ?string $jobId = null
    ) {}
}
