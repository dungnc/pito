<?php

namespace App\Http\Controllers\API\Notification;

use App\Model\User;
use App\Model\Order\Order;
use App\Traits\NotificationHelper;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Mail\CoopratePartner;
use App\Mail\RejectPartner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Model\Notification\NotificationSystem;
use Illuminate\Support\Facades\Mail;
use Exception;

/**
 * @group Notification system
 *
 * APIs for Notification system
 */
class NotificationSystemController extends Controller
{

    use NotificationHelper;

    /**
     * Get List Notification order by id.
     * @bodyParam order_id int required id của order .Example: 18
     */
    public function index_by_order_id(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $class_name_order = 'App\Order\Order';
        $query = NotificationSystem::where('type', $class_name_order)
            ->where('type_id', $request->order_id)
            ->orderBy('id', 'DESC');
        $data = $query->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'succress');
    }

    /**
     * Get List order notifi.
     * @bodyParam name string Tên của order .Example: Bữa tiệc công ty.
     * @bodyParam date_start date . Example: 2020-02-03
     * @bodyParam start_time int  tổng giờ phút đổi sang giây.Example: 54000
     * @bodyParam end_time int  tổng giờ phút đổi sang giây. Example: 61200
     * @bodyParam type_party_id int id của loại tiệc. Example: 1
     * @bodyParam status int trạng thái của order . Example: 0
     * @bodyParam id int id của order. Example: 8
     */
    public function index_for_order(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['order_for_customer.customer', 'type_party', 'sub_order.order_for_partner.partner'])->select('*');
        $data_request = $request->all();
        unset($data_request['page']);
        if ($request->start_time && $request->end_time) {
            $query = $query->where('start_time', '>=', $request->start_time)
                ->where('end_time', '<=', $request->end_time);
        }
        unset($data_request['start_time']);
        unset($data_request['end_time']);
        foreach ($data_request as $key => $value) {
            if ($key == 'date_start' || $key == 'status') {
                $query = $query->where($key, $value);
            } else {
                $query = $query->where($key, 'LIKE', '%' . $value . '%');
            }
        }
        $data = $query->orderBy('id', 'desc')->paginate($request->per_page ? $request->per_page : 15);
        $data_tmp = $data->toArray();
        $user_id = $user->id;
        foreach ($data_tmp['data'] as $key => $value) {
            $data_tmp['data'][$key]['amount_noti_not_seen'] = NotificationSystem::where('type_id', $value['id'])
                ->where('type', Order::class)
                ->where(function ($q) use ($user_id) {
                    $q->where('user_id_seen', 'NOT LIKE', '%-' . $user_id . '-%');
                    $q->orWhere('user_id_seen');
                })->count();
        }
        $data = collect($data_tmp);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
    }

    /**
     * seen order notifi.
     * @bodyParam type_id int required id của type . Example: 8
     * @bodyParam type string required Type với tên là model . Example: App\Order\Order
     */
    public function seen_noti(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type_id' => 'required',
            'type' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $user = $request->user();
        $user_id = $user->id;
        $list = NotificationSystem::where('type_id', $request->type_id)
            ->where('type', $request->type)
            ->where(function ($q) use ($user_id) {
                $q->where('user_id_seen', 'NOT LIKE', '%-' . $user_id . '-%');
                $q->orWhere('user_id_seen');
            })->get();
        foreach ($list as $value) {
            $item = NotificationSystem::find($value->id);
            if ($item->user_id_seen)
                $item->user_id_seen .= "-" . $user->id . "-";
            else $item->user_id_seen = "-" . $user->id . "-";
            $item->save();
        }
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }

    /**
     * reject partner.
     */
    public function reject_partner(Request $request, $id)
    {
        $partner = User::find($id);
        if (!$partner || $partner->type_role != User::$const_type_role['PARTNER']) {
            return AdapterHelper::sendResponse(false, 'Partner Not found', 404, 'Partner not found');
        }
        // Mail::to($partner->email)->send(new RejectPartner($partner));
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }

    /**
     * cooperate partner.
     */
    public function cooperate_partner(Request $request, $id)
    {
        $partner = User::find($id);
        if (!$partner || $partner->type_role != User::$const_type_role['PARTNER']) {
            return AdapterHelper::sendResponse(false, 'Partner Not found', 404, 'Partner not found');
        }
        // Mail::to($partner->email)->send(new CoopratePartner($partner));
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }

    public function allNotify(Request $request)
    {

        $user = $request->user();
        $user_id = $user->id;
        $limit = $request->limit;
        if ($request->per_page) {
            $limit = $request->per_page;
        }
        $only_new = $request->only_new == "true" ? true : false;

        $listEvent = $this->getNotificationEventForRole($user);
        $role_event = $this->getNotificationRole($user->type_role);

        $query = NotificationSystem::query();
        $query = $query->whereIn("tag", $listEvent);

        if ($only_new) {
            $query = $query->where(function ($q) use ($user_id) {
                $q->where('user_id_seen', 'NOT LIKE', '%-' . $user_id . '-%');
                $q->orWhere('user_id_seen');
            });
        }

        if ($role_event == "CUSTOMER") {
            $query = $query
                ->whereHasMorph("type_object", [Order::class], function ($query, $type) use ($user_id) {
                    $query->whereHas('order_for_customer', function ($query) use ($user_id) {
                        $query->where('customer_id', $user_id);
                    });
                });
        } else if ($role_event == "PARTNER") {
            $query = $query
                ->whereHasMorph("type_object", [Order::class], function ($query, $type) use ($user_id) {
                    $query->whereHas('proposale.proposale_for_partner', function ($query) use ($user_id) {
                        $query->where('partner_id', $user_id);
                    });
                });
            $query = $query->with(['type_object.proposale.proposale_for_partner' => function ($query) use ($user_id) {
                $query->where('partner_id', $user_id);
            }]);
        } else { //($role_event == "PITO.*")
            $query = $query->with("type_object");
            $query = $query->orderBy("created_at", "DESC");
        }

        $query = $query->orderBy("created_at", "DESC");

        $list = $query->paginate($limit);

        return AdapterHelper::sendResponsePaginating(true, $list, 200, 'success');
    }


    public function seenANotify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $user = $request->user();
        $user_id = $user->id;
        $notification = NotificationSystem::where('id', $request->id)
            ->where(function ($q) use ($user_id) {
                $q->where('user_id_seen', 'NOT LIKE', '%-' . $user_id . '-%');
                $q->orWhere('user_id_seen');
            })->first();
        if (!$notification) AdapterHelper::sendResponse(false, null, 400, 'Thông báo đã được xem hoặc không còn tồn tại');

        $notification->user_id_seen = $notification->user_id_seen . "-" . $user->id . "-";
        $notification->save();
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }
}
