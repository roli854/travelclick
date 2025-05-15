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
     * Check guest information rules for reservations
     */
    protected function checkGuestInformationRules(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'guest_information_rules';

        // Lead guest validation
        $leadGuest = $data['lead_guest'] ?? [];

        // Required fields for lead guest
        $requiredFields = ['first_name', 'last_name'];
        foreach ($requiredFields as $field) {
            if (empty($leadGuest[$field])) {
                $results['valid'] = false;
                $results['errors'][] = "Lead guest {$field} is required";
            }
        }

        // Email validation for lead guest
        if (!empty($leadGuest['email']) && !filter_var($leadGuest['email'], FILTER_VALIDATE_EMAIL)) {
            $results['valid'] = false;
            $results['errors'][] = "Lead guest email is invalid";
        }

        // Phone validation (if provided)
        if (!empty($leadGuest['phone'])) {
            $phone = preg_replace('/[^0-9+\-\(\)\s]/', '', $leadGuest['phone']);
            if (strlen($phone) < 7) {
                $results['warnings'][] = "Lead guest phone number appears to be too short";
            }
        }

        // Additional guests validation
        if (isset($data['additional_guests'])) {
            foreach ($data['additional_guests'] as $index => $guest) {
                if (empty($guest['first_name']) && empty($guest['last_name'])) {
                    $results['warnings'][] = "Additional guest {$index} has no name information";
                }

                // Age validation for children
                if (isset($guest['age']) && $guest['age'] < 0) {
                    $results['valid'] = false;
                    $results['errors'][] = "Additional guest {$index} has invalid age";
                }
            }
        }
    }

    /**
     * Check room stay rules for reservations
     */
    protected function checkRoomStayRules(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'room_stay_rules';

        $roomStays = $data['room_stays'] ?? [];

        if (empty($roomStays)) {
            $results['valid'] = false;
            $results['errors'][] = "At least one room stay is required";
            return;
        }

        foreach ($roomStays as $index => $roomStay) {
            $arrivalDate = $roomStay['arrival_date'] ?? null;
            $departureDate = $roomStay['departure_date'] ?? null;
            $roomTypeCode = $roomStay['room_type_code'] ?? null;

            // Required fields
            if (!$arrivalDate) {
                $results['valid'] = false;
                $results['errors'][] = "Room stay {$index}: Arrival date is required";
            }

            if (!$departureDate) {
                $results['valid'] = false;
                $results['errors'][] = "Room stay {$index}: Departure date is required";
            }

            if (!$roomTypeCode) {
                $results['valid'] = false;
                $results['errors'][] = "Room stay {$index}: Room type code is required";
            }

            // Date consistency
            if ($arrivalDate && $departureDate) {
                try {
                    $arrival = Carbon::parse($arrivalDate);
                    $departure = Carbon::parse($departureDate);

                    if ($departure->lte($arrival)) {
                        $results['valid'] = false;
                        $results['errors'][] = "Room stay {$index}: Departure date must be after arrival date";
                    }

                    // Maximum stay length validation (typically 30 days)
                    if ($arrival->diffInDays($departure) > 30) {
                        $results['warnings'][] = "Room stay {$index}: Stay length exceeds 30 days - verify this is correct";
                    }

                    // Past date validation
                    if ($arrival->isPast() && $arrival->diffInDays(now()) > 1) {
                        $results['warnings'][] = "Room stay {$index}: Arrival date is in the past";
                    }

                    // Too far in future validation (typically 2 years)
                    if ($arrival->diffInDays(now()) > 730) {
                        $results['warnings'][] = "Room stay {$index}: Arrival date is more than 2 years in the future";
                    }
                } catch (\Exception $e) {
                    $results['valid'] = false;
                    $results['errors'][] = "Room stay {$index}: Invalid date format";
                }
            }

            // Rate plan validation
            if (isset($roomStay['rate_plan_code'])) {
                $ratePlan = $roomStay['rate_plan_code'];
                if (empty($ratePlan)) {
                    $results['warnings'][] = "Room stay {$index}: Empty rate plan code";
                }
            }
        }
    }

    /**
     * Check occupancy rules for reservations
     */
    protected function checkOccupancyRules(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'occupancy_rules';

        $roomStays = $data['room_stays'] ?? [];

        foreach ($roomStays as $index => $roomStay) {
            $adults = $roomStay['adults'] ?? 0;
            $children = $roomStay['children'] ?? 0;
            $infants = $roomStay['infants'] ?? 0;

            // Minimum occupancy validation
            if ($adults <= 0) {
                $results['valid'] = false;
                $results['errors'][] = "Room stay {$index}: At least 1 adult is required";
            }

            // Maximum occupancy validation (typically 4-6 guests total)
            $totalGuests = $adults + $children + $infants;
            if ($totalGuests > 6) {
                $results['warnings'][] = "Room stay {$index}: High guest count ({$totalGuests}) - verify this is correct";
            }

            // Adults vs children ratio check
            if ($children > 0 && $adults === 0) {
                $results['valid'] = false;
                $results['errors'][] = "Room stay {$index}: Children cannot stay without adults";
            }

            // Excessive children validation
            if ($children > 4) {
                $results['warnings'][] = "Room stay {$index}: High number of children ({$children}) - verify this is correct";
            }

            // Guest details consistency
            $guestDetails = $roomStay['guest_details'] ?? [];
            $totalDetailsProvided = count($guestDetails);
            $expectedGuests = $adults + $children;

            if ($totalDetailsProvided > 0 && $totalDetailsProvided !== $expectedGuests) {
                $results['warnings'][] = "Room stay {$index}: Guest details count ({$totalDetailsProvided}) doesn't match adult + children count ({$expectedGuests})";
            }
        }
    }

    /**
     * Check reservation date rules
     */
    protected function checkReservationDateRules(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'reservation_date_rules';

        $bookingDate = $data['booking_date'] ?? null;
        $arrivalDate = null;
        $departureDate = null;

        // Get earliest arrival and latest departure from room stays
        foreach ($data['room_stays'] ?? [] as $roomStay) {
            $arrival = $roomStay['arrival_date'] ?? null;
            $departure = $roomStay['departure_date'] ?? null;

            if ($arrival && (!$arrivalDate || Carbon::parse($arrival)->lt(Carbon::parse($arrivalDate)))) {
                $arrivalDate = $arrival;
            }

            if ($departure && (!$departureDate || Carbon::parse($departure)->gt(Carbon::parse($departureDate)))) {
                $departureDate = $departure;
            }
        }

        // Booking date validation
        if ($bookingDate) {
            try {
                $booking = Carbon::parse($bookingDate);

                if ($arrivalDate) {
                    $arrival = Carbon::parse($arrivalDate);

                    // Booking should not be after arrival
                    if ($booking->gt($arrival)) {
                        $results['warnings'][] = "Booking date is after arrival date - verify this is correct";
                    }

                    // Booking too far before arrival (more than 1 year)
                    if ($booking->diffInDays($arrival) > 365) {
                        $results['warnings'][] = "Booking date is more than 1 year before arrival";
                    }
                }
            } catch (\Exception $e) {
                $results['valid'] = false;
                $results['errors'][] = "Invalid booking date format";
            }
        }

        // Cancellation date validation
        if (isset($data['cancellation_date'])) {
            try {
                $cancellation = Carbon::parse($data['cancellation_date']);

                if ($bookingDate) {
                    $booking = Carbon::parse($bookingDate);
                    if ($cancellation->lt($booking)) {
                        $results['valid'] = false;
                        $results['errors'][] = "Cancellation date cannot be before booking date";
                    }
                }

                if ($arrivalDate) {
                    $arrival = Carbon::parse($arrivalDate);
                    if ($cancellation->gt($arrival)) {
                        $results['warnings'][] = "Cancellation date is after arrival date";
                    }
                }
            } catch (\Exception $e) {
                $results['valid'] = false;
                $results['errors'][] = "Invalid cancellation date format";
            }
        }
    }

    /**
     * Check reservation type specific rules
     */
    protected function checkReservationTypeRules(array $data, ?ReservationType $type, array &$results): void
    {
        $results['rules_checked'][] = "reservation_type_rules_{$type?->value}";

        if (!$type) {
            return;
        }

        switch ($type) {
            case ReservationType::TRAVEL_AGENCY:
                $this->checkTravelAgencyRules($data, $results);
                break;

            case ReservationType::CORPORATE:
                $this->checkCorporateRules($data, $results);
                break;

            case ReservationType::GROUP:
                $this->checkGroupReservationRules($data, $results);
                break;

            case ReservationType::PACKAGE:
                $this->checkPackageRules($data, $results);
                break;

            case ReservationType::TRANSIENT:
                // No specific rules for transient - basic validations apply
                break;
        }
    }

    /**
     * Check travel agency specific rules
     */
    protected function checkTravelAgencyRules(array $data, array &$results): void
    {
        $travelAgency = $data['travel_agency'] ?? [];

        // Required fields for travel agency
        if (empty($travelAgency['iata_number'])) {
            $results['valid'] = false;
            $results['errors'][] = "Travel agency IATA number is required";
        } else {
            // IATA number format validation (typically 8-9 digits)
            $iataNumber = $travelAgency['iata_number'];
            if (!preg_match('/^\d{8,9}$/', $iataNumber)) {
                $results['warnings'][] = "Travel agency IATA number format appears invalid";
            }
        }

        if (empty($travelAgency['name'])) {
            $results['valid'] = false;
            $results['errors'][] = "Travel agency name is required";
        }

        // Commission validation
        if (isset($travelAgency['commission_rate'])) {
            $commissionRate = $travelAgency['commission_rate'];
            if ($commissionRate < 0 || $commissionRate > 50) {
                $results['warnings'][] = "Travel agency commission rate ({$commissionRate}%) appears unusual";
            }
        }
    }

    /**
     * Check corporate reservation rules
     */
    protected function checkCorporateRules(array $data, array &$results): void
    {
        $corporateInfo = $data['corporate_info'] ?? [];

        // Required fields for corporate
        if (empty($corporateInfo['company_name'])) {
            $results['valid'] = false;
            $results['errors'][] = "Corporate company name is required";
        }

        // Corporate code validation
        if (isset($corporateInfo['corporate_code'])) {
            $corporateCode = $corporateInfo['corporate_code'];
            if (empty($corporateCode)) {
                $results['warnings'][] = "Corporate code is empty";
            }
        } else {
            $results['warnings'][] = "Corporate code not provided";
        }

        // Contact information validation
        if (isset($corporateInfo['contact_email'])) {
            $email = $corporateInfo['contact_email'];
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results['valid'] = false;
                $results['errors'][] = "Corporate contact email is invalid";
            }
        }
    }

    /**
     * Check group reservation rules
     */
    protected function checkGroupReservationRules(array $data, array &$results): void
    {
        $groupCode = $data['group_code'] ?? null;

        if (empty($groupCode)) {
            $results['valid'] = false;
            $results['errors'][] = "Group code is required for group reservations";
            return;
        }

        // Group code format validation
        if (strlen($groupCode) < 3) {
            $results['warnings'][] = "Group code appears to be too short";
        }

        // Group name validation
        if (isset($data['group_name'])) {
            if (empty($data['group_name'])) {
                $results['warnings'][] = "Group name is empty";
            }
        }

        // Rooming list validation
        if (isset($data['rooming_list'])) {
            $roomingList = $data['rooming_list'];
            if (!is_array($roomingList) || empty($roomingList)) {
                $results['warnings'][] = "Group reservation rooming list is empty";
            }
        }
    }

    /**
     * Check package reservation rules
     */
    protected function checkPackageRules(array $data, array &$results): void
    {
        $packageCode = $data['package_code'] ?? null;

        if (empty($packageCode)) {
            $results['valid'] = false;
            $results['errors'][] = "Package code is required for package reservations";
            return;
        }

        // Package elements validation
        if (isset($data['package_elements'])) {
            $elements = $data['package_elements'];
            if (!is_array($elements) || empty($elements)) {
                $results['warnings'][] = "Package elements not specified";
            } else {
                foreach ($elements as $index => $element) {
                    if (empty($element['code']) || empty($element['description'])) {
                        $results['warnings'][] = "Package element {$index} missing code or description";
                    }
                }
            }
        }
    }

    /**
     * Check block allocation rules for group blocks
     */
    protected function checkBlockAllocationRules(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'block_allocation_rules';

        $invBlocks = $data['inv_blocks'] ?? [];

        foreach ($invBlocks as $blockIndex => $block) {
            $roomTypes = $block['room_types'] ?? [];

            foreach ($roomTypes as $roomIndex => $roomType) {
                $allocations = $roomType['room_type_allocations'] ?? [];

                // Check for all three required allocation types
                $allocationTypes = collect($allocations)->pluck('pickup_status')->toArray();
                $requiredTypes = [1, 2, 3]; // 1=Allocated, 2=Available, 3=Sold

                foreach ($requiredTypes as $type) {
                    if (!in_array($type, $allocationTypes)) {
                        $results['valid'] = false;
                        $results['errors'][] = "Block {$blockIndex}, Room type {$roomIndex}: Missing allocation type {$type}";
                    }
                }

                // Validate allocation math
                $allocated = 0;
                $available = 0;
                $sold = 0;

                foreach ($allocations as $allocation) {
                    $units = $allocation['number_of_units'] ?? 0;
                    match ($allocation['pickup_status'] ?? null) {
                        1 => $allocated = $units,
                        2 => $available = $units,
                        3 => $sold = $units,
                        default => null,
                    };
                }

                // Check allocation logic: Available + Sold should equal Allocated
                if ($allocated > 0 && ($available + $sold) !== $allocated) {
                    $results['valid'] = false;
                    $results['errors'][] = "Block {$blockIndex}, Room type {$roomIndex}: Allocation math error - Available({$available}) + Sold({$sold}) ≠ Allocated({$allocated})";
                }

                // Sold rooms cannot exceed allocated
                if ($sold > $allocated) {
                    $results['valid'] = false;
                    $results['errors'][] = "Block {$blockIndex}, Room type {$roomIndex}: Sold rooms ({$sold}) exceed allocated rooms ({$allocated})";
                }
            }
        }
    }

    /**
     * Check pickup allocation rules for group blocks
     */
    protected function checkPickupAllocationRules(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'pickup_allocation_rules';

        $invBlocks = $data['inv_blocks'] ?? [];

        foreach ($invBlocks as $blockIndex => $block) {
            $roomTypes = $block['room_types'] ?? [];

            foreach ($roomTypes as $roomIndex => $roomType) {
                $allocations = $roomType['room_type_allocations'] ?? [];

                foreach ($allocations as $allocation) {
                    $numberUnits = $allocation['number_of_units'] ?? 0;
                    $pickupStatus = $allocation['pickup_status'] ?? null;

                    // Negative numbers not allowed
                    if ($numberUnits < 0) {
                        $results['valid'] = false;
                        $results['errors'][] = "Block {$blockIndex}, Room type {$roomIndex}: Negative allocation units not allowed";
                    }

                    // Pickup status validation
                    if (!in_array($pickupStatus, [1, 2, 3])) {
                        $results['valid'] = false;
                        $results['errors'][] = "Block {$blockIndex}, Room type {$roomIndex}: Invalid pickup status '{$pickupStatus}'";
                    }

                    // Date range validation
                    $startDate = $allocation['start_date'] ?? null;
                    $endDate = $allocation['end_date'] ?? null;

                    if ($startDate && $endDate) {
                        try {
                            $start = Carbon::parse($startDate);
                            $end = Carbon::parse($endDate);

                            if ($end->lte($start)) {
                                $results['valid'] = false;
                                $results['errors'][] = "Block {$blockIndex}, Room type {$roomIndex}: Invalid date range";
                            }
                        } catch (\Exception $e) {
                            $results['valid'] = false;
                            $results['errors'][] = "Block {$blockIndex}, Room type {$roomIndex}: Invalid date format";
                        }
                    }
                }
            }
        }
    }

    /**
     * Check cutoff date rules for group blocks
     */
    protected function checkCutoffDateRules(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'cutoff_date_rules';

        $invBlocks = $data['inv_blocks'] ?? [];

        foreach ($invBlocks as $blockIndex => $block) {
            $blockDates = $block['inv_block_dates'] ?? [];
            $startDate = $blockDates['start'] ?? null;
            $cutoffDate = $blockDates['absolute_cutoff'] ?? null;

            if (!$startDate || !$cutoffDate) {
                continue;
            }

            try {
                $start = Carbon::parse($startDate);
                $cutoff = Carbon::parse($cutoffDate);

                // Cutoff should be before or on start date
                if ($cutoff->gt($start)) {
                    $results['valid'] = false;
                    $results['errors'][] = "Block {$blockIndex}: Cutoff date cannot be after block start date";
                }

                // Cutoff in the past warning
                if ($cutoff->isPast()) {
                    $results['warnings'][] = "Block {$blockIndex}: Cutoff date is in the past";
                }

                // Cutoff too far from start date
                $daysBetween = $cutoff->diffInDays($start);
                if ($daysBetween > 90) {
                    $results['warnings'][] = "Block {$blockIndex}: Cutoff date is more than 90 days before start date ({$daysBetween} days)";
                }
            } catch (\Exception $e) {
                $results['valid'] = false;
                $results['errors'][] = "Block {$blockIndex}: Invalid cutoff or start date format";
            }
        }
    }

    /**
     * Check room type allocation rules for group blocks
     */
    protected function checkRoomTypeAllocationRules(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'room_type_allocation_rules';

        $invBlocks = $data['inv_blocks'] ?? [];

        foreach ($invBlocks as $blockIndex => $block) {
            $roomTypes = $block['room_types'] ?? [];

            if (empty($roomTypes)) {
                $results['valid'] = false;
                $results['errors'][] = "Block {$blockIndex}: At least one room type is required";
                continue;
            }

            foreach ($roomTypes as $roomIndex => $roomType) {
                $roomTypeCode = $roomType['room_type_code'] ?? null;

                if (empty($roomTypeCode)) {
                    $results['valid'] = false;
                    $results['errors'][] = "Block {$blockIndex}, Room type {$roomIndex}: Room type code is required";
                }

                // Rate plans validation
                $ratePlans = $roomType['rate_plans'] ?? [];
                if (empty($ratePlans)) {
                    $results['warnings'][] = "Block {$blockIndex}, Room type {$roomIndex}: No rate plans specified";
                } else {
                    foreach ($ratePlans as $ratePlanIndex => $ratePlan) {
                        $ratePlanCode = $ratePlan['rate_plan_code'] ?? null;
                        if (empty($ratePlanCode)) {
                            $results['valid'] = false;
                            $results['errors'][] = "Block {$blockIndex}, Room type {$roomIndex}, Rate plan {$ratePlanIndex}: Rate plan code is required";
                        }

                        // Base amounts validation
                        $baseAmounts = $ratePlan['base_by_guest_amounts'] ?? [];
                        if (empty($baseAmounts)) {
                            $results['valid'] = false;
                            $results['errors'][] = "Block {$blockIndex}, Room type {$roomIndex}, Rate plan {$ratePlanIndex}: Base amounts are required";
                        }
                    }
                }
            }
        }
    }

    /**
     * Check group contact rules for group blocks
     */
    protected function checkGroupContactRules(array $data, array &$results): void
    {
        $results['rules_checked'][] = 'group_contact_rules';

        $invBlocks = $data['inv_blocks'] ?? [];

        foreach ($invBlocks as $blockIndex => $block) {
            $contacts = $block['contacts'] ?? [];

            if (empty($contacts)) {
                $results['warnings'][] = "Block {$blockIndex}: No contacts specified";
                continue;
            }

            $hasGroupOrganizer = false;

            foreach ($contacts as $contactIndex => $contact) {
                $contactType = $contact['contact_type'] ?? null;
                $personName = $contact['person_name'] ?? [];

                // Check for group organizer
                if ($contactType === 'GroupOrganizer') {
                    $hasGroupOrganizer = true;
                }

                // Validate person name
                if (empty($personName['given_name']) && empty($personName['surname'])) {
                    $results['valid'] = false;
                    $results['errors'][] = "Block {$blockIndex}, Contact {$contactIndex}: Person name is required";
                }

                // Email validation
                if (isset($contact['email'])) {
                    $email = $contact['email'];
                    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $results['valid'] = false;
                        $results['errors'][] = "Block {$blockIndex}, Contact {$contactIndex}: Invalid email format";
                    }
                }

                // Phone validation
                if (isset($contact['telephone'])) {
                    foreach ($contact['telephone'] as $teleIndex => $telephone) {
                        $phoneNumber = $telephone['phone_number'] ?? null;
                        if (empty($phoneNumber)) {
                            $results['warnings'][] = "Block {$blockIndex}, Contact {$contactIndex}, Phone {$teleIndex}: Phone number is empty";
                        }
                    }
                }

                // Company name for group company contacts
                if ($contactType === 'GroupCompany') {
                    if (empty($contact['company_name'])) {
                        $results['valid'] = false;
                        $results['errors'][] = "Block {$blockIndex}, Contact {$contactIndex}: Company name is required for GroupCompany contact";
                    }
                }
            }

            // Ensure group organizer is present
            if (!$hasGroupOrganizer) {
                $results['warnings'][] = "Block {$blockIndex}: No GroupOrganizer contact specified";
            }
        }
    }
}
