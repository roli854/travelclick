<?php

namespace Tests\Behat\Support;

use WireMock\Client\WireMock;
use Illuminate\Support\Facades\Log;
use Exception;

class TravelClickWireMockServer
{
  private WireMock $wireMock;
  private string $host;
  private int $port;
  private array $stubMappings = [];
  private bool $isRunning = false;

  public function __construct(string $host = 'localhost', int $port = 8080)
  {
    $this->host = $host;
    $this->port = $port;
    $this->wireMock = WireMock::create($this->host, $this->port);
  }

  public function start(): bool
  {
    try {
      // Test if WireMock is running by trying to reset it
      $this->wireMock->reset();
      $this->isRunning = true;
      Log::info('WireMock server is running', [
        'host' => $this->host,
        'port' => $this->port
      ]);
      return true;
    } catch (Exception $e) {
      Log::error('WireMock server is not running', [
        'host' => $this->host,
        'port' => $this->port,
        'error' => $e->getMessage()
      ]);
      $this->isRunning = false;
      return false;
    }
  }

  public function reset(): void
  {
    if ($this->isRunning) {
      $this->wireMock->reset();
      $this->stubMappings = [];
    }
  }

  public function stop(): void
  {
    if ($this->isRunning) {
      $this->wireMock->shutdownServer();
      $this->stubMappings = [];
    }
  }

  public function setupTravelClickEndpoint(): void
  {
    $this->setupInventoryStubs();
    $this->setupRateStubs();
    $this->setupReservationStubs();
    $this->setupGroupBlockStubs();
    $this->setupAuthenticationStubs();
    $this->setupErrorStubs();
  }

  protected function setupInventoryStubs(): void
  {
    // Successful inventory update
    $this->wireMock->stubFor(
      $this->wireMock->post($this->wireMock->urlPathEqualTo('/HTNGService/services/HTNG2011BService'))
        ->withHeader('Content-Type', $this->wireMock->containing('text/xml'))
        ->withRequestBody($this->wireMock->containing('OTA_HotelInvCountNotifRQ'))
        ->willReturn($this->wireMock->aResponse()
          ->withStatus(200)
          ->withHeader('Content-Type', 'text/xml; charset=utf-8')
          ->withBody($this->getInventorySuccessResponse()))
    );

    // Inventory validation error
    $this->wireMock->stubFor(
      $this->wireMock->post($this->wireMock->urlPathEqualTo('/HTNGService/services/HTNG2011BService'))
        ->withHeader('Content-Type', $this->wireMock->containing('text/xml'))
        ->withRequestBody($this->wireMock->containing('OTA_HotelInvCountNotifRQ'))
        ->withRequestBody($this->wireMock->containing('CountType="999"')) // Invalid count type for testing
        ->willReturn($this->wireMock->aResponse()
          ->withStatus(500)
          ->withHeader('Content-Type', 'text/xml; charset=utf-8')
          ->withBody($this->getInventoryValidationErrorResponse()))
    );
  }

  protected function setupRateStubs(): void
  {
    // Successful rate update
    $this->wireMock->stubFor(
      $this->wireMock->post($this->wireMock->urlPathEqualTo('/HTNGService/services/HTNG2011BService'))
        ->withHeader('Content-Type', $this->wireMock->containing('text/xml'))
        ->withRequestBody($this->wireMock->containing('OTA_HotelRateNotifRQ'))
        ->willReturn($this->wireMock->aResponse()
          ->withStatus(200)
          ->withHeader('Content-Type', 'text/xml; charset=utf-8')
          ->withBody($this->getRateSuccessResponse()))
    );

    // Rate with warnings
    $this->wireMock->stubFor(
      $this->wireMock->post($this->wireMock->urlPathEqualTo('/HTNGService/services/HTNG2011BService'))
        ->withHeader('Content-Type', $this->wireMock->containing('text/xml'))
        ->withRequestBody($this->wireMock->containing('OTA_HotelRateNotifRQ'))
        ->withRequestBody($this->wireMock->containing('RatePlanCode="INVALID"'))
        ->willReturn($this->wireMock->aResponse()
          ->withStatus(200)
          ->withHeader('Content-Type', 'text/xml; charset=utf-8')
          ->withBody($this->getRateWarningResponse()))
    );
  }

  protected function setupReservationStubs(): void
  {
    // Successful reservation
    $this->wireMock->stubFor(
      $this->wireMock->post($this->wireMock->urlPathEqualTo('/HTNGService/services/HTNG2011BService'))
        ->withHeader('Content-Type', $this->wireMock->containing('text/xml'))
        ->withRequestBody($this->wireMock->containing('OTA_HotelResNotifRQ'))
        ->willReturn($this->wireMock->aResponse()
          ->withStatus(200)
          ->withHeader('Content-Type', 'text/xml; charset=utf-8')
          ->withBody($this->getReservationSuccessResponse()))
    );

    // Reservation business rule error
    $this->wireMock->stubFor(
      $this->wireMock->post($this->wireMock->urlPathEqualTo('/HTNGService/services/HTNG2011BService'))
        ->withHeader('Content-Type', $this->wireMock->containing('text/xml'))
        ->withRequestBody($this->wireMock->containing('OTA_HotelResNotifRQ'))
        ->withRequestBody($this->wireMock->containing('RoomTypeCode="NONEXISTENT"'))
        ->willReturn($this->wireMock->aResponse()
          ->withStatus(500)
          ->withHeader('Content-Type', 'text/xml; charset=utf-8')
          ->withBody($this->getReservationBusinessErrorResponse()))
    );
  }

  protected function setupGroupBlockStubs(): void
  {
    // Successful group block creation
    $this->wireMock->stubFor(
      $this->wireMock->post($this->wireMock->urlPathEqualTo('/HTNGService/services/HTNG2011BService'))
        ->withHeader('Content-Type', $this->wireMock->containing('text/xml'))
        ->withRequestBody($this->wireMock->containing('OTA_HotelInvBlockNotifRQ'))
        ->willReturn($this->wireMock->aResponse()
          ->withStatus(200)
          ->withHeader('Content-Type', 'text/xml; charset=utf-8')
          ->withBody($this->getGroupBlockSuccessResponse()))
    );
  }

  protected function setupAuthenticationStubs(): void
  {
    // Authentication failure
    $this->wireMock->stubFor(
      $this->wireMock->post($this->wireMock->urlPathEqualTo('/HTNGService/services/HTNG2011BService'))
        ->withHeader('Content-Type', $this->wireMock->containing('text/xml'))
        ->withRequestBody($this->wireMock->containing('<wsse:Password>INVALID</wsse:Password>'))
        ->willReturn($this->wireMock->aResponse()
          ->withStatus(401)
          ->withHeader('Content-Type', 'text/xml; charset=utf-8')
          ->withBody($this->getAuthenticationErrorResponse()))
    );
  }

  protected function setupErrorStubs(): void
  {
    // Timeout simulation
    $this->wireMock->stubFor(
      $this->wireMock->post($this->wireMock->urlPathEqualTo('/HTNGService/services/HTNG2011BService'))
        ->withHeader('Content-Type', $this->wireMock->containing('text/xml'))
        ->withRequestBody($this->wireMock->containing('TIMEOUT_TEST'))
        ->willReturn($this->wireMock->aResponse()
          ->withFixedDelay(65000) // 65 seconds - longer than typical timeout
          ->withStatus(200))
    );

    // Server error
    $this->wireMock->stubFor(
      $this->wireMock->post($this->wireMock->urlPathEqualTo('/HTNGService/services/HTNG2011BService'))
        ->withHeader('Content-Type', $this->wireMock->containing('text/xml'))
        ->withRequestBody($this->wireMock->containing('SERVER_ERROR_TEST'))
        ->willReturn($this->wireMock->aResponse()
          ->withStatus(500)
          ->withHeader('Content-Type', 'text/xml; charset=utf-8')
          ->withBody($this->getServerErrorResponse()))
    );

    // Malformed XML response
    $this->wireMock->stubFor(
      $this->wireMock->post($this->wireMock->urlPathEqualTo('/HTNGService/services/HTNG2011BService'))
        ->withHeader('Content-Type', $this->wireMock->containing('text/xml'))
        ->withRequestBody($this->wireMock->containing('MALFORMED_XML_TEST'))
        ->willReturn($this->wireMock->aResponse()
          ->withStatus(200)
          ->withHeader('Content-Type', 'text/xml; charset=utf-8')
          ->withBody('<invalid>xml without proper closing'))
    );
  }

  public function verifyRequestReceived(string $messageType, array $criteria = []): bool
  {
    try {
      $requests = $this->wireMock->getAllServeEvents()?->getRequests();

      foreach ($requests as $request) {
        $body = $request['request']['body'] ?? '';

        // Check message type
        if (!str_contains($body, $this->getMessageTypeXmlTag($messageType))) {
          continue;
        }

        // Check additional criteria
        $matchesCriteria = true;
        foreach ($criteria as $key => $value) {
          if (!str_contains($body, $value)) {
            $matchesCriteria = false;
            break;
          }
        }

        if ($matchesCriteria) {
          return true;
        }
      }

      return false;
    } catch (Exception $e) {
      Log::warning('Failed to verify request received', [
        'error' => $e->getMessage()
      ]);
      return false;
    }
  }

  public function getRequestCount(string $messageType = null): int
  {
    try {
      $requests = $this->wireMock->getAllServeEvents()?->getRequests();

      if ($messageType === null) {
        return count($requests);
      }

      $count = 0;
      $xmlTag = $this->getMessageTypeXmlTag($messageType);

      foreach ($requests as $request) {
        $body = $request['request']['body'] ?? '';
        if (str_contains($body, $xmlTag)) {
          $count++;
        }
      }

      return $count;
    } catch (Exception $e) {
      Log::warning('Failed to get request count', [
        'error' => $e->getMessage()
      ]);
      return 0;
    }
  }

  public function getLastRequest(): ?array
  {
    try {
      $requests = $this->wireMock->getAllServeEvents()?->getRequests();

      if (empty($requests)) {
        return null;
      }

      $lastRequest = end($requests);

      return [
        'url' => $lastRequest['request']['url'] ?? '',
        'method' => $lastRequest['request']['method'] ?? '',
        'headers' => $lastRequest['request']['headers'] ?? [],
        'body' => $lastRequest['request']['body'] ?? '',
      ];
    } catch (Exception $e) {
      Log::warning('Failed to get last request', [
        'error' => $e->getMessage()
      ]);
      return null;
    }
  }

  protected function getMessageTypeXmlTag(string $messageType): string
  {
    return match ($messageType) {
      'inventory' => 'OTA_HotelInvCountNotifRQ',
      'rates' => 'OTA_HotelRateNotifRQ',
      'reservation' => 'OTA_HotelResNotifRQ',
      'group' => 'OTA_HotelInvBlockNotifRQ',
      default => $messageType,
    };
  }

  protected function getInventorySuccessResponse(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Header/>
    <soap:Body>
        <OTA_HotelInvCountNotifRS EchoToken="TEST_ECHO" TimeStamp="' . now()->toISOString() . '" Version="1.0">
            <Success/>
            <UniqueID Type="16" ID="INV_' . uniqid() . '"/>
        </OTA_HotelInvCountNotifRS>
    </soap:Body>
</soap:Envelope>';
  }

  protected function getInventoryValidationErrorResponse(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Header/>
    <soap:Body>
        <soap:Fault>
            <soap:Code>
                <soap:Value>Client</soap:Value>
            </soap:Code>
            <soap:Reason>
                <soap:Text>Invalid CountType provided</soap:Text>
            </soap:Reason>
        </soap:Fault>
    </soap:Body>
</soap:Envelope>';
  }

  protected function getRateSuccessResponse(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Header/>
    <soap:Body>
        <OTA_HotelRateNotifRS EchoToken="TEST_ECHO" TimeStamp="' . now()->toISOString() . '" Version="1.0">
            <Success/>
            <UniqueID Type="15" ID="RATE_' . uniqid() . '"/>
        </OTA_HotelRateNotifRS>
    </soap:Body>
</soap:Envelope>';
  }

  protected function getRateWarningResponse(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Header/>
    <soap:Body>
        <OTA_HotelRateNotifRS EchoToken="TEST_ECHO" TimeStamp="' . now()->toISOString() . '" Version="1.0">
            <Success/>
            <Warnings>
                <Warning Type="2">Rate plan code not found, using default mapping</Warning>
            </Warnings>
            <UniqueID Type="15" ID="RATE_' . uniqid() . '"/>
        </OTA_HotelRateNotifRS>
    </soap:Body>
</soap:Envelope>';
  }

  protected function getReservationSuccessResponse(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Header/>
    <soap:Body>
        <OTA_HotelResNotifRS EchoToken="TEST_ECHO" TimeStamp="' . now()->toISOString() . '" Version="1.0">
            <Success/>
            <HotelReservations>
                <HotelReservation>
                    <UniqueID Type="14" ID="RES_' . uniqid() . '"/>
                    <ResGuests>
                        <ResGuest>
                            <Profiles>
                                <ProfileInfo>
                                    <UniqueID Type="10" ID="TC' . rand(10000000, 99999999) . '"/>
                                </ProfileInfo>
                            </Profiles>
                        </ResGuest>
                    </ResGuests>
                </HotelReservation>
            </HotelReservations>
        </OTA_HotelResNotifRS>
    </soap:Body>
</soap:Envelope>';
  }

  protected function getReservationBusinessErrorResponse(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Header/>
    <soap:Body>
        <soap:Fault>
            <soap:Code>
                <soap:Value>Client</soap:Value>
            </soap:Code>
            <soap:Reason>
                <soap:Text>Room type not available</soap:Text>
            </soap:Reason>
        </soap:Fault>
    </soap:Body>
</soap:Envelope>';
  }

  protected function getGroupBlockSuccessResponse(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Header/>
    <soap:Body>
        <OTA_HotelInvBlockNotifRS EchoToken="TEST_ECHO" TimeStamp="' . now()->toISOString() . '" Version="1.0">
            <Success/>
            <UniqueID Type="17" ID="GRP_' . uniqid() . '"/>
        </OTA_HotelInvBlockNotifRS>
    </soap:Body>
</soap:Envelope>';
  }

  protected function getAuthenticationErrorResponse(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Header/>
    <soap:Body>
        <soap:Fault>
            <soap:Code>
                <soap:Value>Client</soap:Value>
            </soap:Code>
            <soap:Reason>
                <soap:Text>Authentication failed</soap:Text>
            </soap:Reason>
        </soap:Fault>
    </soap:Body>
</soap:Envelope>';
  }

  protected function getServerErrorResponse(): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
    <soap:Header/>
    <soap:Body>
        <soap:Fault>
            <soap:Code>
                <soap:Value>Server</soap:Value>
            </soap:Code>
            <soap:Reason>
                <soap:Text>Internal server error</soap:Text>
            </soap:Reason>
        </soap:Fault>
    </soap:Body>
</soap:Envelope>';
  }

  public function getBaseUrl(): string
  {
    return "http://{$this->host}:{$this->port}";
  }

  public function isRunning(): bool
  {
    return $this->isRunning;
  }
}
