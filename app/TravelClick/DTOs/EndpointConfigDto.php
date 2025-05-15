<?php

namespace App\TravelClick\DTOs;

use App\TravelClick\Enums\Environment;

/**
 * Endpoint Configuration DTO
 *
 * This DTO encapsulates TravelClick endpoint configuration data.
 * It provides structured access to SOAP endpoint settings, URLs, and connection parameters.
 */
class EndpointConfigDto
{
    public function __construct(
        public readonly Environment $environment,
        public readonly string $url,
        public readonly string $wsdlUrl,
        public readonly int $connectionTimeout,
        public readonly int $requestTimeout,
        public readonly bool $sslVerifyPeer,
        public readonly bool $sslVerifyHost,
        public readonly ?string $sslCaFile = null,
        public readonly array $soapOptions = [],
        public readonly array $httpHeaders = [],
        public readonly ?string $userAgent = null,
        public readonly bool $compression = false,
        public readonly string $encoding = 'UTF-8',
        public readonly int $maxRedirects = 0,
        public readonly bool $keepAlive = true,
        public readonly array $streamContext = []
    ) {}

    /**
     * Create from environment
     */
    public static function fromEnvironment(Environment $environment): self
    {
        $timeouts = $environment->timeouts();

        return new self(
            environment: $environment,
            url: $environment->endpoint(),
            wsdlUrl: $environment->wsdlUrl(),
            connectionTimeout: $timeouts['connection_timeout'],
            requestTimeout: $timeouts['request_timeout'],
            sslVerifyPeer: $environment->isProduction(),
            sslVerifyHost: $environment->isProduction(),
            sslCaFile: $environment->isProduction() ? null : null,
            soapOptions: self::getDefaultSoapOptions($environment),
            httpHeaders: self::getDefaultHttpHeaders(),
            userAgent: 'TravelClick-Laravel-Integration/1.0',
            compression: $environment->isProduction(),
            encoding: 'UTF-8',
            maxRedirects: 0,
            keepAlive: true,
            streamContext: []
        );
    }

    /**
     * Create from array data
     */
    public static function fromArray(array $data): self
    {
        $environment = Environment::from($data['environment']);

        return new self(
            environment: $environment,
            url: $data['url'] ?? $environment->endpoint(),
            wsdlUrl: $data['wsdl_url'] ?? $environment->wsdlUrl(),
            connectionTimeout: $data['connection_timeout'] ?? 30,
            requestTimeout: $data['request_timeout'] ?? 60,
            sslVerifyPeer: $data['ssl_verify_peer'] ?? true,
            sslVerifyHost: $data['ssl_verify_host'] ?? true,
            sslCaFile: $data['ssl_ca_file'] ?? null,
            soapOptions: $data['soap_options'] ?? [],
            httpHeaders: $data['http_headers'] ?? [],
            userAgent: $data['user_agent'] ?? 'TravelClick-Laravel-Integration/1.0',
            compression: $data['compression'] ?? false,
            encoding: $data['encoding'] ?? 'UTF-8',
            maxRedirects: $data['max_redirects'] ?? 0,
            keepAlive: $data['keep_alive'] ?? true,
            streamContext: $data['stream_context'] ?? []
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'environment' => $this->environment->value,
            'url' => $this->url,
            'wsdl_url' => $this->wsdlUrl,
            'connection_timeout' => $this->connectionTimeout,
            'request_timeout' => $this->requestTimeout,
            'ssl_verify_peer' => $this->sslVerifyPeer,
            'ssl_verify_host' => $this->sslVerifyHost,
            'ssl_ca_file' => $this->sslCaFile,
            'soap_options' => $this->soapOptions,
            'http_headers' => $this->httpHeaders,
            'user_agent' => $this->userAgent,
            'compression' => $this->compression,
            'encoding' => $this->encoding,
            'max_redirects' => $this->maxRedirects,
            'keep_alive' => $this->keepAlive,
            'stream_context' => $this->streamContext
        ];
    }

    /**
     * Get SOAP client options
     */
    public function getSoapClientOptions(): array
    {
        $options = [
            'location' => $this->url,
            'uri' => $this->url,
            'soap_version' => SOAP_1_1,
            'encoding' => $this->encoding,
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => $this->environment->isProduction() ? WSDL_CACHE_BOTH : WSDL_CACHE_NONE,
            'connection_timeout' => $this->connectionTimeout,
            'user_agent' => $this->userAgent,
            'keep_alive' => $this->keepAlive,
            'compression' => $this->compression ? SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP : 0,
        ];

        // Add SSL context
        if (!empty($this->getStreamContext())) {
            $options['stream_context'] = stream_context_create($this->getStreamContext());
        }

        // Merge custom SOAP options
        return array_merge($options, $this->soapOptions);
    }

    /**
     * Get stream context for SOAP client
     */
    public function getStreamContext(): array
    {
        $context = [
            'http' => [
                'timeout' => $this->requestTimeout,
                'user_agent' => $this->userAgent,
                'max_redirects' => $this->maxRedirects,
                'header' => $this->buildHeaderString(),
                'protocol_version' => '1.1',
                'ignore_errors' => false,
            ],
            'ssl' => [
                'verify_peer' => $this->sslVerifyPeer,
                'verify_peer_name' => $this->sslVerifyHost,
                'allow_self_signed' => !$this->environment->isProduction(),
                'verify_depth' => 5,
            ]
        ];

        if ($this->sslCaFile) {
            $context['ssl']['cafile'] = $this->sslCaFile;
        }

        // Add custom stream context
        return array_merge_recursive($context, $this->streamContext);
    }

    /**
     * Build HTTP header string
     */
    protected function buildHeaderString(): string
    {
        $headers = [];

        foreach ($this->httpHeaders as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        return implode("\r\n", $headers);
    }

    /**
     * Get default SOAP options for environment
     */
    protected static function getDefaultSoapOptions(Environment $environment): array
    {
        return [
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
            'style' => SOAP_DOCUMENT,
            'use' => SOAP_LITERAL,
        ];
    }

    /**
     * Get default HTTP headers
     */
    protected static function getDefaultHttpHeaders(): array
    {
        return [
            'Accept' => 'text/xml,application/xml',
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => '""',
        ];
    }

    /**
     * Validate endpoint configuration
     */
    public function validate(): array
    {
        $errors = [];

        if (!filter_var($this->url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid endpoint URL';
        }

        if (!filter_var($this->wsdlUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid WSDL URL';
        }

        if ($this->connectionTimeout < 1 || $this->connectionTimeout > 300) {
            $errors[] = 'Connection timeout must be between 1 and 300 seconds';
        }

        if ($this->requestTimeout < 1 || $this->requestTimeout > 600) {
            $errors[] = 'Request timeout must be between 1 and 600 seconds';
        }

        if ($this->maxRedirects < 0 || $this->maxRedirects > 10) {
            $errors[] = 'Max redirects must be between 0 and 10';
        }

        return $errors;
    }

    /**
     * Test connection to endpoint
     */
    public function testConnection(): bool
    {
        try {
            $context = stream_context_create($this->getStreamContext());
            $handle = fopen($this->url, 'r', false, $context);

            if ($handle) {
                fclose($handle);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a copy with updated values
     */
    public function with(array $updates): self
    {
        $current = $this->toArray();
        $merged = array_merge($current, $updates);
        return self::fromArray($merged);
    }

    /**
     * Get cache key for this endpoint configuration
     */
    public function getCacheKey(): string
    {
        return sprintf(
            '%s:%s',
            \App\TravelClick\Enums\ConfigScope::ENDPOINT->cacheKeyPrefix(),
            $this->environment->value
        );
    }

    /**
     * Check if configuration is for development/testing
     */
    public function isDevelopment(): bool
    {
        return $this->environment->isTest();
    }

    /**
     * Check if configuration is for production
     */
    public function isProduction(): bool
    {
        return $this->environment->isProduction();
    }

    /**
     * Get environment-specific optimizations
     */
    public function getOptimizations(): array
    {
        return [
            'enable_wsdl_cache' => $this->environment->isProduction(),
            'enable_compression' => $this->compression,
            'enable_keep_alive' => $this->keepAlive,
            'strict_ssl' => $this->environment->isProduction(),
            'debug_mode' => $this->environment->isTest(),
        ];
    }
}
