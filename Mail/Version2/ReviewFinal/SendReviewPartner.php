<?php

namespace App\Mail\Version2\ReviewFinal;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendReviewPartner extends Mailable
{
    use Queueable, SerializesModels;

    public $partner;
    public $proposale_partner;
    public $review_id;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($partner, $proposale_partner, $review_id)
    {
        //
        $this->partner = $partner;
        $this->proposale_partner = $proposale_partner;
        $this->review_id = $review_id;
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $proposale = $this->proposale_partner->proposale;
        return $this->subject('Kết quả đánh giá đối tác đã thực hiện đơn hàng #PT' . $proposale->order->id)
            ->view(
                'mails_v2.review_final.send_review_partner',
                compact('proposale')
            );
    }
}
