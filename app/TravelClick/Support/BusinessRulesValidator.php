<?php

declare(strict_types=1);

namespace App\TravelClick\Support;

use App\TravelClick\Enums\CountType;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\ReservationType;
use App\TravelClick\Enums\ValidationErrorType;
use App\TravelClick\Services\Contracts\ConfigurationServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * BusinessRulesValidator
 *
 * Validates business logic rules specific to HTNG 2011B operations.
 * Ensures compliance with TravelClick/iHotelier business requirements.
 */
class BusinessRulesValidator
{
    /**
     * Constructor
     */
    public function __construct(
        protected ConfigurationServiceInterface $configurationService
    ) {}

    /**
     * Validate inventory business rules
     *
     * @param array<string, mixed> $data Inventory data
     * @param string $operation Operation type (create, modify, remove)
     * @return array<string, mixed> Validation results
     */
    public function validateInventoryRules(array $data, string $operation): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'rules_checked' => [],
        ];

        // Rule 1: Inventory method consistency
        $this->checkInventoryMethodConsistency($data, $results);

        // Rule 2: Count type combinations
        $this->checkCountTypeCombinations($data, $results);

        // Rule 3: Physical rooms validation
        $this->checkPhysicalRoomsLogic($data, $results);

        // Rule 4: Date range business rules
        $this->checkInventoryDateRules($data, $results);

        // Rule 5: Oversell validation
        $this->checkOversellRules($data, $results);

        Log::debug('Inventory business rules validation completed', [
            'operation' => $operation,
            'rules_checked' => count($results['rules_checked']),
            'errors' => count($results['errors'])
        ]);

        return $results;
    }

    /**
     * Validate rate business rules
     *
     * @param array<string, mixed> $data Rate data
     * @param string $operation Operation type (create, modify, remove)
     * @return array<string, mixed> Validation results
     */
    public function validateRateRules(array $data, string $operation): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'rules_checked' => [],
        ];

        // Rule 1: Rate amount validations
        $this->checkRateAmountRules($data, $results);

        // Rule 2: Guest count requirements
        $this->checkGuestCountRequirements($data, $results);

        // Rule 3: Additional adult/child rates
        $this->checkAdditionalGuestRates($data, $results);

        // Rule 4: Rate date range rules
        $this->checkRateDateRules($data, $results);

        // Rule 5: Currency consistency
        $this->checkCurrencyConsistency($data, $results);

        Log::debug('Rate business rules validation completed', [
            'operation' => $operation,
            'rules_checked' => count($results['rules_checked']),
            'errors' => count($results['errors'])
        ]);

        return $results;
    }

    /**
     * Validate reservation business rules
     *
     * @param array<string, mixed> $data Reservation data
     * @param string $operation Operation type (create, modify, cancel)
     * @return array<string, mixed> Validation results
     */
    public function validateReservationRules(array $data, string $operation): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'rules_checked' => [],
        ];

        // Rule 1: Guest information completeness
        $this->checkGuestInformationRules($data, $results);

        // Rule 2: Room stay consistency
        $this->checkRoomStayRules($data, $results);

        // Rule 3: Occupancy rules
        $this->checkOccupancyRules($data, $results);

        // Rule 4: Date consistency
        $this->checkReservationDateRules($data, $results);

        // Rule 5: Type-specific validations
        $reservationType = $this->detectReservationType($data);
        $this->checkReservationTypeRules($data, $reservationType, $results);

        Log::debug('Reservation business rules validation completed', [
            'operation' => $operation,
            'reservation_type' => $reservationType?->value,
            'rules_checked' => count($results['rules_checked']),
            'errors' => count($results['errors'])
        ]);

        return $results;
    }

    /**
     * Validate group block business rules
     *
     * @param array<string, mixed> $data Group block data
     * @param string $operation Operation type (create, modify, cancel)
     * @return array<string, mixed> Validation results
     */
    public function validateGroupBlockRules(array $data, string $operation): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'rules_checked' => [],
        ];

        // Rule 1: Block allocation consistency
        $this->checkBlockAllocationRules($data, $results);

        // Rule 2: Pickup allocation rules
        $this->checkPickupAllocationRules($data, $results);

        // Rule 3: Cutoff date validation
        $this->checkCutoffDateRules($data, $results);

        // Rule 4: Room type allocation rules
        $this->checkRoomTypeAllocationRules($data, $results);

        // Rule 5: Contact information requirements
        $this->checkGroupContactRules($data, $results);

        Log::debug('Group block business rules validation completed', [
            'operation' => $operation,
            'rules_checked' => count($results['rules_checked']),
            'errors' => count($results['errors'])
        ]);

        return $results;
    }

    /**
     * Check inventory method consistency
     */
    protected function checkInventoryMethodConsistency(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'inventory_method_consistency';

        $methods = [];

        foreach ($data['inventories'] ?? [] as $inventory) {
            if (isset($inventory['counts'])) {
                $method = $this->determineInventoryMethod($inventory['counts']);
                $methods[] = $method;
            }
        }

        $uniqueMethods = array_unique($methods);

        if (count($uniqueMethods) > 1) {
            $results['valid'] = false;
            $results['errors'][] = 'Mixed inventory methods not allowed - all items must use same method (calculated or not calculated)';
        }
    }

    /**
     * Check count type combinations
     */
    protected function checkCountTypeCombinations(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'count_type_combinations';

        foreach ($data['inventories'] ?? [] as $index => $inventory) {
            $counts = $inventory['counts'] ?? [];
            $method = $this->determineInventoryMethod($counts);

            if ($method === 'not_calculated') {
                // Not calculated method: only CountType 2 allowed
                if (count($counts) !== 1) {
                    $results['valid'] = false;
                    $results['errors'][] = "Inventory item {$index}: Not calculated method must have exactly one count";
                }

                $firstCount = reset($counts);
                if (($firstCount['count_type'] ?? null) !== CountType::AVAILABLE->value) {
                    $results['valid'] = false;
                    $results['errors'][] = "Inventory item {$index}: Not calculated method must use CountType 2 (Available Rooms)";
                }
            } else {
                // Calculated method: validate combinations
                $countTypes = array_column($counts, 'count_type');

                // CountType 4 (Definite Sold) is required
                if (!in_array(CountType::DEFINITE_SOLD->value, $countTypes)) {
                    $results['valid'] = false;
                    $results['errors'][] = "Inventory item {$index}: Calculated method requires CountType 4 (Definite Sold)";
                }

                // CountType 5 (Tentative) should be 0 when present
                $tentativeCount = collect($counts)
                    ->firstWhere('count_type', CountType::TENTATIVE_SOLD->value);

                if ($tentativeCount && ($tentativeCount['count'] ?? 0) !== 0) {
                    $results['warnings'][] = "Inventory item {$index}: CountType 5 (Tentative) is typically 0 in calculated method - tentative should be included in definite sold";
                }
            }
        }
    }

    /**
     * Check physical rooms logic
     */
    protected function checkPhysicalRoomsLogic(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'physical_rooms_logic';

        foreach ($data['inventories'] ?? [] as $index => $inventory) {
            $counts = $inventory['counts'] ?? [];
            $physicalCount = null;
            $definiteSold = 0;
            $tentativeSold = 0;
            $outOfOrder = 0;

            foreach ($counts as $count) {
                match ($count['count_type'] ?? null) {
                    CountType::PHYSICAL->value => $physicalCount = $count['count'] ?? 0,
                    CountType::DEFINITE_SOLD->value => $definiteSold = $count['count'] ?? 0,
                    CountType::TENTATIVE_SOLD->value => $tentativeSold = $count['count'] ?? 0,
                    CountType::OUT_OF_ORDER->value => $outOfOrder = $count['count'] ?? 0,
                    default => null,
                };
            }

            // If physical rooms are provided, validate logic
            if ($physicalCount !== null) {
                $totalUsed = $definiteSold + $tentativeSold + $outOfOrder;

                if ($totalUsed > $physicalCount) {
                    $results['warnings'][] = "Inventory item {$index}: Total used rooms ({$totalUsed}) exceeds physical rooms ({$physicalCount})";
                }
            }
        }
    }

    /**
     * Check inventory date rules
     */
    protected function checkInventoryDateRules(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'inventory_date_rules';

        foreach ($data['inventories'] ?? [] as $index => $inventory) {
            $startDate = $inventory['start_date'] ?? null;
            $endDate = $inventory['end_date'] ?? null;

            if (!$startDate || !$endDate) {
                continue;
            }

            try {
                $start = Carbon::parse($startDate);
                $end = Carbon::parse($endDate);

                // Maximum date range validation (365 days)
                if ($start->diffInDays($end) > 365) {
                    $results['warnings'][] = "Inventory item {$index}: Date range exceeds 365 days - consider splitting into smaller ranges";
                }

                // Future date validation
                if ($start->isPast() && $start->diffInDays(now()) > 1) {
                    $results['warnings'][] = "Inventory item {$index}: Start date is more than 1 day in the past";
                }
            } catch (\Exception $e) {
                // Date parsing already validated elsewhere
            }
        }
    }

    /**
     * Check oversell rules
     */
    protected function checkOversellRules(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'oversell_rules';

        foreach ($data['inventories'] ?? [] as $index => $inventory) {
            $counts = $inventory['counts'] ?? [];
            $oversellCount = null;

            foreach ($counts as $count) {
                if (($count['count_type'] ?? null) === CountType::OVERSELL->value) {
                    $oversellCount = $count['count'] ?? 0;
                    break;
                }
            }

            // If oversell is specified, warn about potential issues
            if ($oversellCount !== null && $oversellCount > 0) {
                $results['warnings'][] = "Inventory item {$index}: Oversell rooms specified ({$oversellCount}) - ensure this is intentional";

                // Warn if oversell is excessive
                if ($oversellCount > 10) {
                    $results['warnings'][] = "Inventory item {$index}: High oversell count ({$oversellCount}) - verify this is correct";
                }
            }
        }
    }

    /**
     * Check rate amount rules
     */
    protected function checkRateAmountRules(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'rate_amount_rules';

        foreach ($data['rates'] ?? [] as $index => $rate) {
            $baseAmount = $rate['base_amount'] ?? 0;

            // Minimum rate validation
            if ($baseAmount <= 0) {
                $results['valid'] = false;
                $results['errors'][] = "Rate item {$index}: Base amount must be greater than 0";
            }

            // Maximum rate validation (reasonableness check)
            if ($baseAmount > 100000) {
                $results['warnings'][] = "Rate item {$index}: Unusually high rate amount ({$baseAmount}) - verify this is correct";
            }

            // Guest amount validations
            if (isset($rate['guest_amounts'])) {
                $this->validateGuestAmountLogic($rate['guest_amounts'], $index, $results);
            }
        }
    }

    /**
     * Check guest count requirements
     */
    protected function checkGuestCountRequirements(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'guest_count_requirements';

        foreach ($data['rates'] ?? [] as $index => $rate) {
            $guestAmounts = $rate['guest_amounts'] ?? [];

            // Ensure rates for 1st and 2nd guest are provided
            $hasAdult1 = collect($guestAmounts)->contains('guest_count', 1);
            $hasAdult2 = collect($guestAmounts)->contains('guest_count', 2);

            if (!$hasAdult1) {
                $results['valid'] = false;
                $results['errors'][] = "Rate item {$index}: Rate for 1 adult is required";
            }

            if (!$hasAdult2) {
                $results['valid'] = false;
                $results['errors'][] = "Rate item {$index}: Rate for 2 adults is required";
            }
        }
    }

    /**
     * Check additional guest rates
     */
    protected function checkAdditionalGuestRates(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'additional_guest_rates';

        foreach ($data['rates'] ?? [] as $index => $rate) {
            if (isset($rate['additional_guests'])) {
                foreach ($rate['additional_guests'] as $addGuest) {
                    $ageQualifier = $addGuest['age_qualifier'] ?? null;
                    $amount = $addGuest['amount'] ?? 0;

                    // Validate age qualifiers
                    $validQualifiers = [8, 10]; // 8 = Child, 10 = Adult
                    if ($ageQualifier && !in_array($ageQualifier, $validQualifiers)) {
                        $results['valid'] = false;
                        $results['errors'][] = "Rate item {$index}: Invalid age qualifier '{$ageQualifier}' for additional guest";
                    }

                    // Additional adult rate should be positive
                    if ($ageQualifier === 10 && $amount <= 0) {
                        $results['warnings'][] = "Rate item {$index}: Additional adult rate is 0 or negative";
                    }
                }
            }
        }
    }

    /**
     * Check rate date rules
     */
    protected function checkRateDateRules(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'rate_date_rules';

        foreach ($data['rates'] ?? [] as $index => $rate) {
            $startDate = $rate['start_date'] ?? null;
            $endDate = $rate['end_date'] ?? null;

            if (!$startDate || !$endDate) {
                continue;
            }

            try {
                $start = Carbon::parse($startDate);
                $end = Carbon::parse($endDate);

                // Maximum date range validation (365 days)
                if ($start->diffInDays($end) > 365) {
                    $results['warnings'][] = "Rate item {$index}: Date range exceeds 365 days - consider splitting into smaller ranges";
                }

                // Rate effective date validation
                if ($start->isPast() && $start->diffInDays(now()) > 30) {
                    $results['warnings'][] = "Rate item {$index}: Rate effective date is more than 30 days in the past";
                }
            } catch (\Exception $e) {
                // Date parsing already validated elsewhere
            }
        }
    }

    /**
     * Check currency consistency
     */
    protected function checkCurrencyConsistency(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'currency_consistency';

        $currencies = [];

        foreach ($data['rates'] ?? [] as $index => $rate) {
            $currency = $rate['currency_code'] ?? null;

            if ($currency) {
                $currencies[] = $currency;
            }
        }

        $uniqueCurrencies = array_unique($currencies);

        if (count($uniqueCurrencies) > 1) {
            $results['warnings'][] = 'Multiple currencies detected in rate update - ensure this is intentional: ' . implode(', ', $uniqueCurrencies);
        }
    }

    /**
     * Determine inventory method from counts
     */
    protected function determineInventoryMethod(array $counts): string
    {
        if (count($counts) === 1) {
            $firstCount = reset($counts);
            if (($firstCount['count_type'] ?? null) === CountType::AVAILABLE->value) {
                return 'not_calculated';
            }
        }

        return 'calculated';
    }

    /**
     * Detect reservation type from data
     */
    protected function detectReservationType(array $data): ?ReservationType
    {
        if (isset($data['travel_agency'])) {
            return ReservationType::TRAVEL_AGENCY;
        }

        if (isset($data['corporate_info'])) {
            return ReservationType::CORPORATE;
        }

        if (isset($data['group_code'])) {
            return ReservationType::GROUP;
        }

        if (isset($data['package_code'])) {
            return ReservationType::PACKAGE;
        }

        return ReservationType::TRANSIENT;
    }

    /**
     * Validate guest amount logic
     */
    protected function validateGuestAmountLogic(array $guestAmounts, int $rateIndex, array &$results): void
    {
        // Check that 2-adult rate is greater than or equal to 1-adult rate
        $adult1Rate = null;
        $adult2Rate = null;

        foreach ($guestAmounts as $guestAmount) {
            if (($guestAmount['guest_count'] ?? null) === 1) {
                $adult1Rate = $guestAmount['amount'] ?? 0;
            } elseif (($guestAmount['guest_count'] ?? null) === 2) {
                $adult2Rate = $guestAmount['amount'] ?? 0;
            }
        }

        if ($adult1Rate !== null && $adult2Rate !== null) {
            if ($adult2Rate < $adult1Rate) {
                $results['warnings'][] = "Rate item {$rateIndex}: 2-adult rate ({$adult2Rate}) is less than 1-adult rate ({$adult1Rate})";
            }
        }
    }

    /**
     * Additional methods for other business rule checks:
     * - checkGuestInformationRules()
     * - checkRoomStayRules()
     * - checkOccupancyRules()
     * - checkReservationDateRules()
     * - checkReservationTypeRules()
     * - checkBlockAllocationRules()
     * - checkPickupAllocationRules()
     * - checkCutoffDateRules()
     * - checkRoomTypeAllocationRules()
     * - checkGroupContactRules()
     *
     * These would follow similar patterns to the examples above,
     * implementing specific business logic validation for each area.
     */
}
