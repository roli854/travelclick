<?php

namespace App\TravelClick\Services;

use App\Models\Property;
use App\TravelClick\Builders\ReservationXmlBuilder;
use App\TravelClick\DTOs\ReservationDataDto;
use App\TravelClick\DTOs\ReservationResponseDto;
use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Enums\MessageDirection;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\ProcessingStatus;
use App\TravelClick\Enums\ReservationType;
use App\TravelClick\Models\TravelClickMessageHistory;
use App\TravelClick\Parsers\ReservationParser;
use App\TravelClick\Services\Contracts\SoapServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Service class for handling reservation operations with TravelClick
 *
 * This service encapsulates the business logic for processing reservation
 * operations including new bookings, modifications, and cancellations.
 */
class ReservationService
{
    /**
     * @var SoapServiceInterface SOAP service for API communication
     */
    protected SoapServiceInterface $soapService;

    /**
     * @var ReservationXmlBuilder XML builder for reservation messages
     */
    protected ReservationXmlBuilder $xmlBuilder;

    /**
     * @var ReservationParser Parser for reservation responses
     */
    protected ReservationParser $parser;

    /**
     * Constructor
     *
     * @param SoapServiceInterface $soapService
     * @param ReservationXmlBuilder $xmlBuilder
     * @param ReservationParser $parser
     */
    public function __construct(
        SoapServiceInterface $soapService,
        ReservationXmlBuilder $xmlBuilder,
        ReservationParser $parser
    ) {
        $this->soapService = $soapService;
        $this->xmlBuilder = $xmlBuilder;
        $this->parser = $parser;
    }

    /**
     * Process a reservation modification
     *
     * Main entry point for handling reservation modifications
     *
     * @param ReservationDataDto $reservationData The reservation data to process
     * @param bool $validateRoomTypes Whether to validate room types (default: true)
     * @return ReservationResponseDto Response with results of the operation
     */
    public function processModification(
        ReservationDataDto $reservationData,
        bool $validateRoomTypes = true
    ): ReservationResponseDto {
        $messageId = Str::uuid()->toString();
        $historyEntry = $this->createMessageHistoryEntry($reservationData, $messageId);

        try {
            Log::info('Processing reservation modification', [
                'reservation_id' => $reservationData->reservationId,
                'confirmation_number' => $reservationData->confirmationNumber,
                'hotel_code' => $reservationData->hotelCode,
                'message_id' => $messageId
            ]);

            // Validate the modification data
            if ($validateRoomTypes) {
                $this->validateModificationData($reservationData);
            }

            // Update the Centrium booking record
            $bookingUpdated = $this->updateCentriumBooking($reservationData);

            if (!$bookingUpdated) {
                throw new \Exception("Failed to update Centrium booking record for reservation {$reservationData->confirmationNumber}");
            }

            // Process the modification
            $result = $this->sendModificationConfirmation($reservationData);

            $historyEntry->markAsProcessed('Reservation modification processed successfully');

            Log::info('Reservation modification completed', [
                'reservation_id' => $reservationData->reservationId,
                'confirmation_number' => $reservationData->confirmationNumber,
                'message_id' => $messageId,
                'success' => $result->isSuccess
            ]);

            return $result;
        } catch (Throwable $e) {
            $errorMessage = "Failed to process reservation modification: {$e->getMessage()}";
            Log::error($errorMessage, [
                'reservation_id' => $reservationData->reservationId,
                'confirmation_number' => $reservationData->confirmationNumber,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);

            $historyEntry->markAsFailed($errorMessage);

            // Create a failure response using the parent class and convert it
            $soapResponse = SoapResponseDto::failure(
                messageId: $messageId,
                rawResponse: '',
                errorMessage: $errorMessage,
                errorCode: 'MODIFICATION_FAILED'
            );

            return ReservationResponseDto::fromSoapResponse($soapResponse);
        }
    }

    /**
     * Validate reservation modification data
     *
     * Ensures that the modification contains valid data including room types, dates, etc.
     *
     * @param ReservationDataDto $reservationData
     * @throws \InvalidArgumentException If validation fails
     */
    protected function validateModificationData(ReservationDataDto $reservationData): void
    {
        if (empty($reservationData->confirmationNumber)) {
            throw new \InvalidArgumentException('Confirmation number is required for reservation modifications');
        }

        if (!$reservationData->isModification()) {
            throw new \InvalidArgumentException('Transaction type must be "modify" for reservation modifications');
        }

        // Validate property exists
        $property = Property::where('PropertyCode', $reservationData->hotelCode)->first();
        if (!$property) {
            throw new \InvalidArgumentException("Invalid hotel code: {$reservationData->hotelCode}");
        }

        // Validate room types
        foreach ($reservationData->roomStays as $roomStay) {
            $roomTypeCode = $roomStay->roomTypeCode;
            if (!$property->hasRoomType($roomTypeCode)) {
                throw new \InvalidArgumentException("Invalid room type code: {$roomTypeCode} for property {$reservationData->hotelCode}");
            }
        }

        // Validate dates
        foreach ($reservationData->roomStays as $roomStay) {
            if ($roomStay->checkInDate->isAfter($roomStay->checkOutDate)) {
                throw new \InvalidArgumentException('Check-in date cannot be after check-out date');
            }

            if ($roomStay->checkInDate->isPast()) {
                throw new \InvalidArgumentException('Cannot modify a reservation with a past check-in date');
            }
        }
    }

    /**
     * Update the Centrium booking record
     *
     * Updates the booking record in the Centrium system with the modified data
     *
     * @param ReservationDataDto $reservationData
     * @return bool True if update successful, false otherwise
     */
    protected function updateCentriumBooking(ReservationDataDto $reservationData): bool
    {
        try {
            // This would be implemented to integrate with your Centrium booking system
            // For now, we'll just log the operation and return true
            Log::info('Updating Centrium booking', [
                'reservation_id' => $reservationData->reservationId,
                'confirmation_number' => $reservationData->confirmationNumber,
                'hotel_code' => $reservationData->hotelCode,
                'arrival_date' => $reservationData->getArrivalDate()->format('Y-m-d'),
                'departure_date' => $reservationData->getDepartureDate()->format('Y-m-d')
            ]);

            // If inventory adjustments are needed, perform them here
            // This would typically happen when dates or room types change
            // $this->adjustInventory($oldReservationData, $reservationData);

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to update Centrium booking', [
                'reservation_id' => $reservationData->reservationId,
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);

            return false;
        }
    }

    /**
     * Adjust inventory based on reservation changes
     *
     * When a reservation is modified to different dates or room types,
     * inventory needs to be adjusted accordingly
     *
     * @param ReservationDataDto $oldData Original reservation data
     * @param ReservationDataDto $newData Modified reservation data
     */
    protected function adjustInventory(ReservationDataDto $oldData, ReservationDataDto $newData): void
    {
        try {
            // Implementation would interact with inventory management system
            // For each night, release inventory for old dates/rooms and allocate for new ones

            Log::info('Adjusting inventory for reservation modification', [
                'reservation_id' => $newData->reservationId,
                'confirmation_number' => $newData->confirmationNumber,
                'old_dates' => [
                    'check_in' => $oldData->getArrivalDate()->format('Y-m-d'),
                    'check_out' => $oldData->getDepartureDate()->format('Y-m-d'),
                ],
                'new_dates' => [
                    'check_in' => $newData->getArrivalDate()->format('Y-m-d'),
                    'check_out' => $newData->getDepartureDate()->format('Y-m-d'),
                ],
            ]);

            // Implementation for inventory adjustment would go here
        } catch (Throwable $e) {
            Log::error('Failed to adjust inventory for reservation modification', [
                'reservation_id' => $newData->reservationId,
                'confirmation_number' => $newData->confirmationNumber,
                'error' => $e->getMessage()
            ]);

            // We're not throwing here because inventory adjustment should not
            // fail the entire modification process, but should be handled separately
        }
    }

    /**
     * Send confirmation of reservation modification to TravelClick
     *
     * @param ReservationDataDto $reservationData
     * @return ReservationResponseDto
     */
    protected function sendModificationConfirmation(ReservationDataDto $reservationData): ReservationResponseDto
    {
        $messageId = Str::uuid()->toString();

        try {
            // Build the XML for the modification confirmation
            // We assume the standard builder method can handle different transaction types
            $xml = $this->xmlBuilder->buildReservationXml($reservationData);

            // Send the confirmation to TravelClick
            $soapResponse = $this->soapService->sendReservation($xml, $reservationData->hotelCode);

            // Parse the response
            return $this->parser->parse($messageId, $soapResponse->rawResponse, $soapResponse->durationMs);
        } catch (Throwable $e) {
            Log::error('Failed to send modification confirmation', [
                'reservation_id' => $reservationData->reservationId,
                'confirmation_number' => $reservationData->confirmationNumber,
                'error' => $e->getMessage()
            ]);

            // Create a failure response using the parent class and convert it
            $soapResponse = SoapResponseDto::failure(
                messageId: $messageId,
                rawResponse: '',
                errorMessage: "Failed to send modification confirmation: {$e->getMessage()}",
                errorCode: 'CONFIRMATION_FAILED'
            );

            return ReservationResponseDto::fromSoapResponse($soapResponse);
        }
    }

    /**
     * Create a message history entry for tracking
     *
     * @param ReservationDataDto $reservationData
     * @param string $messageId
     * @return TravelClickMessageHistory
     */
    protected function createMessageHistoryEntry(
        ReservationDataDto $reservationData,
        string $messageId
    ): TravelClickMessageHistory {
        $property = Property::where('PropertyCode', $reservationData->hotelCode)->first();
        $propertyId = $property ? $property->PropertyID : null;

        return TravelClickMessageHistory::createEntry([
            'MessageID' => $messageId,
            'MessageType' => MessageType::RESERVATION,
            'Direction' => MessageDirection::INBOUND,
            'PropertyID' => $propertyId,
            'HotelCode' => $reservationData->hotelCode,
            'ProcessingStatus' => ProcessingStatus::PENDING,
            'ExtractedData' => [
                'reservation_id' => $reservationData->reservationId,
                'confirmation_number' => $reservationData->confirmationNumber,
                'transaction_type' => 'modify',
                'check_in' => $reservationData->getArrivalDate()->format('Y-m-d'),
                'check_out' => $reservationData->getDepartureDate()->format('Y-m-d'),
                'room_type' => $reservationData->roomStays->first()->roomTypeCode,
            ],
            'SystemUserID' => auth()->id() ?? 1,
        ]);
    }

    /**
     * Find the original reservation data for comparison
     *
     * Retrieves the original reservation data before modification
     * from TravelClick or local storage
     *
     * @param string $confirmationNumber
     * @return ReservationDataDto|null
     */
    public function findOriginalReservation(string $confirmationNumber): ?ReservationDataDto
    {
        // Implementation would retrieve the original reservation data
        // This could be from a local database, or from an API call to TravelClick

        Log::info('Attempting to find original reservation', [
            'confirmation_number' => $confirmationNumber
        ]);

        // For now, returning null as this would be implemented based on your specific system
        return null;
    }

    /**
     * Process a new reservation
     *
     * Handle creating a new reservation in the system
     *
     * @param ReservationDataDto $reservationData
     * @param bool $validateRoomTypes
     * @return ReservationResponseDto
     */
    public function processNewReservation(
        ReservationDataDto $reservationData,
        bool $validateRoomTypes = true
    ): ReservationResponseDto {
        // Implementation for processing new reservations would go here
        // This is a placeholder for future implementation

        throw new \Exception('Method not implemented');
    }

    /**
     * Process a reservation cancellation
     *
     * Handle cancelling a reservation in the system
     *
     * @param ReservationDataDto $reservationData
     * @return ReservationResponseDto
     */
    public function processCancellation(
        ReservationDataDto $reservationData
    ): ReservationResponseDto {
        // Implementation for processing cancellations would go here
        // This is a placeholder for future implementation

        throw new \Exception('Method not implemented');
    }
}
