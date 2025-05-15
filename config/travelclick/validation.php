<?php

return [
  /*
    |--------------------------------------------------------------------------
    | XML Schema Definitions
    |--------------------------------------------------------------------------
    |
    | Paths to XSD schema files for HTNG 2011B validation.
    | These schemas ensure our XML messages conform exactly to TravelClick's
    | expectations, like having a blueprint before building a house.
    |
    */
  'schemas' => [
    'htng_2011b' => [
      'base_path' => storage_path('schemas/htng2011b'),
      'files' => [
        'inventory' => 'OTA_HotelInvCountNotifRQ.xsd',
        'rates' => 'OTA_HotelRateNotifRQ.xsd',
        'reservations' => 'OTA_HotelResNotifRQ.xsd',
        'restrictions' => 'OTA_HotelAvailNotifRQ.xsd',
        'groups' => 'OTA_HotelInvBlockNotifRQ.xsd',
        'common' => 'OTA_CommonTypes.xsd',
        'htng_extensions' => 'HTNG_2011B_Extensions.xsd',
      ],
      'namespace_uri' => 'http://www.opentravel.org/OTA/2003/05',
      'htng_namespace' => 'http://htng.org/PWS/2011B/SingleGuestItinerary/Common/Types',
      'cache_enabled' => env('TRAVELCLICK_SCHEMA_CACHE', true),
      'cache_ttl' => 3600, // 1 hour
    ],
  ],

  /*
    |--------------------------------------------------------------------------
    | Message-Specific Validation Rules
    |--------------------------------------------------------------------------
    |
    | Detailed validation rules for each type of message we send to TravelClick.
    | Each message type has its own business rules and technical constraints.
    |
    */
  'message_validation' => [
    'inventory' => [
      'required_fields' => [
        'HotelCode',
        'Inventories.Inventory.StatusApplicationControl',
        'Inventories.Inventory.InvCounts.InvCount',
      ],
      'count_types' => [
        1 => 'Physical Rooms',
        2 => 'Available Rooms',
        4 => 'Definite Sold',
        5 => 'Tentative Sold',
        6 => 'Out of Order',
        99 => 'Oversell Rooms',
      ],
      'business_rules' => [
        'count_type_combinations' => [
          // When using CountType 2, no other CountTypes allowed
          'not_calculated' => [2],
          // When using calculated method, must include these
          'calculated' => [4, 5],
          // Optional CountTypes for calculated method
          'calculated_optional' => [1, 6, 99],
        ],
        'count_validation' => [
          'min_value' => 0,
          'max_value' => 9999,
        ],
        'date_range' => [
          'max_days_ahead' => 730, // 2 years
          'max_span_days' => 365,  // 1 year max span
        ],
      ],
      'xml_limits' => [
        'max_inventory_items' => 100,
        'max_room_type_length' => 20,
        'max_hotel_code_length' => 10,
      ],
    ],

    'rates' => [
      'required_fields' => [
        'HotelCode',
        'RatePlans.RatePlan',
        'RatePlans.RatePlan.Rates.Rate',
      ],
      'business_rules' => [
        'rate_validation' => [
          'min_amount' => 0.01,
          'max_amount' => 99999.99,
          'decimal_places' => 2,
        ],
        'guest_limits' => [
          'min_guests' => 1,
          'max_guests' => 4,
          'required_guests' => [1, 2], // 1st and 2nd guest mandatory
        ],
        'date_range' => [
          'max_days_ahead' => 730,
          'max_span_days' => 365,
        ],
        'rate_plan_codes' => [
          'min_length' => 1,
          'max_length' => 20,
          'pattern' => '/^[A-Za-z0-9_-]+$/',
        ],
      ],
      'xml_limits' => [
        'max_rate_plans' => 50,
        'max_rates_per_plan' => 365,
        'max_room_types' => 100,
      ],
    ],

    'reservations' => [
      'required_fields' => [
        'HotelReservations.HotelReservation',
        'HotelReservations.HotelReservation.ResGuests.ResGuest',
        'HotelReservations.HotelReservation.RoomStays.RoomStay',
      ],
      'business_rules' => [
        'guest_validation' => [
          'max_guests_per_room' => 8,
          'required_guest_fields' => ['FirstName', 'LastName'],
        ],
        'room_stay_validation' => [
          'max_nights' => 365,
          'min_nights' => 1,
        ],
        'guarantee_types' => [
          'CC' => 'Credit Card',
          'GT' => 'Guarantee',
          'DEP' => 'Deposit',
        ],
        'status_codes' => [
          'OK' => 'Confirmed',
          'CN' => 'Cancelled',
          'MD' => 'Modified',
        ],
      ],
      'xml_limits' => [
        'max_reservations_per_message' => 1,
        'max_room_stays' => 10,
        'max_special_requests' => 20,
      ],
    ],

    'restrictions' => [
      'required_fields' => [
        'HotelCode',
        'AvailStatusMessages.AvailStatusMessage',
      ],
      'business_rules' => [
        'restriction_types' => [
          'OpenForSale' => 'Open',
          'ClosedForArrival' => 'CTA',
          'ClosedForDeparture' => 'CTD',
          'Master' => 'Master',
          'MinLOS' => 'Minimum Length of Stay',
          'MaxLOS' => 'Maximum Length of Stay',
        ],
        'los_validation' => [
          'min_los' => 1,
          'max_los' => 30,
        ],
        'date_range' => [
          'max_days_ahead' => 730,
          'max_span_days' => 365,
        ],
      ],
      'xml_limits' => [
        'max_restrictions_per_message' => 200,
        'max_room_types' => 100,
        'max_rate_plans' => 100,
      ],
    ],

    'groups' => [
      'required_fields' => [
        'InvBlocks.InvBlock',
        'InvBlocks.InvBlock.HotelRef',
        'InvBlocks.InvBlock.InvBlockDates',
        'InvBlocks.InvBlock.RoomTypes.RoomType',
      ],
      'business_rules' => [
        'block_validation' => [
          'max_block_code_length' => 20,
          'max_block_name_length' => 100,
          'min_rooms' => 1,
          'max_rooms' => 1000,
        ],
        'pickup_status_types' => [
          1 => 'Allocated Rooms',
          2 => 'Available Rooms',
          3 => 'Sold Rooms',
        ],
        'cutoff_validation' => [
          'min_days_before_arrival' => 0,
          'max_days_before_arrival' => 365,
        ],
      ],
      'xml_limits' => [
        'max_blocks_per_message' => 1,
        'max_room_types_per_block' => 20,
        'max_contacts' => 10,
      ],
    ],
  ],

  /*
    |--------------------------------------------------------------------------
    | Validation Timeouts and Performance Settings
    |--------------------------------------------------------------------------
    |
    | How long to wait for different validation operations and when to cache
    | validation results for performance. Like setting time limits for
    | different types of inspections.
    |
    */
  'timeouts' => [
    'schema_validation' => [
      'inventory' => 5,     // seconds
      'rates' => 10,        // seconds
      'reservations' => 15, // seconds
      'restrictions' => 5,  // seconds
      'groups' => 20,       // seconds
    ],
    'business_rule_validation' => [
      'simple_rules' => 1,    // seconds
      'complex_rules' => 5,   // seconds
      'database_lookups' => 3, // seconds
    ],
    'cache_settings' => [
      'validation_results' => [
        'ttl' => 300,         // 5 minutes
        'max_items' => 1000,
      ],
      'schema_cache' => [
        'ttl' => 3600,        // 1 hour
        'auto_refresh' => true,
      ],
    ],
  ],

  /*
    |--------------------------------------------------------------------------
    | Error Handling and Reporting
    |--------------------------------------------------------------------------
    |
    | How to handle validation errors and what information to include in
    | error reports. Different error types need different handling strategies.
    |
    */
  'error_handling' => [
    'validation_failure_behavior' => [
      'halt_on_schema_error' => true,
      'halt_on_business_rule_error' => true,
      'collect_all_errors' => true,
      'max_errors_to_collect' => 50,
    ],
    'error_reporting' => [
      'include_xml_context' => true,
      'include_xpath' => true,
      'include_expected_values' => true,
      'sanitize_sensitive_data' => true,
    ],
    'retry_policy' => [
      'schema_errors' => false,        // Don't retry schema errors
      'business_rule_errors' => false, // Don't retry business rule errors
      'validation_timeout_errors' => true, // Retry timeout errors
      'max_retries' => 2,
    ],
    'notification_thresholds' => [
      'error_rate_threshold' => 10,   // Alert if >10% error rate
      'error_count_threshold' => 100, // Alert if >100 errors/hour
      'critical_error_types' => [
        'schema_validation_failure',
        'required_field_missing',
        'invalid_count_type',
      ],
    ],
  ],

  /*
    |--------------------------------------------------------------------------
    | Custom Validation Rules
    |--------------------------------------------------------------------------
    |
    | Configuration for custom Laravel validation rules specific to TravelClick
    | integration. These rules encode business logic that goes beyond simple
    | data type validation.
    |
    */
  'custom_rules' => [
    'valid_count_type' => [
      'class' => \App\TravelClick\Rules\ValidRoomType::class,
      'parameters' => [
        'allowed_combinations' => 'message_validation.inventory.business_rules.count_type_combinations',
      ],
    ],
    'valid_htng_date_range' => [
      'class' => \App\TravelClick\Rules\ValidHtngDateRange::class,
      'parameters' => [
        'max_days_ahead' => 730,
        'max_span_days' => 365,
        'allow_past_dates' => false,
      ],
    ],
    'valid_room_type' => [
      'class' => \App\TravelClick\Rules\ValidRoomType::class,
      'parameters' => [
        'check_exists_in_db' => true,
        'hotel_scope' => true,
      ],
    ],
    'valid_currency_code' => [
      'class' => \App\TravelClick\Rules\ValidCurrencyCode::class,
      'parameters' => [
        'iso_4217_only' => true,
        'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
      ],
    ],
  ],

  /*
    |--------------------------------------------------------------------------
    | Database Validation Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for validations that require database lookups.
    | These validations ensure data consistency between your system
    | and TravelClick.
    |
    */
  'database_validation' => [
    'enabled' => env('TRAVELCLICK_DB_VALIDATION', true),
    'cache_lookups' => true,
    'cache_ttl' => 900, // 15 minutes
    'validations' => [
      'hotel_code_exists' => [
        'table' => 'properties',
        'column' => 'property_code',
        'cache_key_prefix' => 'hotel_exists',
      ],
      'room_type_exists' => [
        'table' => 'property_room_types',
        'columns' => ['property_id', 'code'],
        'cache_key_prefix' => 'room_type_exists',
      ],
      'rate_plan_exists' => [
        'table' => 'contracts',
        'columns' => ['property_id', 'contract_identifier'],
        'cache_key_prefix' => 'rate_plan_exists',
      ],
    ],
    'connection' => config('travelclick.database.main_connection'),
  ],

  /*
    |--------------------------------------------------------------------------
    | Development and Testing Settings
    |--------------------------------------------------------------------------
    |
    | Special validation settings for development and testing environments.
    | These help catch issues early but might be too strict for production.
    |
    */
  'development' => [
    'strict_validation' => env('TRAVELCLICK_STRICT_VALIDATION', false),
    'validate_optional_fields' => true,
    'warn_on_deprecated_fields' => true,
    'log_validation_details' => true,
    'performance_profiling' => env('TRAVELCLICK_PROFILE_VALIDATION', false),
    'test_data_generation' => [
      'generate_invalid_samples' => true,
      'generate_edge_cases' => true,
      'sample_size' => 100,
    ],
  ],

  /*
    |--------------------------------------------------------------------------
    | Monitoring and Metrics
    |--------------------------------------------------------------------------
    |
    | Configuration for monitoring validation performance and collecting
    | metrics about validation success/failure rates.
    |
    */
  'monitoring' => [
    'collect_metrics' => env('TRAVELCLICK_COLLECT_VALIDATION_METRICS', true),
    'metrics_retention_days' => 30,
    'track_validation_times' => true,
    'track_error_patterns' => true,
    'alert_thresholds' => [
      'validation_time_threshold_ms' => 1000,
      'error_rate_threshold_percent' => 5,
      'schema_load_time_threshold_ms' => 500,
    ],
    'dashboard_refresh_interval' => 60, // seconds
  ],
];
