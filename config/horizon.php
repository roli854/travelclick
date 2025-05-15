<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            // TravelClick High Priority Queue
            // Processes: Cancellations, urgent updates, error recovery
            'travelclick-high' => [
                'connection' => 'redis',
                'queue' => ['travelclick-high'],
                'balance' => 'simple',
                'processes' => 5,          // Start with 5 workers
                'tries' => 3,             // Retry up to 3 times
                'maxTime' => 60,          // Max 60 seconds per job
                'nice' => 0,              // Normal priority
                'timeout' => 60,          // 60 second timeout
                'memory' => 512,          // 512MB memory limit
            ],
            // TravelClick Outbound Queue
            // Processes: Inventory updates, rate updates, sending reservations
            'travelclick-outbound' => [
                'connection' => 'redis',
                'queue' => ['travelclick-outbound'],
                'balance' => 'simple',
                'processes' => 10,        // More workers for bulk operations
                'tries' => 3,
                'maxTime' => 120,         // Allow more time for large updates
                'nice' => 0,
                'timeout' => 120,
                'memory' => 1024,         // More memory for large XML payloads
            ],
            // TravelClick Inbound Queue
            // Processes: Incoming reservations, modifications, cancellations
            'travelclick-inbound' => [
                'connection' => 'redis',
                'queue' => ['travelclick-inbound'],
                'balance' => 'auto',      // Auto-balance for varying loads
                'processes' => 8,
                'tries' => 3,
                'maxTime' => 90,
                'nice' => 0,
                'timeout' => 90,
                'memory' => 512,
            ],
            // TravelClick Low Priority Queue
            // Processes: Logs cleanup, reports generation, non-critical tasks
            'travelclick-low' => [
                'connection' => 'redis',
                'queue' => ['travelclick-low'],
                'balance' => 'simple',
                'processes' => 3,         // Fewer workers for background tasks
                'tries' => 2,             // Fewer retries for non-critical tasks
                'maxTime' => 300,         // Allow more time for complex reports
                'nice' => 10,             // Lower priority
                'timeout' => 300,
                'memory' => 256,
            ],
            // Generic supervisor for other tasks (keep existing configuration)
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'auto',
                'processes' => 10,
                'tries' => 3,
            ],
        ],

        'local' => [
            // For local development, use simpler configuration
            'travelclick-high' => [
                'connection' => 'redis',
                'queue' => ['travelclick-high'],
                'balance' => 'simple',
                'processes' => 1,
                'tries' => 3,
            ],
            'travelclick-outbound' => [
                'connection' => 'redis',
                'queue' => ['travelclick-outbound'],
                'balance' => 'simple',
                'processes' => 2,
                'tries' => 3,
            ],
            'travelclick-inbound' => [
                'connection' => 'redis',
                'queue' => ['travelclick-inbound'],
                'balance' => 'simple',
                'processes' => 1,
                'tries' => 3,
            ],
            'travelclick-low' => [
                'connection' => 'redis',
                'queue' => ['travelclick-low'],
                'balance' => 'simple',
                'processes' => 1,
                'tries' => 2,
            ],
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
        ],
    ],
];
