<?php

namespace App\Http\Controllers\API_PARTNER\Proposal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\AdapterHelper;
use App\Model\Order\DetailOrder;
use App\Model\Order\ServiceOrder;
use App\Model\Proposale\ProposaleForPartner;
use App\Model\Proposale\ProposaleForCustomer;
use Illuminate\Support\Facades\Validator;
use App\Model\Order\OrderForPartner;
use App\Model\Order\OrderForCustomer;
use App\Model\Order\Order;
use App\Model\TicketAndReview\TicketStart;

class ProposalController extends Controller
{
    /**
     * Get List Proposale for Partner.
     * @bodyParam id int id của proposale .Example: 6.
     * @bodyParam date_start date ngày bắt đầu tiệc.Example: 2020-02-03
     * @bodyParam customer string tên customer. Example: Thành
     * @bodyParam assign_pito_admin string tên pito admin. Example: Thành
     * @bodyParam address string địa chỉ tổ chức tiệc. Example: 33/4/53 Đào tấn-huế
     * @bodyParam status int trang thai cua order . Example: 0
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Order::with([
            'proposale.proposale_for_partner' => function ($q) use ($user) {
                $q->where('partner_id', $user->id);
            },
            'proposale.proposale_for_customer',
            'company',
            'type_party',
            'assign_pito_admin',
            'proposale.proposale_for_customer.customer' => function ($q) {
                $q->select(['id', 'name', 'email', 'phone', 'type_role']);
            }, 'proposale.order.assign_pito_admin' => function ($q) {
                $q->select(['id', 'name', 'email', 'phone', 'type_role']);
            }
        ])
            ->whereHas('proposale', function ($q) {
                $q->whereNotIn('status', [3, 4]);
            })
            // ->whereHas('proposale.proposale_for_customer', function ($q) {
            //     $q->where('status', 2);
            // })
            ->whereHas('proposale.proposale_for_partner',  function ($q) use ($user) {
                $q->where('partner_id', $user->id);
            });


        $data_request = $request->all();
        unset($data_request['page']);
        if ($request->status !== null) {
            $status = $request->status;
            if ($status == "2")
                $query = $query->whereIn('status', [0, 1, 2, 12]);
            if ($status == "3")
                $query = $query->whereIn('status', [3, 6, 7, 8, 9, 13]);
            if ($status == "11")
                $query = $query->whereIn('status', [11]);
            if ($status == "5")
                $query = $query->whereIn('status', [4, 5, 10]);
        }

        if ($request->id) {
            $query = $query->where('id', 'LIKE', '%' . $request->id . '%');
        }

        if ($request->customer) {
            $customer = $request->customer;
            $query = $query->whereHas('proposale.proposale_for_customer.customer', function ($q) use ($customer) {
                $q->where('name', 'LIKE', '%' . $customer . '%');
            });
        }


        if ($request->assign_pito_admin) {
            $assign_pito_admin = $request->assign_pito_admin;
            $query = $query->whereHas('assign_pito_admin', function ($q) use ($assign_pito_admin) {
                $q->where('name', 'LIKE', '%' . $assign_pito_admin . '%');
            });
        }

        if ($request->address) {
            $address = $request->address;
            $query = $query->where('address', 'LIKE', '%' . $address . '%');
        }

        if ($request->date_start) {
            $date_start = $request->date_start;
            $query = $query->where('date_start', $date_start);
        }


        if ($request->setting_group_menu_id) {
            $setting_group_menu_id = $request->setting_group_menu_id;
            $query =  $query->where('setting_group_menu_id', $setting_group_menu_id);
        }

        if ($request->sort_by && $request->sort_type) {
            if ($request->sort_by == 'status') {
                $sort_sql = "CASE WHEN (status = 0 OR status = 1 OR status = 2 OR status = 12) THEN 4
                    WHEN (status = 3 OR status = 6 OR status = 7 OR status = 8 OR status = 9) THEN 3
                    WHEN (status = 4 OR status = 5 OR status = 10) THEN 2
                    WHEN (status = 11) THEN 1
                    ELSE 5 END " . $request->sort_type;
                $query = $query->orderByRaw($sort_sql);
            } else {
                $query = $query->orderBy($request->sort_by, $request->sort_type);
            }
        } else {
            $query = $query->orderBy('id', 'desc');
        }
        $data = $query->paginate($request->per_page ? $request->per_page : 15)->toArray();
        foreach ($data['data'] as $key => $value) {
            $tmp = $value;
            if (sizeOf($value['proposale']['proposale_for_partner']) > 0)
                $tmp['proposale_for_partner'] = $value['proposale']['proposale_for_partner'][0];

            $tmp['customer'] = $value['proposale']['proposale_for_customer']['customer'];
            unset($tmp['proposale']['order']);
            unset($tmp['proposale']['proposale_for_partner']);
            unset($tmp['proposale']['proposale_for_customer']);
            $data['data'][$key] = $tmp;
        }
        $data = collect($data);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
    }

    /**
     * Get detail proposal
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $data = Order::with('proposale.proposale_for_partner')->find($id)->proposale->proposale_for_partner()->where('partner_id', $user->id);

        $data = $data->with([
            'proposale.order.order_for_customer.customer' => function ($q) {
                $q->select(['id', 'name', 'email', 'phone', 'type_role']);
            }, 'proposale.order.assign_pito_admin' => function ($q) {
                $q->select(['id', 'name', 'email', 'phone', 'type_role']);
            },
            'sub_order.buffet_price.buffet',
            'sub_order.order_for_partner.service',
            'sub_order.order_for_partner.service_none',
            'sub_order.order_for_partner.service_default',
            'sub_order.order_for_partner.service_transport',
            'sub_order.order_for_partner.detail',
            'sub_order.order_for_partner.detail.child',
            'sub_order.order_for_partner.detail.child.child',
            'proposale.order.type_party',

            'proposale.order.company',
            'proposale.order.menu',
            'proposale.order.setting_group_menu',
            'proposale.order.type_menu', 'proposale.order.style_menu',

            'sub_order',
            'sub_order.order_detail_customize'
        ])->where('partner_id', $user->id)->first();
        try {
            //code...
            if (!$data)
                return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
            $res = $data->toArray();

            $res['order'] = $data->proposale->order;
            $res['customer'] = $data->proposale->order->order_for_customer->customer;
            $res['company'] = $data->proposale->order->company;
            $res['assign_pito_admin'] = $data->proposale->order->assign_pito_admin;
            $res['detail'] = $data->sub_order->order_for_partner->detail;
            $res['service'] = $data->sub_order->order_for_partner->service;

            $res['service_none'] = $data->sub_order->order_for_partner->service_none;
            $res['service_default'] = $data->sub_order->order_for_partner->service_default;
            $res['service_transport'] = $data->sub_order->order_for_partner->service_transport;
            $res['type_party'] = $data->proposale->order->type_party;
            $res['menu'] = $data->proposale->order->menu;
            $res['style_menu'] = $data->proposale->order->style_menu;
            $res['type_menu'] = $data->proposale->order->type_menu;
            $res['order_detail_customize'] = $data->sub_order->order_detail_customize;
            $res['buffet_price'] = $data->sub_order->buffet_price;
            $res['ticket_start'] = TicketStart::where('partner_id', $request->user()->id)
                ->where('sub_order_id', $data->sub_order->id)
                ->whereHas('sub_order.order', function ($q) {
                    $q->whereNotIn('status', [0, 1, 5]);
                })->first();


            $total_price_menu = 0;
            foreach ($res['detail'] as $key => $value) {
                if ($value->detail) {
                    $total_price_menu += (int) ($value->amount) * (int) ($value->detail['price']);
                }
            }

            $res['total_price_menu'] = $total_price_menu;

            $total_service = 0;
            $service_none =   $res['service_none'];
            foreach ($service_none as $key => $value) {
                $total_service += (int) ($value->amount) * (int) ($value->price);
            }
            $res['total_service'] = $total_service;

            $total_transport = 0;
            $service_transport = $res['service_transport'];
            foreach ($service_transport as $key => $value) {
                if ($value->partner_id == $user->id)
                    $total_transport += (int) ($value->amount) * (int) ($value->price);
            }
            $res['total_transport'] = $total_transport;
            $res['total_service'] += $res['total_transport'];

            foreach ($res['service_default'] as $key => $value) {
                if (strpos(strtoupper($value->name), strtoupper("Tổng Giá Trị Tiệc")) > -1)
                    $res['total_1_2'] = (int) $value->price;
                if (strpos(strtoupper($value->name), strtoupper("Phí Dịch Vụ (20%)")) > -1)
                    $res['total_percent_service'] = (int) $value->price;
                if (strpos(strtoupper($value->name), strtoupper("Tổng Giá Trị Cần Thanh Toán (chưa bao gồm VAT)")) > -1)
                    $res['total_not_vat'] = (int) $value->price;
                if ($value->name == "VAT")
                    $res['vat'] = (int) $value->price;
                if (strpos(strtoupper($value->name), strtoupper("Tổng Giá Trị Cần Thanh Toán (đã bao gồm VAT)")) > -1)
                    $res['total'] = (int) $value->price;
            }

            unset($res['order']['order_for_customer']);
            unset($res['order']['order_for_partner']);
            unset($res['sub_order']);

            unset($res['order']['sub_order']);
            unset($res['proposale']['order']);
        } catch (\Throwable $th) {
            //throw $th;
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined', 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, $res, 200, 'success');
    }
}
