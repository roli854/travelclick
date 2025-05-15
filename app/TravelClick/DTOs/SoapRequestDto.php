<?php

namespace App\TravelClick\DTOs;

/**
 * Data Transfer Object for SOAP Request
 *
 * This DTO encapsulates all the data needed to make a SOAP request to TravelClick.
 * It provides a structured way to pass request data between components.
 */
class SoapRequestDto
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $action,
        public readonly string $xmlBody,
        public readonly string $hotelCode,
        public readonly array $headers = [],
        public readonly ?string $echoToken = null,
        public readonly ?string $version = '1.0',
        public readonly ?string $target = 'Production'
    ) {}

    /**
     * Create a new request DTO for inventory operations
     */
    public static function forInventory(
        string $messageId,
        string $xmlBody,
        string $hotelCode,
        ?string $echoToken = null
    ): self {
        return new self(
            messageId: $messageId,
            action: 'HTNG2011B_SubmitRequest',
            xmlBody: $xmlBody,
            hotelCode: $hotelCode,
            echoToken: $echoToken
        );
    }

    /**
     * Create a new request DTO for rate operations
     */
    public static function forRates(
        string $messageId,
        string $xmlBody,
        string $hotelCode,
        ?string $echoToken = null
    ): self {
        return new self(
            messageId: $messageId,
            action: 'HTNG2011B_SubmitRequest',
            xmlBody: $xmlBody,
            hotelCode: $hotelCode,
            echoToken: $echoToken
        );
    }

    /**
     * Create a new request DTO for reservation operations
     */
    public static function forReservation(
        string $messageId,
        string $xmlBody,
        string $hotelCode,
        ?string $echoToken = null
    ): self {
        return new self(
            messageId: $messageId,
            action: 'HTNG2011B_SubmitRequest',
            xmlBody: $xmlBody,
            hotelCode: $hotelCode,
            echoToken: $echoToken
        );
    }

    /**
     * Get the complete headers array for the SOAP request
     */
    public function getCompleteHeaders(): array
    {
        return array_merge([
            'MessageID' => $this->messageId,
            'Action' => $this->action,
            'HotelCode' => $this->hotelCode,
        ], $this->headers);
    }

    /**
     * Convert DTO to array for logging purposes
     */
    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'action' => $this->action,
            'hotel_code' => $this->hotelCode,
            'echo_token' => $this->echoToken,
            'version' => $this->version,
            'target' => $this->target,
            'has_xml_body' => !empty($this->xmlBody),
            'xml_size_bytes' => strlen($this->xmlBody),
        ];
    }
}
