<?php

namespace App\TravelClick\Observers;

use App\TravelClick\Models\TravelClickMessageHistory;

class TravelClickMessageHistoryObserver
{
    /**
     * Handle the TravelClickMessageHistory "created" event.
     */
    public function created(TravelClickMessageHistory $travelClickMessageHistory): void
    {
        //
    }

    /**
     * Handle the TravelClickMessageHistory "updated" event.
     */
    public function updated(TravelClickMessageHistory $travelClickMessageHistory): void
    {
        //
    }

    /**
     * Handle the TravelClickMessageHistory "deleted" event.
     */
    public function deleted(TravelClickMessageHistory $travelClickMessageHistory): void
    {
        //
    }

    /**
     * Handle the TravelClickMessageHistory "restored" event.
     */
    public function restored(TravelClickMessageHistory $travelClickMessageHistory): void
    {
        //
    }

    /**
     * Handle the TravelClickMessageHistory "force deleted" event.
     */
    public function forceDeleted(TravelClickMessageHistory $travelClickMessageHistory): void
    {
        //
    }
}
