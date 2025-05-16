<?php

namespace App\TravelClick\Http\Requests;

use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\SyncStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request validation for TravelClick Sync Status operations
 *
 * This request class validates data for updating sync status records.
 * Think of it like a quality control inspector that ensures all the data
 * coming into our sync system meets the required standards before processing.
 *
 * Validates:
 * - Property existence
 * - Message type validity
 * - Status enum values
 * - Business logic constraints
 */
class SyncStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * For now, we'll assume all authenticated users can update sync status.
     * In production, you might want to implement role-based permissions.
     */
    public function authorize(): bool
    {
        // For TravelClick operations, we typically allow all authenticated users
        // You can implement additional authorization logic here if needed
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * These rules ensure the data integrity before we try to update
     * sync status records in the database.
     */
    public function rules(): array
    {
        return [
            // Property ID must exist in the Property table
            'property_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('Property', 'PropertyID')
                    ->where('CurrentProperty', 1), // Only current/active properties
            ],

            // Message type must be valid HTNG 2011B message type
            'message_type' => [
                'required',
                'string',
                Rule::enum(MessageType::class),
            ],

            // Status must be a valid SyncStatus enum value
            'status' => [
                'required',
                'string',
                Rule::enum(SyncStatus::class),
            ],

            // Hotel ID for TravelClick (optional for some operations)
            'hotel_id' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9_-]+$/', // Only alphanumeric, underscore, hyphen
            ],

            // Error message when status is failed
            'error_message' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:status,failed,error', // Required when status indicates failure
            ],

            // Records processed (for progress tracking)
            'records_processed' => [
                'nullable',
                'integer',
                'min:0',
            ],

            // Total records (for progress calculation)
            'records_total' => [
                'nullable',
                'integer',
                'min:0',
                'gte:records_processed', // Total must be >= processed
            ],

            // Success rate (0-100)
            'success_rate' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            // Whether to enable auto-retry
            'auto_retry_enabled' => [
                'nullable',
                'boolean',
            ],

            // Maximum retry attempts
            'max_retries' => [
                'nullable',
                'integer',
                'min:0',
                'max:10', // Reasonable upper limit
            ],

            // Context data (JSON)
            'context' => [
                'nullable',
                'array',
            ],

            // Message ID for tracking
            'message_id' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],

            // Force update flag (for manual overrides)
            'force_update' => [
                'nullable',
                'boolean',
            ],

            // Reset retry count flag
            'reset_retry_count' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom validation messages
     *
     * Provides user-friendly error messages that help identify
     * exactly what's wrong with the submitted data.
     */
    public function messages(): array
    {
        return [
            'property_id.required' => 'Property ID is required for sync status operations.',
            'property_id.exists' => 'The specified property does not exist or is not active.',
            'message_type.required' => 'Message type is required.',
            'message_type.enum' => 'Invalid message type. Must be one of: ' .
                implode(', ', array_column(MessageType::cases(), 'value')),
            'status.required' => 'Sync status is required.',
            'status.enum' => 'Invalid sync status. Must be one of: ' .
                implode(', ', array_column(SyncStatus::cases(), 'value')),
            'hotel_id.regex' => 'Hotel ID contains invalid characters. Only letters, numbers, underscore, and hyphen are allowed.',
            'error_message.required_if' => 'Error message is required when status indicates failure.',
            'error_message.max' => 'Error message cannot exceed 1000 characters.',
            'records_total.gte' => 'Total records must be greater than or equal to processed records.',
            'success_rate.min' => 'Success rate cannot be negative.',
            'success_rate.max' => 'Success rate cannot exceed 100%.',
            'max_retries.max' => 'Maximum retries cannot exceed 10.',
            'message_id.regex' => 'Message ID contains invalid characters.',
        ];
    }

    /**
     * Get custom attributes for validation error messages
     *
     * Makes error messages more user-friendly by using proper names
     * instead of field names in validation messages.
     */
    public function attributes(): array
    {
        return [
            'property_id' => 'property',
            'message_type' => 'message type',
            'hotel_id' => 'hotel ID',
            'error_message' => 'error message',
            'records_processed' => 'processed records',
            'records_total' => 'total records',
            'success_rate' => 'success rate',
            'auto_retry_enabled' => 'auto-retry setting',
            'max_retries' => 'maximum retries',
            'message_id' => 'message ID',
        ];
    }

    /**
     * Configure the validator instance
     *
     * Allows for complex validation logic that depends on multiple fields
     * or business rules that can't be expressed with simple rules.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Business rule: Can't set status to COMPLETED if there are no records processed
            if ($this->status === SyncStatus::COMPLETED->value) {
                if ($this->records_total > 0 && $this->records_processed === 0) {
                    $validator->errors()->add(
                        'status',
                        'Cannot mark as completed when no records have been processed.'
                    );
                }
            }

            // Business rule: Can't set status to RUNNING if already COMPLETED
            if ($this->status === SyncStatus::RUNNING->value) {
                $existingStatus = $this->getExistingSyncStatus();
                if ($existingStatus && $existingStatus->Status === SyncStatus::COMPLETED) {
                    $validator->errors()->add(
                        'status',
                        'Cannot set status to RUNNING for an already completed sync.'
                    );
                }
            }

            // Business rule: Success rate should match calculated rate
            if ($this->has(['records_processed', 'records_total', 'success_rate'])) {
                if ($this->records_total > 0) {
                    $calculatedRate = ($this->records_processed / $this->records_total) * 100;
                    if (abs($calculatedRate - $this->success_rate) > 0.01) {
                        $validator->errors()->add(
                            'success_rate',
                            'Success rate does not match calculated rate based on processed/total records.'
                        );
                    }
                }
            }

            // Business rule: Hotel ID should be provided for certain message types
            $requiresHotelId = [
                MessageType::INVENTORY,
                MessageType::RATES,
                MessageType::RESTRICTIONS,
            ];

            if (in_array($this->message_type, array_column($requiresHotelId, 'value')) && !$this->hotel_id) {
                $validator->errors()->add(
                    'hotel_id',
                    'Hotel ID is required for ' . $this->message_type . ' message type.'
                );
            }
        });
    }

    /**
     * Handle a passed validation attempt.
     *
     * This method runs after validation passes and allows us to
     * transform or prepare the data before it's used.
     */
    protected function passedValidation(): void
    {
        // Convert enum string values to actual enum instances for easier handling
        if ($this->has('message_type')) {
            $this->merge([
                'message_type_enum' => MessageType::from($this->message_type),
            ]);
        }

        if ($this->has('status')) {
            $this->merge([
                'status_enum' => SyncStatus::from($this->status),
            ]);
        }

        // Set default values for optional fields
        $this->merge([
            'auto_retry_enabled' => $this->auto_retry_enabled ?? true,
            'max_retries' => $this->max_retries ?? 3,
            'force_update' => $this->force_update ?? false,
            'reset_retry_count' => $this->reset_retry_count ?? false,
        ]);
    }

    /**
     * Get data ready for sync status update
     *
     * Returns only the data needed for updating the sync status record,
     * properly formatted and with any transformations applied.
     */
    public function getSyncStatusData(): array
    {
        $data = [
            'PropertyID' => $this->property_id,
            'MessageType' => $this->message_type_enum,
            'Status' => $this->status_enum,
        ];

        // Add optional fields if provided
        $optionalFields = [
            'ErrorMessage' => 'error_message',
            'RecordsProcessed' => 'records_processed',
            'RecordsTotal' => 'records_total',
            'SuccessRate' => 'success_rate',
            'AutoRetryEnabled' => 'auto_retry_enabled',
            'MaxRetries' => 'max_retries',
            'LastMessageID' => 'message_id',
            'Context' => 'context',
        ];

        foreach ($optionalFields as $dbField => $requestField) {
            if ($this->has($requestField) && $this->$requestField !== null) {
                $data[$dbField] = $this->$requestField;
            }
        }

        // Handle special status-specific logic
        if ($this->status_enum === SyncStatus::COMPLETED) {
            $data['LastSuccessfulSync'] = now();
            if ($this->reset_retry_count) {
                $data['RetryCount'] = 0;
                $data['NextRetryAt'] = null;
                $data['ErrorMessage'] = null;
            }
        }

        if ($this->status_enum === SyncStatus::RUNNING) {
            $data['LastSyncAttempt'] = now();
        }

        return $data;
    }

    /**
     * Get existing sync status record for validation
     */
    private function getExistingSyncStatus(): mixed
    {
        if (!$this->has(['property_id', 'message_type'])) {
            return null;
        }

        return \App\TravelClick\Models\TravelClickSyncStatus::where('PropertyID', $this->property_id)
            ->where('MessageType', $this->message_type)
            ->first();
    }

    /**
     * Check if this request is for updating an existing record
     */
    public function isUpdateRequest(): bool
    {
        return $this->getExistingSyncStatus() !== null;
    }

    /**
     * Check if this request is for creating a new record
     */
    public function isCreateRequest(): bool
    {
        return !$this->isUpdateRequest();
    }

    /**
     * Get validation rules for API documentation
     *
     * This can be used to generate API documentation automatically
     */
    public static function getApiDocumentation(): array
    {
        return [
            'property_id' => [
                'type' => 'integer',
                'required' => true,
                'description' => 'ID of the property in the Centrium system',
                'example' => 12345,
            ],
            'message_type' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Type of HTNG 2011B message',
                'enum' => array_column(MessageType::cases(), 'value'),
                'example' => 'inventory',
            ],
            'status' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Current sync status',
                'enum' => array_column(SyncStatus::cases(), 'value'),
                'example' => 'completed',
            ],
            'hotel_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'TravelClick hotel identifier',
                'example' => 'HTL_12345',
            ],
            'error_message' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Error message when status indicates failure',
                'example' => 'Connection timeout during inventory sync',
            ],
            'records_processed' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Number of records successfully processed',
                'example' => 150,
            ],
            'records_total' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Total number of records to process',
                'example' => 200,
            ],
            'success_rate' => [
                'type' => 'number',
                'required' => false,
                'description' => 'Success rate percentage (0-100)',
                'example' => 85.5,
            ],
            'auto_retry_enabled' => [
                'type' => 'boolean',
                'required' => false,
                'description' => 'Whether automatic retries are enabled',
                'example' => true,
            ],
            'max_retries' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Maximum number of retry attempts',
                'example' => 3,
            ],
            'context' => [
                'type' => 'object',
                'required' => false,
                'description' => 'Additional context data as JSON',
                'example' => ['batch_id' => 'BATCH_001', 'user_id' => 42],
            ],
        ];
    }
}
