# Jobs
## Overview
This namespace contains 7 classes/interfaces/enums.
## Table of Contents
- [ProcessIncomingReservationJob](#processincomingreservationjob) (Class)
- [ProcessReservationCancellationJob](#processreservationcancellationjob) (Class)
- [ProcessReservationModificationJob](#processreservationmodificationjob) (Class)
- [CancelReservationJob](#cancelreservationjob) (Class)
- [SendReservationJob](#sendreservationjob) (Class)
- [UpdateInventoryJob](#updateinventoryjob) (Class)
- [UpdateRatesJob](#updateratesjob) (Class)
## Complete API Reference
---
### ProcessIncomingReservationJob
**Type:** Class
**Full Name:** `App\TravelClick\Jobs\InboundJobs\ProcessIncomingReservationJob`
#### Properties
```php
public $tries;
public $backoff;
public $job;
public $connection;
public $queue;
public $delay;
public $afterCommit;
public $middleware;
public $chained;
public $chainConnection;
public $chainQueue;
public $chainCatchCallbacks;
```
#### Methods
```php
public function __construct(string $rawXml, string $hotelCode, string $messageId);
public function handle(ReservationParser $parser, SoapService $soapService, ReservationResponseXmlBuilder $xmlBuilder): void;
public static function dispatch(...$arguments);
public static function dispatchIf($boolean, ...$arguments);
public static function dispatchUnless($boolean, ...$arguments);
public static function dispatchSync(...$arguments);
public static function dispatchAfterResponse(...$arguments);
public static function withChain($chain);
public function attempts();
public function delete();
public function fail($exception = null);
public function release($delay = 0);
public function withFakeQueueInteractions();
public function assertDeleted();
public function assertNotDeleted();
public function assertFailed();
public function assertFailedWith($exception);
public function assertNotFailed();
public function assertReleased($delay = null);
public function assertNotReleased();
public function setJob(Illuminate\Contracts\Queue\Job $job);
public function onConnection($connection);
public function onQueue($queue);
public function allOnConnection($connection);
public function allOnQueue($queue);
public function delay($delay);
public function withoutDelay();
public function afterCommit();
public function beforeCommit();
public function through($middleware);
public function chain($chain);
public function prependToChain($job);
public function appendToChain($job);
public function dispatchNextJobInChain();
public function invokeChainCatchCallbacks($e);
public function assertHasChain($expectedChain);
public function assertDoesntHaveChain();
public function __serialize();
public function __unserialize(array $values);
public function restoreModel($value);
```
---
### ProcessReservationCancellationJob
**Type:** Class
**Full Name:** `App\TravelClick\Jobs\InboundJobs\ProcessReservationCancellationJob`
**Description:** Job to process reservation cancellations received from TravelClick
#### Properties
```php
public $tries;
public $backoff;
public $deleteWhenMissingModels;
public $job;
public $connection;
public $queue;
public $delay;
public $afterCommit;
public $middleware;
public $chained;
public $chainConnection;
public $chainQueue;
public $chainCatchCallbacks;
```
#### Methods
```php
public function __construct(ReservationDataDto $reservationData);
public function handle(ReservationService $reservationService): void;
public static function dispatch(...$arguments);
public static function dispatchIf($boolean, ...$arguments);
public static function dispatchUnless($boolean, ...$arguments);
public static function dispatchSync(...$arguments);
public static function dispatchAfterResponse(...$arguments);
public static function withChain($chain);
public function attempts();
public function delete();
public function fail($exception = null);
public function release($delay = 0);
public function withFakeQueueInteractions();
public function assertDeleted();
public function assertNotDeleted();
public function assertFailed();
public function assertFailedWith($exception);
public function assertNotFailed();
public function assertReleased($delay = null);
public function assertNotReleased();
public function setJob(Illuminate\Contracts\Queue\Job $job);
public function onConnection($connection);
public function onQueue($queue);
public function allOnConnection($connection);
public function allOnQueue($queue);
public function delay($delay);
public function withoutDelay();
public function afterCommit();
public function beforeCommit();
public function through($middleware);
public function chain($chain);
public function prependToChain($job);
public function appendToChain($job);
public function dispatchNextJobInChain();
public function invokeChainCatchCallbacks($e);
public function assertHasChain($expectedChain);
public function assertDoesntHaveChain();
public function __serialize();
public function __unserialize(array $values);
public function restoreModel($value);
```
---
### ProcessReservationModificationJob
**Type:** Class
**Full Name:** `App\TravelClick\Jobs\InboundJobs\ProcessReservationModificationJob`
**Description:** Job to process an incoming reservation modification from TravelClick
#### Properties
```php
public $tries;
public $backoff;
public $deleteWhenMissingModels;
public $job;
public $connection;
public $queue;
public $delay;
public $afterCommit;
public $middleware;
public $chained;
public $chainConnection;
public $chainQueue;
public $chainCatchCallbacks;
```
#### Methods
```php
public function __construct(string $messageId, string $messageXml, string $hotelCode, string|null $batchId = null, int|null $messageHistoryId = null);
public function uniqueId();
public function handle(ReservationParser $parser, ReservationService $reservationService);
public function failed(Throwable $exception);
public static function dispatch(...$arguments);
public static function dispatchIf($boolean, ...$arguments);
public static function dispatchUnless($boolean, ...$arguments);
public static function dispatchSync(...$arguments);
public static function dispatchAfterResponse(...$arguments);
public static function withChain($chain);
public function attempts();
public function delete();
public function fail($exception = null);
public function release($delay = 0);
public function withFakeQueueInteractions();
public function assertDeleted();
public function assertNotDeleted();
public function assertFailed();
public function assertFailedWith($exception);
public function assertNotFailed();
public function assertReleased($delay = null);
public function assertNotReleased();
public function setJob(Illuminate\Contracts\Queue\Job $job);
public function onConnection($connection);
public function onQueue($queue);
public function allOnConnection($connection);
public function allOnQueue($queue);
public function delay($delay);
public function withoutDelay();
public function afterCommit();
public function beforeCommit();
public function through($middleware);
public function chain($chain);
public function prependToChain($job);
public function appendToChain($job);
public function dispatchNextJobInChain();
public function invokeChainCatchCallbacks($e);
public function assertHasChain($expectedChain);
public function assertDoesntHaveChain();
public function __serialize();
public function __unserialize(array $values);
public function restoreModel($value);
```
---
### CancelReservationJob
**Type:** Class
**Full Name:** `App\TravelClick\Jobs\OutboundJobs\CancelReservationJob`
**Description:** Job to cancel a reservation in the TravelClick system.
#### Properties
```php
public int $tries;
public int $backoff;
public int $maxExceptions;
public int $timeout;
public bool $failOnTimeout;
public $job;
public $connection;
public $queue;
public $delay;
public $afterCommit;
public $middleware;
public $chained;
public $chainConnection;
public $chainQueue;
public $chainCatchCallbacks;
```
#### Methods
```php
public function __construct(ReservationDataDto $reservationData, string $hotelCode, bool $highPriority = false, int|null $propertyId = null);
public function middleware(): array;
public function handle(SoapService $soapService, RetryHelper $retryHelper): void;
public static function cancel(ReservationDataDto $reservationData, string $hotelCode, int|null $propertyId = null): self;
public static function urgent(ReservationDataDto $reservationData, string $hotelCode, int|null $propertyId = null): self;
public static function dispatch(...$arguments);
public static function dispatchIf($boolean, ...$arguments);
public static function dispatchUnless($boolean, ...$arguments);
public static function dispatchSync(...$arguments);
public static function dispatchAfterResponse(...$arguments);
public static function withChain($chain);
public function attempts();
public function delete();
public function fail($exception = null);
public function release($delay = 0);
public function withFakeQueueInteractions();
public function assertDeleted();
public function assertNotDeleted();
public function assertFailed();
public function assertFailedWith($exception);
public function assertNotFailed();
public function assertReleased($delay = null);
public function assertNotReleased();
public function setJob(Illuminate\Contracts\Queue\Job $job);
public function onConnection($connection);
public function onQueue($queue);
public function allOnConnection($connection);
public function allOnQueue($queue);
public function delay($delay);
public function withoutDelay();
public function afterCommit();
public function beforeCommit();
public function through($middleware);
public function chain($chain);
public function prependToChain($job);
public function appendToChain($job);
public function dispatchNextJobInChain();
public function invokeChainCatchCallbacks($e);
public function assertHasChain($expectedChain);
public function assertDoesntHaveChain();
public function __serialize();
public function __unserialize(array $values);
public function restoreModel($value);
```
---
### SendReservationJob
**Type:** Class
**Full Name:** `App\TravelClick\Jobs\OutboundJobs\SendReservationJob`
**Description:** Job to send reservation data to TravelClick via SOAP.
#### Properties
```php
public $tries;
public $backoff;
public $job;
public $connection;
public $queue;
public $delay;
public $afterCommit;
public $middleware;
public $chained;
public $chainConnection;
public $chainQueue;
public $chainCatchCallbacks;
```
#### Methods
```php
public function __construct(ReservationDataDto $reservationData, bool $updateInventory = true);
public function handle(SoapService $soapService, ReservationXmlBuilder $xmlBuilder, RetryHelper $retryHelper): void;
public function middleware(): array;
public function uniqueId(): string;
public static function dispatch(...$arguments);
public static function dispatchIf($boolean, ...$arguments);
public static function dispatchUnless($boolean, ...$arguments);
public static function dispatchSync(...$arguments);
public static function dispatchAfterResponse(...$arguments);
public static function withChain($chain);
public function attempts();
public function delete();
public function fail($exception = null);
public function release($delay = 0);
public function withFakeQueueInteractions();
public function assertDeleted();
public function assertNotDeleted();
public function assertFailed();
public function assertFailedWith($exception);
public function assertNotFailed();
public function assertReleased($delay = null);
public function assertNotReleased();
public function setJob(Illuminate\Contracts\Queue\Job $job);
public function onConnection($connection);
public function onQueue($queue);
public function allOnConnection($connection);
public function allOnQueue($queue);
public function delay($delay);
public function withoutDelay();
public function afterCommit();
public function beforeCommit();
public function through($middleware);
public function chain($chain);
public function prependToChain($job);
public function appendToChain($job);
public function dispatchNextJobInChain();
public function invokeChainCatchCallbacks($e);
public function assertHasChain($expectedChain);
public function assertDoesntHaveChain();
public function __serialize();
public function __unserialize(array $values);
public function restoreModel($value);
```
---
### UpdateInventoryJob
**Type:** Class
**Full Name:** `App\TravelClick\Jobs\OutboundJobs\UpdateInventoryJob`
**Description:** Job for sending inventory updates to TravelClick via HTNG 2011B
#### Properties
```php
public int $tries;
public int $backoff;
public int $maxExceptions;
public int $timeout;
public bool $failOnTimeout;
public $job;
public $connection;
public $queue;
public $delay;
public $afterCommit;
public $middleware;
public $chained;
public $chainConnection;
public $chainQueue;
public $chainCatchCallbacks;
```
#### Methods
```php
public function __construct(Spatie\LaravelData\DataCollection|App\TravelClick\DTOs\InventoryData $inventoryData, string $hotelCode, bool $isOverlay = false, bool $highPriority = false, int|null $propertyId = null);
public function middleware(): array;
public function handle(SoapService $soapService, RetryHelper $retryHelper): void;
public static function delta(Spatie\LaravelData\DataCollection|App\TravelClick\DTOs\InventoryData $inventoryData, string $hotelCode, int|null $propertyId = null): self;
public static function overlay(Spatie\LaravelData\DataCollection|App\TravelClick\DTOs\InventoryData $inventoryData, string $hotelCode, int|null $propertyId = null, bool $highPriority = true): self;
public static function urgent(Spatie\LaravelData\DataCollection|App\TravelClick\DTOs\InventoryData $inventoryData, string $hotelCode, int|null $propertyId = null): self;
public static function dispatch(...$arguments);
public static function dispatchIf($boolean, ...$arguments);
public static function dispatchUnless($boolean, ...$arguments);
public static function dispatchSync(...$arguments);
public static function dispatchAfterResponse(...$arguments);
public static function withChain($chain);
public function attempts();
public function delete();
public function fail($exception = null);
public function release($delay = 0);
public function withFakeQueueInteractions();
public function assertDeleted();
public function assertNotDeleted();
public function assertFailed();
public function assertFailedWith($exception);
public function assertNotFailed();
public function assertReleased($delay = null);
public function assertNotReleased();
public function setJob(Illuminate\Contracts\Queue\Job $job);
public function onConnection($connection);
public function onQueue($queue);
public function allOnConnection($connection);
public function allOnQueue($queue);
public function delay($delay);
public function withoutDelay();
public function afterCommit();
public function beforeCommit();
public function through($middleware);
public function chain($chain);
public function prependToChain($job);
public function appendToChain($job);
public function dispatchNextJobInChain();
public function invokeChainCatchCallbacks($e);
public function assertHasChain($expectedChain);
public function assertDoesntHaveChain();
public function __serialize();
public function __unserialize(array $values);
public function restoreModel($value);
```
---
### UpdateRatesJob
**Type:** Class
**Full Name:** `App\TravelClick\Jobs\OutboundJobs\UpdateRatesJob`
#### Properties
```php
public $tries;
public $backoff;
public $maxExceptions;
public $deleteWhenMissingModels;
public $job;
public $connection;
public $queue;
public $delay;
public $afterCommit;
public $middleware;
public $chained;
public $chainConnection;
public $chainQueue;
public $chainCatchCallbacks;
```
#### Methods
```php
public function __construct(Illuminate\Support\Collection|array $rates, string $hotelCode, RateOperationType $operationType = \App\TravelClick\Enums\RateOperationType::RATE_UPDATE, bool $isDeltaUpdate = true, int $batchSize = 0, string|null $trackingId = null);
public function handle(SoapService $soapService, RateXmlBuilder $xmlBuilder, RetryHelper $retryHelper): void;
public function getUniqueId(): string|null;
public function middleware(): array;
public function tags(): array;
public static function dispatch(...$arguments);
public static function dispatchIf($boolean, ...$arguments);
public static function dispatchUnless($boolean, ...$arguments);
public static function dispatchSync(...$arguments);
public static function dispatchAfterResponse(...$arguments);
public static function withChain($chain);
public function attempts();
public function delete();
public function fail($exception = null);
public function release($delay = 0);
public function withFakeQueueInteractions();
public function assertDeleted();
public function assertNotDeleted();
public function assertFailed();
public function assertFailedWith($exception);
public function assertNotFailed();
public function assertReleased($delay = null);
public function assertNotReleased();
public function setJob(Illuminate\Contracts\Queue\Job $job);
public function onConnection($connection);
public function onQueue($queue);
public function allOnConnection($connection);
public function allOnQueue($queue);
public function delay($delay);
public function withoutDelay();
public function afterCommit();
public function beforeCommit();
public function through($middleware);
public function chain($chain);
public function prependToChain($job);
public function appendToChain($job);
public function dispatchNextJobInChain();
public function invokeChainCatchCallbacks($e);
public function assertHasChain($expectedChain);
public function assertDoesntHaveChain();
public function __serialize();
public function __unserialize(array $values);
public function restoreModel($value);
```
## Detailed Documentation
For detailed documentation of each class, see:
- [ProcessIncomingReservationJob](ProcessIncomingReservationJob.md)
- [ProcessReservationCancellationJob](ProcessReservationCancellationJob.md)
- [ProcessReservationModificationJob](ProcessReservationModificationJob.md)
- [CancelReservationJob](CancelReservationJob.md)
- [SendReservationJob](SendReservationJob.md)
- [UpdateInventoryJob](UpdateInventoryJob.md)
- [UpdateRatesJob](UpdateRatesJob.md)