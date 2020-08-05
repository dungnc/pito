<?php

namespace App\Http\Controllers\API\Notification;

use App\Model\User;
use App\Model\Order\Order;
use App\Notifications\NotificationOneSignalPusher;
use App\Notifications\NotificationService;
use App\Traits\NotificationHelper;
use Berkayk\OneSignal\OneSignalFacade;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Mail\CoopratePartner;
use App\Mail\RejectPartner;
use Illuminate\Support\Facades\Validator;
use App\Model\Notification\NotificationSystem;
use Illuminate\Support\Facades\Mail;

/**
 * @group Notification system
 *
 * APIs for Notification system
 */
class NotificationMessageController extends Controller {


    public function sendCustomMessage(Request $request) {
        $validator = Validator::make($request->all(), [
            'message' => 'string|require',
            'url' => 'string',
            'data' => 'string',
            'heading' => 'string',
            'subtitle' => 'string',
            'role' => 'string|require'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }

        NotificationOneSignalPusher::sendAll(
            "New message content",
            null,
            null,
            "New Title",
            "Subtitle");
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }

    public function testMessage(Request $request) {
        $noti = NotificationSystem::find(112);


        NotificationService::pushWithExistedNotify($noti);
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }
}
