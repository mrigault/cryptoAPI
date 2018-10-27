<?php

namespace App\Listeners;

use App\Events\ReportError;
use App\Report;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class MakeErrorReport
{

    public $error;

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
    public function handle(ReportError $event)
    {
        
        $Report = Report::updateOrCreate($event->error);
        var_dump('Report created');
    }
}
