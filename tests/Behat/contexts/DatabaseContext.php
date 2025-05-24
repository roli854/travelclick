<?php

declare(strict_types=1);

namespace Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

// TravelClick Models
use App\TravelClick\Models\TravelClickLog;
use App\TravelClick\Models\TravelClickMessageHistory;
use App\TravelClick\Models\TravelClickSyncStatus;
use App\TravelClick\Models\TravelClickErrorLog;
use App\TravelClick\Models\TravelClickPropertyConfig;
use App\TravelClick\Models\TravelClickPropertyMapping;

// TravelClick Enums
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\ProcessingStatus;
use App\TravelClick\Enums\SyncStatus;
use App\TravelClick\Enums\ErrorType;

/**
 * Database Context for BDD Testing
 *
 * This context handles all database-related verifications and operations for TravelClick BDD tests:
 * - Complex database assertions
 * - Data integrity verification
 * - Relationship verification between models
 * - Transaction management
 * - Test data setup and cleanup
 * - Performance verification for database operations
 */
class DatabaseContext implements Context
{
  use RefreshDatabase;

  private Application $app;
  private array $createdRecords = [];
  private array $verificationCache = [];
  private int $currentPropertyId = 1;
  private string $currentHotelCode = 'TEST001';

  /**
   * Initialize database context
   */
  public function __construct()
  {
    // Initialize Laravel application for Behat context
    $this->app = require __DIR__ . '/../../../bootstrap/app.php';
    $this->app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

    $this->setupTestEnvironment();
  }

  /**
   * Setup test environment for database operations
   */
  private function setupTestEnvironment(): void
  {
    // Set testing environment
    config(['app.env' => 'testing']);

    // Setup database for testing
    config(['database.default' => 'testing']);

    // Ensure we're using in-memory SQLite for testing
    config(['database.connections.testing' => [
      'driver' => 'sqlite',
      'database' => ':memory:',
      'prefix' => '',
      'foreign_key_constraints' => true,
    ]]);

    // Run migrations
    $this->artisan('migrate:fresh');
    $this->seedBasicData();
  }

  /**
   * Run artisan command for Behat context
   */
  private function artisan(string $command): int
  {
    return $this->app->make('Illuminate\Contracts\Console\Kernel')->call($command);
  }

  /**
   * Seed basic test data
   */
  private function seedBasicData(): void
  {
    // Create basic property mapping for testing
    TravelClickPropertyMapping::create([
      'PropertyID' => $this->currentPropertyId,
      'HotelCode' => $this->currentHotelCode,
      'IsActive' => true,
      'SyncStatus' => SyncStatus::SUCCESS->value,
      'DateCreated' => Carbon::now(),
    ]);
  }

  // =====================================================
  // GIVEN Steps - Setup Test Data
  // =====================================================

  /**
   * @Given I have a property with ID :propertyId and hotel code :hotelCode
   */
  public function iHaveAPropertyWithIdAndHotelCode(int $propertyId, string $hotelCode): void
  {
    $this->currentPropertyId = $propertyId;
    $this->currentHotelCode = $hotelCode;

    // Create or update the property mapping
    TravelClickPropertyMapping::updateOrCreate(
      ['PropertyID' => $propertyId],
      [
        'HotelCode' => $hotelCode,
        'IsActive' => true,
        'SyncStatus' => SyncStatus::SUCCESS->value,
        'DateCreated' => Carbon::now(),
      ]
    );

    $this->createdRecords['property_mapping'] = $propertyId;
  }

  /**
   * @Given I have a TravelClick log entry for message :messageId
   */
  public function iHaveATravelClickLogEntryForMessage(string $messageId): void
  {
    $log = TravelClickLog::create([
      'MessageID' => $messageId,
      'Direction' => 'outbound',
      'MessageType' => MessageType::INVENTORY->value,
      'PropertyID' => $this->currentPropertyId,
      'HotelCode' => $this->currentHotelCode,
      'Status' => SyncStatus::PROCESSING->value,
      'DateCreated' => Carbon::now(),
    ]);

    $this->createdRecords['log_' . $messageId] = $log->id;
  }

  /**
   * @Given I have a message history entry for message :messageId with status :status
   */
  public function iHaveAMessageHistoryEntryForMessageWithStatus(string $messageId, string $status): void
  {
    $processingStatus = ProcessingStatus::from($status);

    $messageHistory = TravelClickMessageHistory::create([
      'MessageID' => $messageId,
      'MessageType' => MessageType::RESERVATION->value,
      'Direction' => 'inbound',
      'PropertyID' => $this->currentPropertyId,
      'Status' => $processingStatus->value,
      'MessageXML' => '<test>Sample XML</test>',
      'ProcessingStartTime' => Carbon::now()->subSeconds(10),
      'ProcessingEndTime' => Carbon::now(),
      'DateCreated' => Carbon::now(),
    ]);

    $this->createdRecords['message_history_' . $messageId] = $messageHistory->id;
  }

  /**
   * @Given I have a sync status record for property :propertyId with status :status
   */
  public function iHaveASyncStatusRecordForPropertyWithStatus(int $propertyId, string $status): void
  {
    $syncStatus = SyncStatus::from($status);

    $syncRecord = TravelClickSyncStatus::create([
      'PropertyID' => $propertyId,
      'MessageType' => MessageType::INVENTORY->value,
      'Status' => $syncStatus->value,
      'TotalRecords' => 100,
      'ProcessedRecords' => 75,
      'ErrorCount' => 0,
      'LastSyncDateTime' => Carbon::now()->subHour(),
      'DateCreated' => Carbon::now(),
    ]);

    $this->createdRecords['sync_status_' . $propertyId] = $syncRecord->id;
  }

  /**
   * @Given I have an error log entry for message :messageId with error type :errorType
   */
  public function iHaveAnErrorLogEntryForMessageWithErrorType(string $messageId, string $errorType): void
  {
    $errorTypeEnum = ErrorType::from($errorType);

    $errorLog = TravelClickErrorLog::create([
      'MessageID' => $messageId,
      'ErrorType' => $errorTypeEnum->value,
      'ErrorTitle' => 'Test Error',
      'ErrorMessage' => 'This is a test error message',
      'PropertyID' => $this->currentPropertyId,
      'Severity' => 'medium',
      'CanRetry' => $errorTypeEnum->canRetry(),
      'DateCreated' => Carbon::now(),
    ]);

    $this->createdRecords['error_log_' . $messageId] = $errorLog->id;
  }

  // =====================================================
  // THEN Steps - Database Verifications
  // =====================================================

  /**
   * @Then the database should contain :count TravelClick log entries
   */
  public function theDatabaseShouldContainTravelClickLogEntries(int $count): void
  {
    $actualCount = TravelClickLog::count();

    if ($actualCount !== $count) {
      throw new \Exception("Expected {$count} TravelClick log entries, but found {$actualCount}");
    }
  }

  /**
   * @Then the database should have a log entry for message :messageId with status :status
   */
  public function theDatabaseShouldHaveALogEntryForMessageWithStatus(string $messageId, string $status): void
  {
    $syncStatus = SyncStatus::from($status);

    $log = TravelClickLog::where([
      'MessageID' => $messageId,
      'Status' => $syncStatus->value,
    ])->first();

    if (!$log) {
      throw new \Exception("Expected log entry for message '{$messageId}' with status '{$status}' not found");
    }

    $this->verificationCache['last_verified_log'] = $log;
  }

  /**
   * @Then the log entry should have property ID :propertyId and hotel code :hotelCode
   */
  public function theLogEntryShouldHavePropertyIdAndHotelCode(int $propertyId, string $hotelCode): void
  {
    if (!isset($this->verificationCache['last_verified_log'])) {
      throw new \Exception('No log entry has been verified yet. Use a log verification step first.');
    }

    $log = $this->verificationCache['last_verified_log'];

    if ($log->PropertyID !== $propertyId) {
      throw new \Exception("Expected PropertyID {$propertyId}, but got {$log->PropertyID}");
    }

    if ($log->HotelCode !== $hotelCode) {
      throw new \Exception("Expected HotelCode '{$hotelCode}', but got '{$log->HotelCode}'");
    }
  }

  /**
   * @Then the message history should contain :count entries for property :propertyId
   */
  public function theMessageHistoryShouldContainEntriesForProperty(int $count, int $propertyId): void
  {
    $actualCount = TravelClickMessageHistory::where('PropertyID', $propertyId)->count();

    if ($actualCount !== $count) {
      throw new \Exception("Expected {$count} message history entries for property {$propertyId}, but found {$actualCount}");
    }
  }

  /**
   * @Then the message :messageId should be marked as :status with processing time less than :seconds seconds
   */
  public function theMessageShouldBeMarkedAsWithProcessingTimeLessThanSeconds(string $messageId, string $status, int $seconds): void
  {
    $processingStatus = ProcessingStatus::from($status);

    $messageHistory = TravelClickMessageHistory::where([
      'MessageID' => $messageId,
      'Status' => $processingStatus->value,
    ])->first();

    if (!$messageHistory) {
      throw new \Exception("Expected message '{$messageId}' with status '{$status}' not found");
    }

    // Calculate processing time
    if ($messageHistory->ProcessingStartTime && $messageHistory->ProcessingEndTime) {
      $processingTime = $messageHistory->ProcessingEndTime->diffInSeconds($messageHistory->ProcessingStartTime);

      if ($processingTime >= $seconds) {
        throw new \Exception("Expected processing time less than {$seconds}s, but got {$processingTime}s");
      }
    }

    $this->verificationCache['last_verified_message'] = $messageHistory;
  }

  /**
   * @Then the sync status for property :propertyId should show :processedRecords out of :totalRecords processed
   */
  public function theSyncStatusForPropertyShouldShowOutOfProcessed(int $propertyId, int $processedRecords, int $totalRecords): void
  {
    $syncStatus = TravelClickSyncStatus::where('PropertyID', $propertyId)
      ->orderBy('DateCreated', 'desc')
      ->first();

    if (!$syncStatus) {
      throw new \Exception("No sync status record found for property {$propertyId}");
    }

    if ($syncStatus->ProcessedRecords !== $processedRecords) {
      throw new \Exception("Expected {$processedRecords} processed records, but got {$syncStatus->ProcessedRecords}");
    }

    if ($syncStatus->TotalRecords !== $totalRecords) {
      throw new \Exception("Expected {$totalRecords} total records, but got {$syncStatus->TotalRecords}");
    }
  }

  /**
   * @Then there should be :count error log entries with error type :errorType
   */
  public function thereShouldBeErrorLogEntriesWithErrorType(int $count, string $errorType): void
  {
    $errorTypeEnum = ErrorType::from($errorType);

    $actualCount = TravelClickErrorLog::where('ErrorType', $errorTypeEnum->value)->count();

    if ($actualCount !== $count) {
      throw new \Exception("Expected {$count} error log entries with type '{$errorType}', but found {$actualCount}");
    }
  }

  /**
   * @Then the error log should contain retryable errors for message :messageId
   */
  public function theErrorLogShouldContainRetryableErrorsForMessage(string $messageId): void
  {
    $retryableErrors = TravelClickErrorLog::where([
      'MessageID' => $messageId,
      'CanRetry' => true,
    ])->count();

    if ($retryableErrors === 0) {
      throw new \Exception("Expected retryable errors for message '{$messageId}', but found none");
    }
  }

  /**
   * @Then the property mapping should be active for hotel code :hotelCode
   */
  public function thePropertyMappingShouldBeActiveForHotelCode(string $hotelCode): void
  {
    $mapping = TravelClickPropertyMapping::where([
      'HotelCode' => $hotelCode,
      'IsActive' => true,
    ])->first();

    if (!$mapping) {
      throw new \Exception("Expected active property mapping for hotel code '{$hotelCode}' not found");
    }

    $this->verificationCache['last_verified_mapping'] = $mapping;
  }

  /**
   * @Then the property mapping should have sync status :status
   */
  public function thePropertyMappingShouldHaveSyncStatus(string $status): void
  {
    if (!isset($this->verificationCache['last_verified_mapping'])) {
      throw new \Exception('No property mapping has been verified yet. Use a mapping verification step first.');
    }

    $mapping = $this->verificationCache['last_verified_mapping'];
    $syncStatus = SyncStatus::from($status);

    if ($mapping->SyncStatus !== $syncStatus->value) {
      throw new \Exception("Expected sync status '{$status}', but got '{$mapping->SyncStatus}'");
    }
  }

  // =====================================================
  // Database Relationship Verifications
  // =====================================================

  /**
   * @Then the log entry should have :count related error logs
   */
  public function theLogEntryShouldHaveRelatedErrorLogs(int $count): void
  {
    if (!isset($this->verificationCache['last_verified_log'])) {
      throw new \Exception('No log entry has been verified yet. Use a log verification step first.');
    }

    $log = $this->verificationCache['last_verified_log'];
    $errorCount = $log->errorLogs()->count();

    if ($errorCount !== $count) {
      throw new \Exception("Expected {$count} related error logs, but found {$errorCount}");
    }
  }

  /**
   * @Then the message history should have related log entries
   */
  public function theMessageHistoryShouldHaveRelatedLogEntries(): void
  {
    if (!isset($this->verificationCache['last_verified_message'])) {
      throw new \Exception('No message history has been verified yet. Use a message verification step first.');
    }

    $messageHistory = $this->verificationCache['last_verified_message'];
    $relatedLogs = $messageHistory->travelClickLog;

    if (!$relatedLogs) {
      throw new \Exception('Expected related log entries for message history, but found none');
    }
  }

  /**
   * @Then the sync status should be linked to property mapping
   */
  public function theSyncStatusShouldBeLinkedToPropertyMapping(): void
  {
    $syncStatus = TravelClickSyncStatus::where('PropertyID', $this->currentPropertyId)->first();

    if (!$syncStatus) {
      throw new \Exception("No sync status found for property {$this->currentPropertyId}");
    }

    $propertyMapping = $syncStatus->property;

    if (!$propertyMapping) {
      throw new \Exception('Expected sync status to be linked to property mapping, but no relationship found');
    }

    if ($propertyMapping->PropertyID !== $this->currentPropertyId) {
      throw new \Exception("Property mapping relationship mismatch: expected {$this->currentPropertyId}, got {$propertyMapping->PropertyID}");
    }
  }

  // =====================================================
  // Performance and Data Integrity Verifications
  // =====================================================

  /**
   * @Then the database query for logs should execute in less than :milliseconds milliseconds
   */
  public function theDatabaseQueryForLogsShouldExecuteInLessThanMilliseconds(int $milliseconds): void
  {
    $startTime = microtime(true);

    TravelClickLog::where('PropertyID', $this->currentPropertyId)
      ->with(['errorLogs', 'messageHistory'])
      ->get();

    $executionTime = (microtime(true) - $startTime) * 1000;

    if ($executionTime >= $milliseconds) {
      throw new \Exception("Expected query execution time less than {$milliseconds}ms, but got {$executionTime}ms");
    }
  }

  /**
   * @Then all message IDs should be unique across tables
   */
  public function allMessageIdsShouldBeUniqueAcrossTables(): void
  {
    // Get all message IDs from logs
    $logMessageIds = TravelClickLog::pluck('MessageID')->toArray();

    // Get all message IDs from message history
    $historyMessageIds = TravelClickMessageHistory::pluck('MessageID')->toArray();

    // Check for duplicates within each table
    if (count($logMessageIds) !== count(array_unique($logMessageIds))) {
      throw new \Exception('Duplicate MessageIDs found in TravelClickLog table');
    }

    if (count($historyMessageIds) !== count(array_unique($historyMessageIds))) {
      throw new \Exception('Duplicate MessageIDs found in TravelClickMessageHistory table');
    }
  }

  /**
   * @Then foreign key constraints should be enforced
   */
  public function foreignKeyConstraintsShouldBeEnforced(): void
  {
    // Test foreign key constraint by trying to insert invalid data
    try {
      DB::table('travel_click_logs')->insert([
        'MessageID' => 'CONSTRAINT_TEST',
        'PropertyID' => 99999, // Non-existent property ID
        'Direction' => 'outbound',
        'MessageType' => MessageType::INVENTORY->value,
        'Status' => SyncStatus::PENDING->value,
        'DateCreated' => Carbon::now(),
      ]);

      throw new \Exception('Expected foreign key constraint violation, but insert succeeded');
    } catch (\Illuminate\Database\QueryException $e) {
      // This is expected - foreign key constraint should prevent the insert
      if (!str_contains($e->getMessage(), 'FOREIGN KEY constraint failed')) {
        throw new \Exception('Expected foreign key constraint error, but got: ' . $e->getMessage());
      }
    }
  }

  // =====================================================
  // Cleanup and Helper Methods
  // =====================================================

  /**
   * @Given I clean up all test data
   */
  public function iCleanUpAllTestData(): void
  {
    // Clean up in reverse order of dependencies
    TravelClickErrorLog::query()->delete();
    TravelClickMessageHistory::query()->delete();
    TravelClickSyncStatus::query()->delete();
    TravelClickLog::query()->delete();
    TravelClickPropertyConfig::query()->delete();
    TravelClickPropertyMapping::query()->delete();

    $this->createdRecords = [];
    $this->verificationCache = [];
  }

  /**
   * @Then I should be able to recreate the test data structure
   */
  public function iShouldBeAbleToRecreateTheTestDataStructure(): void
  {
    // Verify we can create a complete test data structure
    $this->seedBasicData();
    $this->iHaveATravelClickLogEntryForMessage('RECREATE_TEST');
    $this->iHaveAMessageHistoryEntryForMessageWithStatus('RECREATE_TEST', 'processed');
    $this->iHaveAnErrorLogEntryForMessageWithErrorType('RECREATE_TEST', 'validation');

    // Verify all records were created successfully
    $this->theDatabaseShouldHaveALogEntryForMessageWithStatus('RECREATE_TEST', 'processing');
    $this->theMessageShouldBeMarkedAsWithProcessingTimeLessThanSeconds('RECREATE_TEST', 'processed', 60);
    $this->thereShouldBeErrorLogEntriesWithErrorType(1, 'validation');
  }

  /**
   * Get database statistics for debugging
   */
  public function getDatabaseStatistics(): array
  {
    return [
      'logs_count' => TravelClickLog::count(),
      'message_history_count' => TravelClickMessageHistory::count(),
      'sync_status_count' => TravelClickSyncStatus::count(),
      'error_logs_count' => TravelClickErrorLog::count(),
      'property_mappings_count' => TravelClickPropertyMapping::count(),
      'property_configs_count' => TravelClickPropertyConfig::count(),
    ];
  }

  /**
   * Get created records for inspection
   */
  public function getCreatedRecords(): array
  {
    return $this->createdRecords;
  }

  /**
   * Get verification cache for debugging
   */
  public function getVerificationCache(): array
  {
    return $this->verificationCache;
  }

  /**
   * Reset context state for next scenario
   */
  public function resetContext(): void
  {
    $this->createdRecords = [];
    $this->verificationCache = [];
    $this->currentPropertyId = 1;
    $this->currentHotelCode = 'TEST001';
  }
}
