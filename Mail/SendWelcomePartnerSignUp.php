<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWelcomePartnerSignUp extends Mailable
{
    use Queueable, SerializesModels;

    public $partner;
    public $pito;
    public $mess;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($partner, $mess, $pito)
    {
        //
        $this->partner = $partner;
        $this->pito = $pito;
        $this->mess = $mess;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Đăng ký thành công tài khoản đối tác')
            ->view('mails.welcome_partner_signup');
    }
}
