<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 3/21/20
 * Time: 10:36 PM
 */

namespace App\Notifications;


use App\Model\Notification\NotificationSystem;
use App\Model\Order\Order;
use App\Traits\NotificationHelper;
use Illuminate\Support\Facades\Log;

class NotificationService {

    use NotificationHelper;

    public static function pushForAllUser($message, $heading = "Pito thông báo", $subtitle = "Thông báo từ app.pito.vn", $url = null, $data = null) {
        NotificationOneSignalPusher::sendAll($message, $heading, $subtitle, $url, $data);
    }

    public static function pushForSpecifyRole($message, $role, $headings = "Pito thông báo", $subtitle = null, $url = null, $data = null) {
        if ($role != 'CUSTOMER' || $role != 'PARTNER')
            NotificationOneSignalPusher::sendToRole($message, 'PITO_ADMIN', $headings, $subtitle, $url, $data);
        else  NotificationOneSignalPusher::sendToRole($message, $role, $headings, $subtitle, $url, $data);
    }

    public static function pushToListUserId($message, $listId, $headings = "Pito thông báo", $subtitle = "Thông báo từ app.pito.vn", $url = null, $data = null) {
        NotificationOneSignalPusher::sendWithListID($message, $listId, $headings, $subtitle, $url, $data);
    }

    public static function pushWithExistedNotify(NotificationSystem $notification, $message = null) {
        try {
            $object = $notification->type_object()->first();
            if ($object instanceof Order) {
                // A notification for order
                $eventType = $notification->tag;
                $order = $object;
                $service = new NotificationService();

                $adminEvent = $service->getNotificationEventByRoleName("PITO.*");
                $partnerEvent = $service->getNotificationEventByRoleName("CUSTOMER");
                $customerEvent = $service->getNotificationEventByRoleName("PARTNER");


                if (in_array($eventType, $adminEvent)) { //by admin role
                    $service->pushForSpecifyRole($notification->content, 'PITO_ADMIN', $notification->tag);
                }

                if (in_array($eventType, $partnerEvent)) { // by partner id
                    // TODO: Change it
                    // Only send notification for some order event
                    if (!in_array($eventType, [
                        NotificationSystem::EVENT_TYPE["ORDER.CHANGE"],
                        NotificationSystem::EVENT_TYPE["ORDER.CREATE"],
                        NotificationSystem::EVENT_TYPE["PARTY.START"],
                        NotificationSystem::EVENT_TYPE["PARTY.COMPETE"],
                    ])) return;

                    // Send only for partner have accept order
                    $order_sub_list = $order->sub_order()->get();
                    $listId = [];
                    foreach ($order_sub_list as $order_sub) {
                        $order_for_partner = $order_sub->order_for_partner()->first();
                        if ($order_for_partner) $listId[] = $order_for_partner->partner_id;
                    }
                    if (count($listId) > 0) $service->pushToListUserId($notification->content, $listId, $notification->tag);
                }

                if (in_array($eventType, $customerEvent)) { //by customer id
                    // Send only for customer of this order
                    $order_for_customer_list = $order->order_for_customer()->get();
                    if ($order_for_customer_list) {
                        $listId = [];
                        foreach ($order_for_customer_list as $order_for_customer) $listId[] = $order_for_customer->customer_id;
                        if (count($listId) > 0) $service->pushToListUserId($notification->content, $listId, $notification->tag);
                    }
                }ds;
            } else {
                // TODO: implement here to get more event type
                // send all user a message with default value is notification's content
                self::pushForAllUser($message ?? $notification->content);
            }
        } catch (\Exception $exception) {
            Log::error("Send one signal fail" . $exception->getMessage());
        }

    }
}
