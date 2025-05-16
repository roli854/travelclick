<?php

declare(strict_types=1);

namespace App\TravelClick\Builders;

use App\TravelClick\DTOs\RateData;
use App\TravelClick\DTOs\RatePlanData;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\RateOperationType;
use App\TravelClick\Support\RateStructureValidator;
use App\TravelClick\Support\LinkedRateHandler;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * XML Builder for HTNG 2011B Rate Notification Messages
 *
 * This builder constructs OTA_HotelRateNotifRQ messages for synchronizing
 * rate plans and pricing data with TravelClick. It handles all four types
 * of rate operations and ensures compliance with HTNG 2011B specifications.
 *
 * Key Features:
 * - Support for all rate operation types (UPDATE, CREATION, INACTIVE, REMOVE)
 * - Mandatory 1st/2nd adult rates for certification compliance
 * - Linked rates handling with configurable strategy
 * - Delta vs overlay upload support
 * - Comprehensive validation using business rules
 */
class RateXmlBuilder extends XmlBuilder
{
  /**
   * Rate structure validator
   */
  protected RateStructureValidator $rateValidator;

  /**
   * Linked rate handler
   */
  protected LinkedRateHandler $linkedRateHandler;

  /**
   * Current operation type
   */
  protected RateOperationType $operationType;

  /**
   * Whether this is a delta upload (true) or overlay upload (false)
   */
  protected bool $isDeltaUpload = true;

  public function __construct(
    SoapHeaderDto $soapHeaders,
    RateOperationType $operationType = RateOperationType::UPDATE,
    bool $isDeltaUpload = true,
    bool $validateXml = true,
    bool $formatOutput = false
  ) {
    parent::__construct(
      MessageType::RATES,
      $soapHeaders,
      $validateXml,
      $formatOutput
    );

    $this->operationType = $operationType;
    $this->isDeltaUpload = $isDeltaUpload;
    $this->rateValidator = new RateStructureValidator();
    $this->linkedRateHandler = new LinkedRateHandler();
  }

  /**
   * Validate the message data for rate notifications
   *
   * @param array<string, mixed> $messageData Expected format:
   *   - hotel_code: string
   *   - rate_plans: array of rate plan data
   *   - operation_type: string (optional, override constructor)
   * @throws InvalidArgumentException If validation fails
   */
  protected function validateMessageData(array $messageData): void
  {
    // Validate required fields
    if (empty($messageData['hotel_code'])) {
      throw new InvalidArgumentException('Hotel code is required');
    }

    if (empty($messageData['rate_plans']) || !is_array($messageData['rate_plans'])) {
      throw new InvalidArgumentException('Rate plans array is required');
    }

    // Validate hotel code format
    $this->validateHotelCode($messageData['hotel_code']);

    // Override operation type if provided
    if (isset($messageData['operation_type'])) {
      $this->operationType = RateOperationType::from($messageData['operation_type']);
    }

    // Validate batch size according to operation type
    $maxBatchSize = $this->operationType->getRecommendedBatchSize();
    if (count($messageData['rate_plans']) > $maxBatchSize) {
      throw new InvalidArgumentException(
        "Batch size ({$messageData['rate_plans']}) exceeds recommended maximum ($maxBatchSize) for {$this->operationType->value} operations"
      );
    }

    // Convert and validate each rate plan
    $ratePlans = $this->convertToRatePlanData($messageData['rate_plans']);

    // Validate business rules for all rate plans
    foreach ($ratePlans as $ratePlan) {
      $this->rateValidator->validateRatePlan($ratePlan, $this->operationType);
    }

    // Validate consistency across all rate plans
    $this->validateRatePlansConsistency($ratePlans);
  }

  /**
   * Build the message body for rate notifications
   *
   * @param array<string, mixed> $messageData
   * @return array<string, mixed>
   */
  protected function buildMessageBody(array $messageData): array
  {
    // Convert rate plans to DTOs
    $ratePlans = $this->convertToRatePlanData($messageData['rate_plans']);

    // Handle linked rates according to configuration
    $ratePlans = $this->processLinkedRates($ratePlans);

    // Build the OTA_HotelRateNotifRQ structure
    return [
      $this->getOtaRootElement() => array_merge(
        ['_attributes' => $this->getOtaMessageAttributes()],
        $this->buildRatesElement($messageData['hotel_code'], $ratePlans)
      ),
    ];
  }

  /**
   * Convert rate plan arrays to RatePlanData DTOs
   *
   * @param array<array<string, mixed>> $ratePlansData
   * @return Collection<RatePlanData>
   */
  protected function convertToRatePlanData(array $ratePlansData): Collection
  {
    return collect($ratePlansData)->map(function (array $planData): RatePlanData {
      // Convert individual rates to RateData DTOs
      $rates = collect($planData['rates'] ?? [])->map(function (array $rateData): RateData {
        return RateData::fromArray($rateData);
      });

      // Create RatePlanData with the converted rates
      return new RatePlanData(
        ratePlanCode: $planData['rate_plan_code'],
        ratePlanName: $planData['rate_plan_name'] ?? '',
        rates: $rates,
        currency: $planData['currency'] ?? 'USD',
        isLinked: $planData['is_linked'] ?? false,
        linkedToCode: $planData['linked_to_code'] ?? null,
        linkType: $planData['link_type'] ?? null,
        linkValue: $planData['link_value'] ?? null
      );
    });
  }

  /**
   * Process linked rates according to system configuration
   *
   * @param Collection<RatePlanData> $ratePlans
   * @return Collection<RatePlanData>
   */
  protected function processLinkedRates(Collection $ratePlans): Collection
  {
    // Check if external system handles linked rates
    $externalSystemHandlesLinked = config('travelclick.message_types.rates.supports_linked_rates', true);

    if ($externalSystemHandlesLinked) {
      // Filter out linked rates if external system handles them
      $ratePlans = $ratePlans->map(function (RatePlanData $ratePlan): RatePlanData {
        return $ratePlan->filterLinkedRatesIfNeeded(true);
      });
    } else {
      // Calculate derived rates if we handle linking
      $ratePlans = $this->linkedRateHandler->calculateDerivedRates($ratePlans);
    }

    // Get recommendation for strategy
    $recommendation = $this->linkedRateHandler->recommendStrategy($ratePlans, $this->operationType);

    // Log recommendation for operational insight
    logger()->info('Linked rate strategy', [
      'operation_type' => $this->operationType->value,
      'total_plans' => $ratePlans->count(),
      'recommendation' => $recommendation,
    ]);

    return $ratePlans;
  }

  /**
   * Build the main Rates element
   *
   * @param string $hotelCode
   * @param Collection<RatePlanData> $ratePlans
   * @return array<string, mixed>
   */
  protected function buildRatesElement(string $hotelCode, Collection $ratePlans): array
  {
    return [
      'Rates' => array_merge(
        ['_attributes' => ['HotelCode' => $hotelCode]],
        $this->buildRatePlansXml($ratePlans)
      ),
    ];
  }

  /**
   * Build XML structure for all rate plans
   *
   * @param Collection<RatePlanData> $ratePlans
   * @return array<string, mixed>
   */
  protected function buildRatePlansXml(Collection $ratePlans): array
  {
    $ratePlansXml = [];

    foreach ($ratePlans as $ratePlan) {
      // Group rates by room type for efficient XML structure
      $ratesByRoomType = $ratePlan->groupRatesByRoomType();

      foreach ($ratesByRoomType as $roomType => $rates) {
        // Build base rate element
        $rateElement = $this->buildBaseRateElement($ratePlan, $roomType, $rates);

        // Add operation-specific attributes
        $rateElement = $this->addOperationSpecificAttributes($rateElement, $ratePlan);

        // Add rate plans (room-rate combinations)
        $rateElement = $this->addRatePlansToElement($rateElement, $ratePlan, $rates);

        $ratePlansXml[] = $rateElement;
      }
    }

    return ['Rate' => $ratePlansXml];
  }

  /**
   * Build the base rate element structure
   *
   * @param RatePlanData $ratePlan
   * @param string $roomType
   * @param Collection<RateData> $rates
   * @return array<string, mixed>
   */
  protected function buildBaseRateElement(
    RatePlanData $ratePlan,
    string $roomType,
    Collection $rates
  ): array {
    // Get date range for this room type
    $dateRange = $this->calculateDateRange($rates);

    return [
      '_attributes' => [
        'RatePlanCode' => $ratePlan->ratePlanCode,
        'CurrencyCode' => $ratePlan->currency,
      ],
      'StatusApplicationControl' => $this->buildStatusApplicationControl(
        startDate: $dateRange['start'],
        endDate: $dateRange['end'],
        roomTypeCode: $roomType,
        ratePlanCode: $ratePlan->ratePlanCode
      ),
    ];
  }

  /**
   * Add operation-specific attributes to rate element
   *
   * @param array<string, mixed> $rateElement
   * @param RatePlanData $ratePlan
   * @return array<string, mixed>
   */
  protected function addOperationSpecificAttributes(
    array $rateElement,
    RatePlanData $ratePlan
  ): array {
    switch ($this->operationType) {
      case RateOperationType::CREATION:
        $rateElement['_attributes']['Operation'] = 'Create';
        break;

      case RateOperationType::INACTIVE:
        $rateElement['_attributes']['Operation'] = 'Inactive';
        // For inactive operations, we don't need rate details
        return $rateElement;

      case RateOperationType::REMOVE_ROOM_TYPES:
        $rateElement['_attributes']['Operation'] = 'Remove';
        return $rateElement;

      case RateOperationType::UPDATE:
      default:
        // UPDATE is default, no special operation attribute needed
        break;
    }

    // Add linked rate attributes if applicable
    if ($ratePlan->isLinked && $ratePlan->linkedToCode) {
      $rateElement['_attributes']['BaseRatePlanCode'] = $ratePlan->linkedToCode;

      if ($ratePlan->linkType === 'percentage') {
        $rateElement['_attributes']['RateChangeIndicator'] = 'Percentage';
        $rateElement['_attributes']['RateChangeValue'] = (string) $ratePlan->linkValue;
      } elseif ($ratePlan->linkType === 'offset') {
        $rateElement['_attributes']['RateChangeIndicator'] = 'Amount';
        $rateElement['_attributes']['RateChangeValue'] = (string) $ratePlan->linkValue;
      }
    }

    return $rateElement;
  }

  /**
   * Add rate plans (room-rate combinations) to the element
   *
   * @param array<string, mixed> $rateElement
   * @param RatePlanData $ratePlan
   * @param Collection<RateData> $rates
   * @return array<string, mixed>
   */
  protected function addRatePlansToElement(
    array $rateElement,
    RatePlanData $ratePlan,
    Collection $rates
  ): array {
    // Skip adding rates for inactive/remove operations
    if (in_array($this->operationType, [RateOperationType::INACTIVE, RateOperationType::REMOVE_ROOM_TYPES])) {
      return $rateElement;
    }

    // Group rates by date for efficient processing
    $ratesByDate = $rates->groupBy('effectiveDate');

    $ratePlans = [];

    foreach ($ratesByDate as $effectiveDate => $dateRates) {
      foreach ($dateRates as $rate) {
        $ratePlans[] = $this->buildSingleRatePlan($rate, $ratePlan);
      }
    }

    if (!empty($ratePlans)) {
      $rateElement['RatePlans'] = ['RatePlan' => $ratePlans];
    }

    return $rateElement;
  }

  /**
   * Build a single rate plan element
   *
   * @param RateData $rate
   * @param RatePlanData $ratePlan
   * @return array<string, mixed>
   */
  protected function buildSingleRatePlan(RateData $rate, RatePlanData $ratePlan): array
  {
    $ratePlanElement = [];

    // Add mandatory adult rates (required for HTNG 2011B certification)
    if ($rate->adultRate1 !== null || $rate->adultRate2 !== null) {
      $baseByGuestAmts = [];

      // 1st adult rate (mandatory)
      if ($rate->adultRate1 !== null) {
        $baseByGuestAmts[] = [
          '_attributes' => [
            'NumberOfGuests' => '1',
            'AmountBeforeTax' => number_format($rate->adultRate1, 2, '.', ''),
          ],
        ];
      }

      // 2nd adult rate (mandatory)
      if ($rate->adultRate2 !== null) {
        $baseByGuestAmts[] = [
          '_attributes' => [
            'NumberOfGuests' => '2',
            'AmountBeforeTax' => number_format($rate->adultRate2, 2, '.', ''),
          ],
        ];
      }

      $ratePlanElement['BaseByGuestAmts'] = ['BaseByGuestAmt' => $baseByGuestAmts];
    }

    // Add additional guest amounts if present
    $additionalAmounts = [];

    if ($rate->additionalAdultRate !== null) {
      $additionalAmounts[] = [
        '_attributes' => [
          'AgeQualifyingCode' => '10', // Adult code
          'Amount' => number_format($rate->additionalAdultRate, 2, '.', ''),
        ],
      ];
    }

    if ($rate->additionalChildRate !== null) {
      $additionalAmounts[] = [
        '_attributes' => [
          'AgeQualifyingCode' => '8', // Child code
          'Amount' => number_format($rate->additionalChildRate, 2, '.', ''),
        ],
      ];
    }

    if (!empty($additionalAmounts)) {
      $ratePlanElement['AdditionalGuestAmounts'] = [
        'AdditionalGuestAmount' => $additionalAmounts,
      ];
    }

    // Add effective date if different from plan date range
    if ($rate->effectiveDate) {
      $ratePlanElement['_attributes']['EffectiveDate'] = $rate->effectiveDate;
    }

    // Add optional attributes
    if ($rate->rateBasis) {
      $ratePlanElement['_attributes']['RateBasis'] = $rate->rateBasis;
    }

    return $ratePlanElement;
  }

  /**
   * Calculate date range for a collection of rates
   *
   * @param Collection<RateData> $rates
   * @return array{start: string, end: string}
   */
  protected function calculateDateRange(Collection $rates): array
  {
    $startDates = $rates->pluck('effectiveDate')->filter()->sort();
    $endDates = $rates->pluck('expirationDate')->filter()->sort();

    return [
      'start' => $startDates->first() ?: now()->format('Y-m-d'),
      'end' => $endDates->last() ?: now()->addYear()->format('Y-m-d'),
    ];
  }

  /**
   * Validate consistency across multiple rate plans
   *
   * @param Collection<RatePlanData> $ratePlans
   * @throws InvalidArgumentException If inconsistencies found
   */
  protected function validateRatePlansConsistency(Collection $ratePlans): void
  {
    // Check currency consistency
    $currencies = $ratePlans->pluck('currency')->unique();
    if ($currencies->count() > 1) {
      throw new InvalidArgumentException(
        'All rate plans must use the same currency. Found: ' . $currencies->implode(', ')
      );
    }

    // Check for duplicate rate plan codes
    $ratePlanCodes = $ratePlans->pluck('ratePlanCode');
    $duplicates = $ratePlanCodes->duplicates();
    if ($duplicates->isNotEmpty()) {
      throw new InvalidArgumentException(
        'Duplicate rate plan codes found: ' . $duplicates->implode(', ')
      );
    }

    // Validate linked rate references
    $this->validateLinkedRateReferences($ratePlans);
  }

  /**
   * Validate that linked rates reference valid master rates
   *
   * @param Collection<RatePlanData> $ratePlans
   * @throws InvalidArgumentException If invalid references found
   */
  protected function validateLinkedRateReferences(Collection $ratePlans): void
  {
    $masterRateCodes = $ratePlans
      ->filter(fn(RatePlanData $plan) => !$plan->isLinked)
      ->pluck('ratePlanCode')
      ->toArray();

    $invalidReferences = $ratePlans
      ->filter(fn(RatePlanData $plan) => $plan->isLinked)
      ->filter(fn(RatePlanData $plan) => !in_array($plan->linkedToCode, $masterRateCodes))
      ->pluck('ratePlanCode');

    if ($invalidReferences->isNotEmpty()) {
      throw new InvalidArgumentException(
        'Invalid linked rate references found: ' . $invalidReferences->implode(', ')
      );
    }
  }

  /**
   * Set the operation type for this builder
   *
   * @param RateOperationType $operationType
   * @return self
   */
  public function withOperationType(RateOperationType $operationType): self
  {
    $this->operationType = $operationType;
    return $this;
  }

  /**
   * Set whether this is a delta upload
   *
   * @param bool $isDelta
   * @return self
   */
  public function withDeltaUpload(bool $isDelta = true): self
  {
    $this->isDeltaUpload = $isDelta;
    return $this;
  }

  /**
   * Get the current operation type
   *
   * @return RateOperationType
   */
  public function getOperationType(): RateOperationType
  {
    return $this->operationType;
  }

  /**
   * Check if this is a delta upload
   *
   * @return bool
   */
  public function isDeltaUpload(): bool
  {
    return $this->isDeltaUpload;
  }

  /**
   * Get validation summary for debugging
   *
   * @param array<string, mixed> $messageData
   * @return array<string, mixed>
   */
  public function getValidationSummary(array $messageData): array
  {
    $ratePlans = $this->convertToRatePlanData($messageData['rate_plans'] ?? []);

    $summary = [
      'total_rate_plans' => $ratePlans->count(),
      'operation_type' => $this->operationType->value,
      'is_delta_upload' => $this->isDeltaUpload,
      'currencies' => $ratePlans->pluck('currency')->unique()->values(),
      'linked_rates_count' => $ratePlans->filter(fn($p) => $p->isLinked)->count(),
      'master_rates_count' => $ratePlans->filter(fn($p) => !$p->isLinked)->count(),
    ];

    // Add individual rate plan summaries
    $summary['rate_plans'] = $ratePlans->map(function (RatePlanData $plan) {
      return $this->rateValidator->getValidationSummary($plan);
    })->toArray();

    // Add linked rate analysis
    $summary['linked_rate_analysis'] = $this->linkedRateHandler->getLinkedRateSummary($ratePlans);

    return $summary;
  }
}
