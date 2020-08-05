<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendLinkReview extends Mailable
{
    use Queueable, SerializesModels;

    public $link;
    public $pito;
    public $user;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $link, $pito)
    {
        //
        $this->user = $user;
        $this->link = $link;
        $this->pito = $pito;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Đánh giá – Nhận xét | ' . $this->user->detail->company)
            ->view('mails.send_link_review');
    }
}
