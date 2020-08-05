<?php

namespace App\Mail\Version2\RemiderOrder;

use Swift_Attachment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;

class RemiderPaymentForCustomer extends Mailable
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
        $proposale = $this->order->proposale;
        return $this->subject('Thông tin việc thanh toán đơn hàng #PT' . $this->order->id)
            ->view('mails_v2.remider_order.remider_payment_customer', compact('proposale'));
    }
}
