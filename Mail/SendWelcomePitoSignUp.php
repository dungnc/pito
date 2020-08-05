<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWelcomePitoSignUp extends Mailable
{
    use Queueable, SerializesModels;

    public $pito_new;
    public $pito;
    public $mess;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($pito_new, $mess, $pito)
    {
        //
        $this->pito_new = $pito_new;
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

        return $this->subject('Đăng ký thành công tài khoản nhân viên')
            ->view('mails.welcome_pito_signup');
    }
}
