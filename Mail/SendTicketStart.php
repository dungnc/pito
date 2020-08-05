<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTicketStart extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $user;
    public $pito;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data, $user, $pito)
    {
        //
        $this->data = $data;
        $this->user = $user;
        $this->pito = $pito;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('PITO gửi phiếu đi tiệc')
            ->view('pdf_views.begin_tickets.detail_mail');
    }
}
