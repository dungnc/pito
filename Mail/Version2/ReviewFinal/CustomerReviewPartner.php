<?php

namespace App\Mail\Version2\ReviewFinal;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class CustomerReviewPartner extends Mailable
{
    use Queueable, SerializesModels;

    public $customer;
    public $order;
    public $url_review;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($customer, $order, $url_review)
    {
        //
        $this->customer = $customer;
        $this->order = $order;
        $this->url_review = $url_review;
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $proposale = $this->order->proposale;
        return $this->subject('Mời đánh giá đối tác đã phục vụ bạn')
            ->view('mails_v2.review_final.customer_review_partner', compact('proposale'));
    }
}
