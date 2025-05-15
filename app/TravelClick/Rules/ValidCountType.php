<?php

namespace App\TravelClick\Rules;

use App\TravelClick\Enums\CountType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * ValidCountType - Custom validation rule for HTNG 2011B CountType values
 *
 * This rule validates CountType values according to HTNG specifications:
 * - Ensures only valid CountType values are used
 * - Enforces business rules about CountType combinations
 * - Validates calculated vs non-calculated method constraints
 *
 * Think of this as a quality inspector for inventory count types:
 * - Checks each type is valid according to HTNG standards
 * - Ensures combinations make business sense
 * - Prevents common mistakes in inventory messages
 */
class ValidCountType implements ValidationRule
{
    /**
     * Whether to validate for calculated method (with business rules)
     */
    protected bool $validateCalculated;

    /**
     * Whether to allow multiple count types in the same message
     */
    protected bool $allowMultiple;

    /**
     * Create a new rule instance
     *
     * @param bool $validateCalculated Enforce calculated method rules
     * @param bool $allowMultiple Allow multiple CountTypes in same message
     */
    public function __construct(
        bool $validateCalculated = true,
        bool $allowMultiple = true
    ) {
        $this->validateCalculated = $validateCalculated;
        $this->allowMultiple = $allowMultiple;
    }

    /**
     * Run the validation rule
     *
     * @param string $attribute The attribute being validated
     * @param mixed $value The value to validate
     * @param Closure $fail Callback to call if validation fails
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Handle single CountType validation
        if (is_int($value) || is_string($value)) {
            $this->validateSingleCountType($attribute, $value, $fail);
            return;
        }

        // Handle array of CountTypes (multiple count types in message)
        if (is_array($value)) {
            $this->validateMultipleCountTypes($attribute, $value, $fail);
            return;
        }

        $fail($this->getErrorMessage($attribute, 'invalid_format'));
    }

    /**
     * Validate a single CountType value
     */
    protected function validateSingleCountType(string $attribute, mixed $value, Closure $fail): void
    {
        // Ensure it's a valid integer
        if (!is_numeric($value)) {
            $fail($this->getErrorMessage($attribute, 'not_numeric', ['value' => $value]));
            return;
        }

        $countTypeValue = (int) $value;

        // Check if it's a valid CountType according to enum
        if (!$this->isValidCountType($countTypeValue)) {
            $fail($this->getErrorMessage($attribute, 'invalid_value', [
                'value' => $countTypeValue,
                'valid_values' => $this->getValidCountTypesList()
            ]));
            return;
        }

        $countType = CountType::from($countTypeValue);

        // Validate calculated method specific rules
        if ($this->validateCalculated) {
            $this->validateCalculatedRules($attribute, $countType, [$countTypeValue], $fail);
        }
    }

    /**
     * Validate multiple CountTypes in an array
     */
    protected function validateMultipleCountTypes(string $attribute, array $value, Closure $fail): void
    {
        if (!$this->allowMultiple) {
            $fail($this->getErrorMessage($attribute, 'multiple_not_allowed'));
            return;
        }

        $countTypes = [];
        $countTypeValues = [];

        // Validate each CountType in the array
        foreach ($value as $index => $countTypeData) {
            // Handle both direct values and structured data
            $countTypeValue = $this->extractCountTypeValue($countTypeData);

            if ($countTypeValue === null) {
                $fail($this->getErrorMessage($attribute, 'invalid_structure', ['index' => $index]));
                continue;
            }

            // Validate individual CountType
            if (!$this->isValidCountType($countTypeValue)) {
                $fail($this->getErrorMessage($attribute, 'invalid_value_in_array', [
                    'index' => $index,
                    'value' => $countTypeValue,
                    'valid_values' => $this->getValidCountTypesList()
                ]));
                continue;
            }

            $countType = CountType::from($countTypeValue);
            $countTypes[] = $countType;
            $countTypeValues[] = $countTypeValue;
        }

        // Validate combinations and business rules
        if (!empty($countTypes)) {
            $this->validateCountTypeCombinations($attribute, $countTypes, $countTypeValues, $fail);

            if ($this->validateCalculated) {
                $this->validateCalculatedRules($attribute, null, $countTypeValues, $fail);
            }
        }
    }

    /**
     * Extract CountType value from various data structures
     */
    protected function extractCountTypeValue(mixed $data): ?int
    {
        // Direct integer/string value
        if (is_numeric($data)) {
            return (int) $data;
        }

        // Array with CountType key (e.g., from form data)
        if (is_array($data)) {
            if (isset($data['CountType'])) {
                return is_numeric($data['CountType']) ? (int) $data['CountType'] : null;
            }
            if (isset($data['count_type'])) {
                return is_numeric($data['count_type']) ? (int) $data['count_type'] : null;
            }
        }

        return null;
    }

    /**
     * Validate CountType combinations according to business rules
     */
    protected function validateCountTypeCombinations(
        string $attribute,
        array $countTypes,
        array $countTypeValues,
        Closure $fail
    ): void {
        // Rule: CountType 2 (AVAILABLE) cannot be combined with others
        if (in_array(CountType::AVAILABLE->value, $countTypeValues) && count($countTypeValues) > 1) {
            $fail($this->getErrorMessage($attribute, 'available_no_combination'));
            return;
        }

        // Rule: Don't mix calculated and direct types
        $hasCalculated = false;
        $hasDirect = false;

        foreach ($countTypes as $countType) {
            if ($countType->requiresCalculation()) {
                $hasCalculated = true;
            } else {
                $hasDirect = true;
            }
        }

        if ($hasCalculated && $hasDirect && count($countTypeValues) > 1) {
            $fail($this->getErrorMessage($attribute, 'mixed_calculation_types'));
        }
    }

    /**
     * Validate specific rules for calculated method
     */
    protected function validateCalculatedRules(
        string $attribute,
        ?CountType $singleCountType,
        array $countTypeValues,
        Closure $fail
    ): void {
        // Rule: In calculated method, TENTATIVE_SOLD must be included and be zero
        $hasTentative = in_array(CountType::TENTATIVE_SOLD->value, $countTypeValues);
        $hasCalculatedTypes = !empty(array_intersect($countTypeValues, [
            CountType::DEFINITE_SOLD->value,
            CountType::OUT_OF_ORDER->value,
            CountType::OVERSELL->value
        ]));

        if ($hasCalculatedTypes && !$hasTentative) {
            $fail($this->getErrorMessage($attribute, 'calculated_requires_tentative'));
        }

        // Note: The actual count value (must be 0) is validated elsewhere
        // This rule only checks structure/presence
    }

    /**
     * Check if a numeric value is a valid CountType
     */
    protected function isValidCountType(int $value): bool
    {
        try {
            CountType::from($value);
            return true;
        } catch (\ValueError) {
            return false;
        }
    }

    /**
     * Get list of valid CountType values as string
     */
    protected function getValidCountTypesList(): string
    {
        $types = [];
        foreach (CountType::cases() as $countType) {
            $types[] = $countType->value . ' (' . $countType->name . ')';
        }
        return implode(', ', $types);
    }

    /**
     * Get error message for different validation scenarios
     */
    protected function getErrorMessage(string $attribute, string $type, array $replace = []): string
    {
        $messages = [
            'invalid_format' => 'The :attribute must be a valid CountType (integer) or array of CountTypes.',

            'not_numeric' => 'The :attribute value ":value" must be numeric.',

            'invalid_value' => 'The :attribute value ":value" is not a valid CountType. Valid values are: :valid_values.',

            'invalid_value_in_array' => 'The :attribute at index :index has invalid CountType ":value". Valid values are: :valid_values.',

            'invalid_structure' => 'The :attribute at index :index has invalid structure. Expected CountType value.',

            'multiple_not_allowed' => 'The :attribute can only contain a single CountType value.',

            'available_no_combination' => 'The :attribute contains CountType 2 (AVAILABLE) which cannot be combined with other CountTypes. Use CountType 2 alone for non-calculated method.',

            'mixed_calculation_types' => 'The :attribute cannot mix calculated types (4,5,6,99) with direct types (1,2) in the same message.',

            'calculated_requires_tentative' => 'The :attribute for calculated method must include CountType 5 (TENTATIVE_SOLD) when using other calculated types (4,6,99). Set TENTATIVE_SOLD to 0 if not used.',
        ];

        $message = $messages[$type] ?? 'The :attribute has an invalid CountType.';

        // Replace placeholders
        $replacements = array_merge([':attribute' => $attribute], $replace);
        foreach ($replacements as $search => $replacement) {
            $message = str_replace($search, $replacement, $message);
        }

        return $message;
    }

    /**
     * Create instance for non-calculated method validation
     */
    public static function nonCalculated(bool $allowMultiple = true): self
    {
        return new self(false, $allowMultiple);
    }

    /**
     * Create instance for single CountType validation
     */
    public static function single(bool $validateCalculated = true): self
    {
        return new self($validateCalculated, false);
    }

    /**
     * Create instance for calculated method validation
     */
    public static function calculated(): self
    {
        return new self(true, true);
    }
}
