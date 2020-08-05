<?php

namespace App\Notifications;

use App\Model\Notification\NotificationSystem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class NotificationOrderToSlack extends Notification
{
    use Queueable;

    private $action;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($action)
    {
        //
        $this->action = $action;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['slack'];
    }


    public function toSlack($order)
    {
        switch ($this->action) {
            case NotificationSystem::EVENT_TYPE['ORDER.CREATE']:
                return $this->eventCreateOrder($order);
            case NotificationSystem::EVENT_TYPE['ORDER.CANCEL']:
                return $this->eventCancelOrder($order);
            case NotificationSystem::EVENT_TYPE['PROPOSAL.CUSTOMER.ACCEPT']:
                return $this->eventConfirmedOrder($order);
        }
    }

    private function eventCreateOrder($order)
    {
        return (new SlackMessage)
            ->content('Một order đã được tạo :slightly_smiling_face:' . url(config('app.url_front') . 'cms/orders/show/' . $order->id))
            ->attachment(function ($attachment) use ($order) {
                $attachment
                    ->fields($this->fields($order))
                    ->markdown(['text']);
            });
    }

    private function eventCancelOrder($order)
    {
        return (new SlackMessage)
            ->error()
            ->content('Một order đã huỷ :cry:' . url(config('app.url_front') . 'cms/orders/show/' . $order->id))
            ->attachment(function ($attachment) use ($order) {
                $attachment
                    ->fields($this->fields($order))
                    ->markdown(['text']);
            });
    }

    private function eventConfirmedOrder($order)
    {
        return (new SlackMessage)
            ->success()
            ->content('Khách hàng đã xác nhận đơn hàng :heart_eyes:. ' . url(config('app.url_front') . 'cms/orders/show/' . $order->id))
            ->attachment(function ($attachment) use ($order) {
                $attachment
                    ->fields($this->fields($order))
                    ->markdown(['text']);
            });
    }
    private function fields($order)
    {
        $services = $order->order_for_customer->service;
        foreach ($services as $key => $value) {
            if (strpos($value->name, "Tổng Giá Trị Tiệc") > -1)
                $service_order['price'] = $value->price;
            if (strpos($value->name, "Phí Thuận Tiện") > -1 || strpos($value->name, "Phí Dịch Vụ") > -1)
                $service_order['price_manage'] = $value->price;
        }
        $service_order['total_price_VAT'] = $service_order['price'] + $service_order['price'] * 0.1 + $service_order['price_manage'];
        $service_order['total_price_no_VAT'] = $service_order['price'] + $service_order['price_manage'];

        return [
            'Tên Khách Hàng' => $order->order_for_customer->customer->name,
            'Công Ty' => $order->order_for_customer->customer->detail->company,
            'Email' => $order->order_for_customer->customer->email,
            'Nhân viên phụ trách' => $order->assign_pito_admin->name,
            'Người tạo' => $order->pito_admin->name,
            'Tổng giá trị tiệc' => number_format($service_order['price']),
            'Mã ưu đãi và các giá trị ưu đãi' => 'Không có',
            'Phí thuận tiện' => number_format($service_order['price_manage']),
            'Giá trị tiệc chưa có VAT' => number_format($service_order['total_price_no_VAT']),
            'Giá trị tiệc có VAT' =>  number_format($service_order['total_price_VAT'])
        ];
    }
}
