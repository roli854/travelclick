<?php

namespace App\TravelClick\Jobs\InboundJobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\TravelClick\DTOs\ReservationResponseDto;
use App\TravelClick\DTOs\ReservationDataDto;
use App\TravelClick\DTOs\GuestDataDto;
use App\TravelClick\Enums\ReservationType;
use App\TravelClick\Parsers\ReservationParser;
use App\TravelClick\Services\SoapService;
use App\TravelClick\Models\TravelClickMessageHistory;
use App\TravelClick\Builders\ReservationResponseXmlBuilder;
use App\TravelClick\Enums\MessageDirection;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\ProcessingStatus;
use App\Models\Property;
use App\Models\PropertyRoomType;
use App\Models\Booking;
use App\Models\PropertyBooking;
use App\Models\PropertyRoomBooking;
use Exception;
use Carbon\Carbon;
use Throwable;

class ProcessIncomingReservationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The maximum number of attempts for this job.
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
     * The raw XML message received from TravelClick.
     *
     * @var string
     */
    protected string $rawXml;

    /**
     * The hotel code associated with the reservation.
     *
     * @var string
     */
    protected string $hotelCode;

    /**
     * The message ID associated with the incoming reservation.
     *
     * @var string
     */
    protected string $messageId;

    /**
     * Create a new job instance.
     *
     * @param string $rawXml The raw XML message received from TravelClick
     * @param string $hotelCode The hotel code associated with the reservation
     * @param string $messageId The message ID assigned to this reservation request
     * @return void
     */
    public function __construct(string $rawXml, string $hotelCode, string $messageId)
    {
        $this->rawXml = $rawXml;
        $this->hotelCode = $hotelCode;
        $this->messageId = $messageId;
        $this->queue = 'travelclick-inbound';
    }

    /**
     * Execute the job.
     *
     * @param ReservationParser $parser The parser for processing the reservation XML
     * @param SoapService $soapService The service for sending SOAP responses
     * @param ReservationResponseXmlBuilder $xmlBuilder The builder for creating response XML
     * @return void
     *
     * @throws Exception if the job fails after max attempts or unrecoverable error
     */
    public function handle(
        ReservationParser $parser,
        SoapService $soapService,
        ReservationResponseXmlBuilder $xmlBuilder
    ): void {
        Log::info('Processing incoming TravelClick reservation', [
            'message_id' => $this->messageId,
            'hotel_code' => $this->hotelCode,
        ]);

        try {
            // Step 1: Parse the incoming XML to extract reservation data
            $responseDto = $parser->parse($this->messageId, $this->rawXml);

            if (!$responseDto->isSuccess) {
                Log::error('Failed to parse incoming reservation XML', [
                    'message_id' => $this->messageId,
                    'error' => $responseDto->errorMessage,
                ]);
                $this->logFailure('PARSE_ERROR', $responseDto->errorMessage ?? 'Failed to parse reservation XML');
                $this->sendErrorResponse($soapService, $xmlBuilder, 'Unable to process reservation: Invalid format');
                return;
            }

            // Step 2: Validate the reservation data
            if (!$responseDto->hasPayload()) {
                Log::error('Reservation payload is missing from parsed response', [
                    'message_id' => $this->messageId,
                ]);
                $this->logFailure('MISSING_PAYLOAD', 'Reservation payload is missing from parsed response');
                $this->sendErrorResponse($soapService, $xmlBuilder, 'Unable to process reservation: No payload');
                return;
            }

            // Step 3: Create reservation data DTO
            $reservationData = $this->createReservationDataDto($responseDto);

            // Step 4: Process the reservation based on transaction type
            switch ($reservationData->transactionType) {
                case 'new':
                    $this->processNewReservation($reservationData, $soapService, $xmlBuilder);
                    break;
                case 'modify':
                    $this->processModifyReservation($reservationData, $soapService, $xmlBuilder);
                    break;
                case 'cancel':
                    $this->processCancelReservation($reservationData, $soapService, $xmlBuilder);
                    break;
                default:
                    Log::error('Unknown transaction type', [
                        'message_id' => $this->messageId,
                        'transaction_type' => $reservationData->transactionType,
                    ]);
                    $this->logFailure('INVALID_TRANSACTION_TYPE', 'Unknown transaction type: ' . $reservationData->transactionType);
                    $this->sendErrorResponse($soapService, $xmlBuilder, 'Unable to process reservation: Invalid transaction type');
            }
        } catch (Throwable $e) {
            Log::error('Error processing incoming reservation', [
                'message_id' => $this->messageId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->logFailure('PROCESSING_ERROR', $e->getMessage());
            $this->sendErrorResponse($soapService, $xmlBuilder, 'System error while processing reservation');

            // Re-throw if this is the last attempt
            if ($this->attempts() >= $this->tries) {
                throw $e;
            }
        }
    }

    /**
     * Create a ReservationDataDto from the parsed response.
     *
     * @param ReservationResponseDto $responseDto The parsed response DTO
     * @return ReservationDataDto The reservation data DTO
     */
    protected function createReservationDataDto(ReservationResponseDto $responseDto): ReservationDataDto
    {
        $payload = $responseDto->getPayload();

        // Get the reservation type
        $reservationType = ReservationType::from($payload['type']);

        // Create primary guest
        $guestData = new GuestDataDto([
            'title' => $payload['guest']['title'] ?? 'Mr',
            'firstName' => $payload['guest']['first_name'],
            'lastName' => $payload['guest']['last_name'],
            'middleName' => $payload['guest']['middle_name'] ?? null,
            'email' => $payload['guest']['email'] ?? null,
            'phone' => $payload['guest']['phones']['1'] ?? null,
            'phoneMobile' => $payload['guest']['phones']['3'] ?? null,
            'addressLine1' => $payload['guest']['address']['address_lines'][0] ?? null,
            'addressLine2' => $payload['guest']['address']['address_lines'][1] ?? null,
            'city' => $payload['guest']['address']['city'] ?? null,
            'state' => $payload['guest']['address']['state'] ?? null,
            'postalCode' => $payload['guest']['address']['postal_code'] ?? null,
            'countryCode' => $payload['guest']['address']['country'] ?? null,
            'isPrimaryGuest' => true,
        ]);

        // Create room stays
        $roomStays = [];
        $roomData = $payload['room'] ?? [];

        $checkInDate = Carbon::parse($payload['check_in'] ?? now());
        $checkOutDate = Carbon::parse($payload['check_out'] ?? now()->addDay());

        $roomStays[] = [
            'checkInDate' => $checkInDate,
            'checkOutDate' => $checkOutDate,
            'roomTypeCode' => $roomData['room_type_code'] ?? 'UNKNOWN',
            'ratePlanCode' => $roomData['rate_plan_code'] ?? 'UNKNOWN',
            'rateAmount' => $roomData['rates'][0]['amount_before_tax'] ?? 0.00,
            'currencyCode' => $roomData['rates'][0]['currency_code'] ?? 'USD',
            'numberOfAdults' => $roomData['guests']['adults'] ?? 1,
            'numberOfChildren' => $roomData['guests']['children'] ?? 0,
            'indexNumber' => 1,
        ];

        // Create the base reservation data array
        $reservationDataArray = [
            'reservationType' => $reservationType,
            'reservationId' => $payload['reservation_id'] ?? null,
            'confirmationNumber' => $payload['confirmation_number'] ?? null,
            'transactionType' => $this->determineTransactionType($payload),
            'hotelCode' => $this->hotelCode,
            'primaryGuest' => $guestData,
            'roomStays' => $roomStays,
            'sourceOfBusiness' => 'XML_TRVC',
        ];

        // Add payment information if available
        if (isset($payload['payment'])) {
            $payment = $payload['payment'];
            if (isset($payment['payment_card'])) {
                $reservationDataArray['paymentCardNumber'] = $payment['payment_card']['card_number'] ?? null;
                $reservationDataArray['paymentCardType'] = $payment['payment_card']['card_type'] ?? null;
                $reservationDataArray['paymentCardExpiration'] = $payment['payment_card']['expiry_date'] ?? null;
                $reservationDataArray['paymentCardHolderName'] = $payment['payment_card']['cardholder_name'] ?? null;
            }
            $reservationDataArray['guaranteeType'] = $payment['guarantee_type'] ?? null;
            $reservationDataArray['guaranteeCode'] = $payment['guarantee_code'] ?? null;

            if (isset($payment['deposit'])) {
                $reservationDataArray['depositAmount'] = $payment['deposit']['amount'] ?? null;
            }

            if (isset($payment['alternate_payment'])) {
                $reservationDataArray['alternatePaymentType'] = 'Other';
                $reservationDataArray['alternatePaymentAmount'] = $payment['alternate_payment_details'] ?? null;
            }
        }

        // Add type-specific data
        switch ($reservationType) {
            case ReservationType::TRAVEL_AGENCY:
                if (isset($payload['travel_agency'])) {
                    $reservationDataArray['profile'] = [
                        'profileType' => 'TravelAgency',
                        'name' => $payload['travel_agency']['name'] ?? null,
                        'code' => $payload['travel_agency']['iata_number'] ?? null,
                        'codeType' => $payload['travel_agency']['code_type'] ?? 'IATA',
                        'email' => $payload['travel_agency']['email'] ?? null,
                        'phone' => $payload['travel_agency']['phone'] ?? null,
                    ];
                }
                break;

            case ReservationType::CORPORATE:
                if (isset($payload['corporate'])) {
                    $reservationDataArray['profile'] = [
                        'profileType' => 'Corporate',
                        'name' => $payload['corporate']['name'] ?? null,
                        'code' => $payload['corporate']['company_code'] ?? null,
                        'shortName' => $payload['corporate']['short_name'] ?? null,
                    ];
                }
                break;

            case ReservationType::GROUP:
                if (isset($payload['group'])) {
                    $reservationDataArray['profile'] = [
                        'profileType' => 'Group',
                        'name' => $payload['group']['name'] ?? null,
                    ];
                    $reservationDataArray['invBlockCode'] = $payload['group']['block_code'] ?? null;
                }
                break;

            case ReservationType::PACKAGE:
                if (isset($payload['package'])) {
                    $reservationDataArray['packageCode'] = $payload['package']['code'] ?? null;
                    $reservationDataArray['packageName'] = $payload['package']['name'] ?? null;
                }
                break;
        }

        return new ReservationDataDto($reservationDataArray);
    }

    /**
     * Determine the transaction type from the payload.
     *
     * @param array $payload The reservation payload
     * @return string The transaction type (new, modify, or cancel)
     */
    protected function determineTransactionType(array $payload): string
    {
        // If there's a status field and it's cancelled, assume it's a cancellation
        if (isset($payload['status']) && strtolower($payload['status']) === 'cancelled') {
            return 'cancel';
        }

        // If there's a confirmation number, assume it's a modification
        if (!empty($payload['confirmation_number'])) {
            return 'modify';
        }

        // Otherwise, it's a new reservation
        return 'new';
    }

    /**
     * Process a new reservation by validating and creating it in the system.
     *
     * @param ReservationDataDto $reservationData The reservation data
     * @param SoapService $soapService The SOAP service for sending responses
     * @param ReservationResponseXmlBuilder $xmlBuilder The XML builder for responses
     * @return void
     */
    protected function processNewReservation(
        ReservationDataDto $reservationData,
        SoapService $soapService,
        ReservationResponseXmlBuilder $xmlBuilder
    ): void {
        Log::info('Processing new reservation', [
            'message_id' => $this->messageId,
            'reservation_id' => $reservationData->reservationId,
            'hotel_code' => $reservationData->hotelCode,
        ]);

        // Step 1: Get the property from the hotel code
        $property = $this->getPropertyByHotelCode($reservationData->hotelCode);
        if (!$property) {
            $this->logFailure('INVALID_HOTEL_CODE', "Property not found for hotel code: {$reservationData->hotelCode}");
            $this->sendErrorResponse($soapService, $xmlBuilder, 'Unable to process reservation: Invalid hotel code');
            return;
        }

        // Step 2: Validate room types and availability
        $invalidRoomTypes = [];
        $unavailableRooms = [];

        foreach ($reservationData->roomStays as $roomStay) {
            $roomTypeCode = $roomStay->roomTypeCode;

            // Check if room type exists
            $roomType = $property->getRoomTypeByCode($roomTypeCode);
            if (!$roomType) {
                $invalidRoomTypes[] = $roomTypeCode;
                continue;
            }

            // Check if room type is available
            if (!$roomType->isAvailable()) {
                $unavailableRooms[] = $roomTypeCode;
            }
        }

        if (!empty($invalidRoomTypes)) {
            $this->logFailure('INVALID_ROOM_TYPE', "Invalid room types: " . implode(', ', $invalidRoomTypes));
            $this->sendErrorResponse($soapService, $xmlBuilder, 'Unable to process reservation: Invalid room type(s)');
            return;
        }

        if (!empty($unavailableRooms)) {
            $this->logFailure('UNAVAILABLE_ROOM', "Unavailable room types: " . implode(', ', $unavailableRooms));
            $this->sendErrorResponse($soapService, $xmlBuilder, 'Unable to process reservation: Room type(s) not available');
            return;
        }

        try {
            // Step 3: Create the reservation in the system
            DB::beginTransaction();

            // Create booking
            $booking = $this->createCentriumBooking($reservationData, $property);

            // Generate confirmation number if not provided
            $confirmationNumber = $reservationData->confirmationNumber ?? $this->generateConfirmationNumber($booking->BookingID);
            $booking->BookingReference = $confirmationNumber;
            $booking->save();

            DB::commit();

            // Step 4: Send confirmation response
            $this->sendSuccessResponse(
                $soapService,
                $xmlBuilder,
                $reservationData->reservationId,
                $confirmationNumber
            );

            // Step 5: Log success
            $this->logSuccess($booking->BookingID, $confirmationNumber);

            Log::info('New reservation created successfully', [
                'message_id' => $this->messageId,
                'reservation_id' => $reservationData->reservationId,
                'booking_id' => $booking->BookingID,
                'confirmation_number' => $confirmationNumber,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Failed to create new reservation', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->logFailure('BOOKING_CREATION_ERROR', $e->getMessage());
            $this->sendErrorResponse($soapService, $xmlBuilder, 'System error while creating reservation');

            throw $e;
        }
    }

    /**
     * Process a reservation modification by validating and updating it in the system.
     *
     * @param ReservationDataDto $reservationData The reservation data
     * @param SoapService $soapService The SOAP service for sending responses
     * @param ReservationResponseXmlBuilder $xmlBuilder The XML builder for responses
     * @return void
     */
    protected function processModifyReservation(
        ReservationDataDto $reservationData,
        SoapService $soapService,
        ReservationResponseXmlBuilder $xmlBuilder
    ): void {
        Log::info('Processing reservation modification', [
            'message_id' => $this->messageId,
            'reservation_id' => $reservationData->reservationId,
            'confirmation_number' => $reservationData->confirmationNumber,
            'hotel_code' => $reservationData->hotelCode,
        ]);

        // Step 1: Find the existing booking
        $booking = Booking::where('BookingReference', $reservationData->confirmationNumber)
            ->first();

        if (!$booking) {
            $this->logFailure('BOOKING_NOT_FOUND', "Booking not found for confirmation number: {$reservationData->confirmationNumber}");
            $this->sendErrorResponse($soapService, $xmlBuilder, 'Unable to process modification: Reservation not found');
            return;
        }

        // Step 2: Get the property
        $property = $this->getPropertyByHotelCode($reservationData->hotelCode);
        if (!$property) {
            $this->logFailure('INVALID_HOTEL_CODE', "Property not found for hotel code: {$reservationData->hotelCode}");
            $this->sendErrorResponse($soapService, $xmlBuilder, 'Unable to process modification: Invalid hotel code');
            return;
        }

        // Step 3: Validate room types if changed
        $invalidRoomTypes = [];
        $unavailableRooms = [];

        foreach ($reservationData->roomStays as $roomStay) {
            $roomTypeCode = $roomStay->roomTypeCode;

            // Check if room type exists
            $roomType = $property->getRoomTypeByCode($roomTypeCode);
            if (!$roomType) {
                $invalidRoomTypes[] = $roomTypeCode;
                continue;
            }

            // Check availability only if dates or room type changed
            // We'd need to compare with existing booking data
            // This is a simplified check
            if (!$roomType->isAvailable()) {
                $unavailableRooms[] = $roomTypeCode;
            }
        }

        if (!empty($invalidRoomTypes)) {
            $this->logFailure('INVALID_ROOM_TYPE', "Invalid room types: " . implode(', ', $invalidRoomTypes));
            $this->sendErrorResponse($soapService, $xmlBuilder, 'Unable to process modification: Invalid room type(s)');
            return;
        }

        // We might be more lenient with unavailability for modifications
        // Especially if they're keeping the same room type

        try {
            // Step 4: Update the booking
            DB::beginTransaction();

            // Update booking details
            $this->updateCentriumBooking($booking, $reservationData, $property);

            DB::commit();

            // Step 5: Send confirmation response
            $this->sendSuccessResponse(
                $soapService,
                $xmlBuilder,
                $reservationData->reservationId,
                $booking->BookingReference
            );

            // Step 6: Log success
            $this->logSuccess($booking->BookingID, $booking->BookingReference);

            Log::info('Reservation modified successfully', [
                'message_id' => $this->messageId,
                'reservation_id' => $reservationData->reservationId,
                'booking_id' => $booking->BookingID,
                'confirmation_number' => $booking->BookingReference,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Failed to modify reservation', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->logFailure('BOOKING_MODIFICATION_ERROR', $e->getMessage());
            $this->sendErrorResponse($soapService, $xmlBuilder, 'System error while modifying reservation');

            throw $e;
        }
    }

    /**
     * Process a reservation cancellation by validating and cancelling it in the system.
     *
     * @param ReservationDataDto $reservationData The reservation data
     * @param SoapService $soapService The SOAP service for sending responses
     * @param ReservationResponseXmlBuilder $xmlBuilder The XML builder for responses
     * @return void
     */
    protected function processCancelReservation(
        ReservationDataDto $reservationData,
        SoapService $soapService,
        ReservationResponseXmlBuilder $xmlBuilder
    ): void {
        Log::info('Processing reservation cancellation', [
            'message_id' => $this->messageId,
            'reservation_id' => $reservationData->reservationId,
            'confirmation_number' => $reservationData->confirmationNumber,
            'hotel_code' => $reservationData->hotelCode,
        ]);

        // Step 1: Find the existing booking
        $booking = Booking::where('BookingReference', $reservationData->confirmationNumber)
            ->first();

        if (!$booking) {
            $this->logFailure('BOOKING_NOT_FOUND', "Booking not found for confirmation number: {$reservationData->confirmationNumber}");
            $this->sendErrorResponse($soapService, $xmlBuilder, 'Unable to process cancellation: Reservation not found');
            return;
        }

        try {
            // Step 2: Cancel the booking
            DB::beginTransaction();

            $booking->StatusID = 6; // Assuming 6 is the status ID for 'Cancelled'
            $booking->CancellationDate = Carbon::now();
            $booking->CancellationReasonID = 13; // Assuming 13 is 'Cancelled via API/Interface'
            $booking->CancellationComments = $reservationData->comments ?? 'Cancelled via TravelClick interface';
            $booking->save();

            // Update any related property bookings
            foreach ($booking->propertyBookings as $propertyBooking) {
                $propertyBooking->StatusID = 6; // Cancelled
                $propertyBooking->save();

                // Cancel room bookings
                foreach ($propertyBooking->propertyRoomBookings as $roomBooking) {
                    $roomBooking->StatusID = 6; // Cancelled
                    $roomBooking->save();
                }
            }

            DB::commit();

            // Step 3: Send confirmation response
            $this->sendSuccessResponse(
                $soapService,
                $xmlBuilder,
                $reservationData->reservationId,
                $booking->BookingReference,
                'Cancellation processed successfully'
            );

            // Step 4: Log success
            $this->logSuccess($booking->BookingID, $booking->BookingReference, 'cancel');

            Log::info('Reservation cancelled successfully', [
                'message_id' => $this->messageId,
                'reservation_id' => $reservationData->reservationId,
                'booking_id' => $booking->BookingID,
                'confirmation_number' => $booking->BookingReference,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Failed to cancel reservation', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->logFailure('BOOKING_CANCELLATION_ERROR', $e->getMessage());
            $this->sendErrorResponse($soapService, $xmlBuilder, 'System error while cancelling reservation');

            throw $e;
        }
    }

    /**
     * Create a booking in the Centrium system based on reservation data.
     *
     * @param ReservationDataDto $reservationData The reservation data DTO
     * @param Property $property The property model
     * @return Booking The created booking
     */
    protected function createCentriumBooking(ReservationDataDto $reservationData, Property $property): Booking
    {
        // Create the main booking record
        $booking = new Booking();
        $booking->StatusID = 1; // Assuming 1 is 'Confirmed'
        $booking->BookingDate = Carbon::now();
        $booking->BookingSourceID = 55; // Assuming 55 is for TravelClick
        $booking->Source = $reservationData->reservationType->getCentriumSource();
        $booking->BookingTypeID = 1; // Assuming 1 is the default
        $booking->BookingAgentID = null;
        $booking->BookingAgentCommissionTypeID = null;

        // Guest information
        $booking->LeadGuestTitle = $reservationData->primaryGuest->title ?? 'Mr';
        $booking->LeadGuestFirstName = $reservationData->primaryGuest->firstName;
        $booking->LeadGuestLastName = $reservationData->primaryGuest->lastName;
        $booking->LeadGuestEmail = $reservationData->primaryGuest->email;
        $booking->LeadGuestPhone = $reservationData->primaryGuest->phone;
        $booking->LeadGuestAddress1 = $reservationData->primaryGuest->addressLine1;
        $booking->LeadGuestAddress2 = $reservationData->primaryGuest->addressLine2;
        $booking->LeadGuestTownCity = $reservationData->primaryGuest->city;
        $booking->LeadGuestCounty = $reservationData->primaryGuest->state;
        $booking->LeadGuestPostcode = $reservationData->primaryGuest->postalCode;
        $booking->LeadGuestBookingCountryID = $this->getCountryId($reservationData->primaryGuest->countryCode);

        // Booking details
        $arrivalDate = $reservationData->getArrivalDate();
        $departureDate = $reservationData->getDepartureDate();
        $nights = $reservationData->getTotalNights();

        $booking->ArrivalDate = $arrivalDate;
        $booking->DepartureDate = $departureDate;
        $booking->NumberOfNights = $nights;

        // Save the booking to get an ID
        $booking->save();

        // Create property booking
        $propertyBooking = new PropertyBooking();
        $propertyBooking->BookingID = $booking->BookingID;
        $propertyBooking->PropertyID = $property->PropertyID;
        $propertyBooking->ArrivalDate = $arrivalDate;
        $propertyBooking->DepartureDate = $departureDate;
        $propertyBooking->StatusID = 1; // Confirmed
        $propertyBooking->save();

        // Create room bookings
        foreach ($reservationData->roomStays as $index => $roomStay) {
            $roomType = $property->getRoomTypeByCode($roomStay->roomTypeCode);

            if ($roomType) {
                $roomBooking = new PropertyRoomBooking();
                $roomBooking->PropertyBookingID = $propertyBooking->PropertyBookingID;
                $roomBooking->PropertyRoomTypeID = $roomType->PropertyRoomTypeID;
                $roomBooking->ArrivalDate = $roomStay->checkInDate;
                $roomBooking->DepartureDate = $roomStay->checkOutDate;
                $roomBooking->StatusID = 1; // Confirmed
                $roomBooking->Adults = $roomStay->numberOfAdults;
                $roomBooking->Children = $roomStay->numberOfChildren;
                $roomBooking->RoomRate = $roomStay->rateAmount;
                $roomBooking->save();
            }
        }

        // Handle special requests if any
        if ($reservationData->hasSpecialRequests()) {
            foreach ($reservationData->specialRequests as $request) {
                // Add special request comment to property booking
                $propertyBooking->propertyBookingComments()->create([
                    'Comment' => $request->description,
                    'SystemUserID' => 1, // System user
                    'DateTimeAdded' => Carbon::now(),
                ]);
            }
        }

        // Handle service requests if any (as adjustments)
        if ($reservationData->hasServiceRequests()) {
            $firstRoomBooking = $propertyBooking->propertyRoomBookings()->first();

            if ($firstRoomBooking) {
                foreach ($reservationData->serviceRequests as $service) {
                    $firstRoomBooking->propertyRoomBookingAdjusts()->create([
                        'AdjustItemID' => 1, // Default adjustment item
                        'Description' => $service->description,
                        'Amount' => $service->amount,
                        'DateApplied' => Carbon::now(),
                        'SystemUserID' => 1, // System user
                    ]);
                }
            }
        }

        // Handle profile-specific data
        if ($reservationData->hasProfile()) {
            switch ($reservationData->reservationType) {
                case ReservationType::TRAVEL_AGENCY:
                    // Set booking agency if applicable
                    if ($reservationData->profile->code) {
                        // You'd need to look up or create the agency
                        // $booking->BookingAgentID = $agentId;
                        // $booking->BookingAgentCommissionTypeID = 1;
                        // $booking->save();
                    }
                    break;

                case ReservationType::CORPORATE:
                    // Set corporate client if applicable
                    if ($reservationData->profile->code) {
                        // You'd need to look up or create the corporate client
                        // $booking->CorporateClientID = $corporateId;
                        // $booking->save();
                    }
                    break;

                case ReservationType::GROUP:
                    // Link to group booking if applicable
                    if ($reservationData->invBlockCode) {
                        // You'd need to look up the group booking
                        // $booking->BookingGroupID = $groupId;
                        // $booking->save();
                    }
                    break;
            }
        }

        return $booking;
    }

    /**
     * Update an existing Centrium booking with new reservation data.
     *
     * @param Booking $booking The booking to update
     * @param ReservationDataDto $reservationData The new reservation data
     * @param Property $property The property model
     * @return Booking The updated booking
     */
    protected function updateCentriumBooking(Booking $booking, ReservationDataDto $reservationData, Property $property): Booking
    {
        // Update guest information
        $booking->LeadGuestTitle = $reservationData->primaryGuest->title ?? $booking->LeadGuestTitle;
        $booking->LeadGuestFirstName = $reservationData->primaryGuest->firstName ?? $booking->LeadGuestFirstName;
        $booking->LeadGuestLastName = $reservationData->primaryGuest->lastName ?? $booking->LeadGuestLastName;
        $booking->LeadGuestEmail = $reservationData->primaryGuest->email ?? $booking->LeadGuestEmail;
        $booking->LeadGuestPhone = $reservationData->primaryGuest->phone ?? $booking->LeadGuestPhone;
        $booking->LeadGuestAddress1 = $reservationData->primaryGuest->addressLine1 ?? $booking->LeadGuestAddress1;
        $booking->LeadGuestAddress2 = $reservationData->primaryGuest->addressLine2 ?? $booking->LeadGuestAddress2;
        $booking->LeadGuestTownCity = $reservationData->primaryGuest->city ?? $booking->LeadGuestTownCity;
        $booking->LeadGuestCounty = $reservationData->primaryGuest->state ?? $booking->LeadGuestCounty;
        $booking->LeadGuestPostcode = $reservationData->primaryGuest->postalCode ?? $booking->LeadGuestPostcode;

        if ($reservationData->primaryGuest->countryCode) {
            $booking->LeadGuestBookingCountryID = $this->getCountryId($reservationData->primaryGuest->countryCode);
        }

        // Update booking dates if changed
        $arrivalDate = $reservationData->getArrivalDate();
        $departureDate = $reservationData->getDepartureDate();
        $nights = $reservationData->getTotalNights();

        if ($arrivalDate->ne($booking->ArrivalDate) || $departureDate->ne($booking->DepartureDate)) {
            $booking->ArrivalDate = $arrivalDate;
            $booking->DepartureDate = $departureDate;
            $booking->NumberOfNights = $nights;

            // Also update property booking dates
            $propertyBooking = $booking->propertyBookings->first();
            if ($propertyBooking) {
                $propertyBooking->ArrivalDate = $arrivalDate;
                $propertyBooking->DepartureDate = $departureDate;
                $propertyBooking->save();
            }
        }

        // Save the updated booking
        $booking->save();

        // Update room bookings if needed
        $propertyBooking = $booking->propertyBookings->first();
        if ($propertyBooking) {
            foreach ($reservationData->roomStays as $index => $roomStay) {
                $roomType = $property->getRoomTypeByCode($roomStay->roomTypeCode);

                if ($roomType) {
                    // Find existing room booking or create new one
                    $roomBooking = $propertyBooking->propertyRoomBookings->get($index);

                    if (!$roomBooking) {
                        // Create new room booking
                        $roomBooking = new PropertyRoomBooking();
                        $roomBooking->PropertyBookingID = $propertyBooking->PropertyBookingID;
                    }

                    $roomBooking->PropertyRoomTypeID = $roomType->PropertyRoomTypeID;
                    $roomBooking->ArrivalDate = $roomStay->checkInDate;
                    $roomBooking->DepartureDate = $roomStay->checkOutDate;
                    $roomBooking->Adults = $roomStay->numberOfAdults;
                    $roomBooking->Children = $roomStay->numberOfChildren;
                    $roomBooking->RoomRate = $roomStay->rateAmount;
                    $roomBooking->save();
                }
            }
        }

        return $booking;
    }

    /**
     * Get a property by its hotel code.
     *
     * @param string $hotelCode The hotel code to look up
     * @return Property|null The property if found, null otherwise
     */
    protected function getPropertyByHotelCode(string $hotelCode): ?Property
    {
        // Assuming PropertyCode is where the hotel code is stored
        return Property::where('PropertyCode', $hotelCode)
            ->where('CurrentProperty', true)
            ->first();
    }

    /**
     * Get a country ID from a country code.
     *
     * @param string $countryCode The country code
     * @return int The country ID, or default if not found
     */
    protected function getCountryId(?string $countryCode): int
    {
        if (!$countryCode) {
            return 1; // Default country ID
        }

        // This would typically be a lookup to a countries table
        // For now, return a default value
        return 1;
    }

    /**
     * Generate a confirmation number for a booking.
     *
     * @param int $bookingId The booking ID
     * @return string The generated confirmation number
     */
    protected function generateConfirmationNumber(int $bookingId): string
    {
        // Simple confirmation number generator
        // You might want to replace this with your own logic
        return 'TC' . str_pad($bookingId, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Send a success response for the reservation.
     *
     * @param SoapService $soapService The SOAP service
     * @param ReservationResponseXmlBuilder $xmlBuilder The XML builder
     * @param string $reservationId The reservation ID
     * @param string $confirmationNumber The confirmation number
     * @param string|null $message Optional success message
     * @return void
     */
    protected function sendSuccessResponse(
        SoapService $soapService,
        ReservationResponseXmlBuilder $xmlBuilder,
        string $reservationId,
        string $confirmationNumber,
        ?string $message = null
    ): void {
        // Build success response XML
        $responseXml = $xmlBuilder->buildSuccessResponse(
            $reservationId,
            $confirmationNumber,
            $this->hotelCode,
            $message
        );

        // Send response
        $soapService->sendReservation($responseXml, $this->hotelCode);

        Log::info('Sent success response for reservation', [
            'message_id' => $this->messageId,
            'reservation_id' => $reservationId,
            'confirmation_number' => $confirmationNumber,
        ]);
    }

    /**
     * Send an error response for the reservation.
     *
     * @param SoapService $soapService The SOAP service
     * @param ReservationResponseXmlBuilder $xmlBuilder The XML builder
     * @param string $errorMessage The error message
     * @return void
     */
    protected function sendErrorResponse(
        SoapService $soapService,
        ReservationResponseXmlBuilder $xmlBuilder,
        string $errorMessage
    ): void {
        // Build error response XML
        $responseXml = $xmlBuilder->buildErrorResponse(
            $this->messageId,
            $this->hotelCode,
            $errorMessage
        );

        // Send response
        $soapService->sendReservation($responseXml, $this->hotelCode);

        Log::warning('Sent error response for reservation', [
            'message_id' => $this->messageId,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Log a successful reservation processing.
     *
     * @param int $bookingId The booking ID in the system
     * @param string $confirmationNumber The confirmation number
     * @param string $action The action performed (new, modify, cancel)
     * @return void
     */
    protected function logSuccess(int $bookingId, string $confirmationNumber, string $action = 'new'): void
    {
        TravelClickMessageHistory::create([
            'MessageID' => $this->messageId,
            'MessageType' => MessageType::RESERVATION->value,
            'MessageDirection' => MessageDirection::INBOUND->value,
            'Status' => ProcessingStatus::PROCESSED->value,
            'HotelCode' => $this->hotelCode,
            'ReferenceID' => $bookingId,
            'ConfirmationNumber' => $confirmationNumber,
            'Action' => $action,
            'ProcessedAt' => Carbon::now(),
            'ErrorMessage' => null,
            'RawMessage' => substr($this->rawXml, 0, 65000), // Truncate if too long
        ]);
    }

    /**
     * Log a failed reservation processing.
     *
     * @param string $errorCode The error code
     * @param string $errorMessage The error message
     * @return void
     */
    protected function logFailure(string $errorCode, string $errorMessage): void
    {
        TravelClickMessageHistory::create([
            'MessageID' => $this->messageId,
            'MessageType' => MessageType::RESERVATION->value,
            'MessageDirection' => MessageDirection::INBOUND->value,
            'Status' => ProcessingStatus::FAILED->value,
            'HotelCode' => $this->hotelCode,
            'ErrorCode' => $errorCode,
            'ErrorMessage' => $errorMessage,
            'ProcessedAt' => Carbon::now(),
            'RawMessage' => substr($this->rawXml, 0, 65000), // Truncate if too long
        ]);
    }
}
