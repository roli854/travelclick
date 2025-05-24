@outbound @reservations
Feature: Send Reservations to TravelClick
  As a hotel property management system
  I want to send reservations to TravelClick
  So that bookings are synchronized across all distribution channels

  Background:
    Given TravelClick mock server is running on port 8080
    And I have hotel code "HOTEL001"
    And I have property ID 1

  @priority @transient
  Scenario: Send new transient reservation successfully
    Given I have a "transient" reservation for guest "John" "Doe"
    When I dispatch a new reservation job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And the job should have 3 retry attempts configured
    And WireMock should receive "reservation" request
    And XML should contain HotelCode "HOTEL001"
    And XML should contain guest name "John" "Doe"
    And XML should contain room type "KING"
    And a TravelClickLog should be created with message type "reservation"
    And the request should timeout after 120 seconds

  @priority @transient @modification
  Scenario: Send transient reservation modification
    Given I have a "transient" reservation for guest "Jane" "Smith"
    And the reservation has confirmation number "TC123456"
    When I dispatch a reservation modification job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "reservation" request
    And XML should contain guest name "Jane" "Smith"
    And XML should contain confirmation number "TC123456"
    And XML should contain transaction type "MODIFY"

  @priority @transient @cancellation
  Scenario: Cancel transient reservation successfully
    Given I have a "transient" reservation for guest "Bob" "Johnson"
    And the reservation has confirmation number "TC789012"
    When I dispatch a cancel reservation job
    Then the "CancelReservationJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "reservation" request
    And XML should contain confirmation number "TC789012"
    And XML should contain transaction type "CANCEL"
    And the job should use exponential backoff strategy

  @urgent @cancellation
  Scenario: Send urgent reservation cancellation
    Given I have a "transient" reservation for guest "Emergency" "Cancel"
    And the reservation has confirmation number "TC999999"
    When I dispatch an urgent reservation cancellation
    Then the "CancelReservationJob" job should be queued on "travelclick-high" queue
    And the job should be configured for high priority processing
    And WireMock should receive "reservation" request
    And the processing time should be less than 3 seconds

  @travel_agency
  Scenario: Send travel agency reservation with commission
    Given I have a "travel_agency" reservation for guest "Agent" "Booking"
    And the reservation has travel agency "IATA12345" with commission 10%
    And the reservation has agent name "Travel Pro Agency"
    When I dispatch a new reservation job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "reservation" request
    And XML should contain travel agency profile "IATA12345"
    And XML should contain commission rate 10%
    And XML should contain agent name "Travel Pro Agency"
    And the job should handle "travel_agency" reservation type correctly

  @corporate
  Scenario: Send corporate reservation with company profile
    Given I have a "corporate" reservation for guest "Business" "Traveler"
    And the reservation has corporate profile "CORP123" with company "Tech Solutions Inc"
    And the reservation has corporate rate "CORPORATE"
    When I dispatch a new reservation job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "reservation" request
    And XML should contain corporate profile "CORP123"
    And XML should contain company name "Tech Solutions Inc"
    And XML should contain rate plan "CORPORATE"
    And the job should handle "corporate" reservation type correctly

  @package
  Scenario: Send package reservation with bundled services
    Given I have a "package" reservation for guest "Package" "Guest"
    And the reservation has package "ROMANCE_PKG" with rate plan "PACKAGE_RATE"
    And the package includes breakfast and spa services
    When I dispatch a new reservation job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "reservation" request
    And XML should contain rate plan "PACKAGE_RATE"
    And XML should contain package code "ROMANCE_PKG"
    And the job should handle "package" reservation type correctly

  @group
  Scenario: Send group reservation linked to block
    Given I have a "group" reservation for guest "Group" "Leader"
    And the reservation is linked to group block "GRP2024001"
    And the group has block code "WEDDING_BLOCK"
    When I dispatch a new reservation job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "reservation" request
    And XML should contain group block code "GRP2024001"
    And XML should contain block reference "WEDDING_BLOCK"
    And the job should handle "group" reservation type correctly

  @special_requests
  Scenario: Send reservation with special and service requests
    Given I have a "transient" reservation for guest "Special" "Needs"
    And the reservation has special request "Wheelchair Accessible"
    And the reservation has special request "High Floor"
    And the reservation has service request "Bottle of Wine" with cost 45.00
    And the reservation has service request "Late Checkout" with cost 25.00
    When I dispatch a new reservation job
    Then WireMock should receive "reservation" request
    And XML should contain special request "Wheelchair Accessible"
    And XML should contain special request "High Floor"
    And XML should contain service request "Bottle of Wine" with cost 45.00
    And XML should contain service request "Late Checkout" with cost 25.00

  @payment_methods
  Scenario: Send reservation with alternate payment method
    Given I have a "transient" reservation for guest "Alt" "Payment"
    And the reservation has alternate payment "PayPal" with amount 150.00
    And the reservation has deposit amount 75.00
    When I dispatch a new reservation job
    Then WireMock should receive "reservation" request
    And XML should contain alternate payment "PayPal"
    And XML should contain deposit amount 75.00
    And XML should contain payment amount 150.00

  @multi_guest
  Scenario: Send reservation with multiple guests
    Given I have a "transient" reservation for guest "Primary" "Guest"
    And the reservation has additional guest "Secondary" "Guest"
    And the reservation has additional guest "Third" "Guest"
    And the room occupancy is 3 adults and 1 child
    When I dispatch a new reservation job
    Then WireMock should receive "reservation" request
    And XML should contain guest name "Primary" "Guest"
    And XML should contain guest name "Secondary" "Guest"
    And XML should contain guest name "Third" "Guest"
    And XML should contain guest count 3 adults
    And XML should contain guest count 1 child

  @multi_night
  Scenario: Send multi-night reservation with daily rates
    Given I have a "transient" reservation for guest "Extended" "Stay"
    And the reservation is from "2024-06-01" to "2024-06-05"
    And night 1 has rate 150.00
    And night 2 has rate 175.00
    And night 3 has rate 200.00
    And night 4 has rate 175.00
    When I dispatch a new reservation job
    Then WireMock should receive "reservation" request
    And XML should contain arrival date "2024-06-01"
    And XML should contain departure date "2024-06-05"
    And XML should contain daily rate 150.00 for night 1
    And XML should contain daily rate 200.00 for night 3

  @source_of_business
  Scenario: Send reservation with source of business tracking
    Given I have a "transient" reservation for guest "Source" "Tracked"
    And the reservation has source of business "WEB"
    And the reservation has market segment "LEISURE"
    And the reservation has department code "SALES"
    When I dispatch a new reservation job
    Then WireMock should receive "reservation" request
    And XML should contain source of business "WEB"
    And XML should contain market segment "LEISURE"
    And XML should contain department code "SALES"

  @error_handling
  Scenario: Handle reservation send failure gracefully
    Given WireMock is configured to return "reservation_error" for "reservation" requests
    And I have a "transient" reservation for guest "Error" "Test"
    When I dispatch a new reservation job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "reservation" request
    And WireMock should have returned "reservation_error" response for "reservation"
    And there should be 1 error log entries with error type "soap_xml"
    And the error log should contain retryable errors for message "RES_ERROR_001"

  @business_rule_error
  Scenario: Handle business rule validation error
    Given I have a "transient" reservation for guest "Invalid" "Room"
    And the reservation has invalid room type "NONEXISTENT"
    When I dispatch a new reservation job
    Then there should be 1 error log entries with error type "business_logic"
    And the message should be marked as "failed"
    And the job should not send invalid data to TravelClick

  @authentication_error
  Scenario: Handle authentication error during reservation send
    Given WireMock is configured to simulate "authentication_failure" for requests containing "INVALID_AUTH"
    And I have a "transient" reservation for guest "Auth" "Test"
    When I dispatch a new reservation job
    Then WireMock should receive "reservation" request
    And there should be 1 error log entries with error type "authentication"
    And the sync status should be "failed"
    And the job should use exponential backoff strategy

  @timeout_handling
  Scenario: Handle timeout during reservation send
    Given WireMock is configured to delay responses by 125000 milliseconds
    And I have a "transient" reservation for guest "Timeout" "Test"
    When I dispatch a new reservation job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "reservation" request
    And there should be 1 error log entries with error type "timeout"
    And the error log should contain retryable errors for message "RES_TIMEOUT_001"

  @inventory_sync
  Scenario: Automatic inventory update after reservation
    Given I have a "transient" reservation for guest "Inventory" "Update"
    And the reservation affects room type "KING" inventory
    When I dispatch a new reservation job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And an inventory update should be triggered automatically
    And WireMock should receive "reservation" request
    And WireMock should receive "inventory" request
    And the inventory should reflect the reservation impact

  @concurrency
  Scenario: Handle concurrent reservation sends
    Given I have multiple reservations for different guests
    When I send concurrent 5 requests of type "reservation"
    Then WireMock should have received exactly 5 requests
    And WireMock should handle concurrent requests without errors
    And all message IDs should be unique across tables
    And the database should contain 5 TravelClick log entries

  @performance
  Scenario: Reservation send should meet performance requirements
    Given I have a "transient" reservation for guest "Performance" "Test"
    When I dispatch a new reservation job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "reservation" request
    And WireMock response time should be less than 500 milliseconds
    And the database query for logs should execute in less than 100 milliseconds
    And the processing time should be less than 5 seconds

  @data_integrity
  Scenario: Verify reservation data integrity throughout send process
    Given I have a "transient" reservation for guest "Integrity" "Test"
    When I dispatch a new reservation job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "reservation" request
    And the database should have a log entry for message "RES_INTEGRITY_001" with status "processing"
    And the log entry should have property ID 1 and hotel code "HOTEL001"
    And the message history should contain 1 entries for property 1
    And foreign key constraints should be enforced

  @xml_validation
  Scenario: Validate reservation XML structure before sending
    Given I have a "transient" reservation for guest "XML" "Validation"
    When I dispatch a new reservation job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "reservation" request
    And XML should contain HotelCode "HOTEL001"
    And XML should contain guest name "XML" "Validation"
    And the XML should be valid HTNG 2011B format
    And the XML should contain proper namespaces
    And the XML should have valid UniqueID elements

  @message_sequencing
  Scenario: Ensure proper message sequencing for reservations
    Given I have a "transient" reservation for guest "Sequence" "Test1"
    And I have a "transient" reservation for guest "Sequence" "Test2"
    When I dispatch reservation jobs in order
    Then WireMock should have received requests in the correct order: "reservation,reservation"
    And WireMock should receive exactly 2 requests
    And each request should have unique message ID
    And the message history should maintain proper chronological order

  @confirmation_numbers
  Scenario: Handle confirmation number generation and tracking
    Given I have a "transient" reservation for guest "Confirmation" "Test"
    When I dispatch a new reservation job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "reservation" request
    And SOAP response should contain success confirmation
    And the response should include TravelClick confirmation number
    And the confirmation number should be stored in database

  @rate_validation
  Scenario: Validate rate plans exist before sending reservation
    Given I have a "transient" reservation for guest "Rate" "Validation"
    And the reservation uses rate plan "NONEXISTENT_RATE"
    When I dispatch a new reservation job
    Then there should be 1 error log entries with error type "validation"
    And the message should be marked as "failed"
    And the error should mention "rate plan not found"

  @room_type_validation
  Scenario: Validate room types exist before sending reservation
    Given I have a "transient" reservation for guest "Room" "Validation"
    And the reservation uses room type "INVALID_ROOM"
    When I dispatch a new reservation job
    Then there should be 1 error log entries with error type "validation"
    And the message should be marked as "failed"
    And the error should mention "room type not available"

  @duplicate_prevention
  Scenario: Prevent duplicate reservation sends
    Given I have a "transient" reservation for guest "Duplicate" "Test"
    And the reservation has confirmation number "TC_DUPLICATE_001"
    When I dispatch a new reservation job
    And I dispatch the same reservation job again
    Then only one reservation should be sent to TravelClick
    And WireMock should receive exactly 1 requests
    And the duplicate should be detected and prevented

  @recovery_scenario
  Scenario: Recover from failed reservation send
    Given I have a TravelClick log entry for message "RES_RECOVERY_TEST"
    And I have an error log entry for message "RES_RECOVERY_TEST" with error type "connection"
    And I have a "transient" reservation for guest "Recovery" "Test"
    When I dispatch a new reservation job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "reservation" request
    And the previous error should be resolved
    And the sync status should be "completed"

  @cleanup_scenario
  Scenario: Verify cleanup and resource management for reservations
    Given I have a "transient" reservation for guest "Cleanup" "Test"
    When I dispatch a new reservation job
    And the job completes successfully
    Then the database should contain 1 TravelClick log entries
    And WireMock should receive exactly 1 requests
    And I should be able to recreate the test data structure
    And memory usage should be within acceptable limits

  @edge_cases
  Scenario Outline: Handle edge cases in reservation data
    Given I have a "transient" reservation for guest "<first_name>" "<last_name>"
    And the reservation has <edge_case_data>
    When I dispatch a new reservation job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "reservation" request
    And the job should handle edge case validation correctly

    Examples:
      | first_name                                | last_name  | edge_case_data     | description                |
      | Very-Long-Name-That-Exceeds-Normal-Length | Guest      | normal data        | Long guest names           |
      | Special                                   | Chäractërs | unicode characters | Unicode handling           |
      | Zero                                      | Cost       | rate 0.00          | Zero cost reservations     |
      | Same                                      | Day        | arrival=departure  | Same day arrival/departure |

  @guest_types
  Scenario Outline: Handle different guest types in reservations
    Given I have a "<reservation_type>" reservation for guest "Guest" "Type"
    And the guest type is "<guest_type>"
    When I dispatch a new reservation job
    Then the "SendReservationJob" job should be queued on "travelclick-high" queue
    And WireMock should receive "reservation" request
    And XML should contain guest type "<guest_type>"
    And the job should handle "<reservation_type>" reservation type correctly

    Examples:
      | reservation_type | guest_type   | description                 |
      | transient        | adult        | Standard adult guest        |
      | corporate        | business     | Business traveler           |
      | travel_agency    | leisure      | Leisure traveler via agency |
      | group            | group_member | Group booking member        |

  @seasonal_scenarios
  Scenario Outline: Handle seasonal reservation patterns
    Given I have a "transient" reservation for guest "Seasonal" "Guest"
    And the reservation is for "<season>" dates
    And the reservation uses seasonal rate plan "<rate_plan>"
    When I dispatch a new reservation job
    Then WireMock should receive "reservation" request
    And XML should contain rate plan "<rate_plan>"
    And the job should handle seasonal validation correctly

    Examples:
      | season  | rate_plan    | description           |
      | summer  | SUMMER_RATE  | Peak season rates     |
      | winter  | WINTER_RATE  | Off-season rates      |
      | holiday | HOLIDAY_RATE | Holiday premium rates |
      | weekend | WEEKEND_RATE | Weekend rates         |