<?php

namespace App\Http\Controllers\API\Order;

use App\Model\User;
use App\Model\Order\Order;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Model\TicketAndReview\TicketStart;
use App\Model\Proposale\ProposaleForPartner;
use App\Model\Proposale\ProposaleForCustomer;
use App\Model\TicketAndReview\Review\FullReview;

/**
 * @group Order History
 *
 * APIs for Order History
 */
class PDFController extends Controller
{
    public function download_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            // 'name' => 'required',
            // 'type' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $data = Order::with([
            'assign_pito_admin', 'pito_admin',
            'type_party', 'type_menu', 'style_menu', 'menu', 'setting_group_menu',
            'sub_order',
            'sub_order.buffet_price.buffet',
            'sub_order.buffet_price.menu_buffect',
            'sub_order.order_for_partner',
            'sub_order.order_for_partner.service',
            'sub_order.order_for_partner.service_none',
            'sub_order.order_for_partner.service_default',
            'sub_order.order_for_partner.service_transport',
            'sub_order.order_for_partner.detail',
            'sub_order.order_for_partner.detail.child',
            'sub_order.order_for_partner.detail.child.child',
            'sub_order.order_for_partner.partner',
            'sub_order.order_detail_customize',
            'sub_order.proposale_for_partner',
            'sub_order.proposale_for_partner.detail',
            'sub_order.proposale_for_partner.detail.child',
            'proposale',
            'proposale.proposale_for_customer.customer',
            'proposale.proposale_for_customer.detail',
            'proposale.proposale_for_customer.detail.child',
            'proposale.proposale_for_partner',
            'proposale.proposale_for_partner.partner',
            'proposale.proposale_for_partner.detail',
            'proposale.proposale_for_partner.detail.child',
            'order_for_customer',
            'order_for_customer.detail',
            'order_for_customer.detail.child',
            'order_for_customer.detail.child.child',
            'order_for_customer.customer',
            'order_for_customer.service',
            'order_for_customer.service_none',
            'order_for_customer.service_default',
            'order_for_customer.service_transport',
            'company'
        ])->find($request->order_id);
        $pdf = App::make('snappy.pdf.wrapper');
        $start_time = $data->end_time / 60;
        $hour = (int) ($start_time / 60);
        $minute = (int) ($start_time % 60);
        $customer = $data->order_for_customer->customer;
        $company_customer = $data->company;
        $assign_pito_admin = $data->assign_pito_admin;
        if ($hour < 10) {
            $hour = "0" . $hour;
        }
        if ($minute < 10) {
            $minute = "0" . $minute;
        }
        $start_time = $hour . ":" . $minute;

        // end time
        $end_time = $data->clean_time / 60;
        $hour = (int) ($end_time / 60);
        $minute = (int) ($end_time % 60);
        if ($hour < 10) {
            $hour = "0" . $hour;
        }
        if ($minute < 10) {
            $minute = "0" . $minute;
        }
        $end_time = $hour . ":" . $minute;

        $food_data = $data->order_for_customer->detail;
        $total_price_menu = 0;
        foreach ($food_data as $key => $value) {
            if ($value->detail) {
                $total_price_menu += (int) ($value->amount) * (int) ($value->detail['price']);
            }
        }

        $total_service = 0;
        $service_none = $data->order_for_customer->service_none;
        foreach ($service_none as $key => $value) {
            $total_service += (int) ($value->amount) * (int) ($value->price);
        }
        $total_transport = 0;
        $service_transport = $data->order_for_customer->service_transport;
        foreach ($service_transport as $key => $value) {
            $total_transport += (int) ($value->amount) * (int) ($value->price);
        }
        $total_service += $total_transport;
        $price_default_map = [];
        $service_default = $data->order_for_customer->service_default;
        foreach ($service_default as $key => $value) {
            if (strpos(strtoupper($value->name), strtoupper("Tổng giá trị tiệc")) > -1)
                $price_default_map['total'] = $value;
            if (
                strpos(strtoupper($value->name), strtoupper("Phí thuận tiện")) > -1
                || strpos(strtoupper($value->name), strtoupper("Phí dịch vụ")) > -1
            )
                $price_default_map['price_manage'] = $value;

            if (strtoupper($value->name) ==  strtoupper("Ưu Đãi") || strtoupper($value->name) ==  strtoupper("Ưu đãi")) {
                $price_default_map['promotion'] = $value;
            }
            if (strpos(strtoupper($value->name), strtoupper("Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và chưa bao gồm VAT)")) > -1)
                $price_default_map['total_not_VAT'] = $value;
            if ($value->name == "VAT")
                $price_default_map['VAT'] = $value;
            if (strpos(strtoupper($value->name), strtoupper("Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và  VAT)")) > -1)
                $price_default_map['total_VAT'] = $value;
        }
        $view = 'pdf_dondathang';

        if ($request->type == 'mobile') {
            $view = 'pdf_dondathang_mobile';
        }
        $pdf->loadView('pdf.' . $view, compact(
            'data',
            'food_data',
            'start_time',
            'end_time',
            'total_price_menu',
            'total_service',
            'total_transport',
            'price_default_map',
            'service_none',
            'customer',
            'company_customer',
            'assign_pito_admin'
        ));
        return $pdf->download('đơn-hàng-' . ($request->type ? $request->type : 'desktop') . '.pdf');
    }

    public function download_proposale(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'proposale_id' => 'required',
            'role' => 'required',
            // 'type' => 'required'
        ]);
        $view = 'pdf_baogia_mobile';
        if ($request->type == 'desktop') {
            $view = 'pdf_baogia';
        }
        if ($request->role == User::$const_type_role['CUSTOMER']) {
            $data = ProposaleForCustomer::with([
                'proposale.order.company',
                'proposale.order.assign_pito_admin',
                'proposale.order.order_for_customer',
                'proposale.order.order_for_customer.customer',
                'proposale.order.order_for_customer.service',
                'proposale.order.order_for_customer.service_none',
                'proposale.order.order_for_customer.service_default',
                'proposale.order.order_for_customer.service_transport',
                'proposale.order.order_for_customer.detail.child.child',
                // 'sub_order.order_for_partner.service',
                // 'sub_order.order_for_partner.service_none',
                // 'sub_order.order_for_partner.service_default',
                // 'sub_order.order_for_partner.service_transport',
                // 'sub_order.order_for_partner.detail',
            ])->find($request->proposale_id);

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
            $pdf = App::make('snappy.pdf.wrapper');
            $pdf->loadView('pdf.' . $view, compact(
                'data',
                'food_data',
                'start_time',
                'end_time',
                'total_price_menu',
                'total_service',
                'total_transport',
                'price_default_map',
                'assign_pito_admin',
                'customer',
                'company_customer',
                'order',
                'service_none',
                'percent_manage'
            ));
            return $pdf->download('Báo-giá-PT' . $order->id . '.pdf');
        } else {
            $data = ProposaleForPartner::with([
                'proposale.order.company',
                'proposale.order.assign_pito_admin',
                'proposale.order.order_for_customer',
                'proposale.order.order_for_customer.customer',
                'sub_order.order_for_partner.service',
                'sub_order.order_for_partner.service_none',
                'sub_order.order_for_partner.service_default',
                'sub_order.order_for_partner.service_transport',
                'sub_order.order_for_partner.detail',
            ])->find($request->proposale_id);
            $assign_pito_admin = $data->proposale->order->assign_pito_admin;
            $customer = $data->proposale->order->order_for_customer->customer;
            $company_customer = $data->proposale->order->company;
            $order = $data->proposale->order;
            $service_none = $data->sub_order->order_for_partner->service_none;
            $percent_manage = $order->percent_manage_partner;
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

            $food_data = $data->sub_order->order_for_partner->detail;
            $total_price_menu = 0;
            foreach ($food_data as $key => $value) {
                if ($value->detail) {
                    $total_price_menu += (int) ($value->amount) * (int) ($value->detail['price']);
                }
            }

            $total_service = 0;
            foreach ($service_none as $key => $value) {
                $total_service += (int) ($value->amount) * (int) ($value->price);
            }
            $total_transport = 0;
            foreach ($data->sub_order->order_for_partner->service_transport as $key => $value) {
                $total_transport += (int) ($value->amount) * (int) ($value->price);
            }
            $total_service += $total_transport;
            $price_default_map = [];
            foreach ($data->sub_order->order_for_partner->service_default as $key => $value) {
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
                if (
                    strpos(strtoupper($value->name), strtoupper("Tổng Giá Trị Cần Thanh Toán (chưa bao gồm VAT)")) > -1
                    || strpos(strtoupper($value->name), strtoupper("Tổng Giá Trị Cần Thanh Toán (Chưa bao gồm VAT)")) > -1
                )
                    $price_default_map['total_not_VAT'] = $value;
                if ($value->name == "VAT")
                    $price_default_map['VAT'] = $value;
                if (
                    strpos(strtoupper($value->name), strtoupper("Tổng Giá Trị Cần Thanh Toán (đã bao gồm VAT)")) > -1 ||
                    strpos(strtoupper($value->name), strtoupper("Tổng Giá Trị Cần Thanh Toán (Đã bao gồm VAT)")) > -1
                )
                    $price_default_map['total_VAT'] = $value;
            }
            $partner = true;
            $pdf = App::make('snappy.pdf.wrapper');
            $pdf->loadView('pdf.pdf_dondathang', compact(
                'data',
                'food_data',
                'start_time',
                'end_time',
                'total_price_menu',
                'total_service',
                'total_transport',
                'price_default_map',
                'assign_pito_admin',
                'customer',
                'company_customer',
                'order',
                'service_none',
                'percent_manage',
                'partner'
            ));
            // return $pdf->inline();
            return $pdf->download('Báo-giá-PT' . $order->id . '.pdf');
        }
        $view = 'pdf_baogia_mobile';
        if ($request->type == 'desktop') {
            $view = 'pdf_baogia';
        }
        $pdf = App::make('snappy.pdf.wrapper');
        $pdf->loadView('pdf.' . $view, compact(
            'data',
            'food_data',
            'start_time',
            'end_time',
            'total_price_menu',
            'total_service',
            'total_transport',
            'price_default_map',
            'assign_pito_admin',
            'customer',
            'company_customer',
            'order',
            'service_none',
            'percent_manage'
        ));
        // return $pdf->inline();
        return $pdf->download('Báo-giá-PT' . $order->id . '.pdf');
    }

    public function download_ticket(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sub_order_id' => 'required',
            'role' => 'required',
            // 'type' => 'required'
        ]);
        $data = TicketStart::with([
            'sub_order.order.proposale',
            'sub_order.order', 'sub_order.order.company',
            'sub_order.order.assign_pito_admin',
            'sub_order.proposale_for_partner.detail',
            'sub_order.proposale_for_partner.proposale',
            'sub_order.order.order_for_customer.customer',
            'sub_order.order.pito_admin',
            'sub_order.order_detail_customize',
            'sub_order.order.order_for_customer',
            'sub_order.order_for_partner.partner',
            'sub_order.order.order_for_customer.detail',
            'sub_order.order.order_for_customer.detail.child',
            'sub_order.order.order_for_customer.detail.child.child',
            'sub_order.order.order_for_customer.customer',
            'sub_order.order.order_for_customer.service',
            'sub_order.order.order_for_customer.service_none',
            'sub_order.order.order_for_customer.service_default',
            'sub_order.order.order_for_customer.service_transport',
            'sub_order'
        ])->where('sub_order_id', $request->sub_order_id)->first();

        $pdf = App::make('snappy.pdf.wrapper');
        $view = 'pdf_phieuditiec';
        if ($request->type == 'mobile') {
            $view = 'pdf_phieuditiec_mobile';
        }
        $proposale = $data->sub_order->order->proposale;
        $proposale_for_partner = $data->sub_order->proposale_for_partner;
        $order = $data->sub_order->order;

        $time_setup = ($order->end_time - 60 * 60 * 2) / 60;
        // if ($time_setup < 0) {
        //     $time_setup += 23 * 60 * 60;
        //     $time_setup /= 60;
        // }
        $hour = (int) ($time_setup / 60);
        $minute = (int) ($time_setup % 60);
        if ($hour < 10) {
            $hour = "0" . $hour;
        }
        if ($minute < 10) {
            $minute = "0" . $minute;
        }


        $time_setup = $hour . ":" . $minute;

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
        $food_data = $data->sub_order->order_for_partner->detail;
        $services = $data->field ? $data->field->ticket_service : [];

        $reminds = $data->field ? $data->field->ticket_remind : [];
        // return view('pdf.' . $view, compact(
        //     'data',
        //     'food_data',
        //     'services',
        //     'reminds',
        //     'start_time',
        //     'end_time',
        //     'time_setup',
        //     'order',
        //     'proposale_for_partner',
        //     'proposale'
        // ));
        $pdf->loadView('pdf.' . $view, compact(
            'data',
            'food_data',
            'services',
            'reminds',
            'start_time',
            'end_time',
            'time_setup',
            'order',
            'proposale_for_partner',
            'proposale'
        ));
        // return $pdf->inline();
        return $pdf->download('Phiếu-đi-tiệc-' . $data->id . '.pdf');
    }

    public function download_result_review_final(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'review_id' => 'required',
            // 'type' => 'required'
        ]);
        $data = FullReview::with(['order.proposale'])->find($request->review_id);
        // dd($data);
        $pdf = App::make('snappy.pdf.wrapper');
        $view = 'pdf_ketquadanhgia';
        $pdf->loadView('pdf.' . $view, compact(
            'data'
        ));
        // return view('pdf.' . $view, compact(
        //     'data'
        // ));
        // return $pdf->inline();
        return $pdf->download('Kết-Quả-Đánh-Giá-PT' . $data->order->id . '.pdf');
    }

    public function download_review_final(Request $request)
    {
        $pdf = App::make('snappy.pdf.wrapper');
        $pdf->loadview('pdf.pdf_danhgia');
        return $pdf->download('Đánh-giá' . '.pdf');
    }
}
