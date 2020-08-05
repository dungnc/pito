<?php

namespace App\Http\Controllers\API\TicketAndReview;

use App\Http\Controllers\API\Order\OrderController;
use PDF;
use App\Model\User;
use Illuminate\Http\Request;
use App\Mail\SendTicketStart;
use App\Traits\AdapterHelper;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Model\Order\Order;
use App\Model\Order\SubOrder;

use function GuzzleHttp\json_decode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use App\Model\TicketAndReview\TicketEnd;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Model\TicketAndReview\TicketStart;

/**
 * @group ticket start 
 *
 * APIs for ticket start 
 */
class TicketStartController extends Controller
{

    /**
     * Get List and search ticket start.
     * @bodyParam id int id của phiếu đi tiệc. Example: 1
     * @bodyParam order_id int id của order tổng. Example: 1
     * @bodyParam partner string tên của partner. Example: Tà vẹt
     * @bodyParam customer string Tên của khách hàng . Example: STDIOHUE
     * @bodyParam assign_pito_admin string Tên của pito admin . Example: van a
     * @bodyParam address string địa điểm tổ chức . Example: phan bội châu
     * @bodyParam date_start string ngày bắt đầu. Example: 2020-03-02
     */

    public function index(Request $request)
    {
        $user = $request->user();
        //
        $query = TicketStart::with([
            'partner',
            'sub_order.order', 'sub_order.order.company',
            'sub_order.order.assign_pito_admin',
            'sub_order.order.order_for_customer.customer'
        ])->select('*');

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
        if ($request->address) {
            $address = $request->address;
            $query->whereHas('sub_order.order', function ($q) use ($address) {
                $q->where('address', "LIKE", "%" . $address . "%");
            });
        }
        if ($request->assign_pito_admin) {
            $assign_pito_admin = $request->assign_pito_admin;
            $query->whereHas('sub_order.order.assign_pito_admin', function ($q) use ($assign_pito_admin) {
                $q->where('name', "LIKE", "%" . $assign_pito_admin . "%");
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
        if ($request->status_arrive !== null) {
            if ($request->status_arrive == 1) {
                $query->where('date_confirm_arrived', null)->whereHas('sub_order.order', function ($q) {
                    $q->whereDate('date_start', '>', date('Y-m-d'));
                    $q->orWhere(function ($q) {
                        $q->whereDate('date_start', '=', date('Y-m-d'));
                        $h = date('H');
                        $m = date('i');
                        $time = (int) $h * 3600 + (int) $m * 60;
                        $q->where('start_time', '>', $time);
                    });
                    return $q;
                });
            } elseif ($request->status_arrive == 2) {
                $query->where('date_confirm_arrived', null)->whereHas('sub_order.order', function ($q) {
                    $q->whereDate('date_start', '<', date('Y-m-d'));
                    $q->orWhere(function ($q) {
                        $q->whereDate('date_start', '=', date('Y-m-d'));
                        $h = date('H');
                        $m = date('i');
                        $time = (int) $h * 3600 + (int) $m * 60;
                        $q->where('start_time', '<=', $time);
                    });
                    return $q;
                });
            } elseif ($request->status_arrive == 3) {
                $query->where('date_confirm_arrived', '<>', null)
                    ->whereHas('sub_order.order', function ($q) {
                        $q->where('orders.status', '<>', 4);
                        return $q;
                    });;
            } elseif ($request->status_arrive == 4) {

                $query->whereHas('sub_order.order', function ($q) {
                    $q->where('orders.status', 4);
                    return $q;
                });
            }
        }
        $data = $query->orderBy('id', 'desc')->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'Success');
    }

    /**
     * Get detail ticket start.
     */

    public function show(Request $request, $id)
    {
        //
        $data = TicketStart::with([
            'partner', 'sub_order.order', 'sub_order.order.company',
            'sub_order.order.assign_pito_admin',
            'sub_order.proposale_for_partner.detail',
            'sub_order.order.order_for_customer.customer',
            'sub_order.order.pito_admin',
            'sub_order.order_detail_customize'
        ]);

        // $data->whereHas('sub_order.order', function ($q) {
        //     $q->whereNotIn('status', [0, 1, 5]);
        // });

        if ($request->sub_order_id) {
            $data = $data->where('sub_order_id', $request->sub_order_id)->first();
        } else {
            $data = $data->where('id', $id)->first();
        }

        if (!$data)
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');

        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    /**
     * Export to pdf
     */
    public function export_pdf(Request $request, $id)
    {
        $data = TicketEnd::with([
            'partner', 'sub_order.order', 'sub_order.order.company',
            'sub_order.order.assign_pito_admin',
            'sub_order.proposale_for_partner.detail',
            'sub_order.order.order_for_customer.customer',
            'sub_order.order.pito_admin',
            'sub_order.order_detail_customize'
        ])
            ->find($id);
        if (!$data)
            return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
        $field = $data->field;
        $list_food = $data->sub_order->proposale_for_partner->detail;
        $pdf = PDF::loadView('pdf_views.begin_tickets.detail', ['data' => $data, 'user' => $request->user, 'field' => $field, 'list_food' => $list_food]);
        return $pdf->download('medium.pdf');
    }

    /**
     * Export to pdf
     */
    public function send_ticket(Request $request, $id)
    {
        $data = TicketStart::with([
            'partner', 'sub_order.order', 'sub_order.order.company',
            'sub_order.order.assign_pito_admin',
            'sub_order.proposale_for_partner.detail',
            'sub_order.order.order_for_customer.customer',
            'sub_order.order.pito_admin',
            'sub_order.order_detail_customize'
        ])
            ->find($id);
        $data->status = 1;
        $data->save();
        if (!$data)
            return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
        TicketEnd::where('partner_id', $data->partner_id)
            ->where('sub_order_id', $data->sub_order_id)
            ->update(['status' => 1]);
        $pito = $data->sub_order->order->assign_pito_admin;
        $partner = $data->partner;
        Mail::to($data->partner->email)->send(new SendTicketStart($data, $partner, $pito));
        return AdapterHelper::sendResponse(true, 'success', 200, 'Success');
    }

    /**
     * Test pdf
     */
    public function test_pdf(Request $request, $id)
    {
        $data = TicketStart::with([
            'partner', 'sub_order.order', 'sub_order.order.company',
            'sub_order.order.assign_pito_admin',
            'sub_order.order.order_for_customer.customer',
            'sub_order.order.pito_admin',
            'sub_order.order_detail_customize'
        ])
            ->find($id);

        if (!$data)
            return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
        return view('pdf_views.begin_tickets.detail_mail', ['data' => $data]);
    }

    /**
     * Update ticket 
     * @bodyParam description string Note của pito cho partner. Example: đến nơi phải gọi
     * @bodyParam field_json string Cacs field muốn thêm pass qua json. Example: {"dung_cu":{"name:"dung cu di tiec","value":"chen muong dua"}}
     */
    public function update(Request $request, $id)
    {
        $check_array = false;
        if ($request->sub_order_id) {
            $check_array = true;
            $data = TicketStart::where('sub_order_id', $request->sub_order_id)->first();
        } else {
            $data = TicketStart::find($id);
        }
        if (!$data)
            return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
        DB::beginTransaction();
        try {
            if ($request->image_confirm) {
                $fileName = 'confirm-' . Str::random(4) . "-" . time();
                $dir = TicketStart::$path . $fileName;
                $dir = AdapterHelper::upload_file($request->image_confirm, $dir);
                $data->image_confirm_signature = env('APP_URL') . 'storage/' . $dir;
            }
            if ($request->description) {
                $data->description = $request->description;
            }
            if ($request->field_json) {
                $data_field_json = json_decode($request->field_json);
                if (!$data_field_json) {
                    return AdapterHelper::sendResponse(false, 'Validation error', 400, 'json fail');
                }
                if (isset($data_field_json->review) && isset($data_field_json->review->review_date)) {
                    $data->review_date = $data_field_json->review->review_date;
                }
                $data->field_json = $request->field_json;
            }
            $data->save();
            $ticket_end = TicketEnd::where('partner_id', $data->partner_id)
                ->where('sub_order_id', $data->sub_order_id)->first();
            $ticket_end->field_json = $request->field_json;
            $ticket_end->save();
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());

            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, 'success', 200, 'Success');
    }

    /**
     * Confirm ticket start
     * @bodyParam token string required token bat buoc. Example: zxczxc
     */
    public function share(Request $request, $id)
    {
        $data = TicketStart::with([
            'partner', 'sub_order.order', 'sub_order.order.company',
            'sub_order.order.assign_pito_admin',
            'sub_order.order.order_for_customer.customer',
            'sub_order.order.pito_admin',
            'sub_order.order_detail_customize'
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
        $data = TicketStart::with(['partner', 'sub_order.order.company', 'sub_order.order', 'sub_order.order.order_for_customer.customer', 'sub_order.order.pito_admin'])
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

    /**
     * Confirm arrived  
     * @bodyParam _lat_confirm_arrived string _lat. Example: 12.2
     * @bodyParam _long_confirm_arrived string _long. Example: 123.123
     * @bodyParam address_confirm_arrived string dia chi da den. Example: dien bien
     * @bodyParam image_confirm_arrived file anh. 
     */
    public function confirm_arrived(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            // '_lat_confirm_arrived' => 'required',
            // '_long_confirm_arrived' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $data = TicketStart::find($id);
        if (!$data)
            return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
        DB::beginTransaction();
        try {
            //code...
            $data->date_confirm_arrived = date('Y-m-d H:i:s');
            $data->address_confirm_arrived = $request->address_confirm_arrived ? $request->address_confirm_arrived : null;
            $data->_lat_confirm_arrived = $request->_lat_confirm_arrived ? $request->_lat_confirm_arrived : null;
            $data->_long_confirm_arrived = $request->_long_confirm_arrived ? $request->_long_confirm_arrived : null;
            if ($request->image_confirm_arrived) {
                $fileName = 'confirm-' . Str::random(4) . "-" . time();
                $dir = TicketStart::$path . $fileName;
                $dir = AdapterHelper::upload_file($request->image_confirm_arrived, $dir);
                $data->image_confirm_arrived = env('APP_URL') . 'storage/' . $dir;
            }
            $data->save();

            // kiểm tra đối tác đã tới hết chưa.
            $sub_order = SubOrder::find($data->sub_order_id);
            $list_sub_order = SubOrder::where('order_id', $sub_order->order_id)->get();
            $list_sub_order_id = $list_sub_order->map->id->all();
            $list_ticket_confirm_arrived = TicketStart::whereIn('sub_order_id', $list_sub_order_id)
                ->where('date_confirm_arrived', null)->exists();
            if (!$list_ticket_confirm_arrived) {
                $order_controller = new OrderController();
                $request_new = $request->merge([
                    'status' => 6
                ]);
                $res_order = $order_controller->change_status($request_new, $data->sub_order->order_id);
                if (!$res_order->getData()->status) {
                    DB::rollBack();
                    return $res_order;
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());

            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    /**
     * recent_ticket 
     * @bodyParam user_id int required id cua user. Example: 3
     */
    public function recent_ticket(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required'
        ]);
        $user_id = $request->user_id;
        $orders = Order::with('sub_order')->whereHas('order_for_customer', function ($q) use ($user_id) {
            $q->where('customer_id', $user_id);
        })->get();
        $sub_order_id = [];
        foreach ($orders as $key => $order) {
            $sub_orders = $order->sub_order;
            foreach ($sub_orders as $key => $sub_order) {
                $sub_order_id[] = $sub_order->id;
            }
        }
        $data  = TicketStart::whereIn('sub_order_id', $sub_order_id)->orderBy('id', 'desc')->get();
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }
}
