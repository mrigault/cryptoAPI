<?php

namespace App\Listeners;

use App\Events\ReportError;
use App\Mail\ReportErrorMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class MailReportNotification
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  ReportError  $event
     * @return void
     */
    public function handle(ReportError $event)
    {   
        Mail::to('rigault93@gmail.com')->send(new ReportErrorMail($event->error));
        var_dump('mail sent');
    }
}
