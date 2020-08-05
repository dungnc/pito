<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendProposaleEdit extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $proposale;
    public $pito;
    public $sub_order_id;
    public $token;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $pito, $proposale, $token, $sub_order_id)
    {
        //
        $this->user = $user;
        $this->pito = $pito;
        $this->proposale = $proposale;
        $this->token = $token;
        $this->sub_order_id = $sub_order_id;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $name = "";
        if ($this->user->type_role == "CUSTOMER") {
            $name = $this->user->detail->company;
        } else {
            $name = $this->user->name;
        }
        return $this->subject('Báo giá ' . $this->proposale['id'] . ' kính gửi ' . $name)
            ->view('mails.proposale_edit');
    }
}
