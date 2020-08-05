<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendLinkReviewFeedbackForPartner extends Mailable
{
    use Queueable, SerializesModels;

    public $link;
    public $pito;
    public $customer;
    public $partner;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($link, $pito, $customer, $partner)
    {
        //
        $this->link = $link;
        $this->pito = $pito;
        $this->customer = $customer;
        $this->partner = $partner;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Đánh giá – Nhận xét')
            ->view('mails.send_link_review_partner');
    }
}
