<?php

namespace App\Mail\Version2\PartnerSupport;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PartnerSupport extends Mailable
{
    use Queueable, SerializesModels;

    public $data;


    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        //
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('[Hỗ Trợ] hỗ trợ cho đối tác '.($this->data->user ? $this->data->user->name : "") )
            ->view('mails_v2.partner_support.partner_support');
    }
}
