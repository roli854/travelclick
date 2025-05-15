<?php

declare(strict_types=1);

namespace App\TravelClick\Support;

use App\TravelClick\Enums\SchemaType;
use App\TravelClick\Enums\ValidationErrorType;
use App\TravelClick\Exceptions\ValidationException;
use DOMDocument;
use DOMXPath;
use LibXMLError;
use Illuminate\Support\Facades\Log;

/**
 * XmlValidator
 *
 * Provides comprehensive XML validation for HTNG 2011B messages.
 * Handles schema validation, namespace checking, and HTNG-specific element validation.
 */
class XmlValidator
{
    /**
     * Required namespaces for HTNG 2011B
     *
     * @var array<string, string>
     */
    protected array $requiredNamespaces = [
        'ota' => 'http://www.opentravel.org/OTA/2003/05',
        'wsa' => 'http://www.w3.org/2005/08/addressing',
        'wsse' => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
        'htng' => 'http://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService'
    ];

    /**
     * Validate XML structure and schema
     *
     * @param string $xml The XML to validate
     * @param SchemaType $schemaType The schema type to validate against
     * @return array<string, mixed> Validation results
     * @throws ValidationException
     */
    public function validate(string $xml, SchemaType $schemaType): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'schema_type' => $schemaType->value,
            'validated_at' => now()
        ];

        try {
            // Enable libxml error handling
            libxml_use_internal_errors(true);
            libxml_clear_errors();

            // Parse XML
            $dom = $this->parseXml($xml, $results);
            if (!$dom) {
                return $results;
            }

            // Validate against XSD schema
            $this->validateSchema($dom, $schemaType, $results);

            // Validate namespaces
            $this->validateNamespaces($dom, $results);

            // Validate HTNG-specific elements
            $this->validateHtngElements($dom, $schemaType, $results);

            // Validate root element
            $this->validateRootElement($dom, $schemaType, $results);

            Log::info('XML validation completed', [
                'schema_type' => $schemaType->value,
                'valid' => $results['valid'],
                'error_count' => count($results['errors'])
            ]);
        } catch (\Exception $e) {
            $results['valid'] = false;
            $results['errors'][] = 'XML validation failed: ' . $e->getMessage();

            Log::error('XML validation error', [
                'schema_type' => $schemaType->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            libxml_clear_errors();
        }

        return $results;
    }

    /**
     * Validate XML structure without schema
     *
     * @param string $xml The XML to validate
     * @return array<string, mixed> Validation results
     */
    public function validateStructure(string $xml): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'validated_at' => now()
        ];

        libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = $this->parseXml($xml, $results);
            if ($dom) {
                $this->validateWellFormedness($dom, $results);
            }
        } catch (\Exception $e) {
            $results['valid'] = false;
            $results['errors'][] = 'XML structure validation failed: ' . $e->getMessage();
        } finally {
            libxml_clear_errors();
        }

        return $results;
    }

    /**
     * Validate SOAP envelope structure
     *
     * @param string $xml The SOAP XML to validate
     * @return array<string, mixed> Validation results
     */
    public function validateSoapEnvelope(string $xml): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'validated_at' => now()
        ];

        try {
            $dom = $this->parseXml($xml, $results);
            if (!$dom) {
                return $results;
            }

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('soap', 'http://www.w3.org/2003/05/soap-envelope');

            // Check for SOAP envelope
            $envelope = $xpath->query('//soap:Envelope');
            if ($envelope->length === 0) {
                $results['valid'] = false;
                $results['errors'][] = 'SOAP Envelope not found';
            }

            // Check for SOAP header
            $header = $xpath->query('//soap:Header');
            if ($header->length === 0) {
                $results['warnings'][] = 'SOAP Header not found';
            }

            // Check for SOAP body
            $body = $xpath->query('//soap:Body');
            if ($body->length === 0) {
                $results['valid'] = false;
                $results['errors'][] = 'SOAP Body not found';
            }
        } catch (\Exception $e) {
            $results['valid'] = false;
            $results['errors'][] = 'SOAP envelope validation failed: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Check if XML is valid against schema
     *
     * @param string $xml The XML to check
     * @param SchemaType $schemaType The schema type
     * @return bool
     */
    public function isValid(string $xml, SchemaType $schemaType): bool
    {
        $results = $this->validate($xml, $schemaType);
        return $results['valid'] === true;
    }

    /**
     * Get schema file path
     *
     * @param SchemaType $schemaType The schema type
     * @return string The full path to schema file
     * @throws ValidationException
     */
    public function getSchemaPath(SchemaType $schemaType): string
    {
        $path = $schemaType->getXsdPath();

        if (!file_exists($path)) {
            throw ValidationException::forSchemaValidation(
                $schemaType->value,
                ["Schema file not found: {$path}"]
            );
        }

        return $path;
    }

    /**
     * Parse XML string into DOMDocument
     *
     * @param string $xml The XML to parse
     * @param array<string, mixed> &$results Results array to update
     * @return DOMDocument|null
     */
    protected function parseXml(string $xml, array &$results): ?DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        // Attempt to load XML
        if (!@$dom->loadXML($xml)) {
            $results['valid'] = false;
            $errors = libxml_get_errors();

            foreach ($errors as $error) {
                $results['errors'][] = $this->formatLibXmlError($error);
            }

            return null;
        }

        return $dom;
    }

    /**
     * Validate XML against XSD schema
     *
     * @param DOMDocument $dom The DOM document
     * @param SchemaType $schemaType The schema type
     * @param array<string, mixed> &$results Results array to update
     */
    protected function validateSchema(DOMDocument $dom, SchemaType $schemaType, array &$results): void
    {
        if (!$schemaType->hasXsdFile()) {
            $results['warnings'][] = "No XSD file available for schema type: {$schemaType->value}";
            return;
        }

        $schemaPath = $this->getSchemaPath($schemaType);

        if (!@$dom->schemaValidate($schemaPath)) {
            $results['valid'] = false;
            $errors = libxml_get_errors();

            foreach ($errors as $error) {
                $results['errors'][] = 'Schema validation: ' . $this->formatLibXmlError($error);
            }
        }
    }

    /**
     * Validate required namespaces
     *
     * @param DOMDocument $dom The DOM document
     * @param array<string, mixed> &$results Results array to update
     */
    protected function validateNamespaces(DOMDocument $dom, array &$results): void
    {
        $xpath = new DOMXPath($dom);

        foreach ($this->requiredNamespaces as $prefix => $uri) {
            $elements = $xpath->query("//*[namespace-uri() = '{$uri}']");

            if ($elements->length === 0) {
                $results['warnings'][] = "Required namespace '{$prefix}' ({$uri}) not found";
            }
        }

        // Register namespaces for further validation
        foreach ($this->requiredNamespaces as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }
    }

    /**
     * Validate HTNG-specific elements
     *
     * @param DOMDocument $dom The DOM document
     * @param SchemaType $schemaType The schema type
     * @param array<string, mixed> &$results Results array to update
     */
    protected function validateHtngElements(DOMDocument $dom, SchemaType $schemaType, array &$results): void
    {
        $xpath = new DOMXPath($dom);

        // Register OTA namespace for XPath queries
        $xpath->registerNamespace('ota', $this->requiredNamespaces['ota']);

        // Validate required elements for the schema type
        $requiredElements = $schemaType->getRequiredElements();

        foreach ($requiredElements as $element) {
            $found = $xpath->query("//ota:{$element}")->length > 0;

            if (!$found) {
                $results['valid'] = false;
                $results['errors'][] = "Required element '{$element}' not found";
            }
        }

        // Schema-specific validations
        match ($schemaType) {
            SchemaType::INVENTORY => $this->validateInventoryElements($xpath, $results),
            SchemaType::RATE => $this->validateRateElements($xpath, $results),
            SchemaType::RESERVATION => $this->validateReservationElements($xpath, $results),
            SchemaType::BLOCK => $this->validateBlockElements($xpath, $results),
            default => null,
        };
    }

    /**
     * Validate root element matches schema type
     *
     * @param DOMDocument $dom The DOM document
     * @param SchemaType $schemaType The schema type
     * @param array<string, mixed> &$results Results array to update
     */
    protected function validateRootElement(DOMDocument $dom, SchemaType $schemaType, array &$results): void
    {
        $expectedRoot = $schemaType->getRootElement();
        $actualRoot = $dom->documentElement?->localName;

        if ($actualRoot !== $expectedRoot) {
            $results['valid'] = false;
            $results['errors'][] = "Root element mismatch: expected '{$expectedRoot}', found '{$actualRoot}'";
        }
    }

    /**
     * Validate XML well-formedness
     *
     * @param DOMDocument $dom The DOM document
     * @param array<string, mixed> &$results Results array to update
     */
    protected function validateWellFormedness(DOMDocument $dom, array &$results): void
    {
        // Check for duplicate IDs
        $this->checkDuplicateIds($dom, $results);

        // Check for empty required attributes
        $this->checkEmptyAttributes($dom, $results);

        // Validate encoding
        if ($dom->encoding && strtoupper($dom->encoding) !== 'UTF-8') {
            $results['warnings'][] = "Non-UTF-8 encoding detected: {$dom->encoding}";
        }
    }

    /**
     * Validate inventory-specific elements
     *
     * @param DOMXPath $xpath XPath object
     * @param array<string, mixed> &$results Results array to update
     */
    protected function validateInventoryElements(DOMXPath $xpath, array &$results): void
    {
        // Check for valid CountType values
        $countElements = $xpath->query('//ota:InvCount[@CountType]');

        foreach ($countElements as $element) {
            $countType = $element->getAttribute('CountType');
            $validTypes = [1, 2, 4, 5, 6, 99];

            if (!in_array((int)$countType, $validTypes)) {
                $results['valid'] = false;
                $results['errors'][] = "Invalid CountType: {$countType}";
            }
        }

        // Check for required StatusApplicationControl
        $statusControls = $xpath->query('//ota:StatusApplicationControl');

        foreach ($statusControls as $control) {
            if (!$control->hasAttribute('Start')) {
                $results['valid'] = false;
                $results['errors'][] = 'StatusApplicationControl missing Start attribute';
            }

            if (!$control->hasAttribute('InvTypeCode') && !$control->hasAttribute('AllInvCode')) {
                $results['valid'] = false;
                $results['errors'][] = 'StatusApplicationControl missing InvTypeCode or AllInvCode';
            }
        }
    }

    /**
     * Validate rate-specific elements
     *
     * @param DOMXPath $xpath XPath object
     * @param array<string, mixed> &$results Results array to update
     */
    protected function validateRateElements(DOMXPath $xpath, array &$results): void
    {
        // Check for valid rate amounts
        $rateElements = $xpath->query('//ota:BaseByGuestAmt');

        foreach ($rateElements as $element) {
            $amount = $element->getAttribute('AmountBeforeTax');

            if ($amount !== '' && !is_numeric($amount)) {
                $results['valid'] = false;
                $results['errors'][] = "Invalid rate amount: {$amount}";
            }

            if (is_numeric($amount) && (float)$amount < 0) {
                $results['valid'] = false;
                $results['errors'][] = "Negative rate amount not allowed: {$amount}";
            }
        }

        // Check for required NumberOfGuests
        $guestAmounts = $xpath->query('//ota:BaseByGuestAmt[not(@NumberOfGuests)]');

        if ($guestAmounts->length > 0) {
            $results['valid'] = false;
            $results['errors'][] = 'BaseByGuestAmt missing NumberOfGuests attribute';
        }
    }

    /**
     * Validate reservation-specific elements
     *
     * @param DOMXPath $xpath XPath object
     * @param array<string, mixed> &$results Results array to update
     */
    protected function validateReservationElements(DOMXPath $xpath, array &$results): void
    {
        // Check for required guest information
        $guests = $xpath->query('//ota:ResGuest');

        if ($guests->length === 0) {
            $results['valid'] = false;
            $results['errors'][] = 'No guest information found';
        }

        // Validate room stays
        $roomStays = $xpath->query('//ota:RoomStay');

        foreach ($roomStays as $roomStay) {
            // Check for required arrival/departure dates
            $timeSpan = $xpath->query('ota:TimeSpan', $roomStay);

            if ($timeSpan->length === 0) {
                $results['valid'] = false;
                $results['errors'][] = 'RoomStay missing TimeSpan';
            }
        }
    }

    /**
     * Validate group block-specific elements
     *
     * @param DOMXPath $xpath XPath object
     * @param array<string, mixed> &$results Results array to update
     */
    protected function validateBlockElements(DOMXPath $xpath, array &$results): void
    {
        // Check for required block code
        $blocks = $xpath->query('//ota:InvBlock[not(@InvBlockCode)]');

        if ($blocks->length > 0) {
            $results['valid'] = false;
            $results['errors'][] = 'InvBlock missing InvBlockCode attribute';
        }

        // Validate room type allocations
        $allocations = $xpath->query('//ota:RoomTypeAllocation');

        foreach ($allocations as $allocation) {
            $units = $allocation->getAttribute('NumberOfUnits');

            if ($units !== '' && (!is_numeric($units) || (int)$units < 0)) {
                $results['valid'] = false;
                $results['errors'][] = "Invalid NumberOfUnits: {$units}";
            }
        }
    }

    /**
     * Check for duplicate IDs in the document
     *
     * @param DOMDocument $dom The DOM document
     * @param array<string, mixed> &$results Results array to update
     */
    protected function checkDuplicateIds(DOMDocument $dom, array &$results): void
    {
        $xpath = new DOMXPath($dom);
        $elements = $xpath->query('//*[@ID]');
        $ids = [];

        foreach ($elements as $element) {
            $id = $element->getAttribute('ID');

            if (in_array($id, $ids)) {
                $results['warnings'][] = "Duplicate ID found: {$id}";
            } else {
                $ids[] = $id;
            }
        }
    }

    /**
     * Check for empty required attributes
     *
     * @param DOMDocument $dom The DOM document
     * @param array<string, mixed> &$results Results array to update
     */
    protected function checkEmptyAttributes(DOMDocument $dom, array &$results): void
    {
        $xpath = new DOMXPath($dom);

        // Define critical attributes that shouldn't be empty
        $criticalAttributes = [
            'HotelCode',
            'InvTypeCode',
            'RatePlanCode',
            'Start',
            'End'
        ];

        foreach ($criticalAttributes as $attr) {
            $elements = $xpath->query("//*[@{$attr}='']");

            if ($elements->length > 0) {
                $results['warnings'][] = "Empty {$attr} attribute found";
            }
        }
    }

    /**
     * Format libxml error for readable output
     *
     * @param LibXMLError $error The libxml error
     * @return string Formatted error message
     */
    protected function formatLibXmlError(LibXMLError $error): string
    {
        $level = match ($error->level) {
            LIBXML_ERR_WARNING => 'Warning',
            LIBXML_ERR_ERROR => 'Error',
            LIBXML_ERR_FATAL => 'Fatal',
            default => 'Unknown',
        };

        return "{$level}: {$error->message} (Line: {$error->line}, Column: {$error->column})";
    }
}
