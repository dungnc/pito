<?php

namespace App\Mail\Version2\Proposale;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProposalePartner extends Mailable
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

        $price_default_map = [];
        foreach ($this->proposale_partner->sub_order->order_for_partner->service_default as $key => $value) {
            if (strpos(strtoupper($value->name), strtoupper("Tổng giá trị tiệc")) > -1)
                $price_default_map['total'] = $value;
            if (
                strpos(strtoupper($value->name), strtoupper("Phí thuận tiện")) > -1
                || strpos(strtoupper($value->name), strtoupper("Phí dịch vụ")) > -1
            )
                $price_default_map['price_manage'] = $value;
            if (
                strtoupper($value->name) ==  strtoupper("Ưu Đãi")
                || strtoupper($value->name) ==  strtoupper("Ưu đãi")
            ) {
                $price_default_map['promotion'] = $value;
            }
            if (
                strpos(strtoupper($value->name), strtoupper("Tổng Giá Trị Cần Thanh Toán (chưa bao gồm VAT)")) > -1
                || strpos(strtoupper($value->name), strtoupper("Tổng Giá Trị Cần Thanh Toán (Chưa bao gồm VAT)")) > -1
            )
                $price_default_map['total_not_VAT'] = $value;
            if ($value->name == "VAT")
                $price_default_map['VAT'] = $value;
            if (
                strpos(strtoupper($value->name), strtoupper("Tổng Giá Trị Cần Thanh Toán (đã bao gồm VAT)")) > -1 ||
                strpos(strtoupper($value->name), strtoupper("Tổng Giá Trị Cần Thanh Toán (Đã bao gồm VAT)")) > -1
            )
                $price_default_map['total_VAT'] = $value;
        }
        $url_download = route('PDF.download_proposale') . '?proposale_id=' . $this->proposale_partner->id . '&role=PARTNER&type=desktop&';
        return $this->subject('Thông tin báo giá #PT' . $order->id)
            ->view('mails_v2.proposale.partner', compact(
                'partner',
                'proposale',
                'order',
                'end_time',
                'start_time',
                'price_default_map',
                'url_download',
                'pito_assign'
            ));
    }
}
