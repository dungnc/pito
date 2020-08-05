<?php

namespace App\Http\Controllers\API\TicketAndReview;

use PDF;
use App\Model\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Model\TicketAndReview\Review;
use App\Model\TicketAndReview\TicketEnd;
use App\Model\TicketAndReview\TicketStart;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

/**
 * @group Ticket end
 *
 * APIs for ticket end
 */
class TicketEndController extends Controller
{

    /**
     * Get List and search ticket end.
     * @bodyParam id int id của phiếu đi tiệc. Example: 1
     * @bodyParam order_id int id của order tổng. Example: 1
     * @bodyParam partner string tên của partner. Example: Tà vẹt
     * @bodyParam customer string Tên của khách hàng . Example: STDIOHUE
     * @bodyParam assign_pito_admin string Tên của Ten admin pito. Example: van a
     * @bodyParam address string địa điểm tổ chức . Example: phan bội châu
     * @bodyParam date_start string ngày bắt đầu. Example: 2020-03-02
     */

    public function index(Request $request)
    {
        $user = $request->user();
        //
        $query = TicketEnd::with([
            'partner', 'sub_order.order',
            'sub_order.order.company',
            'sub_order.order.assign_pito_admin',
            'sub_order.order.order_for_customer.customer'
        ])
            ->select('*');

        if ($user->type_role == User::$const_type_role['PARTNER']) {
            $query->where('partner_id', $user->id)->where('status', 1);
        }

        if ($request->id) {
            $query->where('id', "LIKE", "%" . $request->id . "%");
        }
        if ($request->order_id) {
            $order_id = $request->order_id;
            $query->whereHas('sub_order', function ($q) use ($order_id) {
                $q->where('order_id', "LIKE", "%" . $order_id . "%");
            });
        }
        if ($request->partner) {
            $partner = $request->partner;
            $query->whereHas('partner', function ($q) use ($partner) {
                $q->where('name', "LIKE", "%" . $partner . "%");
            });
        }
        if ($request->customer) {
            $customer = $request->customer;
            $query->whereHas('sub_order.order.order_for_customer.customer', function ($q) use ($customer) {
                $q->where('name', "LIKE", "%" . $customer . "%");
            });
        }
        if ($request->pito_admin) {
            $pito_admin = $request->pito_admin;
            $query->whereHas('sub_order.order.assign_pito_admin', function ($q) use ($pito_admin) {
                $q->where('name', "LIKE", "%" . $pito_admin . "%");
            });
        }
        if ($request->address) {
            $address = $request->address;
            $query->whereHas('sub_order.order', function ($q) use ($address) {
                $q->where('address', "LIKE", "%" . $address . "%");
            });
        }
        if ($request->date_start) {
            $date_start = $request->date_start;
            $query->whereHas('sub_order.order', function ($q) use ($date_start) {
                $q->whereDate('date_start', $date_start);
            });
        }
        $query->whereHas('sub_order.order', function ($q) {
            $q->whereNotIn('status', [0, 1, 5]);
        });
        $data = $query->orderBy('id', 'desc')->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'Success');
    }

    /**
     * Get Detail ticket end and review of customer.
     * 
     */
    public function show(Request $request, $id)
    {

        //
        $ticket_end = TicketEnd::with([
            'partner', 'sub_order.order',
            'sub_order.order.order_for_customer.customer',
            'sub_order.proposale_for_partner.detail',
            'sub_order.order.assign_pito_admin',
            'sub_order.order.pito_admin'
        ]);
        $ticket_end->whereHas('sub_order.order', function ($q) {
            $q->whereNotIn('status', [0, 1, 5]);
        });
        $ticket_end = $ticket_end->where('id', $id)->first();
        if (!$ticket_end)
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
        $data = [];
        $data['ticket_end'] = $ticket_end;
        $data['review'] = Review::where('sub_order_id', $ticket_end->sub_order_id)
            ->first();
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    /**
     * Confirm ticket end
     * @bodyParam description string Note của pito cho partner. Example: đến nơi phải gọi
     * @bodyParam field_json string Cacs field muốn thêm pass qua json. Example: {"dung_cu":{"name:"dung cu di tiec","value":"chen muong dua"}}
     */
    public function update(Request $request, $id)
    {
        $data = TicketEnd::find($id);
        try {
            //code...
            if (!$data)
                return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
            if ($request->image_confirm) {
                $fileName = 'confirm-' . Str::random(4) . "-" . time();
                $dir = TicketEnd::$path . $fileName;
                $dir = AdapterHelper::upload_file($request->image_confirm, $dir);
                $data->image_confirm = env('APP_URL') . 'storage/' . $dir;
            }
            if ($request->description) {
                $data->description = $request->description;
            }
            if ($request->field_json) {
                if (!json_decode($request->field_json)) {
                    return AdapterHelper::sendResponse(false, 'Validation error', 400, 'json fail');
                }
                $data->field_json = $request->field_json;
            }

            $data->save();
            $ticket_start = TicketStart::where('partner_id', $data->partner_id)
                ->where('sub_order_id', $data->sub_order_id)->first();
            $ticket_start->field_json = $request->field_json;
            $ticket_start->save();
        } catch (\Throwable $th) {
            //throw $th;
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());

            return AdapterHelper::sendResponse(false, 'Undefined error', 200, $th->getMessage());
        }


        return AdapterHelper::sendResponse(true, 'success', 200, 'Success');
    }

    /**
     * Confirm ticket end
     * @bodyParam token string required token bat buoc. Example: zxczxc
     */
    public function share(Request $request, $id)
    {
        $data = TicketEnd::with([
            'partner', 'sub_order.order', 'sub_order.order.company',
            'sub_order.order.order_for_customer.customer',
            'sub_order.order.assign_pito_admin',
            'sub_order.order.pito_admin'
        ])
            ->find($id);
        $validator = Validator::make($request->all(), [
            'token' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        if (!$data)
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
        if (!Hash::check($data->partner_id . "-" . $data->sub_order_id, $request->token))
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    /**
     * Update ticket 
     * @bodyParam description string Note của pito cho partner. Example: đến nơi phải gọi
     * @bodyParam field_json string Cacs field muốn thêm pass qua json. Example: {"dung_cu":{"name:"dung cu di tiec","value":"chen muong dua"}}
     * @bodyParam token string required token bat buoc. Example: zxczxc
     */
    public function share_update(Request $request, $id)
    {
        $data = TicketStart::with([
            'partner', 'sub_order.order', 'sub_order.order.company',
            'sub_order.order.order_for_customer.customer',
            'sub_order.order.assign_pito_admin',
            'sub_order.order.pito_admin'
        ])
            ->find($id);
        $validator = Validator::make($request->all(), [
            'token' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        if (!$data)
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
        if (!Hash::check($data->partner_id . "-" . $data->sub_order_id, $request->token))
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
        return $this->update($request, $id);
    }
}
