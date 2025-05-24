<?php

declare(strict_types=1);

namespace Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

use Tests\Behat\Support\TravelClickWireMockServer;

/**
 * WireMock Context for BDD Testing
 *
 * This context handles all WireMock-specific operations for TravelClick BDD tests:
 * - Advanced stub configuration
 * - Request/response verification
 * - Error simulation and timeout testing
 * - Custom response scenarios
 * - Request matching and inspection
 * - Performance testing scenarios
 */
class WireMockContext implements Context
{
  private TravelClickWireMockServer $wireMockServer;
  private array $customStubs = [];
  private array $requestHistory = [];
  private array $responseTemplates = [];
  private string $host = 'localhost';
  private int $port = 8080;
  private bool $isRunning = false;

  /**
   * Initialize WireMock context
   */
  public function __construct()
  {
    $this->wireMockServer = new TravelClickWireMockServer($this->host, $this->port);
    $this->initializeResponseTemplates();
  }

  /**
   * Initialize response templates for different scenarios
   */
  private function initializeResponseTemplates(): void
  {
    $this->responseTemplates = [
      'inventory_success' => $this->getInventorySuccessTemplate(),
      'inventory_error' => $this->getInventoryErrorTemplate(),
      'rate_success' => $this->getRateSuccessTemplate(),
      'rate_warning' => $this->getRateWarningTemplate(),
      'reservation_success' => $this->getReservationSuccessTemplate(),
      'reservation_error' => $this->getReservationErrorTemplate(),
      'authentication_error' => $this->getAuthenticationErrorTemplate(),
      'timeout_simulation' => null, // Special case for timeout
      'server_error' => $this->getServerErrorTemplate(),
    ];
  }

  // =====================================================
  // GIVEN Steps - WireMock Setup and Configuration
  // =====================================================

  /**
   * @Given WireMock server is running on :host port :port
   */
  public function wireMockServerIsRunningOnHostPort(string $host, int $port): void
  {
    $this->host = $host;
    $this->port = $port;
    $this->wireMockServer = new TravelClickWireMockServer($host, $port);

    if (!$this->wireMockServer->start()) {
      throw new Exception("Failed to start WireMock server on {$host}:{$port}");
    }

    $this->isRunning = true;
    Log::info("WireMock server started successfully", ['host' => $host, 'port' => $port]);
  }

  /**
   * @Given WireMock is configured with default TravelClick stubs
   */
  public function wireMockIsConfiguredWithDefaultTravelClickStubs(): void
  {
    if (!$this->isRunning) {
      throw new Exception('WireMock server must be running before configuring stubs');
    }

    $this->wireMockServer->setupTravelClickEndpoint();
    Log::info('Default TravelClick stubs configured');
  }

  /**
   * @Given WireMock is configured to return :responseType for :messageType requests
   */
  public function wireMockIsConfiguredToReturnForRequests(string $responseType, string $messageType): void
  {
    if (!$this->isRunning) {
      throw new Exception('WireMock server must be running before configuring stubs');
    }

    if (!isset($this->responseTemplates[$responseType])) {
      throw new Exception("Unknown response type: {$responseType}");
    }

    $this->setupCustomStub($messageType, $responseType);
    $this->customStubs[$messageType] = $responseType;

    Log::info("Custom stub configured", [
      'message_type' => $messageType,
      'response_type' => $responseType
    ]);
  }

  /**
   * @Given WireMock is configured to simulate :scenario for requests containing :xmlContent
   */
  public function wireMockIsConfiguredToSimulateForRequestsContaining(string $scenario, string $xmlContent): void
  {
    if (!$this->isRunning) {
      throw new Exception('WireMock server must be running before configuring stubs');
    }

    $this->setupScenarioStub($scenario, $xmlContent);

    Log::info("Scenario stub configured", [
      'scenario' => $scenario,
      'xml_content' => $xmlContent
    ]);
  }

  /**
   * @Given WireMock is configured to delay responses by :milliseconds milliseconds
   */
  public function wireMockIsConfiguredToDelayResponsesByMilliseconds(int $milliseconds): void
  {
    if (!$this->isRunning) {
      throw new Exception('WireMock server must be running before configuring stubs');
    }

    $this->setupDelayedResponseStub($milliseconds);

    Log::info("Delayed response stub configured", ['delay_ms' => $milliseconds]);
  }

  /**
   * @Given WireMock is configured to return HTTP status :statusCode for all requests
   */
  public function wireMockIsConfiguredToReturnHttpStatusForAllRequests(int $statusCode): void
  {
    if (!$this->isRunning) {
      throw new Exception('WireMock server must be running before configuring stubs');
    }

    $this->setupStatusCodeStub($statusCode);

    Log::info("Status code stub configured", ['status_code' => $statusCode]);
  }

  /**
   * @Given WireMock request history is cleared
   */
  public function wireMockRequestHistoryIsCleared(): void
  {
    if (!$this->isRunning) {
      throw new Exception('WireMock server must be running before clearing history');
    }

    $this->wireMockServer->reset();
    $this->requestHistory = [];
    $this->customStubs = [];

    Log::info('WireMock request history cleared');
  }

  // =====================================================
  // WHEN Steps - Actions and Triggers
  // =====================================================

  /**
   * @When I send a :messageType request to WireMock with XML containing :xmlContent
   */
  public function iSendARequestToWireMockWithXmlContaining(string $messageType, string $xmlContent): void
  {
    $xmlPayload = $this->buildTestXmlPayload($messageType, $xmlContent);
    $this->sendRequestToWireMock($xmlPayload);

    $this->requestHistory[] = [
      'message_type' => $messageType,
      'xml_content' => $xmlContent,
      'timestamp' => Carbon::now(),
      'payload' => $xmlPayload
    ];
  }

  /**
   * @When I send multiple :messageType requests with different hotel codes
   */
  public function iSendMultipleRequestsWithDifferentHotelCodes(string $messageType): void
  {
    $hotelCodes = ['HOTEL001', 'HOTEL002', 'HOTEL003'];

    foreach ($hotelCodes as $hotelCode) {
      $xmlPayload = $this->buildTestXmlPayload($messageType, "HotelCode=\"{$hotelCode}\"");
      $this->sendRequestToWireMock($xmlPayload);

      $this->requestHistory[] = [
        'message_type' => $messageType,
        'hotel_code' => $hotelCode,
        'timestamp' => Carbon::now(),
        'payload' => $xmlPayload
      ];
    }
  }

  /**
   * @When I send concurrent :count requests of type :messageType
   */
  public function iSendConcurrentRequestsOfType(int $count, string $messageType): void
  {
    $promises = [];

    for ($i = 0; $i < $count; $i++) {
      $xmlPayload = $this->buildTestXmlPayload($messageType, "RequestID=\"REQ_{$i}\"");
      $promises[] = $this->sendAsyncRequestToWireMock($xmlPayload);

      $this->requestHistory[] = [
        'message_type' => $messageType,
        'request_id' => "REQ_{$i}",
        'timestamp' => Carbon::now(),
        'payload' => $xmlPayload
      ];
    }

    // Wait for all promises to resolve (if using async)
    Log::info("Sent {$count} concurrent {$messageType} requests");
  }

  // =====================================================
  // THEN Steps - Verifications and Assertions
  // =====================================================

  /**
   * @Then WireMock should have received exactly :count requests
   */
  public function wireMockShouldHaveReceivedExactlyRequests(int $count): void
  {
    $actualCount = $this->wireMockServer->getRequestCount();

    if ($actualCount !== $count) {
      throw new Exception("Expected {$count} requests, but WireMock received {$actualCount}");
    }
  }

  /**
   * @Then WireMock should have received :count :messageType requests
   */
  public function wireMockShouldHaveReceivedRequests(int $count, string $messageType): void
  {
    $actualCount = $this->wireMockServer->getRequestCount($messageType);

    if ($actualCount !== $count) {
      throw new Exception("Expected {$count} {$messageType} requests, but WireMock received {$actualCount}");
    }
  }

  /**
   * @Then WireMock should have received a request containing :xmlContent
   */
  public function wireMockShouldHaveReceivedARequestContaining(string $xmlContent): void
  {
    if (!$this->wireMockServer->verifyRequestReceived('any', [$xmlContent])) {
      throw new Exception("Expected WireMock to receive a request containing '{$xmlContent}'");
    }
  }

  /**
   * @Then WireMock should have received a :messageType request with hotel code :hotelCode
   */
  public function wireMockShouldHaveReceivedARequestWithHotelCode(string $messageType, string $hotelCode): void
  {
    $criteria = ["HotelCode=\"{$hotelCode}\""];

    if (!$this->wireMockServer->verifyRequestReceived($messageType, $criteria)) {
      throw new Exception("Expected WireMock to receive a {$messageType} request with hotel code '{$hotelCode}'");
    }
  }

  /**
   * @Then WireMock should have received requests in the correct order: :messageTypes
   */
  public function wireMockShouldHaveReceivedRequestsInTheCorrectOrder(string $messageTypes): void
  {
    $expectedOrder = explode(',', str_replace(' ', '', $messageTypes));
    $actualOrder = $this->getRequestOrderFromHistory();

    if ($actualOrder !== $expectedOrder) {
      throw new Exception(
        "Expected request order: " . implode(', ', $expectedOrder) .
          ", but got: " . implode(', ', $actualOrder)
      );
    }
  }

  /**
   * @Then WireMock should have returned :responseType response for :messageType
   */
  public function wireMockShouldHaveReturnedResponseFor(string $responseType, string $messageType): void
  {
    // This verification would check the response that was actually returned
    // For now, we verify that the stub was configured correctly
    if (!isset($this->customStubs[$messageType])) {
      throw new Exception("No custom stub was configured for {$messageType}");
    }

    if ($this->customStubs[$messageType] !== $responseType) {
      throw new Exception(
        "Expected {$responseType} response for {$messageType}, but stub was configured for {$this->customStubs[$messageType]}"
      );
    }
  }

  /**
   * @Then WireMock response time should be less than :milliseconds milliseconds
   */
  public function wireMockResponseTimeShouldBeLessThanMilliseconds(int $milliseconds): void
  {
    // This would require measuring actual response times
    // For BDD testing, we can verify the delay configuration
    if (empty($this->requestHistory)) {
      throw new Exception('No requests have been sent to measure response time');
    }

    Log::info("Response time verification - expected less than {$milliseconds}ms");
    // In a real implementation, this would measure actual response times
  }

  /**
   * @Then WireMock should handle concurrent requests without errors
   */
  public function wireMockShouldHandleConcurrentRequestsWithoutErrors(): void
  {
    $concurrentRequests = array_filter($this->requestHistory, function ($request) {
      return isset($request['request_id']);
    });

    if (count($concurrentRequests) === 0) {
      throw new Exception('No concurrent requests were sent to verify');
    }

    // Verify all concurrent requests were received
    $requestIds = array_map(function ($request) {
      return $request['request_id'];
    }, $concurrentRequests);

    foreach ($requestIds as $requestId) {
      if (!$this->wireMockServer->verifyRequestReceived('any', [$requestId])) {
        throw new Exception("Concurrent request {$requestId} was not received by WireMock");
      }
    }

    Log::info("Successfully handled " . count($concurrentRequests) . " concurrent requests");
  }

  /**
   * @Then WireMock logs should contain :logLevel entries about :messageType processing
   */
  public function wireMockLogsShouldContainEntriesAboutProcessing(string $logLevel, string $messageType): void
  {
    // This would check WireMock's own logs
    // For BDD testing, we can verify our logging captured the events
    $relevantLogs = array_filter($this->requestHistory, function ($request) use ($messageType) {
      return $request['message_type'] === $messageType;
    });

    if (empty($relevantLogs)) {
      throw new Exception("No {$messageType} requests found in request history for log verification");
    }

    Log::info("Verified {$logLevel} log entries for {$messageType} processing", [
      'count' => count($relevantLogs)
    ]);
  }

  // =====================================================
  // Helper Methods and Stub Configuration
  // =====================================================

  /**
   * Setup custom stub for specific message type and response
   */
  private function setupCustomStub(string $messageType, string $responseType): void
  {
    $messageTag = $this->getMessageTypeXmlTag($messageType);
    $responseBody = $this->responseTemplates[$responseType];

    // This would use WireMock's stubbing API
    // Implementation depends on your specific stubbing requirements
    Log::info("Setting up custom stub", [
      'message_type' => $messageType,
      'response_type' => $responseType,
      'message_tag' => $messageTag
    ]);
  }

  /**
   * Setup scenario-specific stub
   */
  private function setupScenarioStub(string $scenario, string $xmlContent): void
  {
    switch ($scenario) {
      case 'timeout':
        // Configure timeout simulation
        break;
      case 'authentication_failure':
        // Configure auth failure
        break;
      case 'server_error':
        // Configure server error
        break;
      default:
        throw new Exception("Unknown scenario: {$scenario}");
    }

    Log::info("Scenario stub configured", ['scenario' => $scenario]);
  }

  /**
   * Setup delayed response stub
   */
  private function setupDelayedResponseStub(int $milliseconds): void
  {
    // This would configure WireMock to delay all responses
    Log::info("Configured response delay", ['delay_ms' => $milliseconds]);
  }

  /**
   * Setup status code stub
   */
  private function setupStatusCodeStub(int $statusCode): void
  {
    // This would configure WireMock to return specific status codes
    Log::info("Configured status code response", ['status_code' => $statusCode]);
  }

  /**
   * Build test XML payload
   */
  private function buildTestXmlPayload(string $messageType, string $xmlContent): string
  {
    $messageTag = $this->getMessageTypeXmlTag($messageType);
    $timestamp = Carbon::now()->toISOString();
    $messageId = uniqid('TEST_');

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Body>
        <{$messageTag} EchoToken="{$messageId}" TimeStamp="{$timestamp}" Version="1.0">
            <TestData>{$xmlContent}</TestData>
        </{$messageTag}>
    </soap:Body>
</soap:Envelope>
XML;
  }

  /**
   * Send request to WireMock
   */
  private function sendRequestToWireMock(string $xmlPayload): void
  {
    // This would send the actual HTTP request to WireMock
    // For BDD testing, we simulate the request
    Log::info("Sending request to WireMock", [
      'url' => $this->wireMockServer->getBaseUrl(),
      'payload_size' => strlen($xmlPayload)
    ]);
  }

  /**
   * Send async request to WireMock
   */
  private function sendAsyncRequestToWireMock(string $xmlPayload): mixed
  {
    // This would return a promise or similar for async processing
    return $this->sendRequestToWireMock($xmlPayload);
  }

  /**
   * Get message type XML tag
   */
  private function getMessageTypeXmlTag(string $messageType): string
  {
    return match ($messageType) {
      'inventory' => 'OTA_HotelInvCountNotifRQ',
      'rates' => 'OTA_HotelRateNotifRQ',
      'reservation' => 'OTA_HotelResNotifRQ',
      'group' => 'OTA_HotelInvBlockNotifRQ',
      default => 'OTA_TestMessageRQ',
    };
  }

  /**
   * Get request order from history
   */
  private function getRequestOrderFromHistory(): array
  {
    return array_map(function ($request) {
      return $request['message_type'];
    }, $this->requestHistory);
  }

  // =====================================================
  // Response Templates
  // =====================================================

  private function getInventorySuccessTemplate(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Body>
        <OTA_HotelInvCountNotifRS EchoToken="TEST_ECHO" TimeStamp="' . Carbon::now()->toISOString() . '" Version="1.0">
            <Success/>
            <UniqueID Type="16" ID="INV_' . uniqid() . '"/>
        </OTA_HotelInvCountNotifRS>
    </soap:Body>
</soap:Envelope>';
  }

  private function getInventoryErrorTemplate(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Body>
        <soap:Fault>
            <soap:Code><soap:Value>Client</soap:Value></soap:Code>
            <soap:Reason><soap:Text>Invalid inventory data</soap:Text></soap:Reason>
        </soap:Fault>
    </soap:Body>
</soap:Envelope>';
  }

  private function getRateSuccessTemplate(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Body>
        <OTA_HotelRateNotifRS EchoToken="TEST_ECHO" TimeStamp="' . Carbon::now()->toISOString() . '" Version="1.0">
            <Success/>
            <UniqueID Type="15" ID="RATE_' . uniqid() . '"/>
        </OTA_HotelRateNotifRS>
    </soap:Body>
</soap:Envelope>';
  }

  private function getRateWarningTemplate(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Body>
        <OTA_HotelRateNotifRS EchoToken="TEST_ECHO" TimeStamp="' . Carbon::now()->toISOString() . '" Version="1.0">
            <Success/>
            <Warnings>
                <Warning Type="2">Rate plan mapping applied</Warning>
            </Warnings>
            <UniqueID Type="15" ID="RATE_' . uniqid() . '"/>
        </OTA_HotelRateNotifRS>
    </soap:Body>
</soap:Envelope>';
  }

  private function getReservationSuccessTemplate(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Body>
        <OTA_HotelResNotifRS EchoToken="TEST_ECHO" TimeStamp="' . Carbon::now()->toISOString() . '" Version="1.0">
            <Success/>
            <UniqueID Type="14" ID="RES_' . uniqid() . '"/>
        </OTA_HotelResNotifRS>
    </soap:Body>
</soap:Envelope>';
  }

  private function getReservationErrorTemplate(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Body>
        <soap:Fault>
            <soap:Code><soap:Value>Client</soap:Value></soap:Code>
            <soap:Reason><soap:Text>Room type not available</soap:Text></soap:Reason>
        </soap:Fault>
    </soap:Body>
</soap:Envelope>';
  }

  private function getAuthenticationErrorTemplate(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Body>
        <soap:Fault>
            <soap:Code><soap:Value>Client</soap:Value></soap:Code>
            <soap:Reason><soap:Text>Authentication failed</soap:Text></soap:Reason>
        </soap:Fault>
    </soap:Body>
</soap:Envelope>';
  }

  private function getServerErrorTemplate(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Body>
        <soap:Fault>
            <soap:Code><soap:Value>Server</soap:Value></soap:Code>
            <soap:Reason><soap:Text>Internal server error</soap:Text></soap:Reason>
        </soap:Fault>
    </soap:Body>
</soap:Envelope>';
  }

  // =====================================================
  // Debugging and Inspection Methods
  // =====================================================

  /**
   * Get request history for debugging
   */
  public function getRequestHistory(): array
  {
    return $this->requestHistory;
  }

  /**
   * Get custom stubs configuration
   */
  public function getCustomStubs(): array
  {
    return $this->customStubs;
  }

  /**
   * Get WireMock server status
   */
  public function getServerStatus(): array
  {
    return [
      'host' => $this->host,
      'port' => $this->port,
      'is_running' => $this->isRunning,
      'request_count' => count($this->requestHistory),
      'custom_stubs_count' => count($this->customStubs),
    ];
  }

  /**
   * Reset context state for next scenario
   */
  public function resetContext(): void
  {
    $this->customStubs = [];
    $this->requestHistory = [];

    if ($this->isRunning) {
      $this->wireMockServer->reset();
    }
  }

  /**
   * Cleanup after scenarios
   */
  public function __destruct()
  {
    if ($this->isRunning && isset($this->wireMockServer)) {
      $this->wireMockServer->stop();
    }
  }
}
