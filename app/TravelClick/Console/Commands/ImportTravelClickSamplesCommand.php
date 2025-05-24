<?php

namespace App\TravelClick\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportTravelClickSamplesCommand extends Command
{
  protected $signature = 'travelclick:import-samples
                          {--source-dir= : Source directory containing TravelClick samples}
                          {--target-dir= : Target directory for BDD fixtures}
                          {--force : Overwrite existing fixture files}';

  protected $description = 'Import TravelClick XML samples and convert them to BDD fixtures with variables';

  protected array $sampleMappings = [
    // Inventory samples
    'Inventory/Pmsconnect_HTNG2011B_Inventory.xml' => 'inventory/inventory_available_count.xml',

    // Rate samples
    'Rates/Pmsconnect_HTNG2011B_New rate.xml' => 'rates/rate_new.xml',
    'Rates/Pmsconnect_HTNG2011B_Delta Rate.xml' => 'rates/rate_update.xml',
    'Rates/Pmsconnect_HTNG2011B_Inactive Rate.xml' => 'rates/rate_inactive.xml',

    // Transient Reservation samples
    'Reservations/Transient Reservations/Pmsconnect_HTNG2011B_NewTransientReservationSample.xml' => 'reservations/reservation_new_transient.xml',
    'Reservations/Transient Reservations/Pmsconnect_HTNG2011B_ModifyTransientReservationSample.xml' => 'reservations/reservation_modify_transient.xml',
    'Reservations/Transient Reservations/Pmsconnect_HTNG2011B_CancelTransientReservationSample.xml' => 'reservations/reservation_cancel_transient.xml',

    // Group Reservation samples
    'Reservations/Group Reservations/Pmsconnect_HTNG2011B_NewGroupReservationMultipleGuestNoCreditCardSample.xml' => 'reservations/reservation_new_group.xml',
    'Reservations/Group Reservations/Pmsconnect_HTNG2011B_ModifyGroupReservationMultipleGuestNoCreditCardSample.xml' => 'reservations/reservation_modify_group.xml',
    'Reservations/Group Reservations/Pmsconnect_HTNG2011B_CancelGroupReservationMultipleGuestNoCreditCardSample.xml' => 'reservations/reservation_cancel_group.xml',

    // Corporate Reservation samples
    'Reservations/Corporate Reservations/Pmsconnect_HTNG2011B_NewCorporateReservationSample.xml' => 'reservations/reservation_new_corporate.xml',
    'Reservations/Corporate Reservations/Pmsconnect_HTNG2011B_ModifyCorporateReservationSample.xml' => 'reservations/reservation_modify_corporate.xml',
    'Reservations/Corporate Reservations/Pmsconnect_HTNG2011B_CancelCorporateReservationSample.xml' => 'reservations/reservation_cancel_corporate.xml',

    // Travel Agency Reservation samples
    'Reservations/Travel Agency Reservations/Pmsconnect_HTNG2011B_NewTravelAgencyReservationSample.xml' => 'reservations/reservation_new_travel_agency.xml',
    'Reservations/Travel Agency Reservations/Pmsconnect_HTNG2011B_ModifyTravelAgencyReservationSample.xml' => 'reservations/reservation_modify_travel_agency.xml',
    'Reservations/Travel Agency Reservations/Pmsconnect_HTNG2011B_CancelTravelAgencyReservationSample.xml' => 'reservations/reservation_cancel_travel_agency.xml',

    // Inbound Reservation samples
    'Inbound Reservations/New_ibound.xml' => 'reservations/incoming_reservation_new.xml',
    'Inbound Reservations/MOD_ibound.xml' => 'reservations/incoming_reservation_modify.xml',
    'Inbound Reservations/CAN_ibound.xml' => 'reservations/incoming_reservation_cancel.xml',

    // Group Block samples
    'Groups Blocks/PMSConnect_HTNG2011B_Group_NEW.xml' => 'groups/group_new.xml',
    'Groups Blocks/PMSConnect_HTNG2011B_Group_MODIFY.xml' => 'groups/group_modify.xml',
    'Groups Blocks/PMSConnect_HTNG2011B_Group_CANCEL.xml' => 'groups/group_cancel.xml',

    // Response samples
    'Response Reservations/Pmsconnect_HTNG2011B_Response NEW Reservation.xml' => 'responses/reservation_success_new.xml',
    'Response Reservations/Pmsconnect_HTNG2011B_Response MOD Reservation.xml' => 'responses/reservation_success_modify.xml',
    'Response Reservations/Pmsconnect_HTNG2011B_Response CAN Reservation.xml' => 'responses/reservation_success_cancel.xml',
    'Response Reservations/Pmsconnect_HTNG2011B_Response Error Reservation.xml' => 'responses/reservation_error.xml',
    'Response Reservations/Pmsconnect_HTNG2011B_Response Warning Reservation.xml' => 'responses/reservation_warning.xml',
  ];

  protected array $variableReplacements = [
    // Hotel and property codes
    '/HotelCode="[^"]*"/' => 'HotelCode="{{hotel_code}}"',
    '/PropertyID="[^"]*"/' => 'PropertyID="{{property_id}}"',

    // Room types
    '/InvTypeCode="[^"]*"/' => 'InvTypeCode="{{room_type}}"',
    '/RoomTypeCode="[^"]*"/' => 'RoomTypeCode="{{room_type}}"',

    // Rate plans
    '/RatePlanCode="[^"]*"/' => 'RatePlanCode="{{rate_plan}}"',
    '/RatePlanID="[^"]*"/' => 'RatePlanID="{{rate_plan}}"',

    // Dates
    '/Start="[0-9]{4}-[0-9]{2}-[0-9]{2}"/' => 'Start="{{start_date}}"',
    '/End="[0-9]{4}-[0-9]{2}-[0-9]{2}"/' => 'End="{{end_date}}"',
    '/TimeStamp="[^"]*"/' => 'TimeStamp="{{timestamp}}"',

    // Guest information
    '/<GivenName>[^<]*<\/GivenName>/' => '<GivenName>{{guest_first_name}}</GivenName>',
    '/<Surname>[^<]*<\/Surname>/' => '<Surname>{{guest_last_name}}</Surname>',
    '/<Email>[^<]*<\/Email>/' => '<Email>{{guest_email}}</Email>',

    // Reservation IDs
    '/ResID_Value="[^"]*"/' => 'ResID_Value="{{confirmation_number}}"',
    '/UniqueID ID="[^"]*"/' => 'UniqueID ID="{{reservation_id}}"',

    // Message IDs
    '/MessageID="[^"]*"/' => 'MessageID="{{message_id}}"',
    '/EchoToken="[^"]*"/' => 'EchoToken="{{echo_token}}"',

    // Amounts
    '/AmountBeforeTax="[^"]*"/' => 'AmountBeforeTax="{{rate_amount}}"',
    '/AmountAfterTax="[^"]*"/' => 'AmountAfterTax="{{rate_amount_with_tax}}"',
    '/Count="[^"]*"/' => 'Count="{{count}}"',

    // Credit card info (mask for security)
    '/CardNumber="[^"]*"/' => 'CardNumber="{{card_number}}"',
    '/SeriesCode="[^"]*"/' => 'SeriesCode="{{card_cvv}}"',

    // Phone numbers
    '/PhoneNumber="[^"]*"/' => 'PhoneNumber="{{phone_number}}"',

    // Addresses
    '/<AddressLine>[^<]*<\/AddressLine>/' => '<AddressLine>{{address_line}}</AddressLine>',
    '/<CityName>[^<]*<\/CityName>/' => '<CityName>{{city}}</CityName>',
    '/<PostalCode>[^<]*<\/PostalCode>/' => '<PostalCode>{{postal_code}}</PostalCode>',
  ];

  public function handle(): int
  {
    $sourceDir = $this->option('source-dir') ?: $this->guessSourceDirectory();
    $targetDir = $this->option('target-dir') ?: 'tests/Behat/fixtures/xml_samples';

    if (!$sourceDir || !File::isDirectory($sourceDir)) {
      $this->error('Source directory not found. Please specify with --source-dir option.');
      $this->line('Expected structure: /path/to/samples/Pmsconnect_HTNG_GoldCertificationSamples/');
      return Command::FAILURE;
    }

    $this->info("Importing TravelClick samples from: {$sourceDir}");
    $this->info("Target directory: {$targetDir}");

    if (!File::isDirectory($targetDir)) {
      File::makeDirectory($targetDir, 0755, true);
    }

    $imported = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($this->sampleMappings as $sourcePath => $targetPath) {
      $fullSourcePath = $sourceDir . '/' . $sourcePath;
      $fullTargetPath = $targetDir . '/' . $targetPath;

      if (!File::exists($fullSourcePath)) {
        $this->warn("Source file not found: {$sourcePath}");
        $skipped++;
        continue;
      }

      if (File::exists($fullTargetPath) && !$this->option('force')) {
        $this->line("Skipping existing: {$targetPath}");
        $skipped++;
        continue;
      }

      try {
        $this->processSampleFile($fullSourcePath, $fullTargetPath, $targetPath);
        $this->info("Imported: {$targetPath}");
        $imported++;
      } catch (\Exception $e) {
        $this->error("Failed to import {$sourcePath}: " . $e->getMessage());
        $errors++;
      }
    }

    $this->newLine();
    $this->info("Import summary:");
    $this->line("✅ Imported: {$imported} files");
    $this->line("⏭️  Skipped: {$skipped} files");
    $this->line("❌ Errors: {$errors} files");

    if ($imported > 0) {
      $this->newLine();
      $this->info("Next steps:");
      $this->line("1. Review fixture files in: {$targetDir}");
      $this->line("2. Run: make start-wiremock");
      $this->line("3. Run: vendor/bin/behat --dry-run");
    }

    return Command::SUCCESS;
  }

  protected function guessSourceDirectory(): ?string
  {
    $possiblePaths = [
      'storage/samples/Pmsconnect_HTNG_GoldCertificationSamples',
      'tests/fixtures/TravelClick/Pmsconnect_HTNG_GoldCertificationSamples',
      'docs/samples/Pmsconnect_HTNG_GoldCertificationSamples',
      '../docs/samples/Pmsconnect_HTNG_GoldCertificationSamples',
    ];

    foreach ($possiblePaths as $path) {
      if (File::isDirectory($path)) {
        return $path;
      }
    }

    return null;
  }

  protected function processSampleFile(string $sourcePath, string $targetPath, string $relativePath): void
  {
    // Ensure target directory exists
    $targetDir = dirname($targetPath);
    if (!File::isDirectory($targetDir)) {
      File::makeDirectory($targetDir, 0755, true);
    }

    // Read original XML
    $xmlContent = File::get($sourcePath);

    // Apply variable replacements
    $processedContent = $this->applyVariableReplacements($xmlContent);

    // Add BDD fixture header
    $fixtureContent = $this->addFixtureHeader($processedContent, $relativePath);

    // Write processed content
    File::put($targetPath, $fixtureContent);
  }

  protected function applyVariableReplacements(string $content): string
  {
    foreach ($this->variableReplacements as $pattern => $replacement) {
      $content = preg_replace($pattern, $replacement, $content);
    }

    return $content;
  }

  protected function addFixtureHeader(string $content, string $relativePath): string
  {
    $header = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $header .= "<!-- BDD Fixture: {$relativePath} -->\n";
    $header .= "<!-- Generated from TravelClick Gold Certification Samples -->\n";
    $header .= "<!-- Variables: Use {{variable_name}} format for template replacement -->\n";
    $header .= "\n";

    // Remove existing XML declaration if present
    $content = preg_replace('/<\?xml[^>]*\?>\s*/', '', $content);

    return $header . $content;
  }

  protected function createVariableDocumentation(): void
  {
    $docPath = 'tests/Behat/fixtures/VARIABLES.md';

    $documentation = <<<'MD'
# BDD Fixture Variables

This document describes the template variables available in XML fixtures.

## Hotel & Property
- `{{hotel_code}}` - Hotel code (e.g., "TEST001")
- `{{property_id}}` - Property ID (e.g., "12345")

## Room Types & Rates
- `{{room_type}}` - Room type code (e.g., "KING", "QUEEN")
- `{{rate_plan}}` - Rate plan code (e.g., "BAR", "AAA")
- `{{rate_amount}}` - Rate amount (e.g., "150.00")
- `{{rate_amount_with_tax}}` - Rate amount including tax

## Dates & Time
- `{{start_date}}` - Start date (YYYY-MM-DD format)
- `{{end_date}}` - End date (YYYY-MM-DD format)
- `{{timestamp}}` - Full timestamp (ISO 8601 format)

## Guest Information
- `{{guest_first_name}}` - Guest first name
- `{{guest_last_name}}` - Guest last name
- `{{guest_email}}` - Guest email address
- `{{phone_number}}` - Phone number
- `{{address_line}}` - Address line
- `{{city}}` - City name
- `{{postal_code}}` - Postal code

## Reservation Details
- `{{confirmation_number}}` - Confirmation number
- `{{reservation_id}}` - Unique reservation ID
- `{{message_id}}` - SOAP message ID
- `{{echo_token}}` - Echo token for request/response matching

## Inventory
- `{{count}}` - Inventory count value

## Payment (Masked for Security)
- `{{card_number}}` - Credit card number (test values only)
- `{{card_cvv}}` - Card CVV (test values only)

## Usage in BDD Tests

```gherkin
Given I have a reservation with hotel code "{{hotel_code}}"
When I send the reservation to TravelClick
Then the XML should contain confirmation number "{{confirmation_number}}"
```

Variables are replaced at runtime by the BDD context classes.
MD;

    File::put($docPath, $documentation);
  }
}
