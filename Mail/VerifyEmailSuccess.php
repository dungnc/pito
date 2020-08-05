<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyEmailSuccess extends Mailable
{
    use Queueable, SerializesModels;

    public $customer;
    public $mess;
    /**
     * Create a new mess instance.
     *
     * @return void
     */
    public function __construct($customer, $mess)
    {
        //
        $this->customer = $customer;
        $this->mess = $mess;
    }

    /**
     * Build the mess.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Tài khoản thành viên PITO Club')
            ->view('mails.verify_email_success');
    }
}
