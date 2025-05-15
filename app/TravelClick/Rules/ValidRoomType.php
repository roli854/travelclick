<?php

namespace App\TravelClick\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use App\Models\PropertyRoomType;
use App\Models\Property;

class ValidRoomType implements ValidationRule, DataAwareRule
{
    /**
     * All of the data under validation.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * The property ID to validate against
     *
     * @var int|null
     */
    protected ?int $propertyId = null;

    /**
     * Create a new rule instance.
     *
     * @param int|null $propertyId The property ID to validate against
     */
    public function __construct(?int $propertyId = null)
    {
        $this->propertyId = $propertyId;
    }

    /**
     * Set the data under validation.
     *
     * @param array<string, mixed> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @param \Closure $fail
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        // Check if value is provided
        if (empty($value)) {
            $fail('The :attribute field is required.');
            return;
        }

        // Validate format: alphanumeric, max 10 characters
        if (!preg_match('/^[A-Za-z0-9]{1,10}$/', $value)) {
            $fail('The :attribute must be alphanumeric and maximum 10 characters.');
            return;
        }

        // Get property ID from constructor or data
        $propertyId = $this->propertyId ?? $this->getPropertyIdFromData();

        if (!$propertyId) {
            $fail('Property ID is required to validate room type.');
            return;
        }

        // Verify property exists
        $property = Property::find($propertyId);
        if (!$property) {
            $fail('The specified property does not exist.');
            return;
        }

        // Check if room type exists for this property
        $roomType = PropertyRoomType::where('PropertyID', $propertyId)
            ->where('Code', $value)
            ->current() // scope for Current = 1
            ->first();

        if (!$roomType) {
            $fail('The :attribute does not exist for the specified property.');
            return;
        }

        // Verify room type is active
        if (!$roomType->Current) {
            $fail('The :attribute is not currently active.');
            return;
        }

        // Verify room type is not closed out (if closeout date exists)
        if ($roomType->CloseoutDate && $roomType->CloseoutDate <= now()) {
            $fail('The :attribute is closed out and not available.');
            return;
        }
    }

    /**
     * Extract property ID from validation data
     *
     * @return int|null
     */
    protected function getPropertyIdFromData(): ?int
    {
        // Try common field names for property ID
        $possibleFields = [
            'property_id',
            'PropertyID',
            'hotel_id',
            'HotelID',
            'hotel_code',
            'HotelCode'
        ];

        foreach ($possibleFields as $field) {
            if (isset($this->data[$field])) {
                // If it's a hotel code, try to find the property by code
                if (in_array($field, ['hotel_code', 'HotelCode'])) {
                    $property = Property::where('PropertyCode', $this->data[$field])
                        ->orWhere('Reference', $this->data[$field])
                        ->first();
                    return $property?->PropertyID;
                }

                // Otherwise assume it's a numeric ID
                return is_numeric($this->data[$field]) ? (int) $this->data[$field] : null;
            }
        }

        return null;
    }

    /**
     * Create a new rule instance for a specific property
     *
     * @param int $propertyId
     * @return static
     */
    public static function forProperty(int $propertyId): static
    {
        return new static($propertyId);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'The :attribute must be a valid, active room type code for the specified property.';
    }
}
