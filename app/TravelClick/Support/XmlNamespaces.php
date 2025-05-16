<?php

namespace App\TravelClick\Support;

/**
 * XML Namespaces manager for HTNG 2011B Interface
 *
 * This class centralizes all namespace definitions and provides utilities
 * for working with XML namespaces in TravelClick integration.
 * Like having a directory of all the "languages" (namespaces) that
 * different parts of the XML document speak.
 */
class XmlNamespaces
{
  /**
   * SOAP envelope namespace - the "wrapper" for all SOAP messages
   */
  public const SOAP_ENVELOPE = 'http://www.w3.org/2003/05/soap-envelope';

  /**
   * WS-Addressing namespace - for routing and addressing SOAP messages
   */
  public const WS_ADDRESSING = 'http://www.w3.org/2005/08/addressing';

  /**
   * WS-Security namespace - for authentication and security headers
   */
  public const WS_SECURITY = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

  /**
   * HTN namespace - TravelClick/iHotelier specific namespace
   */
  public const HTN_SERVICE = 'http://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService';

  /**
   * OTA namespace - OpenTravel Alliance standard namespace
   */
  public const OTA_2003 = 'http://www.opentravel.org/OTA/2003/05';

  /**
   * XML Schema Instance namespace - for XML schema references
   */
  public const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

  /**
   * XML Schema namespace - for XML schema definitions
   */
  public const XSD = 'http://www.w3.org/2001/XMLSchema';

  /**
   * Standard namespace prefixes mapping
   * Like having standard abbreviations for long department names
   */
  public const PREFIX_MAPPING = [
    'soapenv' => self::SOAP_ENVELOPE,
    'wsa' => self::WS_ADDRESSING,
    'wsse' => self::WS_SECURITY,
    'htn' => self::HTN_SERVICE,
    'ota' => self::OTA_2003,
    'xsi' => self::XSI,
    'xsd' => self::XSD,
  ];

  /**
   * Get all standard namespaces for HTNG messages
   *
   * @return array<string, string> Array of prefix => namespace URI
   */
  public static function getStandardNamespaces(): array
  {
    return self::PREFIX_MAPPING;
  }

  /**
   * Get namespaces for SOAP envelope
   *
   * @return array<string, string>
   */
  public static function getSoapEnvelopeNamespaces(): array
  {
    return [
      'soapenv' => self::SOAP_ENVELOPE,
      'wsa' => self::WS_ADDRESSING,
      'wsse' => self::WS_SECURITY,
      'htn' => self::HTN_SERVICE,
    ];
  }

  /**
   * Get namespaces for OTA message bodies
   *
   * @return array<string, string>
   */
  public static function getOtaNamespaces(): array
  {
    return [
      'ota' => self::OTA_2003,
      'xsi' => self::XSI,
    ];
  }

  /**
   * Build namespace attributes for XML elements
   *
   * @param array<string, string> $namespaces Key-value pairs of prefix => URI
   * @return array<string, string> Array of xmlns attributes
   */
  public static function buildNamespaceAttributes(array $namespaces): array
  {
    $attributes = [];

    foreach ($namespaces as $prefix => $uri) {
      $attribute = $prefix === '' ? 'xmlns' : "xmlns:$prefix";
      $attributes[$attribute] = $uri;
    }

    return $attributes;
  }

  /**
   * Get complete namespace attributes for a SOAP envelope
   *
   * @return array<string, string>
   */
  public static function getSoapEnvelopeAttributes(): array
  {
    return self::buildNamespaceAttributes(self::getSoapEnvelopeNamespaces());
  }

  /**
   * Get schema location for OTA messages
   *
   * @return string
   */
  public static function getOtaSchemaLocation(): string
  {
    return self::OTA_2003 . ' OTA_HotelInvCountNotifRQ.xsd';
  }

  /**
   * Validate that a namespace prefix is recognized
   *
   * @param string $prefix
   * @return bool
   */
  public static function isValidPrefix(string $prefix): bool
  {
    return array_key_exists($prefix, self::PREFIX_MAPPING);
  }

  /**
   * Get namespace URI by prefix
   *
   * @param string $prefix
   * @return string|null
   */
  public static function getNamespaceByPrefix(string $prefix): ?string
  {
    return self::PREFIX_MAPPING[$prefix] ?? null;
  }

  /**
   * Get default namespace for different message types
   *
   * @param string $messageType ('inventory', 'rate', 'reservation', 'restriction', 'group')
   * @return string
   */
  public static function getDefaultNamespaceForMessageType(string $messageType): string
  {
    // All HTNG 2011B messages use OTA namespace as default
    return self::OTA_2003;
  }

  /**
   * Build complete namespace context for XML builders
   * Combines all necessary namespaces for a complete HTNG message
   *
   * @param bool $includeSoapNamespaces Whether to include SOAP-specific namespaces
   * @return array<string, string>
   */
  public static function getCompleteNamespaceContext(bool $includeSoapNamespaces = true): array
  {
    $namespaces = self::getOtaNamespaces();

    if ($includeSoapNamespaces) {
      $namespaces = array_merge($namespaces, self::getSoapEnvelopeNamespaces());
    }

    return $namespaces;
  }
}
