<?php

return [
  /*
    |--------------------------------------------------------------------------
    | TravelClick Integration Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all configuration settings for the TravelClick
    | integration. Think of it as our "recipe book" that tells the system
    | exactly how to communicate with TravelClick's services.
    |
    */

  /*
    |--------------------------------------------------------------------------
    | Service Endpoints
    |--------------------------------------------------------------------------
    |
    | These are the URLs where TravelClick services live. It's like having
    | the correct address for different departments in a large company.
    |
    */
  'endpoints' => [
    'production' => env('TRAVELCLICK_ENDPOINT_PROD', 'https://pms.ihotelier.com/HTNGService/services/HTNG2011BService'),
    'test' => env('TRAVELCLICK_ENDPOINT_TEST', 'https://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService'),
    'wsdl' => env('TRAVELCLICK_WSDL_URL', 'https://pms.ihotelier.com/HTNGService/services/HTNG2011BService?wsdl'),
  ],

  /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials for accessing TravelClick services. These work like
    | a key card and PIN to access different areas of a building.
    |
    */
  'credentials' => [
    'username' => env('TRAVELCLICK_USERNAME', null),
    'password' => env('TRAVELCLICK_PASSWORD', null),
    'hotel_code' => env('TRAVELCLICK_HOTEL_CODE', null),
  ],

  /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Different types of operations go to different queues, like having
    | separate lanes at a supermarket for different types of customers.
    |
    */
  'queues' => [
    'high_priority' => 'travelclick-high',      // Cancellations, urgent updates
    'outbound' => 'travelclick-outbound',       // Sending data to TravelClick
    'inbound' => 'travelclick-inbound',         // Processing data from TravelClick
    'low_priority' => 'travelclick-low',        // Background tasks, reports
  ],

  /*
    |--------------------------------------------------------------------------
    | Retry and Error Handling
    |--------------------------------------------------------------------------
    |
    | How many times to retry failed operations and how long to wait between
    | attempts. Like a patient person who tries knocking on a door multiple
    | times before giving up.
    |
    */
  'retry_policy' => [
    'max_attempts' => 3,
    'backoff_strategy' => 'exponential',  // Wait longer between each retry
    'initial_delay_seconds' => 10,        // Wait 10 seconds before first retry
    'max_delay_seconds' => 300,           // Never wait more than 5 minutes
    'multiplier' => 2,                    // Double the wait time each retry
  ],

  /*
    |--------------------------------------------------------------------------
    | Message Types Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for different types of messages we send to TravelClick.
    | Each type has its own requirements and processing rules.
    |
    */
  'message_types' => [
    'inventory' => [
      'enabled' => true,
      'batch_size' => 100,              // How many items to process at once
      'timeout_seconds' => 60,          // How long to wait for response
      'count_types' => [1, 2, 4, 5, 6, 99], // Valid inventory count types
    ],
    'rates' => [
      'enabled' => true,
      'batch_size' => 50,
      'timeout_seconds' => 90,
      'supports_linked_rates' => true,
    ],
    'reservations' => [
      'enabled' => true,
      'batch_size' => 20,
      'timeout_seconds' => 120,
      'auto_send_inventory_updates' => true, // Send inventory updates after reservations
    ],
    'restrictions' => [
      'enabled' => true,
      'batch_size' => 200,
      'timeout_seconds' => 45,
    ],
    'groups' => [
      'enabled' => true,
      'batch_size' => 10,
      'timeout_seconds' => 180,
    ],
  ],

  /*
    |--------------------------------------------------------------------------
    | SOAP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Low-level settings for how we communicate with TravelClick's SOAP service.
    | Like configuring the telephone system to work properly.
    |
    */
  'soap' => [
    'trace' => true,                      // Keep record of all SOAP calls
    'cache_wsdl' => WSDL_CACHE_BOTH,     // Cache WSDL files for performance
    'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
    'user_agent' => 'Centrium-TravelClick-Integration/1.0',
    'stream_context' => [
      'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false,
      ],
      'http' => [
        'timeout' => 30,
        'follow_location' => 0,
      ],
    ],
  ],

  /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | How detailed our logging should be and where to store the logs.
    | Like choosing what level of detail to include in a diary.
    |
    */
  'logging' => [
    'level' => env('TRAVELCLICK_LOG_LEVEL', 'info'),
    'channels' => [
      'main' => 'travelclick',
      'soap_trace' => 'travelclick-soap',
      'errors' => 'travelclick-errors',
    ],
    'log_successful_operations' => env('TRAVELCLICK_LOG_SUCCESS', true),
    'log_soap_payloads' => env('TRAVELCLICK_LOG_SOAP', false),
    'retention_days' => 30,               // Keep logs for 30 days
  ],

  /*
    |--------------------------------------------------------------------------
    | Data Validation Rules
    |--------------------------------------------------------------------------
    |
    | Rules for validating data before sending to TravelClick.
    | Like a quality inspector checking products before shipping.
    |
    */
  'validation' => [
    'hotel_code' => [
      'min_length' => 1,
      'max_length' => 10,
      'pattern' => '/^[A-Za-z0-9]+$/',
    ],
    'room_type_code' => [
      'min_length' => 1,
      'max_length' => 20,
      'pattern' => '/^[A-Za-z0-9_-]+$/',
    ],
    'rate_plan_code' => [
      'min_length' => 1,
      'max_length' => 20,
      'pattern' => '/^[A-Za-z0-9_-]+$/',
    ],
  ],

  /*
    |--------------------------------------------------------------------------
    | Synchronization Settings
    |--------------------------------------------------------------------------
    |
    | How often to sync different types of data and in what order.
    | Like scheduling different cleaning tasks throughout the day.
    |
    */
  'synchronization' => [
    'full_sync_schedule' => '0 2 * * *',  // Daily at 2:00 AM
    'delta_sync_interval' => 5,           // Every 5 minutes for changes
    'order' => [
      'inventory',    // Always sync inventory first
      'rates',        // Then rates
      'restrictions', // Then restrictions
      'groups',       // Finally groups
    ],
    'parallel_processing' => false,       // Process sequentially for safety
  ],

  /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Which database connections to use for different operations.
    | Like knowing which file cabinet to look in for different documents.
    |
    */
  'database' => [
    'main_connection' => 'centrium',      // Main business data
    'log_connection' => 'centriumLog',    // Audit and log data
    'use_transactions' => true,          // Ensure data integrity
  ],
];
