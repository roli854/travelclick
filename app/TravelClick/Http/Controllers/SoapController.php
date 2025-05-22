<?php

namespace App\TravelClick\Http\Controllers;

use App\TravelClick\Enums\MessageDirection;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\ProcessingStatus;
use App\TravelClick\Jobs\InboundJobs\ProcessIncomingReservationJob;
use App\TravelClick\Jobs\InboundJobs\ProcessReservationCancellationJob;
use App\TravelClick\Jobs\InboundJobs\ProcessReservationModificationJob;
use App\TravelClick\Models\TravelClickErrorLog;
use App\TravelClick\Models\TravelClickMessageHistory;
use App\TravelClick\Parsers\ReservationParser;
use App\TravelClick\Support\MessageIdGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

class SoapController extends Controller
{
  /**
   * Handle incoming SOAP requests from TravelClick
   *
   * @param  Request  $request
   * @return Response
   */
  public function handle(Request $request): Response
  {
    $messageId = app(MessageIdGenerator::class)->generate();
    $rawXml = $request->getContent();
    $hotelCode = $request->attributes->get('hotel_code');

    Log::channel('travelclick')->debug('Received SOAP request', [
      'message_id' => $messageId,
      'hotel_code' => $hotelCode,
      'content_length' => strlen($rawXml),
    ]);

    try {
      // Identify message type
      $messageType = $this->identifyMessageType($rawXml);

      // Create message history entry
      $messageHistory = $this->createMessageHistory($messageId, $messageType, $hotelCode, $rawXml);

      // Process message based on type
      switch ($messageType) {
        case MessageType::RESERVATION:
          return $this->processReservation($rawXml, $messageId, $hotelCode, $messageHistory);

        case MessageType::GROUP_BLOCK:
          return $this->processGroupBlock($rawXml, $messageId, $hotelCode, $messageHistory);

        default:
          throw new \Exception('Unsupported message type: ' . $messageType->value);
      }
    } catch (Throwable $e) {
      Log::channel('travelclick')->error('Error processing SOAP request', [
        'message_id' => $messageId,
        'hotel_code' => $hotelCode,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);

      $this->logError($messageId, $hotelCode, 'PROCESSING_ERROR', $e->getMessage());

      return $this->createSoapFaultResponse(
        'Server',
        'Error processing request: ' . $e->getMessage()
      );
    }
  }

  /**
   * Process a reservation request
   *
   * @param  string  $rawXml
   * @param  string  $messageId
   * @param  string  $hotelCode
   * @param  TravelClickMessageHistory  $messageHistory
   * @return Response
   */
  protected function processReservation(
    string $rawXml,
    string $messageId,
    string $hotelCode,
    TravelClickMessageHistory $messageHistory
  ): Response {
    try {
      // Determine reservation transaction type (new, modify, cancel)
      $transactionType = $this->determineReservationTransactionType($rawXml);

      // Dispatch job based on transaction type
      switch ($transactionType) {
        case 'new':
          ProcessIncomingReservationJob::dispatch($rawXml, $hotelCode, $messageId);
          $message = 'New reservation received and queued for processing';
          break;

        case 'modify':
          ProcessReservationModificationJob::dispatch($rawXml, $hotelCode, $messageId);
          $message = 'Reservation modification received and queued for processing';
          break;

        case 'cancel':
          ProcessReservationCancellationJob::dispatch($rawXml, $hotelCode, $messageId);
          $message = 'Reservation cancellation received and queued for processing';
          break;

        default:
          throw new \Exception('Unknown reservation transaction type: ' . $transactionType);
      }

      // Update message history
      $messageHistory->update([
        'Status' => ProcessingStatus::PENDING->value,
        'Action' => $transactionType,
        'ProcessedAt' => now(),
      ]);

      // Return success acknowledgment
      return $this->createSuccessResponse(
        'OTA_HotelResNotifRS',
        $messageId,
        $message
      );
    } catch (Throwable $e) {
      Log::channel('travelclick')->error('Error processing reservation', [
        'message_id' => $messageId,
        'error' => $e->getMessage(),
      ]);

      $messageHistory->update([
        'Status' => ProcessingStatus::FAILED->value,
        'ErrorMessage' => $e->getMessage(),
        'ProcessedAt' => now(),
      ]);

      return $this->createSoapFaultResponse(
        'Server',
        'Error processing reservation: ' . $e->getMessage()
      );
    }
  }

  /**
   * Process a group block request
   *
   * @param  string  $rawXml
   * @param  string  $messageId
   * @param  string  $hotelCode
   * @param  TravelClickMessageHistory  $messageHistory
   * @return Response
   */
  protected function processGroupBlock(
    string $rawXml,
    string $messageId,
    string $hotelCode,
    TravelClickMessageHistory $messageHistory
  ): Response {
    // For now, just log receipt of group block message
    // In a future update, implement processing for group blocks

    Log::channel('travelclick')->info('Received group block message', [
      'message_id' => $messageId,
      'hotel_code' => $hotelCode,
    ]);

    $messageHistory->update([
      'Status' => ProcessingStatus::PROCESSED->value,
      'ProcessedAt' => now(),
    ]);

    // Return success acknowledgment
    return $this->createSuccessResponse(
      'OTA_HotelInvBlockNotifRS',
      $messageId,
      'Group block message received'
    );
  }

  /**
   * Identify the type of message from the XML content
   *
   * @param  string  $xml
   * @return MessageType
   */
  protected function identifyMessageType(string $xml): MessageType
  {
    if (str_contains($xml, 'OTA_HotelResNotifRQ')) {
      return MessageType::RESERVATION;
    }

    if (str_contains($xml, 'OTA_HotelInvBlockNotifRQ')) {
      return MessageType::GROUP_BLOCK;
    }

    if (str_contains($xml, 'OTA_HotelInvCountNotifRQ')) {
      return MessageType::INVENTORY;
    }

    if (str_contains($xml, 'OTA_HotelRateNotifRQ')) {
      return MessageType::RATES;
    }

    if (str_contains($xml, 'OTA_HotelAvailNotifRQ')) {
      return MessageType::RESTRICTIONS;
    }

    return MessageType::UNKNOWN;
  }

  /**
   * Determine the reservation transaction type (new, modify, cancel)
   *
   * @param  string  $xml
   * @return string
   */
  protected function determineReservationTransactionType(string $xml): string
  {
    if (str_contains($xml, 'ResStatus="Cancel"')) {
      return 'cancel';
    }

    if (str_contains($xml, 'ResStatus="Modify"')) {
      return 'modify';
    }

    return 'new';
  }

  /**
   * Create message history record
   *
   * @param  string  $messageId
   * @param  MessageType  $messageType
   * @param  string  $hotelCode
   * @param  string  $rawXml
   * @return TravelClickMessageHistory
   */
  protected function createMessageHistory(
    string $messageId,
    MessageType $messageType,
    string $hotelCode,
    string $rawXml
  ): TravelClickMessageHistory {
    return TravelClickMessageHistory::create([
      'MessageID' => $messageId,
      'MessageType' => $messageType->value,
      'MessageDirection' => MessageDirection::INBOUND->value,
      'Status' => ProcessingStatus::PENDING->value,
      'HotelCode' => $hotelCode,
      'RawMessage' => substr($rawXml, 0, 65000), // Truncate to avoid DB column size limits
      'ReceivedAt' => now(),
    ]);
  }

  /**
   * Log error for tracking
   *
   * @param  string  $messageId
   * @param  string  $hotelCode
   * @param  string  $errorCode
   * @param  string  $errorMessage
   * @return void
   */
  protected function logError(
    string $messageId,
    string $hotelCode,
    string $errorCode,
    string $errorMessage
  ): void {
    TravelClickErrorLog::create([
      'MessageID' => $messageId,
      'HotelCode' => $hotelCode,
      'ErrorCode' => $errorCode,
      'ErrorMessage' => $errorMessage,
      'ErrorTrace' => null,
      'CreatedAt' => now(),
    ]);
  }

  /**
   * Create a successful SOAP response
   *
   * @param  string  $responseType
   * @param  string  $messageId
   * @param  string  $message
   * @return Response
   */
  protected function createSuccessResponse(
    string $responseType,
    string $messageId,
    string $message
  ): Response {
    $timestamp = date('Y-m-d\TH:i:s');

    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
    <SOAP-ENV:Body>
        <{$responseType} xmlns="http://www.opentravel.org/OTA/2003/05"
                      TimeStamp="{$timestamp}"
                      EchoToken="{$messageId}"
                      Version="1.0">
            <Success />
            <Warnings>
                <Warning Type="3">{$message}</Warning>
            </Warnings>
        </{$responseType}>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;

    return response($xml, 200)
      ->header('Content-Type', 'text/xml');
  }

  /**
   * Create SOAP fault response
   *
   * @param  string  $faultCode
   * @param  string  $faultString
   * @return Response
   */
  protected function createSoapFaultResponse(
    string $faultCode,
    string $faultString
  ): Response {
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
    <SOAP-ENV:Body>
        <SOAP-ENV:Fault>
            <faultcode>SOAP-ENV:{$faultCode}</faultcode>
            <faultstring>{$faultString}</faultstring>
        </SOAP-ENV:Fault>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;

    return response($xml, 500)
      ->header('Content-Type', 'text/xml');
  }
}
