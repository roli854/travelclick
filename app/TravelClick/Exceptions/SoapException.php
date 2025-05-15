<?php

namespace App\TravelClick\Exceptions;

use Exception;

/**
 * Base exception for all SOAP-related errors in TravelClick integration
 *
 * This exception provides a base for all SOAP communication errors,
 * with additional context for debugging and monitoring.
 */
class SoapException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $messageId = null,
        public readonly ?string $soapFaultCode = null,
        public readonly ?string $soapFaultString = null,
        public readonly ?array $context = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception from SoapFault
     */
    public static function fromSoapFault(
        \SoapFault $fault,
        ?string $messageId = null,
        ?array $context = null
    ): self {
        return new self(
            message: "SOAP Fault: {$fault->getMessage()}",
            messageId: $messageId,
            soapFaultCode: $fault->faultcode ?? null,
            soapFaultString: $fault->faultstring ?? $fault->getMessage(),
            context: $context,
            code: $fault->getCode(),
            previous: $fault
        );
    }

    /**
     * Get exception context for logging
     */
    public function getContext(): array
    {
        return [
            'message_id' => $this->messageId,
            'soap_fault_code' => $this->soapFaultCode,
            'soap_fault_string' => $this->soapFaultString,
            'additional_context' => $this->context,
            'exception_class' => static::class,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }

    /**
     * Check if this is a specific type of SOAP fault
     */
    public function isFaultCode(string $code): bool
    {
        return $this->soapFaultCode === $code;
    }

    /**
     * Check if this is a connection-related error
     */
    public function isConnectionError(): bool
    {
        return str_contains($this->getMessage(), 'Connection') ||
            str_contains($this->getMessage(), 'timeout') ||
            str_contains($this->getMessage(), 'network');
    }

    /**
     * Check if this is an authentication error
     */
    public function isAuthenticationError(): bool
    {
        return str_contains($this->getMessage(), 'Authentication') ||
            str_contains($this->getMessage(), 'Unauthorized') ||
            $this->isFaultCode('AUTHENTICATION_FAILED');
    }
}
