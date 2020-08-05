<?php

namespace App\Mail\Version2\RemiderOrder;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class RemiderPartnerOrderDoing extends Mailable
{
    use Queueable, SerializesModels;

    public $proposale_partner;

    public function __construct($proposale_partner)
    {
        //
        $this->proposale_partner = $proposale_partner;
    }
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $partner = $this->proposale_partner->partner;
        $order = $this->proposale_partner->proposale->order;
        $proposale = $this->proposale_partner->proposale;
        $pito_assign = $order->assign_pito_admin;
        // start time
        $start_time = $order->end_time / 60;
        $hour = (int) ($start_time / 60);
        $minute = (int) ($start_time % 60);
        if ($hour < 10) {
            $hour = "0" . $hour;
        }
        if ($minute < 10) {
            $minute = "0" . $minute;
        }
        $start_time = $hour . ":" . $minute;

        // end time
        $end_time = $order->clean_time / 60;
        $hour = (int) ($end_time / 60);
        $minute = (int) ($end_time % 60);
        if ($hour < 10) {
            $hour = "0" . $hour;
        }
        if ($minute < 10) {
            $minute = "0" . $minute;
        }
        $end_time = $hour . ":" . $minute;
        $url_download = route('PDF.download_proposale') . '?proposale_id=' . $this->proposale_partner->id . '&role=PARTNER&type=desktop&';
        return $this->subject($partner->name . ' có lịch triển khai đơn hàng #PT' . $order->id . ' vào ngày ' . date('d-m-Y', strtotime($order->date_start)) . " " . $start_time)
            ->view('mails_v2.remider_order.partner', compact(
                'partner',
                'proposale',
                'order',
                'end_time',
                'start_time',
                'url_download',
                'pito_assign'
            ));
    }
}
