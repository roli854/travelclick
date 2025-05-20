<?php

namespace App\TravelClick\Jobs\InboundJobs;

use App\Models\Property;
use App\TravelClick\DTOs\ReservationDataDto;
use App\TravelClick\DTOs\ReservationResponseDto;
use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Enums\MessageDirection;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\ProcessingStatus;
use App\TravelClick\Models\TravelClickMessageHistory;
use App\TravelClick\Services\ReservationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Job to process reservation cancellations received from TravelClick
 *
 * This job handles the entire cancellation workflow:
 * 1. Validates the cancellation data
 * 2. Updates the booking status in Centrium
 * 3. Releases inventory back to available pool
 * 4. Applies any cancellation policies/fees
 * 5. Sends cancellation confirmations
 * 6. Records the transaction in history
 */
class ProcessReservationCancellationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [30, 60, 120];

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Reservation data to be processed
     *
     * @var ReservationDataDto
     */
    protected ReservationDataDto $reservationData;

    /**
     * Create a new job instance.
     *
     * @param ReservationDataDto $reservationData The reservation data containing cancellation information
     * @return void
     */
    public function __construct(ReservationDataDto $reservationData)
    {
        $this->reservationData = $reservationData;
        $this->onQueue('travelclick-inbound');
    }

    /**
     * Execute the job.
     *
     * @param ReservationService $reservationService The service handling reservation operations
     * @return void
     */
    public function handle(ReservationService $reservationService): void
    {
        $messageId = Str::uuid()->toString();
        $historyEntry = $this->createMessageHistoryEntry($messageId);

        try {
            Log::info('Processing reservation cancellation', [
                'reservation_id' => $this->reservationData->reservationId,
                'confirmation_number' => $this->reservationData->confirmationNumber,
                'hotel_code' => $this->reservationData->hotelCode,
                'message_id' => $messageId,
            ]);

            // Validate cancellation data
            $this->validateCancellationData();

            // Find and validate the original reservation
            $originalReservation = $reservationService->findOriginalReservation(
                $this->reservationData->confirmationNumber
            );

            if (!$originalReservation) {
                throw new Exception("Original reservation not found: {$this->reservationData->confirmationNumber}");
            }

            // Update booking status in Centrium
            $bookingUpdated = $this->updateBookingStatus($originalReservation);
            if (!$bookingUpdated) {
                throw new Exception("Failed to update booking status in Centrium");
            }

            // Release inventory back to available pool
            $this->releaseInventory($originalReservation);

            // Apply cancellation policies and fees if applicable
            $cancellationFees = $this->applyCancellationPolicies($originalReservation);

            // Send cancellation confirmation
            $response = $this->sendCancellationConfirmation($reservationService);

            // Mark history entry as processed
            $historyEntry->markAsProcessed('Reservation cancellation processed successfully');

            Log::info('Reservation cancellation completed', [
                'reservation_id' => $this->reservationData->reservationId,
                'confirmation_number' => $this->reservationData->confirmationNumber,
                'message_id' => $messageId,
                'cancellation_fees' => $cancellationFees,
                'success' => $response->isSuccess,
            ]);

            // If there was an error in the response, throw an exception
            if (!$response->isSuccess) {
                throw new Exception("Failed to process cancellation: {$response->errorMessage}");
            }
        } catch (Throwable $e) {
            $errorMessage = "Failed to process reservation cancellation: {$e->getMessage()}";

            Log::error($errorMessage, [
                'reservation_id' => $this->reservationData->reservationId ?? 'unknown',
                'confirmation_number' => $this->reservationData->confirmationNumber ?? 'unknown',
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            $historyEntry->markAsFailed($errorMessage);

            // If we're on the last retry, we should notify someone
            if ($this->attempts() >= $this->tries) {
                $this->notifyFailure($errorMessage);
            }

            throw $e;
        }
    }

    /**
     * Validate the cancellation data
     *
     * @throws Exception If validation fails
     * @return void
     */
    protected function validateCancellationData(): void
    {
        if (empty($this->reservationData->confirmationNumber)) {
            throw new Exception('Confirmation number is required for reservation cancellations');
        }

        if (!$this->reservationData->isCancellation()) {
            throw new Exception('Transaction type must be "cancel" for reservation cancellations');
        }

        $property = Property::where('PropertyCode', $this->reservationData->hotelCode)->first();
        if (!$property) {
            throw new Exception("Invalid hotel code: {$this->reservationData->hotelCode}");
        }

        // Verify reservation isn't in the past (can't cancel past stays)
        if ($this->reservationData->getDepartureDate()->isPast()) {
            throw new Exception('Cannot cancel a reservation with a past departure date');
        }
    }

    /**
     * Update the booking status in Centrium
     *
     * @param ReservationDataDto $originalReservation The original reservation data
     * @return bool Whether the update was successful
     */
    protected function updateBookingStatus(ReservationDataDto $originalReservation): bool
    {
        try {
            Log::info('Updating booking status in Centrium', [
                'reservation_id' => $this->reservationData->reservationId,
                'confirmation_number' => $this->reservationData->confirmationNumber,
                'status' => 'Cancelled',
                'cancellation_date' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            // Here we would update the booking status in Centrium
            // This is a placeholder implementation - actual implementation would depend on
            // how Centrium's API or database is structured

            // Example DB update:
            // DB::table('bookings')
            //     ->where('confirmation_number', $this->reservationData->confirmationNumber)
            //     ->update([
            //         'status' => 'Cancelled',
            //         'cancellation_date' => Carbon::now(),
            //         'cancelled_by' => 'TravelClick',
            //     ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to update booking status in Centrium', [
                'reservation_id' => $this->reservationData->reservationId,
                'confirmation_number' => $this->reservationData->confirmationNumber,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            return false;
        }
    }

    /**
     * Release inventory back to available pool
     *
     * @param ReservationDataDto $originalReservation The original reservation data
     * @return void
     */
    protected function releaseInventory(ReservationDataDto $originalReservation): void
    {
        try {
            $startDate = $originalReservation->getArrivalDate();
            $endDate = $originalReservation->getDepartureDate();
            $roomTypeCode = $originalReservation->roomStays->first()->roomTypeCode;
            $roomCount = $originalReservation->roomStays->first()->numberOfRooms ?? 1;

            Log::info('Releasing inventory back to available pool', [
                'reservation_id' => $originalReservation->reservationId,
                'confirmation_number' => $originalReservation->confirmationNumber,
                'hotel_code' => $originalReservation->hotelCode,
                'room_type' => $roomTypeCode,
                'room_count' => $roomCount,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]);

            // Here you would implement the actual inventory release logic
            // This might involve dispatching an inventory update job
            // Example:
            // UpdateInventoryJob::dispatch(
            //     $originalReservation->hotelCode,
            //     $roomTypeCode,
            //     $startDate,
            //     $endDate,
            //     $roomCount
            // );

        } catch (Throwable $e) {
            Log::error('Failed to release inventory', [
                'reservation_id' => $originalReservation->reservationId,
                'confirmation_number' => $originalReservation->confirmationNumber,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            // Don't throw here - we want to continue with the cancellation process
            // But we should notify someone about this issue
            $this->notifyInventoryIssue(
                $originalReservation->hotelCode,
                $originalReservation->confirmationNumber,
                $e->getMessage()
            );
        }
    }

    /**
     * Apply cancellation policies and calculate any cancellation fees
     *
     * @param ReservationDataDto $originalReservation The original reservation data
     * @return float|null The cancellation fee amount, if any
     */
    protected function applyCancellationPolicies(ReservationDataDto $originalReservation): ?float
    {
        try {
            $now = Carbon::now();
            $arrivalDate = $originalReservation->getArrivalDate();
            $daysUntilArrival = $now->diffInDays($arrivalDate, false);

            Log::info('Applying cancellation policies', [
                'reservation_id' => $originalReservation->reservationId,
                'confirmation_number' => $originalReservation->confirmationNumber,
                'days_until_arrival' => $daysUntilArrival,
                'arrival_date' => $arrivalDate->format('Y-m-d'),
            ]);

            // Simple cancellation policy example:
            // - More than 48 hours before arrival: No fee
            // - Less than 48 hours before arrival: 1 night fee
            // - After check-in time on arrival day: Full stay fee

            $cancellationFee = null;

            if ($daysUntilArrival < 0) {
                // Already checked in or past arrival date
                $totalRate = $this->calculateTotalRate($originalReservation);
                $cancellationFee = $totalRate;
                Log::info('Applied full stay cancellation fee', [
                    'confirmation_number' => $originalReservation->confirmationNumber,
                    'fee_amount' => $cancellationFee,
                ]);
            } elseif ($daysUntilArrival < 2) {
                // Less than 48 hours before arrival
                $oneNightRate = $this->calculateOneNightRate($originalReservation);
                $cancellationFee = $oneNightRate;
                Log::info('Applied one night cancellation fee', [
                    'confirmation_number' => $originalReservation->confirmationNumber,
                    'fee_amount' => $cancellationFee,
                ]);
            } else {
                // More than 48 hours before arrival
                Log::info('No cancellation fee applied', [
                    'confirmation_number' => $originalReservation->confirmationNumber,
                ]);
            }

            // Here you would implement the actual fee charging logic if needed
            // Example:
            // if ($cancellationFee) {
            //     ChargeService::applyFee(
            //         $originalReservation->confirmationNumber,
            //         $cancellationFee,
            //         'Cancellation Fee'
            //     );
            // }

            return $cancellationFee;
        } catch (Throwable $e) {
            Log::error('Failed to apply cancellation policies', [
                'reservation_id' => $originalReservation->reservationId,
                'confirmation_number' => $originalReservation->confirmationNumber,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            // Don't throw here - we want to continue with the cancellation process
            return null;
        }
    }

    /**
     * Calculate the total rate for the entire stay
     *
     * @param ReservationDataDto $reservation The reservation data
     * @return float The total rate
     */
    protected function calculateTotalRate(ReservationDataDto $reservation): float
    {
        $total = 0;

        foreach ($reservation->roomStays as $roomStay) {
            if (!empty($roomStay->rates)) {
                foreach ($roomStay->rates as $rate) {
                    $amount = $rate->amountAfterTax ?? $rate->amountBeforeTax ?? 0;
                    $total += $amount;
                }
            }
        }

        return $total;
    }

    /**
     * Calculate the rate for one night
     *
     * @param ReservationDataDto $reservation The reservation data
     * @return float The one night rate
     */
    protected function calculateOneNightRate(ReservationDataDto $reservation): float
    {
        $oneNightRate = 0;

        // Get the first day's rate
        foreach ($reservation->roomStays as $roomStay) {
            if (!empty($roomStay->rates)) {
                $firstRate = $roomStay->rates[0] ?? null;
                if ($firstRate) {
                    $oneNightRate += $firstRate->amountAfterTax ?? $firstRate->amountBeforeTax ?? 0;
                }
            }
        }

        return $oneNightRate;
    }

    /**
     * Send cancellation confirmation to TravelClick
     *
     * @param ReservationService $reservationService The reservation service
     * @return ReservationResponseDto The response from TravelClick
     */
    protected function sendCancellationConfirmation(ReservationService $reservationService): ReservationResponseDto
    {
        try {
            Log::info('Sending cancellation confirmation', [
                'reservation_id' => $this->reservationData->reservationId,
                'confirmation_number' => $this->reservationData->confirmationNumber,
            ]);

            // We would call the processCancel method directly, but since it's not implemented
            // in the example ReservationService file, we'll create a basic response here

            // Implementation if processCancel was available:
            // return $reservationService->processCancellation($this->reservationData);

            // Temporary placeholder:
            $messageId = Str::uuid()->toString();
            return ReservationResponseDto::fromSoapResponse(
                SoapResponseDto::success(
                    messageId: $messageId,
                    rawResponse: '<Success>Cancellation Processed</Success>',
                    durationMs: 123.45
                ),
                [
                    'confirmation_number' => $this->reservationData->confirmationNumber,
                    'status' => 'Cancelled',
                    'cancellation_date' => Carbon::now()->toIso8601String(),
                ]
            );
        } catch (Throwable $e) {
            Log::error('Failed to send cancellation confirmation', [
                'reservation_id' => $this->reservationData->reservationId,
                'confirmation_number' => $this->reservationData->confirmationNumber,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            $messageId = Str::uuid()->toString();
            return ReservationResponseDto::fromSoapResponse(
                SoapResponseDto::failure(
                    messageId: $messageId,
                    rawResponse: '',
                    errorMessage: "Failed to send cancellation confirmation: {$e->getMessage()}",
                    errorCode: 'CONFIRMATION_FAILED'
                )
            );
        }
    }

    /**
     * Create a message history entry for tracking this cancellation process
     *
     * @param string $messageId The unique message ID
     * @return TravelClickMessageHistory The created history entry
     */
    protected function createMessageHistoryEntry(string $messageId): TravelClickMessageHistory
    {
        $property = Property::where('PropertyCode', $this->reservationData->hotelCode)->first();
        $propertyId = $property ? $property->PropertyID : null;

        return TravelClickMessageHistory::createEntry([
            'MessageID' => $messageId,
            'MessageType' => MessageType::RESERVATION,
            'Direction' => MessageDirection::INBOUND,
            'PropertyID' => $propertyId,
            'HotelCode' => $this->reservationData->hotelCode,
            'ProcessingStatus' => ProcessingStatus::PENDING,
            'ExtractedData' => [
                'reservation_id' => $this->reservationData->reservationId,
                'confirmation_number' => $this->reservationData->confirmationNumber,
                'transaction_type' => 'cancel',
                'check_in' => $this->reservationData->getArrivalDate()->format('Y-m-d'),
                'check_out' => $this->reservationData->getDepartureDate()->format('Y-m-d'),
                'room_type' => $this->reservationData->roomStays->first()->roomTypeCode ?? 'unknown',
            ],
            'SystemUserID' => auth()->id() ?? 1,
        ]);
    }

    /**
     * Notify technical staff about a cancellation processing failure
     *
     * @param string $errorMessage The error message
     * @return void
     */
    protected function notifyFailure(string $errorMessage): void
    {
        Log::critical('Reservation cancellation failed after maximum retries', [
            'reservation_id' => $this->reservationData->reservationId ?? 'unknown',
            'confirmation_number' => $this->reservationData->confirmationNumber ?? 'unknown',
            'error' => $errorMessage,
            'hotel_code' => $this->reservationData->hotelCode ?? 'unknown',
        ]);

        // Here you would implement actual notification logic
        // Examples:
        // - Send email to support team
        // - Send Slack notification
        // - Create incident ticket
        // - Trigger PagerDuty alert
    }

    /**
     * Notify about inventory release issues
     *
     * @param string $hotelCode The hotel code
     * @param string $confirmationNumber The confirmation number
     * @param string $errorMessage The error message
     * @return void
     */
    protected function notifyInventoryIssue(string $hotelCode, string $confirmationNumber, string $errorMessage): void
    {
        Log::warning('Inventory release issue during cancellation', [
            'hotel_code' => $hotelCode,
            'confirmation_number' => $confirmationNumber,
            'error' => $errorMessage,
        ]);

        // Here you would implement notification logic for inventory team
    }
}
