<?php

declare(strict_types=1);

namespace App\TravelClick\Builders;

use App\TravelClick\DTOs\RateData;
use App\TravelClick\DTOs\RatePlanData;
use App\TravelClick\DTOs\SoapHeaderDto;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\RateOperationType;
use App\TravelClick\Support\RateStructureValidator;
use App\TravelClick\Support\LinkedRateHandler;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * XML Builder for Rate messages (OTA_HotelRateNotifRQ)
 *
 * This builder constructs HTNG 2011B compliant rate notification messages
 * for sending to TravelClick. Think of it as a specialized architect who
 * knows exactly how to design rate messages that TravelClick understands.
 *
 * Key responsibilities:
 * - Build OTA_HotelRateNotifRQ messages for different operation types
 * - Handle linked rates according to external system capabilities
 * - Support both delta updates and full synchronization
 * - Validate rate structures before building XML
 * - Apply business rules specific to rate operations
 *
 * Usage example:
 * ```php
 * $builder = new RateXmlBuilder(
 *     MessageType::RATES,
 *     $soapHeaders,
 *     RateOperationType::RATE_UPDATE
 * );
 *
 * $xml = $builder->build(['rate_plans' => [$ratePlan1, $ratePlan2]]);
 * ```
 */
class RateXmlBuilder extends XmlBuilder
{
  /**
   * Rate structure validator for business rules
   */
  private RateStructureValidator $rateValidator;

  /**
   * Linked rate handler for master/child relationships
   */
  private LinkedRateHandler $linkedRateHandler;

  /**
   * Operation type for this rate message
   */
  private RateOperationType $operationType;

  /**
   * Whether to use delta updates (send only changes)
   */
  private bool $isDeltaUpdate;

  /**
   * Maximum number of rate plans per message
   * Configured based on operation type
   */
  private int $maxRatePlansPerMessage;

  /**
   * Whether external system handles linked rates
   */
  private bool $externalHandlesLinkedRates;

  public function __construct(
    MessageType $messageType,
    SoapHeaderDto $soapHeaders,
    RateOperationType $operationType,
    bool $isDeltaUpdate = true,
    bool $validateXml = true,
    bool $formatOutput = false
  ) {
    if ($messageType !== MessageType::RATES) {
      throw new InvalidArgumentException('RateXmlBuilder can only handle RATES message type');
    }

    parent::__construct($messageType, $soapHeaders, $validateXml, $formatOutput);

    $this->operationType = $operationType;
    $this->isDeltaUpdate = $isDeltaUpdate;
    $this->rateValidator = new RateStructureValidator();
    $this->linkedRateHandler = new LinkedRateHandler();

    // Load configuration
    $this->maxRatePlansPerMessage = $operationType->getRecommendedBatchSize();
    $this->externalHandlesLinkedRates = $this->linkedRateHandler->externalSystemHandlesLinkedRates();
  }

  /**
   * Validate the message data before building
   *
   * Ensures that rate plans are valid according to HTNG 2011B specification
   * and TravelClick's business rules for the specific operation type.
   *
   * @param array<string, mixed> $messageData The message data to validate
   * @throws InvalidArgumentException If validation fails
   */
  protected function validateMessageData(array $messageData): void
  {
    // Validate required data structure
    if (!isset($messageData['rate_plans'])) {
      throw new InvalidArgumentException('Rate message must contain "rate_plans" key');
    }

    $ratePlans = $messageData['rate_plans'];

    // Validate it's an array
    if (!is_array($ratePlans)) {
      throw new InvalidArgumentException('Rate plans must be an array');
    }

    // Convert array items to RatePlanData if needed
    $ratePlanInstances = collect($ratePlans)->map(function ($ratePlan) {
      if ($ratePlan instanceof RatePlanData) {
        return $ratePlan;
      }

      if (is_array($ratePlan)) {
        return RatePlanData::fromArray($ratePlan);
      }

      throw new InvalidArgumentException('Each rate plan must be a RatePlanData instance or array');
    });

    // Validate batch size
    if ($ratePlanInstances->count() > $this->maxRatePlansPerMessage) {
      throw new InvalidArgumentException(
        "Too many rate plans in message. Maximum {$this->maxRatePlansPerMessage} allowed for {$this->operationType->value} operations"
      );
    }

    // Use the validator to check business rules
    $this->rateValidator->validateBatchRatePlans(
      $ratePlanInstances->toArray(),
      $this->operationType
    );

    // Validate linked rate dependencies
    $this->linkedRateHandler->validateLinkedRateDependencies(
      $ratePlanInstances,
      $this->operationType
    );
  }

  /**
   * Build the message body for rate notifications
   *
   * Creates the complete OTA_HotelRateNotifRQ structure with all rate plans,
   * handling different operation types and linked rate logic.
   *
   * @param array<string, mixed> $messageData Message data containing rate plans
   * @return array<string, mixed> The complete message body structure
   */
  protected function buildMessageBody(array $messageData): array
  {
    $ratePlans = collect($messageData['rate_plans'])->map(function ($ratePlan) {
      if ($ratePlan instanceof RatePlanData) {
        return $ratePlan;
      }
      return RatePlanData::fromArray($ratePlan);
    });

    // Filter linked rates if external system handles them
    $filteredRatePlans = $ratePlans->map(function (RatePlanData $ratePlan) {
      return $this->linkedRateHandler->filterLinkedRatesIfNeeded(
        $ratePlan,
        $this->operationType
      );
    });

    // Build the complete OTA message
    $otaMessage = [
      '_attributes' => $this->getOtaMessageAttributes(),
      'RateAmountMessages' => $this->buildRateAmountMessages($filteredRatePlans),
    ];

    return [$this->getOtaRootElement() => $otaMessage];
  }

  /**
   * Build RateAmountMessages structure
   *
   * This method creates the core content of the rate message, organizing
   * rate plans by hotel and handling the specific XML structure required
   * by TravelClick.
   *
   * @param \Illuminate\Support\Collection<RatePlanData> $ratePlans Collection of rate plans
   * @return array<string, mixed> RateAmountMessages structure
   */
  private function buildRateAmountMessages($ratePlans): array
  {
    // Group rate plans by hotel code
    $ratePlansByHotel = $ratePlans->groupBy('hotelCode');

    $rateAmountMessages = [];

    foreach ($ratePlansByHotel as $hotelCode => $hotelRatePlans) {
      $rateAmountMessage = [
        '_attributes' => $this->buildHotelReference()['_attributes'],
        'RateAmountMessage' => $this->buildRateAmountMessage($hotelRatePlans),
      ];

      $rateAmountMessages[] = $rateAmountMessage;
    }

    return [
      'RateAmountMessage' => count($rateAmountMessages) === 1
        ? $rateAmountMessages[0]
        : $rateAmountMessages
    ];
  }

  /**
   * Build RateAmountMessage for a specific hotel
   *
   * @param \Illuminate\Support\Collection<RatePlanData> $ratePlans Rate plans for one hotel
   * @return array<string, mixed> RateAmountMessage structure
   */
  private function buildRateAmountMessage($ratePlans): array
  {
    $rateAmountMessage = [];

    foreach ($ratePlans as $ratePlan) {
      // Build status application control for the entire rate plan
      $statusControl = $this->buildStatusApplicationControl(
        $ratePlan->startDate->format('Y-m-d'),
        $ratePlan->endDate->format('Y-m-d')
      );

      // Build rates structure grouped by room type
      $rates = $this->buildRatesStructure($ratePlan);

      $messageElement = array_merge($statusControl, ['Rates' => $rates]);
      $rateAmountMessage[] = $messageElement;
    }

    return count($rateAmountMessage) === 1
      ? $rateAmountMessage[0]
      : $rateAmountMessage;
  }

  /**
   * Build Rates structure for a rate plan
   *
   * Organizes rates by room type and builds the proper XML structure
   * according to HTNG 2011B specification.
   *
   * @param RatePlanData $ratePlan The rate plan to process
   * @return array<string, mixed> Rates structure
   */
  private function buildRatesStructure(RatePlanData $ratePlan): array
  {
    $ratesStructure = [];

    // Group rates by room type
    $ratesByRoomType = $ratePlan->rates->groupBy('roomTypeCode');

    foreach ($ratesByRoomType as $roomTypeCode => $roomTypeRates) {
      $rateElement = [
        '_attributes' => ['RoomTypeCode' => $roomTypeCode],
        'RatePlans' => $this->buildRatePlansForRoomType($roomTypeRates)
      ];

      $ratesStructure[] = $rateElement;
    }

    return ['Rate' => $ratesStructure];
  }

  /**
   * Build RatePlans structure for a specific room type
   *
   * Creates RatePlan elements with proper date ranges and rate values
   * according to the operation type and HTNG requirements.
   *
   * @param \Illuminate\Support\Collection<RateData> $rates Rates for one room type
   * @return array<string, mixed> RatePlans structure
   */
  private function buildRatePlansForRoomType($rates): array
  {
    $ratePlans = [];

    // Group rates by date range to create separate RatePlan elements
    $ratesByDateRange = $rates->groupBy(function (RateData $rate) {
      return $rate->startDate->format('Y-m-d') . '_' . $rate->endDate->format('Y-m-d');
    });

    foreach ($ratesByDateRange as $dateRangeKey => $dateRangeRates) {
      $firstRate = $dateRangeRates->first();

      $ratePlanElement = [
        '_attributes' => $this->buildRatePlanAttributes($firstRate),
      ];

      // Add base guest amounts (1st and 2nd adult - mandatory for HTNG)
      $ratePlanElement['BaseByGuestAmts'] = $this->buildBaseByGuestAmts($firstRate);

      // Add additional guest amounts if present
      if ($firstRate->additionalAdultRate !== null || $firstRate->additionalChildRate !== null) {
        $ratePlanElement['AdditionalGuestAmounts'] = $this->buildAdditionalGuestAmounts($firstRate);
      }

      // Handle operation-specific elements
      $ratePlanElement = $this->addOperationSpecificElements($ratePlanElement, $firstRate);

      $ratePlans[] = $ratePlanElement;
    }

    return ['RatePlan' => $ratePlans];
  }

  /**
   * Build RatePlan attributes
   *
   * @param RateData $rate The rate to build attributes from
   * @return array<string, string> RatePlan attributes
   */
  private function buildRatePlanAttributes(RateData $rate): array
  {
    $attributes = [
      'RatePlanCode' => $rate->ratePlanCode,
      'Start' => $rate->startDate->format('Y-m-d'),
      'End' => $rate->endDate->format('Y-m-d'),
    ];

    // Add optional attributes
    if ($rate->maxGuestApplicable !== null) {
      $attributes['MaxGuestApplicable'] = (string) $rate->maxGuestApplicable;
    }

    if ($rate->restrictedDisplayIndicator !== null) {
      $attributes['RestrictedDisplayIndicator'] = $rate->restrictedDisplayIndicator ? 'true' : 'false';
    }

    if ($rate->isCommissionable !== null) {
      $attributes['IsCommissionable'] = $rate->isCommissionable ? 'true' : 'false';
    }

    if ($rate->ratePlanQualifier !== null) {
      $attributes['RatePlanQualifier'] = $rate->ratePlanQualifier;
    }

    if ($rate->marketCode !== null) {
      $attributes['MarketCode'] = $rate->marketCode;
    }

    return $attributes;
  }

  /**
   * Build BaseByGuestAmts structure (1st and 2nd adult rates)
   *
   * This is mandatory according to HTNG 2011B certification requirements.
   * Both first and second adult rates must be present.
   *
   * @param RateData $rate The rate to extract guest amounts from
   * @return array<string, mixed> BaseByGuestAmts structure
   */
  private function buildBaseByGuestAmts(RateData $rate): array
  {
    return [
      'BaseByGuestAmt' => [
        [
          '_attributes' => [
            'NumberOfGuests' => '1',
            'AmountBeforeTax' => number_format($rate->firstAdultRate, 2, '.', ''),
          ],
        ],
        [
          '_attributes' => [
            'NumberOfGuests' => '2',
            'AmountBeforeTax' => number_format($rate->secondAdultRate, 2, '.', ''),
          ],
        ],
      ],
    ];
  }

  /**
   * Build AdditionalGuestAmounts structure
   *
   * Optional element for additional adult and child rates beyond the standard
   * two adults included in BaseByGuestAmts.
   *
   * @param RateData $rate The rate to extract additional amounts from
   * @return array<string, mixed> AdditionalGuestAmounts structure
   */
  private function buildAdditionalGuestAmounts(RateData $rate): array
  {
    $additionalAmounts = [];

    if ($rate->additionalAdultRate !== null) {
      $additionalAmounts[] = [
        '_attributes' => [
          'AgeQualifyingCode' => '10', // Adult
          'Amount' => number_format($rate->additionalAdultRate, 2, '.', ''),
        ],
      ];
    }

    if ($rate->additionalChildRate !== null) {
      $additionalAmounts[] = [
        '_attributes' => [
          'AgeQualifyingCode' => '8', // Child
          'Amount' => number_format($rate->additionalChildRate, 2, '.', ''),
        ],
      ];
    }

    return ['AdditionalGuestAmount' => $additionalAmounts];
  }

  /**
   * Add operation-specific elements to RatePlan
   *
   * Different operation types may require additional elements or modifications
   * to the standard RatePlan structure.
   *
   * @param array<string, mixed> $ratePlanElement Current RatePlan element
   * @param RateData $rate The rate data
   * @return array<string, mixed> Enhanced RatePlan element
   */
  private function addOperationSpecificElements(array $ratePlanElement, RateData $rate): array
  {
    switch ($this->operationType) {
      case RateOperationType::RATE_CREATION:
        // For creation, we might need additional metadata
        if (!empty($rate->ratePlanQualifier)) {
          $ratePlanElement['_attributes']['RatePlanQualifier'] = $rate->ratePlanQualifier;
        }
        break;

      case RateOperationType::INACTIVE_RATE:
        // For inactive rates, set the status
        $ratePlanElement['_attributes']['Status'] = 'Inactive';
        break;

      case RateOperationType::REMOVE_ROOM_TYPES:
        // For room type removal, we need special handling
        $ratePlanElement['_attributes']['RemoveFromInventory'] = 'true';
        break;

      case RateOperationType::FULL_SYNC:
        // Full sync might include additional validation flags
        $ratePlanElement['_attributes']['SyncType'] = 'Full';
        break;

      case RateOperationType::DELTA_UPDATE:
        // Delta updates are the default, no special attributes needed
        break;
    }

    return $ratePlanElement;
  }

  /**
   * Get OTA message attributes specific to rate operations
   *
   * @return array<string, string> Message attributes
   */
  protected function getOtaMessageAttributes(): array
  {
    $attributes = parent::getOtaMessageAttributes();

    // Add rate-specific attributes
    $attributes['MessageType'] = $this->operationType->value;

    // Add delta indicator if applicable
    if ($this->isDeltaUpdate && in_array($this->operationType, RateOperationType::getBatchableOperations())) {
      $attributes['UpdateType'] = 'Delta';
    } elseif (!$this->isDeltaUpdate || $this->operationType === RateOperationType::FULL_SYNC) {
      $attributes['UpdateType'] = 'Full';
    }

    return $attributes;
  }

  /**
   * Set operation type for the builder
   *
   * Allows changing the operation type after instantiation, useful for
   * builders that handle multiple operation types.
   *
   * @param RateOperationType $operationType The operation type to set
   * @return self
   */
  public function withOperationType(RateOperationType $operationType): self
  {
    $this->operationType = $operationType;
    $this->maxRatePlansPerMessage = $operationType->getRecommendedBatchSize();
    return $this;
  }

  /**
   * Set delta update mode
   *
   * @param bool $isDeltaUpdate Whether to use delta updates
   * @return self
   */
  public function withDeltaUpdate(bool $isDeltaUpdate = true): self
  {
    $this->isDeltaUpdate = $isDeltaUpdate;
    return $this;
  }

  /**
   * Get the operation type
   *
   * @return RateOperationType
   */
  public function getOperationType(): RateOperationType
  {
    return $this->operationType;
  }

  /**
   * Check if delta updates are enabled
   *
   * @return bool
   */
  public function isDeltaUpdate(): bool
  {
    return $this->isDeltaUpdate;
  }

  /**
   * Get the maximum rate plans per message for current operation
   *
   * @return int
   */
  public function getMaxRatePlansPerMessage(): int
  {
    return $this->maxRatePlansPerMessage;
  }

  /**
   * Get linked rate configuration summary
   *
   * Useful for debugging and understanding how linked rates will be handled.
   *
   * @return array<string, mixed> Linked rate configuration
   */
  public function getLinkedRateConfig(): array
  {
    return [
      'external_handles_linked' => $this->externalHandlesLinkedRates,
      'operation_supports_linked' => $this->operationType->supportsLinkedRates(),
      'will_send_linked_rates' => $this->linkedRateHandler->shouldSendLinkedRates($this->operationType),
      'strategy' => $this->linkedRateHandler->getLinkedRateStrategy($this->operationType),
    ];
  }

  /**
   * Build rate message with comprehensive validation and error handling
   *
   * This is the main entry point with additional safety measures and
   * detailed error reporting for rate-specific issues.
   *
   * @param array<string, mixed> $messageData The rate data to build
   * @return string The complete XML message
   * @throws InvalidArgumentException If rate data is invalid
   */
  public function buildWithValidation(array $messageData): string
  {
    try {
      // Pre-validate rate plans
      if (!isset($messageData['rate_plans'])) {
        throw new InvalidArgumentException('Rate plans are required');
      }

      $ratePlans = collect($messageData['rate_plans'])->map(function ($ratePlan) {
        return $ratePlan instanceof RatePlanData ? $ratePlan : RatePlanData::fromArray($ratePlan);
      });

      // Generate summary for logging/debugging
      $summary = $this->generateBuildSummary($ratePlans);

      // Perform the build
      $xml = $this->build($messageData);

      // Log success if configured
      if (config('travelclick.logging.log_successful_operations', true)) {
        logger()->info('Rate XML built successfully', [
          'operation_type' => $this->operationType->value,
          'is_delta' => $this->isDeltaUpdate,
          'summary' => $summary,
        ]);
      }

      return $xml;
    } catch (\Exception $e) {
      // Enhanced error logging for rate-specific issues
      logger()->error('Rate XML build failed', [
        'operation_type' => $this->operationType->value,
        'is_delta' => $this->isDeltaUpdate,
        'error' => $e->getMessage(),
        'trace' => config('app.debug') ? $e->getTraceAsString() : null,
      ]);

      throw $e;
    }
  }

  /**
   * Generate build summary for logging and debugging
   *
   * @param \Illuminate\Support\Collection<RatePlanData> $ratePlans
   * @return array<string, mixed>
   */
  private function generateBuildSummary($ratePlans): array
  {
    return [
      'total_rate_plans' => $ratePlans->count(),
      'hotels' => $ratePlans->pluck('hotelCode')->unique()->toArray(),
      'operation_type' => $this->operationType->value,
      'is_delta_update' => $this->isDeltaUpdate,
      'total_rates' => $ratePlans->sum(fn($plan) => $plan->rates->count()),
      'room_types' => $ratePlans->flatMap(fn($plan) => $plan->roomTypes)->unique()->toArray(),
      'currencies' => $ratePlans->pluck('currencyCode')->unique()->toArray(),
      'date_range' => [
        'earliest' => $ratePlans->min('startDate')->format('Y-m-d'),
        'latest' => $ratePlans->max('endDate')->format('Y-m-d'),
      ],
      'linked_rates_config' => $this->getLinkedRateConfig(),
    ];
  }
}
