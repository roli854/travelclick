<?php

namespace App\TravelClick\Http\Middleware;

use App\TravelClick\Exceptions\TravelClickAuthenticationException;
use App\TravelClick\Models\TravelClickErrorLog;
use App\TravelClick\Models\TravelClickPropertyConfig;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SoapAuthMiddleware
{
  /**
   * Handle an incoming SOAP request from TravelClick.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return mixed
   */
  public function handle(Request $request, Closure $next)
  {
    try {
      // Extract XML from request
      $xmlContent = $request->getContent();

      // Validate that this is a SOAP envelope
      if (!str_contains($xmlContent, '<soap') && !str_contains($xmlContent, '<soapenv')) {
        throw new TravelClickAuthenticationException('Invalid SOAP format');
      }

      // Parse the SOAP envelope to extract authentication details
      libxml_use_internal_errors(true);
      $xml = new \SimpleXMLElement($xmlContent);

      // Register namespaces
      $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
      $xml->registerXPathNamespace('soapenv', 'http://www.w3.org/2003/05/soap-envelope');
      $xml->registerXPathNamespace('wsse', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
      $xml->registerXPathNamespace('wsa', 'http://www.w3.org/2005/08/addressing');
      $xml->registerXPathNamespace('htn', 'http://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService');

      // Extract username and password
      $credentials = $this->extractCredentials($xml);

      if (empty($credentials['username']) || empty($credentials['password'])) {
        throw new TravelClickAuthenticationException('Missing username or password');
      }

      // Extract hotel code
      $hotelCode = $this->extractHotelCode($xml);

      if (empty($hotelCode)) {
        throw new TravelClickAuthenticationException('Missing hotel code');
      }

      // Validate credentials against stored configurations
      $this->validateCredentials($credentials['username'], $credentials['password'], $hotelCode);

      // Store hotel code in request for later use
      $request->attributes->set('hotel_code', $hotelCode);

      return $next($request);
    } catch (TravelClickAuthenticationException $e) {
      Log::channel('travelclick')->error('SOAP Authentication error', [
        'error' => $e->getMessage()
      ]);

      // Log the error
      TravelClickErrorLog::create([
        'ErrorCode' => 'AUTH_ERROR',
        'ErrorMessage' => $e->getMessage(),
        'CreatedAt' => now(),
      ]);

      // Return SOAP fault for authentication errors
      return $this->createAuthenticationFault($e->getMessage());
    } catch (\Exception $e) {
      Log::channel('travelclick')->error('SOAP processing error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      // Log the error
      TravelClickErrorLog::create([
        'ErrorCode' => 'SOAP_PROCESSING_ERROR',
        'ErrorMessage' => $e->getMessage(),
        'ErrorTrace' => $e->getTraceAsString(),
        'CreatedAt' => now(),
      ]);

      // Return SOAP fault for general errors
      return $this->createAuthenticationFault('SOAP processing error');
    }
  }

  /**
   * Extract username and password from SOAP envelope
   *
   * @param  \SimpleXMLElement  $xml
   * @return array
   */
  protected function extractCredentials(\SimpleXMLElement $xml): array
  {
    $credentials = [
      'username' => null,
      'password' => null
    ];

    // Try SOAP 1.1 format first
    $usernameElements = $xml->xpath('//soap:Header/wsse:Security/wsse:UsernameToken/wsse:Username');
    $passwordElements = $xml->xpath('//soap:Header/wsse:Security/wsse:UsernameToken/wsse:Password');

    // If not found, try SOAP 1.2
    if (empty($usernameElements)) {
      $usernameElements = $xml->xpath('//soapenv:Header/wsse:Security/wsse:UsernameToken/wsse:Username');
      $passwordElements = $xml->xpath('//soapenv:Header/wsse:Security/wsse:UsernameToken/wsse:Password');
    }

    if (!empty($usernameElements)) {
      $credentials['username'] = (string)$usernameElements[0];
    }

    if (!empty($passwordElements)) {
      $credentials['password'] = (string)$passwordElements[0];
    }

    return $credentials;
  }

  /**
   * Extract hotel code from SOAP envelope
   *
   * @param  \SimpleXMLElement  $xml
   * @return string|null
   */
  protected function extractHotelCode(\SimpleXMLElement $xml): ?string
  {
    // Try SOAP 1.1 format first
    $hotelCodeElements = $xml->xpath('//soap:Header/wsa:From/wsa:ReferenceProperties/htn:HotelCode');

    // If not found, try SOAP 1.2
    if (empty($hotelCodeElements)) {
      $hotelCodeElements = $xml->xpath('//soapenv:Header/wsa:From/wsa:ReferenceProperties/htn:HotelCode');
    }

    // If still not found, try to extract from the Body
    if (empty($hotelCodeElements)) {
      // Try OTA_HotelResNotifRQ
      $hotelCodeElements = $xml->xpath('//soap:Body//*[@HotelCode]/@HotelCode | //soapenv:Body//*[@HotelCode]/@HotelCode');

      // Or OTA_HotelInvCountNotifRQ and others that use HotelCode in Inventories
      if (empty($hotelCodeElements)) {
        $hotelCodeElements = $xml->xpath('//soap:Body//Inventories/@HotelCode | //soapenv:Body//Inventories/@HotelCode');
      }
    }

    return !empty($hotelCodeElements) ? (string)$hotelCodeElements[0] : null;
  }

  /**
   * Validate the provided credentials against stored configurations
   *
   * @param  string  $username
   * @param  string  $password
   * @param  string  $hotelCode
   * @return bool
   * @throws TravelClickAuthenticationException
   */
  protected function validateCredentials(string $username, string $password, string $hotelCode): bool
  {
    // Retrieve property configuration
    $propertyConfig = TravelClickPropertyConfig::where('ExternalPropertyID', $hotelCode)
      ->orWhere('PropertyCode', $hotelCode)
      ->first();

    if (!$propertyConfig) {
      throw new TravelClickAuthenticationException("Unknown hotel code: {$hotelCode}");
    }

    // Verify username and password
    if ($propertyConfig->Username !== $username || $propertyConfig->Password !== $password) {
      throw new TravelClickAuthenticationException('Invalid credentials');
    }

    // Check if property integration is enabled
    if (!$propertyConfig->IsActive) {
      throw new TravelClickAuthenticationException('Integration is disabled for this property');
    }

    return true;
  }

  /**
   * Create SOAP fault for authentication errors
   *
   * @param  string  $message
   * @return \Illuminate\Http\Response
   */
  protected function createAuthenticationFault(string $message)
  {
    $fault = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
    <SOAP-ENV:Body>
        <SOAP-ENV:Fault>
            <faultcode>SOAP-ENV:Client</faultcode>
            <faultstring>Authentication Error: {$message}</faultstring>
        </SOAP-ENV:Fault>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;

    return response($fault, 401)
      ->header('Content-Type', 'text/xml');
  }
}
