<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class MailBase extends Mailable
{
    use Queueable, SerializesModels;

    public $message;
    public $text_subject;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($text_subject, $message)
    {
        //
        $this->message = $message;
        $this->text_subject = $text_subject;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->text_subject)
            ->view('mails.mail_base');
    }
}
