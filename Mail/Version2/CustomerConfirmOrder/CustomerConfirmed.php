<?php

namespace App\Mail\Version2\CustomerConfirmOrder;

use Swift_Attachment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;

class CustomerConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $customer;
    public $token;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($order, $customer, $token)
    {
        $this->order = $order;
        $this->customer = $customer;
        $this->token = $token;
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        Log::stack(['cronjob'])->info('-------------send mail user confirm-' . date('y-m-d H:i:s') . '-------------');
        $service_default = $this->order->order_for_customer->service_default;
        $proposale = $this->order->proposale->proposale_for_customer;
        $pito_assign = $this->order->assign_pito_admin;
        $customer = $proposale->customer;
        $price_default_map = [];
        $url_download = route('PDF.download_proposale') . '?proposale_id=' . $proposale->id . '&role=CUSTOMER&type=desktop&';
        // start time

        $start_time = $this->order->end_time / 60;
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
        $end_time = $this->order->clean_time / 60;
        $hour = (int) ($end_time / 60);
        $minute = (int) ($end_time % 60);
        if ($hour < 10) {
            $hour = "0" . $hour;
        }

        if ($minute < 10) {
            $minute = "0" . $minute;
        }

        $end_time = $hour . ":" . $minute;

        foreach ($service_default as $key => $value) {
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
            if (strpos(strtoupper($value->name), strtoupper("Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và chưa bao gồm VAT)")) > -1)
                $price_default_map['total_not_VAT'] = $value;
            if ($value->name == "VAT")
                $price_default_map['VAT'] = $value;
            if (strpos(strtoupper($value->name), strtoupper("Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và  VAT)")) > -1)
                $price_default_map['total_VAT'] = $value;
        }
        $proposale = $this->order->proposale;
        return $this->subject('Đơn hàng #PT' . $this->order->id . ' được đặt thành công')
            ->view('mails_v2.order_confirmed.customer', compact(
                'url_download',
                'price_default_map',
                'customer',
                'proposale',
                'start_time',
                'end_time',
                'pito_assign'
            ));
    }
}
