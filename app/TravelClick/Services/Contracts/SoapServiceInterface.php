<?php

namespace App\TravelClick\Services\Contracts;

use App\TravelClick\DTOs\SoapRequestDto;
use App\TravelClick\DTOs\SoapResponseDto;

/**
 * Interface for TravelClick SOAP service operations
 *
 * This interface defines the contract for all SOAP communications with TravelClick.
 * It ensures consistent method signatures and proper error handling across implementations.
 */
interface SoapServiceInterface
{
    /**
     * Send a SOAP request to TravelClick
     *
     * @param SoapRequestDto $request The SOAP request data transfer object
     * @return SoapResponseDto The parsed SOAP response
     * @throws \App\TravelClick\Exceptions\SoapException
     * @throws \App\TravelClick\Exceptions\TravelClickConnectionException
     * @throws \App\TravelClick\Exceptions\TravelClickAuthenticationException
     */
    public function sendRequest(SoapRequestDto $request): SoapResponseDto;

    /**
     * Update inventory at TravelClick
     *
     * @param string $xml The inventory XML message
     * @param string $hotelCode The hotel code
     * @return SoapResponseDto
     */
    public function updateInventory(string $xml, string $hotelCode): SoapResponseDto;

    /**
     * Update rates at TravelClick
     *
     * @param string $xml The rates XML message
     * @param string $hotelCode The hotel code
     * @return SoapResponseDto
     */
    public function updateRates(string $xml, string $hotelCode): SoapResponseDto;

    /**
     * Send reservation to TravelClick
     *
     * @param string $xml The reservation XML message
     * @param string $hotelCode The hotel code
     * @return SoapResponseDto
     */
    public function sendReservation(string $xml, string $hotelCode): SoapResponseDto;

    /**
     * Test connection to TravelClick
     *
     * @return bool True if connection is successful
     */
    public function testConnection(): bool;

    /**
     * Get the current SOAP client instance
     *
     * @return \SoapClient
     */
    public function getClient(): \SoapClient;

    /**
     * Check if the service is currently connected
     *
     * @return bool
     */
    public function isConnected(): bool;
}
