<?php

namespace App\Listeners;

use App\Error;
use App\Events\ReportError;
use App\Events\TempError;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class MakeTempError
{

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Handle the event.
     *
     * @param  ReportError  $event
     * @return void
     */
    public function handle(TempError $event)
    {
        $TempError = Error::where('name', $event->error['name'])->first();
        
        if($TempError) {
            $newTotal = intval($TempError->total) + 1;

            if($newTotal == 3) {
                 $error = array(
                                'level' => 'Warning',
                                'title' => 'Could not get price',
                                'report' => 'Could not get price after 3 tries : ' .$event->error['name']. '<br/> Please deactivate the relation.'
                             );
                event(new ReportError($error));
            }

            $TempError->update(['total' => $newTotal]);

            return true;

        }

        $TempError = Error::create($event->error);
        return true;
    }
}
