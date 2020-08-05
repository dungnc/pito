<?php

namespace App\Mail\Version2\PartnerRegisterProcess;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class RegisterPartner extends Mailable
{
    use Queueable, SerializesModels;

    public $partner;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($partner)
    {
        $this->partner = $partner;
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        return $this->subject('Cám ơn bạn đã đăng ký trở thành đối tác của PITO')
            ->view('mails_v2.partner_register_process.register');
    }
}
