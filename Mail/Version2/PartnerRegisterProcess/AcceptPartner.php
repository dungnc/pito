<?php

namespace App\Mail\Version2\PartnerRegisterProcess;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Swift_Attachment;

class AcceptPartner extends Mailable
{
    use Queueable, SerializesModels;

    public $file_contract;
    public $partner;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($file_contract, $partner)
    {
        $this->file_contract = $file_contract;
        $this->partner = $partner;
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mail_boot = $this->subject('Chúc mừng bạn đã trở thành đối tác của PITO')
            ->view('mails_v2.partner_register_process.accept');
        if ($this->file_contract) {
            $mail_boot = $mail_boot->attach(Storage::disk('public')->path($this->file_contract));
        }
        return $mail_boot;
    }
}
