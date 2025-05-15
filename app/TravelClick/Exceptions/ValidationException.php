<?php

declare(strict_types=1);

namespace App\TravelClick\Exceptions;

use Exception;
use Throwable;

/**
 * ValidationException
 *
 * Thrown when data validation fails for TravelClick operations.
 * This exception provides detailed information about what validation failed
 * and includes context for debugging and error reporting.
 */
class ValidationException extends Exception
{
    /**
     * Validation context (e.g., 'inventory', 'rate', 'reservation')
     */
    protected string $context;

    /**
     * Array of validation errors
     *
     * @var array<string>
     */
    protected array $validationErrors;

    /**
     * Array of validation warnings
     *
     * @var array<string>
     */
    protected array $validationWarnings;

    /**
     * The data that failed validation
     */
    protected mixed $invalidData;

    /**
     * Create a new ValidationException instance
     *
     * @param string $message The exception message
     * @param string $context The validation context
     * @param array<string> $validationErrors Array of validation errors
     * @param array<string> $validationWarnings Array of validation warnings
     * @param mixed $invalidData The data that failed validation
     * @param int $code The exception code
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Validation failed',
        string $context = '',
        array $validationErrors = [],
        array $validationWarnings = [],
        mixed $invalidData = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
        $this->validationErrors = $validationErrors;
        $this->validationWarnings = $validationWarnings;
        $this->invalidData = $invalidData;
    }

    /**
     * Get the validation context
     */
    public function getContext(): string
    {
        return $this->context;
    }

    /**
     * Get validation errors
     *
     * @return array<string>
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Get validation warnings
     *
     * @return array<string>
     */
    public function getValidationWarnings(): array
    {
        return $this->validationWarnings;
    }

    /**
     * Get the invalid data
     */
    public function getInvalidData(): mixed
    {
        return $this->invalidData;
    }

    /**
     * Get formatted error message with full details
     */
    public function getDetailedMessage(): string
    {
        $details = [
            'Context: ' . $this->context,
            'Message: ' . $this->getMessage(),
        ];

        if (!empty($this->validationErrors)) {
            $details[] = 'Errors: ' . implode(', ', $this->validationErrors);
        }

        if (!empty($this->validationWarnings)) {
            $details[] = 'Warnings: ' . implode(', ', $this->validationWarnings);
        }

        return implode(' | ', $details);
    }

    /**
     * Convert exception to array for logging/API responses
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'context' => $this->context,
            'errors' => $this->validationErrors,
            'warnings' => $this->validationWarnings,
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }

    /**
     * Create exception for XML validation failure
     */
    public static function forXmlValidation(array $errors, string $xmlType = 'XML'): static
    {
        return new static(
            message: "XML validation failed for {$xmlType}",
            context: 'xml_validation',
            validationErrors: $errors
        );
    }

    /**
     * Create exception for business logic validation failure
     */
    public static function forBusinessLogic(string $rule, mixed $data = null): static
    {
        return new static(
            message: "Business logic validation failed: {$rule}",
            context: 'business_logic',
            validationErrors: [$rule],
            invalidData: $data
        );
    }

    /**
     * Create exception for inventory validation failure
     */
    public static function forInventory(array $errors, array $data = []): static
    {
        return new static(
            message: 'Inventory validation failed',
            context: 'inventory',
            validationErrors: $errors,
            invalidData: $data
        );
    }

    /**
     * Create exception for rate validation failure
     */
    public static function forRate(array $errors, array $data = []): static
    {
        return new static(
            message: 'Rate validation failed',
            context: 'rate',
            validationErrors: $errors,
            invalidData: $data
        );
    }

    /**
     * Create exception for reservation validation failure
     */
    public static function forReservation(array $errors, array $data = []): static
    {
        return new static(
            message: 'Reservation validation failed',
            context: 'reservation',
            validationErrors: $errors,
            invalidData: $data
        );
    }

    /**
     * Create exception for group block validation failure
     */
    public static function forGroupBlock(array $errors, array $data = []): static
    {
        return new static(
            message: 'Group block validation failed',
            context: 'group_block',
            validationErrors: $errors,
            invalidData: $data
        );
    }

    /**
     * Create exception for SOAP header validation failure
     */
    public static function forSoapHeaders(array $errors): static
    {
        return new static(
            message: 'SOAP headers validation failed',
            context: 'soap_headers',
            validationErrors: $errors
        );
    }

    /**
     * Create exception for property rules validation failure
     */
    public static function forPropertyRules(string $propertyId, array $errors): static
    {
        return new static(
            message: "Property rules validation failed for property {$propertyId}",
            context: 'property_rules',
            validationErrors: $errors
        );
    }

    /**
     * Create exception for required fields validation failure
     */
    public static function forRequiredFields(array $missingFields): static
    {
        $errors = array_map(fn($field) => "Required field missing: {$field}", $missingFields);

        return new static(
            message: 'Required fields validation failed',
            context: 'required_fields',
            validationErrors: $errors
        );
    }

    /**
     * Create exception for date range validation failure
     */
    public static function forDateRange(string $error): static
    {
        return new static(
            message: 'Date range validation failed',
            context: 'date_range',
            validationErrors: [$error]
        );
    }

    /**
     * Create exception from validation results array
     *
     * @param array<string, mixed> $results Validation results array
     * @param string $context Validation context
     * @return static
     */
    public static function fromValidationResults(array $results, string $context = ''): static
    {
        $message = $results['message'] ?? 'Validation failed';
        $errors = $results['errors'] ?? [];
        $warnings = $results['warnings'] ?? [];
        $data = $results['data'] ?? null;

        return new static(
            message: $message,
            context: $context,
            validationErrors: $errors,
            validationWarnings: $warnings,
            invalidData: $data
        );
    }

    /**
     * Create exception for schema validation failure
     */
    public static function forSchemaValidation(string $schemaType, array $errors): static
    {
        return new static(
            message: "Schema validation failed for {$schemaType}",
            context: 'schema_validation',
            validationErrors: $errors
        );
    }

    /**
     * Create exception for count type validation failure
     */
    public static function forCountType(string $method, array $errors): static
    {
        return new static(
            message: "Count type validation failed for {$method} method",
            context: 'count_type',
            validationErrors: $errors
        );
    }

    /**
     * Create exception for message type mismatch
     */
    public static function forMessageTypeMismatch(string $expected, string $actual): static
    {
        return new static(
            message: "Message type mismatch: expected {$expected}, got {$actual}",
            context: 'message_type',
            validationErrors: ["Expected: {$expected}", "Actual: {$actual}"]
        );
    }

    /**
     * Create exception for data sanitization failure
     */
    public static function forSanitization(string $error): static
    {
        return new static(
            message: "Data sanitization failed: {$error}",
            context: 'sanitization',
            validationErrors: [$error]
        );
    }

    /**
     * Check if this exception has validation errors
     */
    public function hasValidationErrors(): bool
    {
        return !empty($this->validationErrors);
    }

    /**
     * Check if this exception has validation warnings
     */
    public function hasValidationWarnings(): bool
    {
        return !empty($this->validationWarnings);
    }

    /**
     * Get total error count
     */
    public function getErrorCount(): int
    {
        return count($this->validationErrors);
    }

    /**
     * Get total warning count
     */
    public function getWarningCount(): int
    {
        return count($this->validationWarnings);
    }

    /**
     * Add additional validation error
     */
    public function addError(string $error): void
    {
        $this->validationErrors[] = $error;
    }

    /**
     * Add additional validation warning
     */
    public function addWarning(string $warning): void
    {
        $this->validationWarnings[] = $warning;
    }

    /**
     * Get summary of validation issues
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return [
            'context' => $this->context,
            'message' => $this->getMessage(),
            'error_count' => $this->getErrorCount(),
            'warning_count' => $this->getWarningCount(),
            'has_data' => $this->invalidData !== null,
        ];
    }

    /**
     * Convert to JSON string for API responses
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}
