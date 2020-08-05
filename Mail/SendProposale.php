<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendProposale extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $user;
    public $data_user;
    public $sub_order_id;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data, $user, $data_user, $sub_order_id)
    {
        //
        $this->data = $data;
        $this->user = $user;
        $this->data_user = $data_user;
        $this->sub_order_id = $sub_order_id;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('PITO gửi báo giá')
            ->view('pdf_views.proposale.detail_mail');
    }
}
