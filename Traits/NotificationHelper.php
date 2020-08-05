<?php

/**
 * Created by PhpStorm.
 * User: dell
 * Date: 3/20/20
 * Time: 8:13 AM
 */

namespace App\Traits;


use App\Model\User;
use GuzzleHttp\Client;
use App\Jobs\SendNotifyMessage;
use Illuminate\Support\Facades\Log;
use App\Model\Notification\NotificationSystem;
use App\Notifications\NotificationOrderToSlack;

trait NotificationHelper
{




    public function getNotificationRole($type)
    {
        if ($type == "CUSTOMER" || $type == "PARTNER") return $type;
        return "PITO.*";
    }

    public function getEventByKey($listEventName, $origin_events)
    {
        $result = [];
        foreach ($listEventName as $event_key) {
            $event_key = str_replace("*", "", $event_key);
            foreach ($origin_events as $origin_key => $event) {
                if ($origin_key == $event_key || substr($origin_key, 0, strlen($event_key)) == $event_key) {
                    $result[] = $origin_events[$origin_key];
                }
            }
        }
        return $result;
    }

    public function getNotificationEventForRole(User $user = null)
    {
        $type = $this->getNotificationRole($user->type_role);
        $events = NotificationSystem::EVENT_TYPE;

        $admin_event = NotificationSystem::ADMIN_EVENT;
        $partner_event = NotificationSystem::PARTNER_EVENT;
        $customer_event = NotificationSystem::CUSTOMER_EVENT;

        $result = [];

        if ($type == "CUSTOMER") $result = $this->getEventByKey($customer_event, $events);
        else if ($type == "PARTNER") $result = $this->getEventByKey($partner_event, $events);
        else if ($type == "PITO.*") {
            // TODO: Implement notification's event for each role
            $result = $this->getEventByKey($admin_event, $events);
        }

        // Only get value
        $result = array_values($result);
        return $result;
    }

    public function getNotificationEventByRoleName($type)
    {
        $events = NotificationSystem::EVENT_TYPE;

        $admin_event = NotificationSystem::ADMIN_EVENT;
        $partner_event = NotificationSystem::PARTNER_EVENT;
        $customer_event = NotificationSystem::CUSTOMER_EVENT;

        $result = [];

        if ($type == "CUSTOMER") $result = $this->getEventByKey($customer_event, $events);
        else if ($type == "PARTNER") $result = $this->getEventByKey($partner_event, $events);
        else if ($type == "PITO.*") {
            // TODO: Implement notification's event for each role
            $result = $this->getEventByKey($admin_event, $events);
        }

        // Only get value
        $result = array_values($result);
        return $result;
    }

    public static function esms($Phone, $Content)
    {
        // return;
        $api_key = '0d6f10e9c81f6a0becd75d5c597f36f79c28ccf312e4c391d4813f19a4716977bf460b9f';
        $client = new Client();
        // if ($Phone == '0378869933' || $Phone == '0974922032' || $Phone == '0775514403')
        //     return;
        $params = [
            'Phone' => $Phone,
            'Content' => $Content,
            'SmsType' => 2,
            'Brandname' => 'PITO',
            'ApiKey' => env('ESMS_API_KEY'),
            'SecretKey' => env('ESMS_SECRET_KEY')
        ];
        $query = "";
        foreach ($params as $key => $value) $query .= urlencode($key) . '=' . urlencode($value) . '&';
        $query = rtrim($query, '& ');
        $api = 'http://rest.esms.vn/MainService.svc/json/SendMultipleMessage_V4_get?';
        $url = $api . $query;
        $res = $client->request('GET', $url);
        $response_data = $res->getBody()->getContents();
        Log::stack(['state'])->debug("ems: " . $Phone . " " . "content: " . $Content . " \n " . $response_data);
        $response_data = json_decode($response_data, true);
        return $response_data;
    }

    public function sendNotifyToUser($filter, $content, $more = null)
    {
        $arr = [];
        $arr['filters'] = [];
        foreach ($filter['filters'] as $key => $value) {
            if (isset($value['operator']))
                $arr['filters'][] = ["operator" => "OR"];

            $arr['filters'][] = ["field" => "tag", "key" => $value['key'], "relation" => $value['relation'], "value" => $value['value']];
        }
        $arr['included_segments'] = $filter['included_segments'];
        $filter =  $arr;

        $response = $this->sendMessage($filter, $content, $more);
        return $response;
    }
    public function sendMessage($filter, $content, $more = null)
    {
        if (\is_array($content)) {
            $heading_arr = [
                "en" => $content['heading']
            ];
            $content_arr      = array(
                "en" => $content['content']
            );
        } else {
            $content_arr      = array(
                "en" => $content
            );
            $heading_arr = [
                "en" => ''
            ];
        }

        $fields = array(
            'app_id' => self::getAppId(),
            'filters' => $filter['filters'],
            'included_segments' => $filter['included_segments'],
            'content_available' => true,
            'mutable_content' => true,
            'contents' => $content_arr,
            'headings' => $heading_arr
        );
        if ($more) {
            foreach ($more as $key => $value) {
                $fields[$key] = $value;
            }
        }
        $fields = json_encode($fields);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . self::getRestApiKey(),
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    public function pushInfo($user_id, $identifier, $device_type)
    {
        $fields = array(
            'app_id' => self::getAppId(),
            'identifier' => $identifier,
            'language' => "en",
            'game_version' => "1.0",
            'device_type' => $device_type,
            'tags' => array("userId" => $user_id)
        );
        $fields = json_encode($fields);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/players");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    public static function notifi_more($data, $content, $list_user = null, $more_any)
    {
        $filter = [];
        $filter['included_segments'] = [];
        $filter['filters'] = [];
        foreach ($list_user as $key => $value) {
            if (!$key) {
                $filter['filters'][] = ['key' => $key, 'value' => $value, 'relation' => '='];
            } else {
                $filter['filters'][] = ['key' => $key, 'value' => $value, 'relation' => '=', 'operator' => 'OR'];
            }
        }
        $more = [
            'data' => $data,
        ];
        foreach ($more_any as $key => $value) {
            $more[$key] = $value;
        }
        SendNotifyMessage::dispatch($filter, $content, $more);
    }
}
