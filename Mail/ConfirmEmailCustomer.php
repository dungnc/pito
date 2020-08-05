<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ConfirmEmailCustomer extends Mailable
{
    use Queueable, SerializesModels;

    public $customer;
    public $mess;
    public $url_login;
    /**
     * Create a new mess instance.
     *
     * @return void
     */
    public function __construct($customer, $mess, $url_login)
    {
        //
        $this->customer = $customer;
        $this->mess = $mess;
        $this->url_login = $url_login;
    }

    /**
     * Build the mess.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Tài khoản thành viên PITO Club')
            ->view('mails.welcome_customer_signup');
    }
}
