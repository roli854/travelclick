<?php

declare(strict_types=1);

namespace Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

// TravelClick Classes
use App\TravelClick\Jobs\InboundJobs\ProcessIncomingReservationJob;
use App\TravelClick\Jobs\InboundJobs\ProcessReservationCancellationJob;
use App\TravelClick\Jobs\InboundJobs\ProcessReservationModificationJob;

use App\TravelClick\DTOs\ReservationDataDto;
use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\DTOs\GuestDataDto;
use App\TravelClick\DTOs\RoomStayDataDto;

use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\ReservationType;
use App\TravelClick\Enums\ProcessingStatus;
use App\TravelClick\Enums\SyncStatus;

use App\TravelClick\Models\TravelClickLog;
use App\TravelClick\Models\TravelClickMessageHistory;
use App\TravelClick\Models\TravelClickSyncStatus;

use App\TravelClick\Parsers\ReservationParser;
use App\TravelClick\Services\ReservationService;
use App\TravelClick\Http\Controllers\SoapController;

use Tests\Behat\Support\TravelClickWireMockServer;

/**
 * TravelClick Inbound Context for BDD Testing
 *
 * This context handles all inbound scenarios where data flows from TravelClick to the PMS:
 * - New reservation processing
 * - Reservation modifications
 * - Reservation cancellations
 * - Response message handling
 * - SOAP endpoint testing
 */
class TravelClickInboundContext implements Context
{
  use RefreshDatabase;

  private Application $app;
  private TravelClickWireMockServer $wireMockServer;
  private array $templateVariables = [];
  private array $processedJobs = [];
  private array $incomingMessages = [];
  private string $currentHotelCode = 'TEST001';
  private int $currentPropertyId = 1;
  private string $soapEndpointUrl = 'http://localhost:8080/api/travelclick/soap';
  private Client $httpClient;

  /**
   * Initialize context with SOAP endpoint and message processing
   */
  public function __construct()
  {
    // Initialize Laravel application for Behat context
    $this->app = require __DIR__ . '/../../../bootstrap/app.php';
    $this->app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

    $this->wireMockServer = new TravelClickWireMockServer();
    $this->httpClient = new Client(['timeout' => 30]);
    $this->initializeTemplateVariables();
    $this->setupTestEnvironment();

    // Enable queue testing
    Queue::fake();
    Bus::fake();
  }

  /**
   * Setup test environment for Behat
   */
  private function setupTestEnvironment(): void
  {
    // Set testing environment
    config(['app.env' => 'testing']);

    // Setup database for testing
    config(['database.default' => 'testing']);

    // Ensure we're using in-memory SQLite for testing
    config(['database.connections.testing' => [
      'driver' => 'sqlite',
      'database' => ':memory:',
      'prefix' => '',
      'foreign_key_constraints' => true,
    ]]);

    // Run migrations
    $this->artisan('migrate:fresh');
  }

  /**
   * Run artisan command for Behat context
   */
  private function artisan(string $command): int
  {
    return $this->app->make('Illuminate\Contracts\Console\Kernel')->call($command);
  }

  /**
   * Initialize default template variables for XML fixtures
   */
  private function initializeTemplateVariables(): void
  {
    $this->templateVariables = [
      'hotel_code' => $this->currentHotelCode,
      'property_id' => $this->currentPropertyId,
      'room_type' => 'KING',
      'rate_plan' => 'BAR',
      'arrival_date' => Carbon::today()->addDays(1)->format('Y-m-d'),
      'departure_date' => Carbon::today()->addDays(3)->format('Y-m-d'),
      'guest_first_name' => 'John',
      'guest_last_name' => 'Doe',
      'confirmation_number' => 'TC' . rand(100000, 999999),
      'reservation_id' => 'RES_' . uniqid(),
      'message_id' => uniqid('MSG_', true),
      'echo_token' => 'ECHO_' . uniqid(),
      'timestamp' => Carbon::now()->toISOString(),
    ];
  }

  // =====================================================
  // GIVEN Steps - Setup Environment and Data
  // =====================================================

  /**
   * @Given SOAP endpoint is running on port :port
   */
  public function soapEndpointIsRunningOnPort(int $port): void
  {
    $this->soapEndpointUrl = "http://localhost:{$port}/api/travelclick/soap";

    // Test if the SOAP endpoint is accessible
    try {
      $response = $this->httpClient->get($this->soapEndpointUrl . '?wsdl');
      if ($response->getStatusCode() !== 200) {
        throw new \Exception("SOAP endpoint not accessible on port {$port}");
      }
    } catch (\Exception $e) {
      throw new \Exception("SOAP endpoint is not running on port {$port}: " . $e->getMessage());
    }
  }

  /**
   * @Given I have hotel code :hotelCode for inbound processing
   */
  public function iHaveHotelCodeForInboundProcessing(string $hotelCode): void
  {
    $this->currentHotelCode = $hotelCode;
    $this->templateVariables['hotel_code'] = $hotelCode;
  }

  /**
   * @Given I have property ID :propertyId for inbound processing
   */
  public function iHavePropertyIdForInboundProcessing(int $propertyId): void
  {
    $this->currentPropertyId = $propertyId;
    $this->templateVariables['property_id'] = $propertyId;
  }

  /**
   * @Given I have a :reservationType reservation XML for guest :firstName :lastName
   */
  public function iHaveAReservationXmlForGuest(string $reservationType, string $firstName, string $lastName): void
  {
    $this->templateVariables['reservation_type'] = $reservationType;
    $this->templateVariables['guest_first_name'] = $firstName;
    $this->templateVariables['guest_last_name'] = $lastName;

    // Generate reservation XML based on type
    $reservationXml = $this->generateReservationXml($reservationType, $firstName, $lastName);
    $this->templateVariables['reservation_xml'] = $reservationXml;
  }

  /**
   * @Given I have a reservation modification XML for confirmation :confirmationNumber
   */
  public function iHaveAReservationModificationXmlForConfirmation(string $confirmationNumber): void
  {
    $this->templateVariables['confirmation_number'] = $confirmationNumber;

    $modificationXml = $this->generateReservationModificationXml($confirmationNumber);
    $this->templateVariables['modification_xml'] = $modificationXml;
  }

  /**
   * @Given I have a reservation cancellation XML for confirmation :confirmationNumber
   */
  public function iHaveAReservationCancellationXmlForConfirmation(string $confirmationNumber): void
  {
    $this->templateVariables['confirmation_number'] = $confirmationNumber;

    $cancellationXml = $this->generateReservationCancellationXml($confirmationNumber);
    $this->templateVariables['cancellation_xml'] = $cancellationXml;
  }

  /**
   * @Given I have XML from fixture file :fixtureFile
   */
  public function iHaveXmlFromFixtureFile(string $fixtureFile): void
  {
    $fixturePath = __DIR__ . '/../fixtures/xml_samples/' . $fixtureFile;

    if (!file_exists($fixturePath)) {
      throw new \Exception("Fixture file not found: {$fixturePath}");
    }

    $xmlContent = file_get_contents($fixturePath);

    // Replace template variables in the XML
    foreach ($this->templateVariables as $key => $value) {
      $xmlContent = str_replace("{{$key}}", (string)$value, $xmlContent);
    }

    $this->templateVariables['fixture_xml'] = $xmlContent;
  }

  // =====================================================
  // WHEN Steps - Process Incoming Messages
  // =====================================================

  /**
   * @When TravelClick sends new :reservationType reservation XML
   */
  public function travelClickSendsNewReservationXml(string $reservationType): void
  {
    $reservationXml = $this->templateVariables['reservation_xml'] ??
      $this->generateReservationXml($reservationType, 'John', 'Doe');

    $this->sendSoapRequest($reservationXml);
    $this->recordIncomingMessage('reservation', 'new', $reservationXml);
  }

  /**
   * @When TravelClick sends reservation modification XML
   */
  public function travelClickSendsReservationModificationXml(): void
  {
    $modificationXml = $this->templateVariables['modification_xml'] ??
      $this->generateReservationModificationXml('TC123456');

    $this->sendSoapRequest($modificationXml);
    $this->recordIncomingMessage('reservation', 'modify', $modificationXml);
  }

  /**
   * @When TravelClick sends reservation cancellation XML
   */
  public function travelClickSendsReservationCancellationXml(): void
  {
    $cancellationXml = $this->templateVariables['cancellation_xml'] ??
      $this->generateReservationCancellationXml('TC123456');

    $this->sendSoapRequest($cancellationXml);
    $this->recordIncomingMessage('reservation', 'cancel', $cancellationXml);
  }

  /**
   * @When TravelClick sends XML from fixture :fixtureFile
   */
  public function travelClickSendsXmlFromFixture(string $fixtureFile): void
  {
    $this->iHaveXmlFromFixtureFile($fixtureFile);
    $xmlContent = $this->templateVariables['fixture_xml'];

    $this->sendSoapRequest($xmlContent);
    $this->recordIncomingMessage('fixture', $fixtureFile, $xmlContent);
  }

  /**
   * @When I process the incoming message manually
   */
  public function iProcessTheIncomingMessageManually(): void
  {
    if (empty($this->incomingMessages)) {
      throw new \Exception('No incoming messages to process');
    }

    $lastMessage = end($this->incomingMessages);
    $xmlContent = $lastMessage['xml'];

    // Dispatch the appropriate job based on message type
    if (str_contains($xmlContent, 'ResStatus="New"')) {
      ProcessIncomingReservationJob::dispatch(
        $xmlContent,
        $this->currentHotelCode,
        $this->templateVariables['message_id']
      );
    } elseif (str_contains($xmlContent, 'ResStatus="Modify"')) {
      ProcessReservationModificationJob::dispatch(
        $this->templateVariables['message_id'],
        $xmlContent,
        $this->currentHotelCode
      );
    } elseif (str_contains($xmlContent, 'ResStatus="Cancel"')) {
      $reservationData = $this->parseReservationFromXml($xmlContent);
      ProcessReservationCancellationJob::dispatch($reservationData);
    }
  }

  // =====================================================
  // THEN Steps - Verify Processing Results
  // =====================================================

  /**
   * @Then ProcessIncomingReservationJob should be queued on :queueName queue
   */
  public function processIncomingReservationJobShouldBeQueuedOnQueue(string $queueName): void
  {
    Queue::assertPushedOn($queueName, ProcessIncomingReservationJob::class);
  }

  /**
   * @Then ProcessReservationModificationJob should be queued on :queueName queue
   */
  public function processReservationModificationJobShouldBeQueuedOnQueue(string $queueName): void
  {
    Queue::assertPushedOn($queueName, ProcessReservationModificationJob::class);
  }

  /**
   * @Then ProcessReservationCancellationJob should be queued on :queueName queue
   */
  public function processReservationCancellationJobShouldBeQueuedOnQueue(string $queueName): void
  {
    Queue::assertPushedOn($queueName, ProcessReservationCancellationJob::class);
  }

  /**
   * @Then reservation should be stored in database with confirmation number :confirmationNumber
   */
  public function reservationShouldBeStoredInDatabaseWithConfirmationNumber(string $confirmationNumber): void
  {
    // Check if reservation was logged in TravelClickMessageHistory
    $messageHistory = TravelClickMessageHistory::where([
      'MessageType' => MessageType::RESERVATION->value,
      'Direction' => 'inbound',
      'PropertyID' => $this->currentPropertyId,
    ])->whereRaw("MessageXML LIKE ?", ["%{$confirmationNumber}%"])->first();

    if (!$messageHistory) {
      throw new \Exception("Expected reservation with confirmation number '{$confirmationNumber}' not found in message history");
    }
  }

  /**
   * @Then guest :firstName :lastName should be stored with email :email
   */
  public function guestShouldBeStoredWithEmail(string $firstName, string $lastName, string $email): void
  {
    // Verify guest data is captured in message history
    $messageHistory = TravelClickMessageHistory::where([
      'MessageType' => MessageType::RESERVATION->value,
      'Direction' => 'inbound',
    ])->whereRaw("MessageXML LIKE ?", ["%{$firstName}%"])
      ->whereRaw("MessageXML LIKE ?", ["%{$lastName}%"])
      ->whereRaw("MessageXML LIKE ?", ["%{$email}%"])
      ->first();

    if (!$messageHistory) {
      throw new \Exception("Expected guest '{$firstName} {$lastName}' with email '{$email}' not found");
    }
  }

  /**
   * @Then room stay should be recorded for room type :roomType from :arrivalDate to :departureDate
   */
  public function roomStayShouldBeRecordedForRoomType(string $roomType, string $arrivalDate, string $departureDate): void
  {
    $messageHistory = TravelClickMessageHistory::where([
      'MessageType' => MessageType::RESERVATION->value,
      'Direction' => 'inbound',
    ])->whereRaw("MessageXML LIKE ?", ["%{$roomType}%"])
      ->whereRaw("MessageXML LIKE ?", ["%{$arrivalDate}%"])
      ->whereRaw("MessageXML LIKE ?", ["%{$departureDate}%"])
      ->first();

    if (!$messageHistory) {
      throw new \Exception("Expected room stay for '{$roomType}' from '{$arrivalDate}' to '{$departureDate}' not found");
    }
  }

  /**
   * @Then a TravelClickLog should be created with direction :direction and message type :messageType
   */
  public function aTravelClickLogShouldBeCreatedWithDirectionAndMessageType(string $direction, string $messageType): void
  {
    $log = TravelClickLog::where([
      'MessageType' => MessageType::from($messageType)->value,
      'HotelCode' => $this->currentHotelCode,
      'Direction' => $direction
    ])->first();

    if (!$log) {
      throw new \Exception(
        "Expected TravelClickLog with direction '{$direction}' and MessageType '{$messageType}' not found"
      );
    }
  }

  /**
   * @Then the message should be marked as :status
   */
  public function theMessageShouldBeMarkedAs(string $status): void
  {
    $processingStatus = ProcessingStatus::from($status);

    $messageHistory = TravelClickMessageHistory::where([
      'PropertyID' => $this->currentPropertyId,
      'Status' => $processingStatus->value,
    ])->first();

    if (!$messageHistory) {
      throw new \Exception("Expected message with status '{$status}' not found");
    }
  }

  /**
   * @Then the processing time should be less than :seconds seconds
   */
  public function theProcessingTimeShouldBeLessThanSeconds(int $seconds): void
  {
    if (empty($this->incomingMessages)) {
      throw new \Exception('No messages processed to check timing');
    }

    $lastMessage = end($this->incomingMessages);
    $processingTime = $lastMessage['processing_time'] ?? 0;

    if ($processingTime >= $seconds) {
      throw new \Exception("Expected processing time less than {$seconds}s, but got {$processingTime}s");
    }
  }

  /**
   * @Then SOAP response should be successful with status :statusCode
   */
  public function soapResponseShouldBeSuccessfulWithStatus(int $statusCode): void
  {
    if (empty($this->incomingMessages)) {
      throw new \Exception('No messages sent to check response');
    }

    $lastMessage = end($this->incomingMessages);
    $responseStatus = $lastMessage['response_status'] ?? 0;

    if ($responseStatus !== $statusCode) {
      throw new \Exception("Expected SOAP response status {$statusCode}, but got {$responseStatus}");
    }
  }

  /**
   * @Then SOAP response should contain success confirmation
   */
  public function soapResponseShouldContainSuccessConfirmation(): void
  {
    if (empty($this->incomingMessages)) {
      throw new \Exception('No messages sent to check response');
    }

    $lastMessage = end($this->incomingMessages);
    $responseBody = $lastMessage['response_body'] ?? '';

    if (!str_contains($responseBody, '<Success/>') && !str_contains($responseBody, 'success')) {
      throw new \Exception("Expected SOAP response to contain success confirmation");
    }
  }

  /**
   * @Then the job should handle :reservationType reservation type correctly
   */
  public function theJobShouldHandleReservationTypeCorrectly(string $reservationType): void
  {
    // Verify that the job was dispatched with correct reservation type processing
    $reservationTypeEnum = ReservationType::from($reservationType);

    // Check if appropriate profile handling was triggered based on reservation type
    if ($reservationTypeEnum->requiresProfile()) {
      $profileType = $reservationTypeEnum->getRequiredProfileType();

      $messageHistory = TravelClickMessageHistory::where([
        'MessageType' => MessageType::RESERVATION->value,
        'Direction' => 'inbound',
      ])->whereRaw("MessageXML LIKE ?", ["%{$profileType}%"])->first();

      if (!$messageHistory) {
        throw new \Exception("Expected profile type '{$profileType}' processing for reservation type '{$reservationType}' not found");
      }
    }
  }

  // =====================================================
  // Helper Methods and XML Generation
  // =====================================================

  /**
   * Send SOAP request to the endpoint
   */
  private function sendSoapRequest(string $xmlContent): void
  {
    $startTime = microtime(true);

    try {
      $response = $this->httpClient->post($this->soapEndpointUrl, [
        'headers' => [
          'Content-Type' => 'text/xml; charset=utf-8',
          'SOAPAction' => 'HTNG2011B_SubmitRequest'
        ],
        'body' => $xmlContent
      ]);

      $responseStatus = $response->getStatusCode();
      $responseBody = $response->getBody()->getContents();
    } catch (\Exception $e) {
      $responseStatus = 500;
      $responseBody = $e->getMessage();
    }

    $processingTime = microtime(true) - $startTime;

    // Update the last message record with response data
    if (!empty($this->incomingMessages)) {
      $lastIndex = count($this->incomingMessages) - 1;
      $this->incomingMessages[$lastIndex]['response_status'] = $responseStatus;
      $this->incomingMessages[$lastIndex]['response_body'] = $responseBody;
      $this->incomingMessages[$lastIndex]['processing_time'] = $processingTime;
    }
  }

  /**
   * Record incoming message for tracking
   */
  private function recordIncomingMessage(string $type, string $operation, string $xml): void
  {
    $this->incomingMessages[] = [
      'type' => $type,
      'operation' => $operation,
      'xml' => $xml,
      'timestamp' => Carbon::now(),
      'message_id' => $this->templateVariables['message_id'],
      'hotel_code' => $this->currentHotelCode,
    ];
  }

  /**
   * Generate reservation XML based on type
   */
  private function generateReservationXml(string $reservationType, string $firstName, string $lastName): string
  {
    $messageId = $this->templateVariables['message_id'];
    $hotelCode = $this->currentHotelCode;
    $arrivalDate = $this->templateVariables['arrival_date'];
    $departureDate = $this->templateVariables['departure_date'];
    $confirmationNumber = $this->templateVariables['confirmation_number'];
    $roomType = $this->templateVariables['room_type'];

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Body>
        <OTA_HotelResNotifRQ EchoToken="{$this->templateVariables['echo_token']}"
                             TimeStamp="{$this->templateVariables['timestamp']}"
                             Version="1.0">
            <HotelReservations>
                <HotelReservation CreateDateTime="{$this->templateVariables['timestamp']}"
                                  LastModifyDateTime="{$this->templateVariables['timestamp']}"
                                  ResStatus="New">
                    <UniqueID Type="14" ID="{$confirmationNumber}"/>
                    <RoomStays>
                        <RoomStay>
                            <RoomTypes>
                                <RoomType RoomTypeCode="{$roomType}"/>
                            </RoomTypes>
                            <RatePlans>
                                <RatePlan RatePlanCode="{$this->templateVariables['rate_plan']}"/>
                            </RatePlans>
                            <TimeSpan Start="{$arrivalDate}" End="{$departureDate}"/>
                            <GuestCounts>
                                <GuestCount AgeQualifyingCode="10" Count="2"/>
                            </GuestCounts>
                        </RoomStay>
                    </RoomStays>
                    <ResGuests>
                        <ResGuest>
                            <Profiles>
                                <ProfileInfo>
                                    <Profile>
                                        <Customer>
                                            <PersonName>
                                                <GivenName>{$firstName}</GivenName>
                                                <Surname>{$lastName}</Surname>
                                            </PersonName>
                                            <Email>{$firstName}@test.com</Email>
                                        </Customer>
                                    </Profile>
                                </ProfileInfo>
                            </Profiles>
                        </ResGuest>
                    </ResGuests>
                    <ResGlobalInfo>
                        <HotelReservationIDs>
                            <HotelReservationID ResID_Type="14" ResID_Value="{$confirmationNumber}"/>
                        </HotelReservationIDs>
                        <BasicPropertyInfo HotelCode="{$hotelCode}"/>
                    </ResGlobalInfo>
                </HotelReservation>
            </HotelReservations>
        </OTA_HotelResNotifRQ>
    </soap:Body>
</soap:Envelope>
XML;
  }

  /**
   * Generate reservation modification XML
   */
  private function generateReservationModificationXml(string $confirmationNumber): string
  {
    $xml = $this->generateReservationXml('transient', 'John', 'Doe');
    return str_replace('ResStatus="New"', 'ResStatus="Modify"', $xml);
  }

  /**
   * Generate reservation cancellation XML
   */
  private function generateReservationCancellationXml(string $confirmationNumber): string
  {
    $xml = $this->generateReservationXml('transient', 'John', 'Doe');
    return str_replace('ResStatus="New"', 'ResStatus="Cancel"', $xml);
  }

  /**
   * Parse reservation data from XML for job dispatching
   */
  private function parseReservationFromXml(string $xmlContent): ReservationDataDto
  {
    // This would normally use your ReservationParser
    // For BDD testing, we'll create a minimal DTO
    return new ReservationDataDto([
      'reservationType' => ReservationType::TRANSIENT,
      'reservationId' => $this->templateVariables['reservation_id'],
      'confirmationNumber' => $this->templateVariables['confirmation_number'],
      'hotelCode' => $this->currentHotelCode,
      'transactionType' => 'CANCEL',
      'primaryGuest' => [
        'firstName' => $this->templateVariables['guest_first_name'],
        'lastName' => $this->templateVariables['guest_last_name'],
        'title' => 'Mr',
        'email' => 'test@example.com'
      ],
      'roomStays' => [],
      'createDateTime' => Carbon::now()->toISOString(),
      'sourceOfBusiness' => 'TRAVELCLICK'
    ]);
  }

  /**
   * Get current template variables for debugging
   */
  public function getCurrentTemplateVariables(): array
  {
    return $this->templateVariables;
  }

  /**
   * Get processed jobs for inspection
   */
  public function getProcessedJobs(): array
  {
    return $this->processedJobs;
  }

  /**
   * Get incoming messages for inspection
   */
  public function getIncomingMessages(): array
  {
    return $this->incomingMessages;
  }

  /**
   * Reset context state for next scenario
   */
  public function resetContext(): void
  {
    $this->processedJobs = [];
    $this->incomingMessages = [];
    $this->initializeTemplateVariables();
    Queue::fake();
    Bus::fake();
  }

  /**
   * Cleanup after scenarios
   */
  public function __destruct()
  {
    if (isset($this->wireMockServer)) {
      $this->wireMockServer->stop();
    }
  }
}
