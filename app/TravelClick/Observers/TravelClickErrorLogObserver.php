<?php

namespace App\TravelClick\Observers;

use App\TravelClick\Models\TravelClickErrorLog;

class TravelClickErrorLogObserver
{
    /**
     * Handle the TravelClickErrorLog "created" event.
     */
    public function created(TravelClickErrorLog $travelClickErrorLog): void
    {
        //
    }

    /**
     * Handle the TravelClickErrorLog "updated" event.
     */
    public function updated(TravelClickErrorLog $travelClickErrorLog): void
    {
        //
    }

    /**
     * Handle the TravelClickErrorLog "deleted" event.
     */
    public function deleted(TravelClickErrorLog $travelClickErrorLog): void
    {
        //
    }

    /**
     * Handle the TravelClickErrorLog "restored" event.
     */
    public function restored(TravelClickErrorLog $travelClickErrorLog): void
    {
        //
    }

    /**
     * Handle the TravelClickErrorLog "force deleted" event.
     */
    public function forceDeleted(TravelClickErrorLog $travelClickErrorLog): void
    {
        //
    }
}
