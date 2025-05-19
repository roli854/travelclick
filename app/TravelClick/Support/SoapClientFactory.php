<?php

namespace App\TravelClick\Support;

use App\TravelClick\Support\SoapHeaders;
use App\TravelClick\Exceptions\SoapException;
use SoapClient;
use SoapHeader;
use Illuminate\Support\Facades\Log;

/**
 * Factory for creating SOAP clients configured for TravelClick
 *
 * OPTIMIZED: Now integrates with SoapHeaders class for proper WSSE authentication
 * following TravelClick HTNG 2011B specifications.
 */
class SoapClientFactory
{
    public function __construct(
        private readonly string $wsdl,
        private readonly string $username,
        private readonly string $password,
        private readonly string $hotelCode,
        private readonly array $options = []
    ) {}

    /**
     * Create a new SOAP client instance with proper headers
     */
    public function create(): SoapClient
    {
        $this->validateConfiguration();

        $defaultOptions = $this->getDefaultOptions();
        $mergedOptions = array_merge($defaultOptions, $this->options);

        try {
            $client = new SoapClient($this->wsdl, $mergedOptions);

            // NO longer adding headers here - will be handled per request
            // This allows for dynamic messageId per operation

            Log::info('SOAP client created successfully', [
                'wsdl' => $this->wsdl,
                'hotel_code' => $this->hotelCode
            ]);

            return $client;
        } catch (\Exception $e) {
            throw new SoapException(
                "Failed to create SOAP client: {$e->getMessage()}",
                '',
                previous: $e
            );
        }
    }

    /**
     * Create client and automatically inject headers for a specific operation
     */
    public function createWithHeaders(string $messageId, string $action = 'HTNG2011B_SubmitRequest'): SoapClient
    {
        $client = $this->create();
        $this->injectHeaders($client, $messageId, $action);
        return $client;
    }

    /**
     * Inject TravelClick headers using SoapHeaders class
     */
    public function injectHeaders(
        SoapClient $client,
        string $messageId,
        string $action = 'HTNG2011B_SubmitRequest'
    ): void {
        try {
            // Use optimized SoapHeaders class to generate complete headers
            $headersXml = $this->generateHeaders($messageId, $action);

            // Parse XML headers into SoapHeader objects and inject
            $this->injectXmlHeaders($client, $headersXml);

            Log::debug('SOAP headers injected', [
                'message_id' => $messageId,
                'action' => $action,
                'hotel_code' => $this->hotelCode
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to inject SOAP headers', [
                'error' => $e->getMessage(),
                'message_id' => $messageId
            ]);
            throw $e;
        }
    }

    /**
     * Generate headers using SoapHeaders class with current configuration
     */
    private function generateHeaders(string $messageId, string $action): string
    {
        $config = $this->buildConfigForHeaders();
        return SoapHeaders::fromConfig($config, $messageId, $action);
    }

    /**
     * Build configuration array for SoapHeaders from factory params
     */
    private function buildConfigForHeaders(): array
    {
        return [
            'credentials' => [
                'username' => $this->username,
                'password' => $this->password,
                'hotel_code' => $this->hotelCode,
            ],
            'endpoints' => [
                'production' => $this->extractEndpointFromWsdl(),
                'test' => $this->extractEndpointFromWsdl(),
            ]
        ];
    }

    /**
     * Extract endpoint URL from WSDL URL
     */
    private function extractEndpointFromWsdl(): string
    {
        // Remove ?wsdl parameter to get service endpoint
        return preg_replace('/\?wsdl$/i', '', $this->wsdl);
    }

    /**
     * Convert XML headers to SoapHeader objects and inject into client
     */
    private function injectXmlHeaders(SoapClient $client, string $headersXml): void
    {
        // For now, we'll use a simple approach since TravelClick expects
        // headers in the SOAP envelope, not as SoapHeader objects

        // Store headers for later use in envelope building
        $client-> __headers = $headersXml;
    }

    /**
     * Get default SOAP client options optimized for TravelClick
     */
    private function getDefaultOptions(): array
    {
        return [
            'soap_version' => SOAP_1_2,
            'trace' => true,                // Enable tracing for debugging
            'exceptions' => true,           // Throw exceptions on SOAP faults
            'cache_wsdl' => WSDL_CACHE_BOTH, // Cache WSDL for performance
            'connection_timeout' => 30,     // 30 seconds connection timeout
            'user_agent' => 'Centrium-TravelClick-Integration/1.0',
            'keep_alive' => true,          // Reuse connections
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
            'encoding' => 'UTF-8',
            'stream_context' => $this->createStreamContext(),
        ];
    }

    /**
     * Create stream context with SSL and timeout settings
     */
    private function createStreamContext()
    {
        return stream_context_create([
            'http' => [
                'timeout' => 60,           // 60 seconds total timeout
                'user_agent' => 'Centrium-TravelClick-Integration/1.0',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'cafile' => null,          // Use system CA bundle
            ]
        ]);
    }

    /**
     * Create a client with custom options
     */
    public function createWithOptions(array $customOptions): SoapClient
    {
        return (new self(
            $this->wsdl,
            $this->username,
            $this->password,
            $this->hotelCode,
            array_merge($this->options, $customOptions)
        ))->create();
    }

    /**
     * Create a client optimized for testing
     */
    public function createForTesting(): SoapClient
    {
        $testOptions = [
            'cache_wsdl' => WSDL_CACHE_NONE,  // Disable caching for tests
            'connection_timeout' => 5,        // Faster timeout for tests
            'exceptions' => true,
            'trace' => true,
        ];

        return $this->createWithOptions($testOptions);
    }

    /**
     * Validate configuration before creating client
     */
    public function validateConfiguration(): bool
    {
        if (empty($this->wsdl)) {
            throw new \InvalidArgumentException('WSDL URL is required');
        }

        if (empty($this->username) || empty($this->password)) {
            throw new \InvalidArgumentException('Username and password are required');
        }

        if (empty($this->hotelCode)) {
            throw new \InvalidArgumentException('Hotel code is required');
        }

        return true;
    }

    /**
     * Create factory from Laravel configuration
     */
    public static function fromConfig(?array $config = null): self
    {
        $config = $config ?? config('travelclick');

        return new self(
            wsdl: $config['endpoints']['wsdl'],
            username: $config['credentials']['username'],
            password: $config['credentials']['password'],
            hotelCode: $config['credentials']['hotel_code'],
            options: $config['soap'] ?? []
        );
    }

    /**
     * Test connection to TravelClick
     */
    public function testConnection(): bool
    {
        try {
            $client = $this->create();
            $client->__getFunctions();
            return true;
        } catch (\Exception $e) {
            Log::warning('TravelClick connection test failed', [
                'error' => $e->getMessage(),
                'wsdl' => $this->wsdl
            ]);
            return false;
        }
    }

    /**
     * Get configuration summary for debugging
     */
    public function getConfigSummary(): array
    {
        return [
            'wsdl' => $this->wsdl,
            'hotel_code' => $this->hotelCode,
            'username' => substr($this->username, 0, 3) . '***',
            'options_count' => count($this->options),
        ];
    }
}
