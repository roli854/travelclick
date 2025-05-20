<?php

namespace App\TravelClick\Facades;

use App\TravelClick\DTOs\RateData;
use App\TravelClick\Enums\RateOperationType;
use App\TravelClick\Jobs\OutboundJobs\UpdateRatesJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void dispatch(Collection|array $rates, string $hotelCode, RateOperationType $operationType = RateOperationType::RATE_UPDATE, bool $isDeltaUpdate = true, int $batchSize = 0, ?string $trackingId = null)
 * @method static void dispatchSync(Collection|array $rates, string $hotelCode, RateOperationType $operationType = RateOperationType::RATE_UPDATE, bool $isDeltaUpdate = true, int $batchSize = 0, ?string $trackingId = null)
 * @method static void dispatchAfterResponse(Collection|array $rates, string $hotelCode, RateOperationType $operationType = RateOperationType::RATE_UPDATE, bool $isDeltaUpdate = true, int $batchSize = 0, ?string $trackingId = null)
 * @method static void dispatchIf(bool $condition, Collection|array $rates, string $hotelCode, RateOperationType $operationType = RateOperationType::RATE_UPDATE, bool $isDeltaUpdate = true, int $batchSize = 0, ?string $trackingId = null)
 * @method static void dispatchUnless(bool $condition, Collection|array $rates, string $hotelCode, RateOperationType $operationType = RateOperationType::RATE_UPDATE, bool $isDeltaUpdate = true, int $batchSize = 0, ?string $trackingId = null)
 *
 * @see \App\TravelClick\Jobs\OutboundJobs\UpdateRatesJob
 */
class RateSync extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'travelclick.rate-sync';
    }

    /**
     * Dispatch a new rate sync job.
     *
     * @param Collection<int, RateData>|array<int, RateData> $rates
     * @param string $hotelCode
     * @param RateOperationType $operationType
     * @param bool $isDeltaUpdate
     * @param int $batchSize
     * @param string|null $trackingId
     * @return void
     */
    public static function dispatch(
        Collection|array $rates,
        string $hotelCode,
        RateOperationType $operationType = RateOperationType::RATE_UPDATE,
        bool $isDeltaUpdate = true,
        int $batchSize = 0,
        ?string $trackingId = null
    ): void {
        UpdateRatesJob::dispatch($rates, $hotelCode, $operationType, $isDeltaUpdate, $batchSize, $trackingId);
    }

    /**
     * Dispatch a new rate sync job synchronously.
     *
     * @param Collection<int, RateData>|array<int, RateData> $rates
     * @param string $hotelCode
     * @param RateOperationType $operationType
     * @param bool $isDeltaUpdate
     * @param int $batchSize
     * @param string|null $trackingId
     * @return void
     */
    public static function dispatchSync(
        Collection|array $rates,
        string $hotelCode,
        RateOperationType $operationType = RateOperationType::RATE_UPDATE,
        bool $isDeltaUpdate = true,
        int $batchSize = 0,
        ?string $trackingId = null
    ): void {
        UpdateRatesJob::dispatchSync($rates, $hotelCode, $operationType, $isDeltaUpdate, $batchSize, $trackingId);
    }
}
