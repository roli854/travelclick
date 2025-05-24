@outbound @rates
Feature: Send Rate Updates to TravelClick
  As a hotel property management system
  I want to send rate updates to TravelClick
  So that room pricing is synchronized across all distribution channels

  Background:
    Given TravelClick mock server is running on port 8080
    And I have hotel code "HOTEL001"
    And I have property ID 1

  @priority @rate_update
  Scenario: Send new rate plan successfully
    Given I have rate data for room "KING" and rate plan "BAR" with rate 150.00
    When I dispatch a rate update job with "rate_update" operation
    Then the "UpdateRatesJob" job should be queued on "travelclick-outbound" queue
    And the job should have 3 retry attempts configured
    And WireMock should receive "rates" request
    And XML should contain HotelCode "HOTEL001"
    And XML should contain rate plan "BAR" with rate 150.00
    And XML should contain room type "KING"
    And a TravelClickLog should be created with message type "rates"
    And the request should timeout after 90 seconds

  @rate_creation
  Scenario: Create new rate plan with multiple room types
    Given I have rate data for room "KING" and rate plan "CORPORATE" with rate 120.00
    And I have rate data for room "QUEEN" and rate plan "CORPORATE" with rate 100.00
    And I have rate data for room "SUITE" and rate plan "CORPORATE" with rate 200.00
    When I dispatch a rate update job with "rate_creation" operation
    Then the "UpdateRatesJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "rates" request
    And XML should contain rate plan "CORPORATE" with rate 120.00
    And XML should contain rate plan "CORPORATE" with rate 100.00
    And XML should contain rate plan "CORPORATE" with rate 200.00
    And the job should use exponential backoff strategy

  @rate_modification
  Scenario: Update existing rate plan pricing
    Given I have rate data for room "KING" and rate plan "BAR" with rate 175.00
    When I dispatch a rate update job with "rate_update" operation
    Then the "UpdateRatesJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "rates" request
    And XML should contain rate plan "BAR" with rate 175.00
    And the sync status should be "processing"
    And the database should have a log entry for message "RATE_001" with status "processing"

  @rate_inactivation
  Scenario: Inactivate rate plan
    Given I have rate data for room "KING" and rate plan "SEASONAL" with rate 0.00
    When I dispatch a rate update job with "rate_inactive" operation
    Then the "UpdateRatesJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "rates" request
    And XML should contain rate plan "SEASONAL"
    And the message should be marked as "sent"

  @seasonal_rates
  Scenario: Send seasonal rate variations
    Given I have rate data for room "KING" and rate plan "SUMMER" with rate 200.00
    And the rate is valid from "2024-06-01" to "2024-08-31"
    And I have rate data for room "KING" and rate plan "WINTER" with rate 120.00
    And the rate is valid from "2024-12-01" to "2024-02-28"
    When I dispatch a rate update job with "rate_update" operation
    Then WireMock should receive "rates" request
    And XML should contain rate plan "SUMMER" with rate 200.00
    And XML should contain rate plan "WINTER" with rate 120.00
    And the job should handle seasonal rate validation correctly

  @linked_rates
  Scenario: Send linked rate with percentage discount
    Given I have a master rate plan "BAR" with rate 150.00 for room "KING"
    And I have a linked rate plan "AAA" with 10% discount from "BAR"
    When I dispatch a rate update job with "rate_update" operation
    Then the "UpdateRatesJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "rates" request
    And XML should contain rate plan "BAR" with rate 150.00
    And the job should handle linked rate business rules correctly
    And only master rate should be sent to TravelClick

  @guest_pricing
  Scenario: Send rate with different guest pricing
    Given I have rate data with first adult rate 150.00 and second adult rate 170.00
    And the rate has additional adult rate 25.00 and child rate 15.00
    And the rate is for room "SUITE" and rate plan "FAMILY"
    When I dispatch a rate update job with "rate_update" operation
    Then WireMock should receive "rates" request
    And XML should contain first adult rate 150.00
    And XML should contain second adult rate 170.00
    And XML should contain additional adult rate 25.00
    And XML should contain additional child rate 15.00

  @currency_handling
  Scenario: Send rates with different currencies
    Given I have rate data for room "KING" and rate plan "USD_RATE" with rate 150.00 in "USD"
    And I have rate data for room "KING" and rate plan "EUR_RATE" with rate 130.00 in "EUR"
    When I dispatch a rate update job with "rate_update" operation
    Then WireMock should receive "rates" request
    And XML should contain currency "USD" with rate 150.00
    And XML should contain currency "EUR" with rate 130.00
    And the job should handle multi-currency validation correctly

  @batch_processing
  Scenario: Send large batch of rate updates efficiently
    Given I have 45 rate plans for different room types and date ranges
    When I dispatch a rate update job with "rate_update" operation
    Then the "UpdateRatesJob" job should be queued on "travelclick-outbound" queue
    And the job should process rates in batches of 50
    And WireMock should receive "rates" request
    And the processing time should be less than 10 seconds
    And memory usage should be within acceptable limits

  @error_handling
  Scenario: Handle rate update failure gracefully
    Given WireMock is configured to return "rate_error" for "rates" requests
    And I have rate data for room "KING" and rate plan "ERROR_TEST" with rate 100.00
    When I dispatch a rate update job with "rate_update" operation
    Then the "UpdateRatesJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "rates" request
    And WireMock should have returned "rate_error" response for "rates"
    And there should be 1 error log entries with error type "soap_xml"
    And the error log should contain retryable errors for message "RATE_ERROR_001"

  @validation_error
  Scenario: Handle invalid rate data validation
    Given I have rate data for room "INVALID_ROOM" and rate plan "TEST" with rate -50.00
    When I dispatch a rate update job with "rate_update" operation
    Then there should be 1 error log entries with error type "validation"
    And the message should be marked as "failed"
    And the job should not send invalid data to TravelClick

  @authentication_error
  Scenario: Handle authentication error during rate sync
    Given WireMock is configured to simulate "authentication_failure" for requests containing "INVALID_AUTH"
    And I have rate data for room "KING" and rate plan "AUTH_TEST" with rate 100.00
    When I dispatch a rate update job with "rate_update" operation
    Then WireMock should receive "rates" request
    And there should be 1 error log entries with error type "authentication"
    And the sync status should be "failed"
    And the job should use exponential backoff strategy

  @timeout_handling
  Scenario: Handle timeout during rate synchronization
    Given WireMock is configured to delay responses by 95000 milliseconds
    And I have rate data for room "KING" and rate plan "TIMEOUT_TEST" with rate 100.00
    When I dispatch a rate update job with "rate_update" operation
    Then the "UpdateRatesJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "rates" request
    And there should be 1 error log entries with error type "timeout"
    And the error log should contain retryable errors for message "RATE_TIMEOUT_001"

  @business_rules
  Scenario: Validate rate business rules before sending
    Given I have rate data for room "KING" and rate plan "BUSINESS_TEST" with rate 150.00
    And the rate is commissionable with 10% commission
    And the rate has market code "LEISURE"
    And the rate supports maximum 4 guests
    When I dispatch a rate update job with "rate_update" operation
    Then WireMock should receive "rates" request
    And XML should contain commission rate 10%
    And XML should contain market code "LEISURE"
    And XML should contain maximum guests 4
    And the job should handle rate business rules correctly

  @date_range_validation
  Scenario: Validate rate date ranges
    Given I have rate data for room "KING" and rate plan "DATE_TEST" with rate 150.00
    And the rate is valid from "2024-01-01" to "2024-12-31"
    When I dispatch a rate update job with "rate_update" operation
    Then WireMock should receive "rates" request
    And XML should contain start date "2024-01-01"
    And XML should contain end date "2024-12-31"
    And the job should validate date range constraints

  @concurrency
  Scenario: Handle concurrent rate updates
    Given I have rate data for room "KING" and rate plan "CONCURRENT_TEST" with rate 150.00
    When I send concurrent 5 requests of type "rates"
    Then WireMock should have received exactly 5 requests
    And WireMock should handle concurrent requests without errors
    And all message IDs should be unique across tables
    And the database should contain 5 TravelClick log entries

  @performance
  Scenario: Rate sync should meet performance requirements
    Given I have rate data for room "KING" and rate plan "PERF_TEST" with rate 150.00
    When I dispatch a rate update job with "rate_update" operation
    Then the "UpdateRatesJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "rates" request
    And WireMock response time should be less than 300 milliseconds
    And the database query for logs should execute in less than 100 milliseconds
    And the processing time should be less than 5 seconds

  @data_integrity
  Scenario: Verify rate data integrity throughout sync process
    Given I have rate data for room "KING" and rate plan "INTEGRITY_TEST" with rate 150.00
    When I dispatch a rate update job with "rate_update" operation
    Then the "UpdateRatesJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "rates" request
    And the database should have a log entry for message "RATE_INTEGRITY_001" with status "processing"
    And the log entry should have property ID 1 and hotel code "HOTEL001"
    And the message history should contain 1 entries for property 1
    And foreign key constraints should be enforced

  @xml_validation
  Scenario: Validate rate XML structure before sending
    Given I have rate data for room "KING" and rate plan "XML_TEST" with rate 150.00
    When I dispatch a rate update job with "rate_update" operation
    Then the "UpdateRatesJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "rates" request
    And XML should contain HotelCode "HOTEL001"
    And XML should contain rate plan "XML_TEST" with rate 150.00
    And the XML should be valid HTNG 2011B format
    And the XML should contain proper namespaces

  @rate_derivation
  Scenario: Handle rate derivation from master rates
    Given I have a master rate plan "BAR" with rate 200.00 for room "KING"
    And I have derived rates with offsets: "SENIOR" (-20.00), "GOVERNMENT" (-30.00)
    When I dispatch a rate update job with "rate_update" operation
    Then WireMock should receive "rates" request
    And XML should contain rate plan "SENIOR" with rate 180.00
    And XML should contain rate plan "GOVERNMENT" with rate 170.00
    And the job should handle rate derivation correctly

  @message_sequencing
  Scenario: Ensure proper message sequencing for rate updates
    Given I have rate data for room "KING" and rate plan "SEQ_1" with rate 150.00
    And I have rate data for room "QUEEN" and rate plan "SEQ_2" with rate 130.00
    When I dispatch a rate update job with "rate_update" operation
    Then WireMock should have received requests in the correct order: "rates,rates"
    And WireMock should receive exactly 2 requests
    And each request should have unique message ID
    And the message history should maintain proper chronological order

  @delta_vs_overlay
  Scenario: Compare delta and overlay rate synchronization
    Given I have rate data for room "KING" and rate plan "DELTA_TEST" with rate 150.00
    When I dispatch a rate update job with "rate_update" operation using delta mode
    Then the job should send only changed rates
    When I dispatch a rate update job with "rate_update" operation using overlay mode
    Then the job should send all rates for the date range
    And both methods should maintain data consistency

  @recovery_scenario
  Scenario: Recover from failed rate sync
    Given I have a TravelClick log entry for message "RATE_RECOVERY_TEST"
    And I have an error log entry for message "RATE_RECOVERY_TEST" with error type "connection"
    And I have rate data for room "KING" and rate plan "RECOVERY" with rate 150.00
    When I dispatch a rate update job with "rate_update" operation
    Then the "UpdateRatesJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "rates" request
    And the previous error should be resolved
    And the sync status should be "completed"

  @cleanup_scenario
  Scenario: Verify cleanup and resource management for rates
    Given I have rate data for room "KING" and rate plan "CLEANUP_TEST" with rate 150.00
    When I dispatch a rate update job with "rate_update" operation
    And the job completes successfully
    Then the database should contain 1 TravelClick log entries
    And WireMock should receive exactly 1 requests
    And I should be able to recreate the test data structure
    And memory usage should be within acceptable limits

  @edge_cases
  Scenario Outline: Handle edge cases in rate values
    Given I have rate data for room "KING" and rate plan "EDGE_<case>" with rate <rate_value>
    When I dispatch a rate update job with "rate_update" operation
    Then the "UpdateRatesJob" job should be queued on "travelclick-outbound" queue
    And WireMock should receive "rates" request
    And XML should contain rate plan "EDGE_<case>" with rate <rate_value>
    And the job should handle edge case validation correctly

    Examples:
      | case    | rate_value | description       |
      | ZERO    | 0.00       | Zero rate         |
      | HIGH    | 9999.99    | Very high rate    |
      | LOW     | 0.01       | Minimum rate      |
      | DECIMAL | 123.45     | Decimal precision |

  @commission_scenarios
  Scenario Outline: Handle different commission structures
    Given I have rate data for room "KING" and rate plan "COMM_<type>" with rate 150.00
    And the rate has commission type "<commission_type>" with value <commission_value>
    When I dispatch a rate update job with "rate_update" operation
    Then WireMock should receive "rates" request
    And XML should contain commission type "<commission_type>"
    And XML should contain commission value <commission_value>

    Examples:
      | type | commission_type | commission_value |
      | PCT  | percentage      | 10.0             |
      | FLAT | flat_amount     | 15.00            |
      | NONE | none            | 0.0              |