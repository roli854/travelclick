<?php

declare(strict_types=1);

namespace Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use WireMock\Client\WireMock;
use Spatie\LaravelData\DataCollection;

// TravelClick Classes
use App\TravelClick\Jobs\OutboundJobs\UpdateInventoryJob;
use App\TravelClick\Jobs\OutboundJobs\UpdateRatesJob;
use App\TravelClick\Jobs\OutboundJobs\SendReservationJob;
use App\TravelClick\Jobs\OutboundJobs\CancelReservationJob;

use App\TravelClick\DTOs\InventoryData;
use App\TravelClick\DTOs\RateData;
use App\TravelClick\DTOs\RatePlanData;
use App\TravelClick\DTOs\ReservationDataDto;
use App\TravelClick\DTOs\SoapResponseDto;

use App\TravelClick\Enums\CountType;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\ReservationType;
use App\TravelClick\Enums\ProcessingStatus;
use App\TravelClick\Enums\RateOperationType;
use App\TravelClick\Enums\SyncStatus;

use App\TravelClick\Models\TravelClickLog;
use App\TravelClick\Models\TravelClickMessageHistory;
use App\TravelClick\Models\TravelClickSyncStatus;

use App\TravelClick\Services\SoapService;
use Tests\Behat\Support\TravelClickWireMockServer;

/**
 * TravelClick Outbound Context for BDD Testing
 *
 * This context handles all outbound scenarios where data flows from the PMS to TravelClick:
 * - Inventory updates (Available count, Calculated method)
 * - Rate updates (Create, Update, Inactive)
 * - Reservation sending (New, Modify, Cancel)
 * - Group block operations
 */
class TravelClickOutboundContext implements Context
{
  use RefreshDatabase;

  private Application $app;
  private TravelClickWireMockServer $wireMockServer;
  private array $templateVariables = [];
  private array $queuedJobs = [];
  private array $sentRequests = [];
  private string $currentHotelCode = 'TEST001';
  private int $currentPropertyId = 1;

  /**
   * Initialize context with WireMock server and template variables
   */
  public function __construct()
  {
    // Initialize Laravel application for Behat context
    $this->app = require __DIR__ . '/../../../bootstrap/app.php';
    $this->app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

    $this->wireMockServer = new TravelClickWireMockServer();
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
      'start_date' => Carbon::today()->format('Y-m-d'),
      'end_date' => Carbon::today()->addDays(7)->format('Y-m-d'),
      'guest_first_name' => 'John',
      'guest_last_name' => 'Doe',
      'confirmation_number' => 'TEST12345',
      'message_id' => uniqid('TEST_', true),
    ];
  }

  // =====================================================
  // GIVEN Steps - Setup Data and Environment
  // =====================================================

  /**
   * @Given TravelClick mock server is running on port :port
   */
  public function travelClickMockServerIsRunningOnPort(int $port): void
  {
    $this->wireMockServer = new TravelClickWireMockServer('localhost', $port);

    if (!$this->wireMockServer->start()) {
      throw new \Exception("Failed to start WireMock server on port {$port}");
    }

    $this->wireMockServer->setupTravelClickEndpoint();

    // Update configuration to point to mock server
    config(['travelclick.endpoints.test' => "http://localhost:{$port}/HTNGService/services/HTNG2011BService"]);
  }

  /**
   * @Given I have hotel code :hotelCode
   */
  public function iHaveHotelCode(string $hotelCode): void
  {
    $this->currentHotelCode = $hotelCode;
    $this->templateVariables['hotel_code'] = $hotelCode;
  }

  /**
   * @Given I have property ID :propertyId
   */
  public function iHavePropertyId(int $propertyId): void
  {
    $this->currentPropertyId = $propertyId;
    $this->templateVariables['property_id'] = $propertyId;
  }

  /**
   * @Given I have inventory data for room :roomType with :count available rooms
   */
  public function iHaveInventoryDataForRoomWithAvailableRooms(string $roomType, int $count): void
  {
    $this->templateVariables['room_type'] = $roomType;
    $this->templateVariables['available_count'] = $count;

    // Create the InventoryData using the exact factory method from your DTO
    $inventoryData = InventoryData::createAvailable(
      hotelCode: $this->currentHotelCode,
      startDate: $this->templateVariables['start_date'],
      endDate: $this->templateVariables['end_date'],
      roomTypeCode: $roomType,
      availableCount: $count
    );

    $this->templateVariables['inventory_data'] = $inventoryData;
  }

  /**
   * @Given I have inventory data with calculated method: :definiteSold sold, :tentativeSold tentative, :outOfOrder out of order
   */
  public function iHaveInventoryDataWithCalculatedMethod(int $definiteSold, int $tentativeSold, int $outOfOrder): void
  {
    $inventoryData = InventoryData::createCalculated(
      hotelCode: $this->currentHotelCode,
      startDate: $this->templateVariables['start_date'],
      endDate: $this->templateVariables['end_date'],
      roomTypeCode: $this->templateVariables['room_type'],
      definiteSold: $definiteSold,
      tentativeSold: $tentativeSold,
      outOfOrder: $outOfOrder,
      oversell: 0
    );

    $this->templateVariables['inventory_data'] = $inventoryData;
    $this->templateVariables['definite_sold'] = $definiteSold;
    $this->templateVariables['tentative_sold'] = $tentativeSold;
    $this->templateVariables['out_of_order'] = $outOfOrder;
  }

  /**
   * @Given I have rate data for room :roomType and rate plan :ratePlan with rate :rate
   */
  public function iHaveRateDataForRoomAndRatePlanWithRate(string $roomType, string $ratePlan, float $rate): void
  {
    $rateData = RateData::fromArray([
      'firstAdultRate' => $rate,
      'secondAdultRate' => $rate + 20.00,
      'roomTypeCode' => $roomType,
      'ratePlanCode' => $ratePlan,
      'startDate' => $this->templateVariables['start_date'],
      'endDate' => $this->templateVariables['end_date'],
      'currencyCode' => 'USD'
    ]);

    $this->templateVariables['rate_data'] = $rateData;
    $this->templateVariables['room_type'] = $roomType;
    $this->templateVariables['rate_plan'] = $ratePlan;
    $this->templateVariables['first_adult_rate'] = $rate;
  }

  /**
   * @Given I have a :reservationType reservation for guest :firstName :lastName
   */
  public function iHaveAReservationForGuest(string $reservationType, string $firstName, string $lastName): void
  {
    $reservationData = [
      'reservationType' => ReservationType::from($reservationType),
      'primaryGuest' => [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'title' => 'Mr',
        'email' => strtolower($firstName) . '@test.com',
        'phone' => '+1234567890'
      ],
      'roomStays' => [[
        'checkInDate' => $this->templateVariables['start_date'],
        'checkOutDate' => $this->templateVariables['end_date'],
        'roomTypeCode' => $this->templateVariables['room_type'],
        'ratePlanCode' => $this->templateVariables['rate_plan'],
        'adultCount' => 2,
        'childCount' => 0,
        'rateAmount' => 150.00,
        'currencyCode' => 'USD'
      ]],
      'hotelCode' => $this->currentHotelCode,
      'reservationId' => 'RES_' . uniqid(),
      'createDateTime' => Carbon::now()->toISOString(),
      'transactionType' => 'NEW',
      'sourceOfBusiness' => 'WEB'
    ];

    $reservationDto = new ReservationDataDto($reservationData);

    $this->templateVariables['reservation_data'] = $reservationDto;
    $this->templateVariables['guest_first_name'] = $firstName;
    $this->templateVariables['guest_last_name'] = $lastName;
    $this->templateVariables['reservation_type'] = $reservationType;
  }

  // =====================================================
  // WHEN Steps - Actions and Job Dispatching
  // =====================================================

  /**
   * @When I dispatch an inventory update job with :updateType method
   */
  public function iDispatchAnInventoryUpdateJobWithMethod(string $updateType): void
  {
    $inventoryData = $this->templateVariables['inventory_data'];

    $job = match ($updateType) {
      'delta' => UpdateInventoryJob::delta($inventoryData, $this->currentHotelCode, $this->currentPropertyId),
      'overlay' => UpdateInventoryJob::overlay($inventoryData, $this->currentHotelCode, $this->currentPropertyId),
      'urgent' => UpdateInventoryJob::urgent($inventoryData, $this->currentHotelCode, $this->currentPropertyId),
      default => throw new \InvalidArgumentException("Unknown update type: {$updateType}")
    };

    $this->queuedJobs[] = [
      'job' => $job,
      'type' => 'inventory',
      'method' => $updateType,
      'dispatched_at' => Carbon::now()
    ];
  }

  /**
   * @When I dispatch a rate update job with :operationType operation
   */
  public function iDispatchARateUpdateJobWithOperation(string $operationType): void
  {
    $rateData = $this->templateVariables['rate_data'];
    $rates = collect([$rateData]);
    $operation = RateOperationType::from(strtoupper($operationType));

    $job = new UpdateRatesJob(
      rates: $rates,
      hotelCode: $this->currentHotelCode,
      operationType: $operation,
      isDeltaUpdate: true
    );

    $this->queuedJobs[] = [
      'job' => $job,
      'type' => 'rates',
      'operation' => $operationType,
      'dispatched_at' => Carbon::now()
    ];
  }

  /**
   * @When I dispatch a new reservation job
   */
  public function iDispatchANewReservationJob(): void
  {
    $reservationData = $this->templateVariables['reservation_data'];

    $job = new SendReservationJob($reservationData, true);

    $this->queuedJobs[] = [
      'job' => $job,
      'type' => 'reservation',
      'operation' => 'new',
      'dispatched_at' => Carbon::now()
    ];
  }

  /**
   * @When I dispatch a cancel reservation job
   */
  public function iDispatchACancelReservationJob(): void
  {
    $reservationData = $this->templateVariables['reservation_data'];

    $job = CancelReservationJob::cancel($reservationData, $this->currentHotelCode, $this->currentPropertyId);

    $this->queuedJobs[] = [
      'job' => $job,
      'type' => 'reservation',
      'operation' => 'cancel',
      'dispatched_at' => Carbon::now()
    ];
  }

  /**
   * @When I dispatch an urgent reservation cancellation
   */
  public function iDispatchAnUrgentReservationCancellation(): void
  {
    $reservationData = $this->templateVariables['reservation_data'];

    $job = CancelReservationJob::urgent($reservationData, $this->currentHotelCode, $this->currentPropertyId);

    $this->queuedJobs[] = [
      'job' => $job,
      'type' => 'reservation',
      'operation' => 'urgent_cancel',
      'dispatched_at' => Carbon::now()
    ];
  }

  // =====================================================
  // THEN Steps - Assertions and Verification
  // =====================================================

  /**
   * @Then the :jobType job should be queued on :queueName queue
   */
  public function theJobShouldBeQueuedOnQueue(string $jobType, string $queueName): void
  {
    $jobMapping = [
      'UpdateInventoryJob' => UpdateInventoryJob::class,
      'UpdateRatesJob' => UpdateRatesJob::class,
      'SendReservationJob' => SendReservationJob::class,
      'CancelReservationJob' => CancelReservationJob::class,
    ];

    $jobClass = $jobMapping[$jobType] ?? throw new \InvalidArgumentException("Unknown job type: {$jobType}");

    Queue::assertPushedOn($queueName, $jobClass);
  }

  /**
   * @Then the job should have :retryCount retry attempts configured
   */
  public function theJobShouldHaveRetryAttemptsConfigured(int $retryCount): void
  {
    if (count($this->queuedJobs) !== 1) {
      throw new \Exception('Expected exactly one job to be dispatched, got ' . count($this->queuedJobs));
    }

    $lastJob = end($this->queuedJobs);
    $job = $lastJob['job'];

    if ($job->tries !== $retryCount) {
      throw new \Exception("Expected {$retryCount} retry attempts, but got {$job->tries}");
    }
  }

  /**
   * @Then WireMock should receive :messageType request with XML containing :xmlContent
   */
  public function wireMockShouldReceiveRequestWithXmlContaining(string $messageType, string $xmlContent): void
  {
    $criteria = [$xmlContent];

    if (!$this->wireMockServer->verifyRequestReceived($messageType, $criteria)) {
      throw new \Exception("Expected WireMock to receive {$messageType} request containing '{$xmlContent}'");
    }
  }

  /**
   * @Then WireMock should receive :messageType request
   */
  public function wireMockShouldReceiveRequest(string $messageType): void
  {
    if (!$this->wireMockServer->verifyRequestReceived($messageType)) {
      throw new \Exception("Expected WireMock to receive {$messageType} request");
    }
  }

  /**
   * @Then XML should contain HotelCode :hotelCode
   */
  public function xmlShouldContainHotelCode(string $hotelCode): void
  {
    $lastRequest = $this->wireMockServer->getLastRequest();

    if (!$lastRequest || !str_contains($lastRequest['body'], "HotelCode=\"{$hotelCode}\"")) {
      throw new \Exception("Expected XML to contain HotelCode=\"{$hotelCode}\"");
    }
  }

  /**
   * @Then XML should contain CountType :countType with count :count
   */
  public function xmlShouldContainCountTypeWithCount(int $countType, int $count): void
  {
    $lastRequest = $this->wireMockServer->getLastRequest();
    $expectedXml = "CountType=\"{$countType}\" Count=\"{$count}\"";

    if (!$lastRequest || !str_contains($lastRequest['body'], $expectedXml)) {
      throw new \Exception("Expected XML to contain CountType=\"{$countType}\" Count=\"{$count}\"");
    }
  }

  /**
   * @Then XML should contain room type :roomType
   */
  public function xmlShouldContainRoomType(string $roomType): void
  {
    $lastRequest = $this->wireMockServer->getLastRequest();

    if (!$lastRequest || !str_contains($lastRequest['body'], "InvTypeCode=\"{$roomType}\"")) {
      throw new \Exception("Expected XML to contain InvTypeCode=\"{$roomType}\"");
    }
  }

  /**
   * @Then XML should contain rate plan :ratePlan with rate :rate
   */
  public function xmlShouldContainRatePlanWithRate(string $ratePlan, float $rate): void
  {
    $lastRequest = $this->wireMockServer->getLastRequest();

    if (!$lastRequest) {
      throw new \Exception("No request found to verify");
    }

    $body = $lastRequest['body'];

    if (!str_contains($body, "RatePlanCode=\"{$ratePlan}\"")) {
      throw new \Exception("Expected XML to contain RatePlanCode=\"{$ratePlan}\"");
    }

    if (!str_contains($body, "AmountBeforeTax=\"{$rate}\"")) {
      throw new \Exception("Expected XML to contain AmountBeforeTax=\"{$rate}\"");
    }
  }

  /**
   * @Then XML should contain guest name :firstName :lastName
   */
  public function xmlShouldContainGuestName(string $firstName, string $lastName): void
  {
    $lastRequest = $this->wireMockServer->getLastRequest();

    if (!$lastRequest) {
      throw new \Exception("No request found to verify");
    }

    $body = $lastRequest['body'];

    if (!str_contains($body, "<GivenName>{$firstName}</GivenName>")) {
      throw new \Exception("Expected XML to contain <GivenName>{$firstName}</GivenName>");
    }

    if (!str_contains($body, "<Surname>{$lastName}</Surname>")) {
      throw new \Exception("Expected XML to contain <Surname>{$lastName}</Surname>");
    }
  }

  /**
   * @Then a TravelClickLog should be created with message type :messageType
   */
  public function aTravelClickLogShouldBeCreatedWithMessageType(string $messageType): void
  {
    $log = TravelClickLog::where([
      'MessageType' => MessageType::from($messageType)->value,
      'HotelCode' => $this->currentHotelCode,
      'Direction' => 'outbound'
    ])->first();

    if (!$log) {
      throw new \Exception(
        "Expected TravelClickLog with MessageType '{$messageType}' and HotelCode '{$this->currentHotelCode}' not found"
      );
    }
  }

  /**
   * @Then the sync status should be :status
   */
  public function theSyncStatusShouldBe(string $status): void
  {
    $syncStatus = SyncStatus::from($status);

    $record = TravelClickSyncStatus::where([
      'PropertyID' => $this->currentPropertyId,
      'Status' => $syncStatus->value,
    ])->first();

    if (!$record) {
      throw new \Exception(
        "Expected sync status '{$status}' for property {$this->currentPropertyId} not found"
      );
    }
  }

  /**
   * @Then the job should be configured for high priority processing
   */
  public function theJobShouldBeConfiguredForHighPriorityProcessing(): void
  {
    if (count($this->queuedJobs) !== 1) {
      throw new \Exception('Expected exactly one job to be dispatched, got ' . count($this->queuedJobs));
    }

    $lastJob = end($this->queuedJobs);
    $job = $lastJob['job'];

    // Verify the job uses high priority queue configuration
    Queue::assertPushedOn('travelclick-high', get_class($job));
  }

  /**
   * @Then the request should timeout after :seconds seconds
   */
  public function theRequestShouldTimeoutAfterSeconds(int $seconds): void
  {
    if (count($this->queuedJobs) !== 1) {
      throw new \Exception('Expected exactly one job to be dispatched, got ' . count($this->queuedJobs));
    }

    $lastJob = end($this->queuedJobs);
    $jobType = $lastJob['type'];

    $expectedTimeout = match ($jobType) {
      'inventory' => MessageType::INVENTORY->getTimeout(),
      'rates' => MessageType::RATES->getTimeout(),
      'reservation' => MessageType::RESERVATION->getTimeout(),
      default => throw new \InvalidArgumentException("Unknown job type: {$jobType}")
    };

    if ($expectedTimeout !== $seconds) {
      throw new \Exception("Expected timeout {$seconds}s, but got {$expectedTimeout}s for job type '{$jobType}'");
    }
  }

  /**
   * @Then WireMock should receive exactly :count requests
   */
  public function wireMockShouldReceiveExactlyRequests(int $count): void
  {
    $actualCount = $this->wireMockServer->getRequestCount();

    if ($actualCount !== $count) {
      throw new \Exception("Expected {$count} requests, but got {$actualCount}");
    }
  }

  /**
   * @Then the job should use exponential backoff strategy
   */
  public function theJobShouldUseExponentialBackoffStrategy(): void
  {
    $retryPolicy = config('travelclick.retry_policy');

    if ($retryPolicy['backoff_strategy'] !== 'exponential') {
      throw new \Exception("Expected exponential backoff strategy, got '{$retryPolicy['backoff_strategy']}'");
    }

    if ($retryPolicy['max_attempts'] !== 3) {
      throw new \Exception("Expected 3 max attempts, got {$retryPolicy['max_attempts']}");
    }

    if ($retryPolicy['initial_delay_seconds'] !== 10) {
      throw new \Exception("Expected 10s initial delay, got {$retryPolicy['initial_delay_seconds']}");
    }
  }

  // =====================================================
  // Helper Methods and Cleanup
  // =====================================================

  /**
   * Get current template variables for debugging
   */
  public function getCurrentTemplateVariables(): array
  {
    return $this->templateVariables;
  }

  /**
   * Get queued jobs for inspection
   */
  public function getQueuedJobs(): array
  {
    return $this->queuedJobs;
  }

  /**
   * Reset context state for next scenario
   */
  public function resetContext(): void
  {
    $this->queuedJobs = [];
    $this->sentRequests = [];
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
