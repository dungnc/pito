<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class CancelOrder extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $order;
    public $pito;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $pito, $order)
    {
        //
        $this->user = $user;
        $this->pito = $pito;
        $this->order = $order;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('PITO gửi báo giá')
            ->view('mails.cancel_order');
    }
}
