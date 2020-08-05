<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ConfirmOrder extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $proposale;
    public $pito;
    public $sub_order_id;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $pito, $proposale)
    {
        //
        $this->user = $user;
        $this->pito = $pito;
        $this->proposale = $proposale;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Xác nhận đơn hàng ' . $this->proposale['id'] . ' | ' . $this->user->detail['company'])
            ->view('mails.confirmed_order');
    }
}
