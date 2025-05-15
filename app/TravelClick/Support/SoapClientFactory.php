<?php

namespace App\TravelClick\Support;

use SoapClient;
use SoapHeader;

/**
 * Factory for creating SOAP clients configured for TravelClick
 *
 * This factory encapsulates all the complexity of creating properly configured
 * SoapClient instances with the right settings for TravelClick integration.
 */
class SoapClientFactory
{
    public function __construct(
        private readonly string $wsdl,
        private readonly string $username,
        private readonly string $password,
        private readonly array $options = []
    ) {}

    /**
     * Create a new SOAP client instance
     */
    public function create(): SoapClient
    {
        $defaultOptions = $this->getDefaultOptions();
        $mergedOptions = array_merge($defaultOptions, $this->options);

        try {
            $client = new SoapClient($this->wsdl, $mergedOptions);
            $this->addRequiredHeaders($client);
            return $client;
        } catch (\Exception $e) {
            throw new \App\TravelClick\Exceptions\SoapException(
                "Failed to create SOAP client: {$e->getMessage()}",
                previous: $e
            );
        }
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
     * Add required SOAP headers for TravelClick authentication
     */
    private function addRequiredHeaders(SoapClient $client): void
    {
        // WS-Security header for authentication
        $securityHeader = new SoapHeader(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
            'Security',
            $this->createSecurityHeaderData(),
            false
        );

        $client->__setSoapHeaders($securityHeader);
    }

    /**
     * Create WS-Security header data structure
     */
    private function createSecurityHeaderData(): array
    {
        return [
            'UsernameToken' => [
                'Username' => $this->username,
                'Password' => $this->password,
            ]
        ];
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

        return true;
    }
}
