@outbound @inventory
Feature: Send Inventory Updates to TravelClick
  As a hotel property management system
  I want to send inventory updates to TravelClick
  So that room availability is synchronized across all distribution channels

  Background:
    Given TravelClick mock server is running on port 8080
    And I have hotel code "HOTEL001"
    And I have property ID 1

  @priority @available_count
  Scenario: Send available count inventory update successfully
    Given I have inventory data for room "KING" with 15 available rooms
    When I dispatch an inventory update job with "delta" method
    Then the "UpdateInventoryJob" job should be queued on "travelclick-outbound" queue
    And the job should have 3 retry attempts configured
    And WireMock should receive "inventory" request
    And XML should contain HotelCode "HOTEL001"
    And XML should contain CountType 2 with count 15
    And XML should contain room type "KING"
    And a TravelClickLog should be created with message type "inventory"
    And the request should timeout after 60 seconds

  @calculated_method
  Scenario: Send calculated method inventory update
    Given I have inventory data with calculated method: 8 sold, 2 tentative, 1 out of order
    When I dispatch an inventory update job with "delta" method
    Then the "UpdateInventoryJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "inventory" request
    And XML should contain CountType 4 with count 8
    And XML should contain CountType 5 with count 2
    And XML should contain CountType 6 with count 1
    And the job should use exponential backoff strategy

  @overlay_sync
  Scenario: Send full overlay inventory synchronization
    Given I have inventory data for room "SUITE" with 5 available rooms
    When I dispatch an inventory update job with "overlay" method
    Then the "UpdateInventoryJob" job should be queued on "travelclick-outbound" queue
    And the job should be configured for high priority processing
    And WireMock should receive "inventory" request
    And XML should contain room type "SUITE"
    And the sync status should be "processing"

  @urgent_sync
  Scenario: Send urgent inventory update
    Given I have inventory data for room "QUEEN" with 0 available rooms
    When I dispatch an inventory update job with "urgent" method
    Then the "UpdateInventoryJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "inventory" request
    And XML should contain CountType 2 with count 0
    And the processing time should be less than 5 seconds

  @property_level
  Scenario: Send property level inventory update
    Given I have inventory data for room "KING" with 10 available rooms
    And I have inventory data for room "QUEEN" with 8 available rooms
    And I have inventory data for room "SUITE" with 3 available rooms
    When I dispatch an inventory update job with "delta" method
    Then WireMock should receive "inventory" request
    And XML should contain HotelCode "HOTEL001"
    And the database should contain 1 TravelClick log entries
    And the database should have a log entry for message "INV_001" with status "processing"

  @business_rules
  Scenario: Validate inventory business rules before sending
    Given I have inventory data with calculated method: 25 sold, 5 tentative, 2 out of order
    And the physical room count is 30
    When I dispatch an inventory update job with "delta" method
    Then the "UpdateInventoryJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "inventory" request
    And XML should contain CountType 1 with count 30
    And XML should contain CountType 4 with count 25
    And the job should handle business rule validation correctly

  @error_handling
  Scenario: Handle inventory update failure gracefully
    Given WireMock is configured to return "inventory_error" for "inventory" requests
    And I have inventory data for room "KING" with 10 available rooms
    When I dispatch an inventory update job with "delta" method
    Then the "UpdateInventoryJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "inventory" request
    And WireMock should have returned "inventory_error" response for "inventory"
    And there should be 1 error log entries with error type "soap_xml"
    And the error log should contain retryable errors for message "INV_ERROR_001"

  @authentication_error
  Scenario: Handle authentication error during inventory sync
    Given WireMock is configured to simulate "authentication_failure" for requests containing "INVALID_AUTH"
    And I have inventory data for room "KING" with 10 available rooms
    When I dispatch an inventory update job with "delta" method
    Then WireMock should receive "inventory" request
    And there should be 1 error log entries with error type "authentication"
    And the message should be marked as "failed"
    And the sync status should be "failed"

  @timeout_handling
  Scenario: Handle timeout during inventory synchronization
    Given WireMock is configured to delay responses by 65000 milliseconds
    And I have inventory data for room "KING" with 10 available rooms
    When I dispatch an inventory update job with "delta" method
    Then the "UpdateInventoryJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "inventory" request
    And there should be 1 error log entries with error type "timeout"
    And the job should use exponential backoff strategy

  @multiple_rooms
  Scenario: Send inventory updates for multiple room types
    Given I have inventory data for room "KING" with 12 available rooms
    And I have inventory data for room "QUEEN" with 8 available rooms
    And I have inventory data for room "SUITE" with 4 available rooms
    When I dispatch an inventory update job with "delta" method
    Then WireMock should receive exactly 3 requests
    And WireMock should have received a request containing "KING"
    And WireMock should have received a request containing "QUEEN"
    And WireMock should have received a request containing "SUITE"
    And the database should contain 3 TravelClick log entries

  @concurrency
  Scenario: Handle concurrent inventory updates
    Given I have inventory data for room "KING" with 15 available rooms
    When I send concurrent 5 requests of type "inventory"
    Then WireMock should have received exactly 5 requests
    And WireMock should handle concurrent requests without errors
    And all message IDs should be unique across tables
    And the database should contain 5 TravelClick log entries

  @performance
  Scenario: Inventory sync should meet performance requirements
    Given I have inventory data for room "KING" with 20 available rooms
    When I dispatch an inventory update job with "delta" method
    Then the "UpdateInventoryJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "inventory" request
    And WireMock response time should be less than 200 milliseconds
    And the database query for logs should execute in less than 100 milliseconds
    And the processing time should be less than 3 seconds

  @data_integrity
  Scenario: Verify inventory data integrity throughout sync process
    Given I have inventory data with calculated method: 15 sold, 3 tentative, 2 out of order
    When I dispatch an inventory update job with "delta" method
    Then the "UpdateInventoryJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "inventory" request
    And the database should have a log entry for message "INV_INTEGRITY_001" with status "processing"
    And the log entry should have property ID 1 and hotel code "HOTEL001"
    And the message history should contain 1 entries for property 1
    And foreign key constraints should be enforced

  @oversell_handling
  Scenario: Send inventory update with oversell count
    Given I have inventory data with calculated method: 22 sold, 0 tentative, 1 out of order
    And the physical room count is 20
    And oversell is enabled with count 3
    When I dispatch an inventory update job with "delta" method
    Then WireMock should receive "inventory" request
    And XML should contain CountType 1 with count 20
    And XML should contain CountType 4 with count 22
    And XML should contain CountType 99 with count 3
    And the job should handle oversell business rules correctly

  @rate_limiting
  Scenario: Handle rate limiting from TravelClick
    Given WireMock is configured to simulate "rate_limit" for requests containing "RATE_LIMIT_TEST"
    And I have inventory data for room "KING" with 10 available rooms
    When I dispatch an inventory update job with "delta" method
    Then WireMock should receive "inventory" request
    And there should be 1 error log entries with error type "rate_limit"
    And the error log should contain retryable errors for message "INV_RATE_LIMIT_001"
    And the job should use exponential backoff strategy

  @xml_validation
  Scenario: Validate XML structure before sending to TravelClick
    Given I have inventory data for room "KING" with 15 available rooms
    When I dispatch an inventory update job with "delta" method
    Then the "UpdateInventoryJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "inventory" request
    And XML should contain HotelCode "HOTEL001"
    And XML should contain room type "KING"
    And XML should contain CountType 2 with count 15
    And the XML should be valid HTNG 2011B format

  @message_sequencing
  Scenario: Ensure proper message sequencing for inventory updates
    Given I have inventory data for room "KING" with 10 available rooms
    And I have inventory data for room "QUEEN" with 8 available rooms
    When I dispatch an inventory update job with "delta" method
    Then WireMock should have received requests in the correct order: "inventory,inventory"
    And WireMock should receive exactly 2 requests
    And each request should have unique message ID
    And the message history should maintain proper chronological order

  @recovery_scenario
  Scenario: Recover from failed inventory sync
    Given I have a TravelClick log entry for message "RECOVERY_TEST"
    And I have an error log entry for message "RECOVERY_TEST" with error type "connection"
    And I have inventory data for room "KING" with 10 available rooms
    When I dispatch an inventory update job with "delta" method
    Then the "UpdateInventoryJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "inventory" request
    And the previous error should be resolved
    And the sync status should be "completed"

  @cleanup_scenario
  Scenario: Verify cleanup and resource management
    Given I have inventory data for room "KING" with 10 available rooms
    When I dispatch an inventory update job with "delta" method
    And the job completes successfully
    Then the database should contain 1 TravelClick log entries
    And WireMock should receive exactly 1 requests
    And I should be able to recreate the test data structure
    And memory usage should be within acceptable limits

  @edge_cases
  Scenario Outline: Handle edge cases in inventory counts
    Given I have inventory data for room "KING" with <available_count> available rooms
    When I dispatch an inventory update job with "delta" method
    Then the "UpdateInventoryJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "inventory" request
    And XML should contain CountType 2 with count <available_count>
    And the job should handle edge case validation correctly

    Examples:
      | available_count | description           |
      | 0               | Zero availability     |
      | 999             | High availability     |
      | 1               | Single room available |