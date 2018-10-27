<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ReportErrorMail extends Mailable
{
    use Queueable, SerializesModels;

    public $error = [];

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(array $error)
    {
        $this->error = $error;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        $subject = 'Error notification';

        return $this->view('emails.report-error')
                ->subject($subject);

    }
}
