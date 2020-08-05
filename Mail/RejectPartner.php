<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class RejectPartner extends Mailable
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
        //
        $this->partner = $partner;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->partner['name'] . ' | Kết quả xét duyệt hồ sơ đối tác')
            ->view('mails.reject_partner');
    }
}
