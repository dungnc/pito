<?php

namespace App\Http\Controllers\API\Order;

use App\Model\User;
use App\Model\Order\Order;
use App\Traits\Notification;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use App\Model\Proposale\Proposale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Model\Order\OrderForPartner;
use App\Model\Order\ChangeRequestOrder;
use App\Model\GenerateToken\RequestToken;
use Illuminate\Support\Facades\Validator;
use App\Model\TicketAndReview\TicketStart;
use App\Model\Proposale\ProposaleForPartner;
use App\Model\Proposale\ProposaleForCustomer;
use App\Model\Notification\NotificationSystem;
use App\Notifications\NotificationOrderToSlack;
use App\Http\Controllers\API\Order\OrderController;
use App\Http\Controllers\API\TicketAndReview\TicketStartController;
use IWasHereFirst2\LaravelMultiMail\Facades\MultiMail;
use App\Model\HistoryRevenue\HistoryRevenueForCustomer;
use App\Mail\Version2\CustomerConfirmOrder\CustomerConfirmed;
use App\Mail\Version2\CustomerConfirmOrder\PartnerWhenCustomerConfirmed;
use App\Model\Order\SubOrder;

/**
 * @group Order History
 *
 * APIs for Order History
 */
class RequestMailController extends Controller
{
    public function customer_comfirm_order(Request $request)
    {
        // DB::beginTransaction();
        try {
            //code...
            $validator = Validator::make($request->all(), [
                'token' => 'required',
            ]);
            if ($validator->fails()) {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
            }
            // if (!$request->confirm) {
            //     $token = $request->token;
            //     return view('confirm-email-pass-google', compact('token'));
            // }
            $token_request = RequestToken::FindToken($request->token)->first();
            if (!$token_request || $token_request->type != "CUSTOMER.CONFIRM") {
                return AdapterHelper::sendResponse(false, 'Bạn đã xác nhận hoặc mã Token không tồn tại.', 404, 'Bạn đã xác nhận hoặc mã Token không tồn tại.');
            }
            Log::stack(['cronjob'])->info('-------------' . $request->token . '-------------');
            $data = json_decode($token_request->request);
            Log::stack(['cronjob'])->info('-------------send out request mail user confirm-' . date('y-m-d H:i:s') . '-------------');
            (new ProposaleController())->change_status_customer($data);
            // $status = $token_request->delete();
            // Log::stack(['cronjob'])->info('-------------status delete: ' . $status . '-------------');
            // DB::commit();
            return AdapterHelper::sendResponse(true, 'success', 200, 'success');
        } catch (\Throwable $th) {
            //throw $th;
            // DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }

    public function customer_request_change_order(Request $request)
    {
        DB::beginTransaction();
        try {
            //code...
            $validator = Validator::make($request->all(), [
                'token' => 'required',
                "change_request" => 'required'
            ]);
            if ($validator->fails()) {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
            }
            $token_request = RequestToken::FindToken($request->token)->first();
            if (!$token_request || $token_request->type != "CUSTOMER.REQUEST_CHANGE") {
                return AdapterHelper::sendResponse(false, 'Bạn đã yêu cầu chỉnh sửa rồi!', 404, 'Bạn đã yêu cầu chỉnh sửa rồi!');
            }
            $data = json_decode($token_request->request);
            $order = Order::find($data->order_id);
            $order->status = $data->status;
            $order->save();
            ChangeRequestOrder::create([
                'content' => $request->change_request,
                'order_id' => $data->order_id,
                'user_id' => $data->user_id
            ]);
            $token_request->delete();
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }

    private function get_data_order($data)
    {
        $assign_pito_admin = $data->proposale->order->assign_pito_admin;
        $customer = $data->proposale->order->order_for_customer->customer;
        $company_customer = $data->proposale->order->company;
        $order = $data->proposale->order;
        $service_none = $order->order_for_customer->service_none;
        $percent_manage = $order->percent_manage_customer;
        // start time
        $start_time = $order->end_time / 60;
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
        $end_time = $order->clean_time / 60;
        $hour = (int) ($end_time / 60);
        $minute = (int) ($end_time % 60);
        if ($hour < 10) {
            $hour = "0" . $hour;
        }
        if ($minute < 10) {
            $minute = "0" . $minute;
        }
        $end_time = $hour . ":" . $minute;

        $food_data = $order->order_for_customer->detail;
        $total_price_menu = 0;
        foreach ($food_data as $key => $value) {
            if ($value->detail) {
                $total_price_menu += (int) ($value->amount) * (int) ($value->detail['price']);
            }
        }

        $total_service = 0;
        foreach ($order->order_for_customer->service_none as $key => $value) {
            $total_service += (int) ($value->amount) * (int) ($value->price);
        }
        $total_transport = 0;
        foreach ($order->order_for_customer->service_transport as $key => $value) {
            $total_transport += (int) ($value->amount) * (int) ($value->price);
        }
        $total_service += $total_transport;
        $price_default_map = [];
        foreach ($order->order_for_customer->service_default as $key => $value) {
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
        $data_final = [
            'data' => $data,
            'food_data' => $food_data,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'total_price_menu' => $total_price_menu,
            'total_service' => $total_service,
            'total_transport' => $total_transport,
            'price_default_map' => $price_default_map,
            'assign_pito_admin' => $assign_pito_admin,
            'customer' => $customer,
            'company_customer' => $company_customer,
            'order' => $order,
            'service_none' => $service_none,
            'percent_manage' => $percent_manage
        ];
        return $data_final;
    }

    public function share_detail_proposale(Request $request)
    {
        DB::beginTransaction();
        try {
            //code...
            $validator = Validator::make($request->all(), [
                'token' => 'required',
            ]);
            if ($validator->fails()) {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
            }
            $token_request = RequestToken::FindToken($request->token)->first();

            if (
                !$token_request
                || ($token_request->type != "CUSTOMER.REQUEST_CHANGE"
                    && $token_request->type != "CUSTOMER.CONFIRM")
            ) {
                return AdapterHelper::sendResponse(false, 'Token không tồn tại.', 404, 'Token không tồn tại.');
            }
            $data_token = json_decode($token_request->request);

            $data = ProposaleForCustomer::with([
                'proposale.order.company',
                'proposale.order.assign_pito_admin',
                'proposale.order.sub_order.order_for_partner.partner',
                'proposale.order.order_for_customer',
                'proposale.order.order_for_customer.customer',
                'proposale.order.order_for_customer.service',
                'proposale.order.order_for_customer.service_none',
                'proposale.order.order_for_customer.service_default',
                'proposale.order.order_for_customer.service_transport',
                'proposale.order.order_for_customer.detail',
                'proposale.order.order_for_customer.detail.child.child',
            ])->where('proposale_id', $data_token->proposale_id)->first();

            $data_final = $this->get_data_order($data);
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $data_final, 200, 'success');
    }

    public function share_detail_ticket_and_order(Request $request)
    {
        DB::beginTransaction();
        try {
            //code...
            $validator = Validator::make($request->all(), [
                'token' => 'required',
            ]);
            if ($validator->fails()) {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
            }
            $token_request = RequestToken::FindToken($request->token)->first();

            if (!$token_request || $token_request->type != "PARTNER.BEGIN_TICKET") {
                return AdapterHelper::sendResponse(false, 'Token không tồn tại.', 404, 'Token không tồn tại.');
            }
            $data_token = json_decode($token_request->request);
            $order_controller = new OrderController();
            $res_order = $order_controller->show($request, $data_token->order_id);
            if (!$res_order->getData()->status) {
                return $res_order;
            }

            $order = $res_order->getData()->data;
            $ticket_start_controller = new TicketStartController();
            $request_new = $request->merge([
                'sub_order_id' => $order->sub_order[0]->id
            ]);
            $res_ticket_start  = $ticket_start_controller->show($request_new, null);
            if (!$res_ticket_start->getData()->status) {
                return $res_ticket_start;
            }
            $data_final = [
                'begin_ticket' => $res_ticket_start->getData()->data,
                'order' => $order
            ];
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $data_final, 200, 'success');
    }

    public function share_payment_for_customer(Request $request)
    {
        DB::beginTransaction();
        try {
            //code...
            $validator = Validator::make($request->all(), [
                'token' => 'required',
            ]);
            if ($validator->fails()) {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
            }
            $token_request = RequestToken::FindToken($request->token)->first();
            if (!$token_request || $token_request->type != "CUSTOMER.PAYMENT") {
                return AdapterHelper::sendResponse(false, 'Token không tồn tại.', 404, 'Token không tồn tại.');
            }
            $data_token = json_decode($token_request->request);

            $data = ProposaleForCustomer::with([
                'proposale.order',
            ])->where('proposale_id', $data_token->proposale_id)->first();
            // refresh new proposale
            $order = $data->proposale->order;
            $order = Order::with('proposale.proposale_for_customer')->find($order->id);
            $data = ProposaleForCustomer::with([
                'proposale.order.company',
                'proposale.order.assign_pito_admin',
                'proposale.order.sub_order.order_for_partner.partner',
                'proposale.order.order_for_customer',
                'proposale.order.order_for_customer.customer',
                'proposale.order.order_for_customer.service',
                'proposale.order.order_for_customer.service_none',
                'proposale.order.order_for_customer.service_default',
                'proposale.order.order_for_customer.service_transport',
                'proposale.order.order_for_customer.detail',
                'proposale.order.order_for_customer.detail.child.child',
            ])->where('proposale_id', $order->proposale->proposale_for_customer->id)->first();
            $total_price = 0;
            $list_price = HistoryRevenueForCustomer::where('proposale_id', $data->id)->where('status', '00')->get();
            foreach ($list_price as $key => $value) {
                $total_price += (int) $value->price;
            }
            $data_final = $this->get_data_order($data);
            $data_final['paymented'] = $total_price;
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $data_final, 200, 'success');
    }
}
