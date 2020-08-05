<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 3/21/20
 * Time: 9:51 PM
 */

namespace App\Notifications;

use Berkayk\OneSignal\OneSignalFacade;

class NotificationOneSignalPusher {


    public static function sendAll($message, $headings = null, $subtitle = null, $url = null, $data = null) {
        OneSignalFacade::sendNotificationToAll(
            $message,
            $url ?? config('onesignal.front_end_app_url'),
            $data,
            null,
            null,
            $headings,
            $subtitle
        );
    }

    public static function sendToAUser($message, $userId, $url = null, $data = null, $headings = null, $subtitle = null) {
        OneSignalFacade::sendNotificationToAll(
            $message,
            $url ?? config('onesignal.front_end_app_url'),
            $data,
            null,
            null,
            $headings,
            $subtitle
        );
    }

    public static function sendToRole($message, $role, $headings = null, $subtitle = null, $url = null, $data = null) {
        OneSignalFacade::sendNotificationUsingTags(
            $message,
            [
                ["field" => "tag", "key" => "role", "relation" => "=", "value" => $role]
            ],
            $url ?? config('onesignal.front_end_app_url'),
            $data,
            null,
            null,
            $headings,
            $subtitle
        );
    }

    public static function sendWithListID($message, $listId, $headings = null, $subtitle = null, $url = null, $data = null) {

        $listTagId = [];

        foreach ($listId as $id) {
            if (count($listTagId) == 0) {
                $listTagId[] = ["field" => "tag", "key" => "userId", "relation" => "=", "value" => $id];
            } else {
                $listTagId[] = ["operator" => "OR"];
                $listTagId[] = ["field" => "tag", "key" => "userId", "relation" => "=", "value" => $id];
            }
        }


        OneSignalFacade::sendNotificationUsingTags(
            $message,
            $listTagId,
            $url ?? config('onesignal.front_end_app_url'),
            $data,
            null,
            null,
            $headings,
            $subtitle
        );
    }

    public static function sendCustomer($parameters = []) {
        OneSignalFacade::sendNotificationCustom($parameters);
    }


}
