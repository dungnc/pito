<?php

namespace App\Http\Controllers\API\Order;

use PDF;
use App\Model\User;
use App\Mail\ConfirmOrder;
use App\Model\Order\Order;
use App\Mail\SendProposale;
use App\Traits\Notification;
use Illuminate\Http\Request;
use App\Mail\SendProposaleV2;
use App\Model\Order\SubOrder;
use App\Traits\AdapterHelper;
use App\Model\Order\DetailOrder;
use App\Model\Order\ServiceOrder;
use App\Model\Proposale\Proposale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\Controller;
use App\Model\Order\OrderForPartner;
use Illuminate\Support\Facades\Mail;
use App\Model\Order\OrderForCustomer;
use App\Model\TicketAndReview\Review;
use App\Model\TicketAndReview\TicketEnd;
use App\Model\GenerateToken\RequestToken;
use Illuminate\Support\Facades\Validator;
use App\Model\TicketAndReview\TicketStart;
use App\Model\Proposale\ProposaleForPartner;
use App\Model\Proposale\ProposaleForCustomer;
use App\Model\Notification\NotificationSystem;
use App\Notifications\NotificationOrderToSlack;
use App\Mail\Version2\Proposale\ProposalePartner;
use App\Mail\Version2\Proposale\ProposaleCustomer;
use App\Model\HistoryRevenue\HistoryRevenueForPartner;
use IWasHereFirst2\LaravelMultiMail\Facades\MultiMail;
use App\Model\HistoryRevenue\HistoryRevenueForCustomer;
use App\Mail\Version2\CustomerConfirmOrder\CustomerConfirmed;
use App\Mail\Version2\CustomerConfirmOrder\PartnerWhenCustomerConfirmed;

/**
 * @group Proposale
 *
 * APIs for Proposale
 */
class ProposaleController extends Controller
{
    /**
     * Get List Proposale.
     * @bodyParam id int id của proposale .Example: 6.
     * @bodyParam order_id int id của order . Example: 8
     * @bodyParam date_start date ngày bắt đầu tiệc.Example: 2020-02-03
     * @bodyParam partner string  tên partner. Example: PARTNER
     * @bodyParam customer string tên customer. Example: Thành
     * @bodyParam address string địa chỉ tổ chức tiệc. Example: 33/4/53 Đào tấn-huế
     * @bodyParam status int trạng thái của loại tiệc . Example: 0
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->type_role == 'PARTNER') {
            return $this->index_for_partner($request);
        }
        $query = Proposale::with([
            'order', 'order.company', 'order.type_party',
            'order.sub_order.buffet_price.buffet',
            'order.assign_pito_admin',
            'order.sub_order.order_detail_customize',
            'proposale_for_customer',
            'proposale_for_customer.customer',
            'proposale_for_partner',
            'proposale_for_partner.partner'
        ])->select('*');
        $data_request = $request->all();
        unset($data_request['page']);
        if ($request->status !== null) {
            $query = $query->where('status', $request->status);
        }

        if ($request->id) {
            $query = $query->where('id', 'LIKE', '%' . $request->id . '%');
        }

        if ($request->order_id) {
            $query = $query->where('order_id', 'LIKE', '%' . $request->order_id . '%');
        }

        if ($request->partner) {
            $partner = $request->partner;
            $query = $query->whereHas('proposale_for_partner.partner', function ($q) use ($partner) {
                $q->where('name', 'LIKE', '%' . $partner . '%');
            });
        }

        if ($request->customer) {
            $customer = $request->customer;
            $query = $query->whereHas('proposale_for_customer.customer', function ($q) use ($customer) {
                $q->where('name', 'LIKE', '%' . $customer . '%');
            });
        }

        if ($request->address) {
            $address = $request->address;
            $query = $query->whereHas('order', function ($q) use ($address) {
                $q->where('address', 'LIKE', '%' . $address . '%');
            });
        }

        if ($request->date_start) {
            $date_start = $request->date_start;
            $query = $query->whereHas('order', function ($q) use ($date_start) {
                $q->where('date_start', $date_start);
            });
        }

        $data = $query->orderBy('id', 'desc')->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
    }

    /**
     * Get List Proposale for Partner.
     * @bodyParam id int id của proposale .Example: 6.
     * @bodyParam date_start date ngày bắt đầu tiệc.Example: 2020-02-03
     * @bodyParam customer string tên customer. Example: Thành
     * @bodyParam assign_pito_admin string tên pito admin. Example: Thành
     * @bodyParam address string địa chỉ tổ chức tiệc. Example: 33/4/53 Đào tấn-huế
     * @bodyParam status int trang thai cua order . Example: 0
     */
    private function index_for_partner(Request $request)
    {
        $query = ProposaleForPartner::with([
            'proposale.order', 'proposale.order.company', 'proposale.order.type_party',
            'sub_order.buffet_price.buffet',
            'sub_order.order_detail_customize',
            'proposale.proposale_for_customer.customer' => function ($q) {
                $q->select(['id', 'name', 'email', 'phone', 'type_role']);
            }, 'proposale.order.assign_pito_admin' => function ($q) {
                $q->select(['id', 'name', 'email', 'phone', 'type_role']);
            },
        ])->select('*')
            ->whereHas('proposale.proposale_for_customer', function ($q) {
                $q->where('status', 2);
            })
            ->whereHas('proposale', function ($q) {
                $q->whereNotIn('status', [3, 4]);
            })
            ->where('partner_id', $request->user()->id)
            ->where('status', '>=', 1);
        $data_request = $request->all();
        unset($data_request['page']);
        if ($request->status !== null) {
            $status = $request->status;
            $query = $query->whereHas('proposale.order', function ($q) use ($status) {
                $q->where('status', $status);
            });
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
            $query = $query->whereHas('proposale.order.assign_pito_admin', function ($q) use ($assign_pito_admin) {
                $q->where('name', 'LIKE', '%' . $assign_pito_admin . '%');
            });
        }

        if ($request->address) {
            $address = $request->address;
            $query = $query->whereHas('proposale.order', function ($q) use ($address) {
                $q->where('address', 'LIKE', '%' . $address . '%');
            });
        }

        if ($request->date_start) {
            $date_start = $request->date_start;
            $query = $query->whereHas('proposale.order', function ($q) use ($date_start) {
                $q->where('date_start', $date_start);
            });
        }
        $data = $query->orderBy('id', 'desc')->paginate($request->per_page ? $request->per_page : 15)->toArray();
        foreach ($data['data'] as $key => $value) {
            $tmp = $value;
            $tmp['order'] = $value['proposale']['order'];
            $tmp['customer'] = $value['proposale']['proposale_for_customer']['customer'];
            unset($tmp['proposale']['order']);
            unset($tmp['proposale']['proposale_for_customer']);
            $data['data'][$key] = $tmp;
        }
        $data = collect($data);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
    }
    /**
     * Get Proposale by id.
     *
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        if ($user->type_role == User::$const_type_role['PARTNER']) {
            return $this->show_for_partner($request, $id);
        }
        $data = Proposale::with([
            'order', 'order.company', 'order.type_menu', 'order.menu', 'order.style_menu',
            'order.assign_pito_admin',
            'order.type_party',
            'order.order_for_customer.detail.child.child',
            'order.order_for_customer.service',
            'order.order_for_customer.service_none',
            'order.order_for_customer.service_default',
            'order.order_for_customer.service_transport',
            'order.sub_order.order_detail_customize',
            'order.sub_order.buffet_price.buffet',
            'proposale_for_customer',
            'proposale_for_customer.customer',
            'proposale_for_partner',
            'proposale_for_partner.partner',
            'proposale_for_partner.sub_order',
            'proposale_for_partner.sub_order.order_for_partner.detail.child.child',
            'proposale_for_partner.sub_order.order_for_partner.service',
            'proposale_for_partner.sub_order.order_for_partner.service_none',
            'proposale_for_partner.sub_order.order_for_partner.service_default',
            'proposale_for_partner.sub_order.order_for_partner.service_transport',
            'proposale_for_partner.sub_order.order_detail_customize'
        ])->find($id);
        if (!$data)
            return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }

    /**
     * Get Proposale by id cuar partner.
     *
     */
    public function show_for_partner(Request $request, $id)
    {
        $data = ProposaleForPartner::with([
            'proposale.order.order_for_customer.customer' => function ($q) {
                $q->select(['id', 'name', 'email', 'phone', 'type_role']);
            }, 'proposale.order.assign_pito_admin' => function ($q) {
                $q->select(['id', 'name', 'email', 'phone', 'type_role']);
            },
            'sub_order.buffet_price.buffet',
            'sub_order.order_for_partner.service',
            'proposale.order.type_party',
            'proposale.order.company',
            'proposale.order.menu',
            'proposale.order.type_menu', 'proposale.order.style_menu',
            'detail',
            'detail.child',
            'sub_order',
            'sub_order.order_detail_customize'
        ])->find($id);
        try {
            //code...
            if (!$data)
                return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
            $res = $data->toArray();

            $res['order'] = $data->proposale->order;
            $res['customer'] = $data->proposale->order->order_for_customer->customer;
            $res['company'] = $data->proposale->order->company;
            $res['assign_pito_admin'] = $data->proposale->order->assign_pito_admin;
            $res['service'] = $data->sub_order->order_for_partner->service;
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
            $res['ticket_end'] = TicketEnd::where('partner_id', $request->user()->id)
                ->where('sub_order_id', $data->sub_order->id)
                ->whereHas('sub_order.order', function ($q) {
                    $q->whereNotIn('status', [0, 1, 5]);
                })->first();
            $res['review'] = Review::where('sub_order_id', $data->sub_order->id)
                ->first();
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
    /**
     * Get Proposale by id cuar Customer.
     *
     */
    public function show_for_customer(Request $request, $id)
    {
        $data = ProposaleForCustomer::with([
            'proposale.order.assign_pito_admin' => function ($q) {
                $q->select(['id', 'name', 'email', 'phone', 'type_role']);
            },
            'proposale.order.type_party', 'proposale.order.menu',
            'proposale.order.type_menu', 'proposale.order.style_menu',
            'proposale.order.company',
            'detail',
            'detail.child'
        ])->find($id);
        try {
            //code...
            if (!$data)
                return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
            $res = $data->toArray();
        } catch (\Throwable $th) {
            //throw $th;
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined', 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, $res, 200, 'success');
    }


    /**
     * Export to pdf file
     *
     */
    public function export_pdf(Request $request, $id)
    {
        $data = Proposale::with([
            'order', 'order.company', 'order.type_menu', 'order.style_menu', 'order.menu', 'order.order_for_customer.service', 'order.sub_order.order_for_partner.service', 'order.assign_pito_admin', 'order.type_party',
            'proposale_for_customer',
            'order.sub_order.buffet_price.buffet',
            'proposale_for_customer.customer', 'proposale_for_customer.detail', 'proposale_for_customer.detail.child',
            'proposale_for_partner', 'proposale_for_partner.partner', 'proposale_for_partner.detail', 'proposale_for_partner.detail.child',
            'proposale_for_partner.sub_order', 'proposale_for_partner.sub_order.order_detail_customize'
        ])->find($id);
        if (!$data)
            return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
        $pdf = PDF::loadView('pdf_views.proposale.detail', ['data' => $data, 'user' => $request->user]);
        return $pdf->download('medium.pdf');
    }

    public function status_send($request, $proposale_id, $type_role)
    {
        if ($type_role == User::$const_type_role['CUSTOMER']) {
            $proposaleForCustomer = ProposaleForCustomer::where('proposale_id', $proposale_id)
                ->where('customer_id', $request->user_id)
                ->first();
            if (!$proposaleForCustomer) {
                return AdapterHelper::sendResponse(false, 'Proposale for customer not found', 404, 'Proposale for customer not found');
            }
            $proposale = Proposale::find($proposale_id);

            $order = Order::find($proposale->order_id);
            $new_notifi = new NotificationSystem();
            $new_notifi->content = 'Order PT' . $order->id . ' gửi báo giá cho khách hàng ' . User::find($request->user_id)->name;
            $new_notifi->tag = NotificationSystem::EVENT_TYPE["PROPOSAL.CUSTOMER.SEND"];
            $new_notifi->type = Order::class;
            $new_notifi->type_id = $order->id;
            $new_notifi->save();
            $data = [
                'order_id' => $order->id,
                'tag' => NotificationSystem::EVENT_TYPE["PROPOSAL.CUSTOMER.SEND"]
            ];
            $list_user = [
                'role' => 'PITO_ADMIN'
            ];
            Notification::notifi_more($data, 'Order PT' . $order->id . ' gửi báo giá cho khách hàng ' . User::find($request->user_id)->name, $list_user, []);

            if (!$proposale) {
                return AdapterHelper::sendResponse(false, 'Proposale  not found', 404, 'Proposale not found');
            }        // da gui
            $proposaleForCustomer->status = 1;
            $proposaleForCustomer->save();

            // kiem tra xem da gui bao gia cho partner chua
            $check_for_partner_not_send = ProposaleForPartner::where('proposale_id', $proposale_id)
                ->where('status', '>=', 1)->where('status', '<=', 2)->exists();

            // kiem tra xem trang thai tong cua bao gia co huy hay ko
            $check_for_partner_cancel = Proposale::where('id', $proposale_id)
                ->where('status', 3)->exists();
            if ($check_for_partner_not_send && !$check_for_partner_cancel) {
                $proposale->status = 1;
                $proposale->save();
            }
        } else {
            if (!$request->sub_order_id) {
                return AdapterHelper::sendResponse(false, 'Validation error', 404, 'Đây là Partner xin hãy truyền sub order id');
            }
            $proposaleForPartner = ProposaleForPartner::where('proposale_id', $proposale_id)
                ->where('sub_order_id', $request->sub_order_id)
                ->where('partner_id', $request->user_id)
                ->first();
            if (!$proposaleForPartner) {
                return AdapterHelper::sendResponse(false, 'Proposale for partner not found', 404, 'Proposale for partner not found');
            }
            $proposale = Proposale::find($proposale_id);
            if (!$proposale) {
                return AdapterHelper::sendResponse(false, 'Proposale  not found', 404, 'Proposale not found');
            }


            $order = Order::find($proposale->order_id);
            $new_notifi = new NotificationSystem();
            $new_notifi->content = 'Order PT' . $order->id . ' gửi báo giá cho đối tác ' . User::find($request->user_id)->name;
            $new_notifi->tag = NotificationSystem::EVENT_TYPE["PROPOSAL.PARTNER.SEND"];
            $new_notifi->type = Order::class;
            $new_notifi->type_id = $order->id;
            $new_notifi->save();
            $data = [
                'order_id' => $order->id,
                'tag' => NotificationSystem::EVENT_TYPE["PROPOSAL.PARTNER.SEND"]
            ];
            $list_user = [
                'role' => 'PITO_ADMIN'
            ];
            Notification::notifi_more($data, 'Order PT' . $order->id . ' gửi báo giá cho đối tác ' . User::find($request->user_id)->name, $list_user, []);
            // da gui
            $proposaleForPartner->status = 1;
            $proposaleForPartner->save();

            // kiem tra xem da gui bao gia cho partner chua
            $check_for_partner_not_send = ProposaleForPartner::where('proposale_id', $proposale_id)
                ->where('status', '>=', 1)->where('status', '<=', 2)->exists();

            // kiem tra xem trang thai tong cua bao gia co huy hay ko
            $check_for_partner_cancel = Proposale::where('id', $proposale_id)
                ->where('status', 3)->exists();
            if ($check_for_partner_not_send && !$check_for_partner_cancel) {
                $proposale->status = 1;
                $proposale->save();
            }
        }
        // update status cho order
        $order = Order::find($proposale->order_id);
        $order->status = $proposale->status;
        $order->save();
    }
    /**
     * send proposale
     * @bodyParam user_id  int required id cua user. Example: 20.
     * @bodyParam sub_order_id int id cua sub order neu laf partner. Example: 20.
     */
    public function send_proposale(Request $request, $id)
    {
        $user = User::with(['company'])->find($request->user_id);
        if (!$user) {
            return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
        }
        $this->status_send($request, $id, $user->type_role);
        DB::beginTransaction();
        try {
            //code...
            $data = Proposale::with([
                'order', 'order.company', 'order.type_menu', 'order.style_menu', 'order.menu', 'order.order_for_customer.service',
                'order.sub_order.order_for_partner.service', 'order.assign_pito_admin', 'order.type_party',
                'proposale_for_customer',
                'order.sub_order.buffet_price.buffet',
                'proposale_for_customer.customer', 'proposale_for_customer.detail', 'proposale_for_customer.detail.child',
                'proposale_for_partner', 'proposale_for_partner.partner', 'proposale_for_partner.detail', 'proposale_for_partner.detail.child',
                'proposale_for_partner.sub_order', 'proposale_for_partner.sub_order.order_detail_customize'
            ])->find($id);

            if (!$data)
                return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
            // Mail::to($user->email)->send(new SendProposale($data, $user->type_role, $user, $request->sub_order_id));

            $time_start = (int) ($data->order->start_time / 60);
            $hour = (int) ($time_start / 60);
            $minute = (int) $time_start % 60;
            if ($hour < 10) {
                $hour = "0" . $hour;
            }
            if ($minute < 10) {
                $minute = "0" . $minute;
            }
            $date_time = $hour . ":" . $minute . " " . date('d/m/Y', strtotime($data->order->date_start));
            if ($user->type_role == User::$const_type_role['CUSTOMER']) {
                $services = $data->order->order_for_customer->service;
                foreach ($services as $key => $value) {
                    if (strpos($value->name, "Tổng Giá Trị Tiệc") > -1)
                        $service_order['price'] = $value->price;
                    if (strpos($value->name, "Phí Thuận Tiện") > -1 || strpos($value->name, "Phí Dịch Vụ") > -1)
                        $service_order['price_manage'] = $value->price;
                }
                $service_order['total_price_VAT'] = $service_order['price'] + $service_order['price'] * 0.1 + $service_order['price_manage'];
                $service_order['total_price_no_VAT'] = $service_order['price'] + $service_order['price_manage'];
            } else {
                $orderForPartner =  OrderForPartner::where('sub_order_id', $request->sub_order_id)->first();
                $tmp = ServiceOrder::where('service_orderable_type', OrderForPartner::class)
                    ->where('service_orderable_id', $orderForPartner->id)->get();
                foreach ($tmp as $key => $value) {
                    if (strpos($value->name, "Tổng Giá Trị Tiệc") > -1)
                        $service_order['price'] = $value->price;
                    if (strpos($value->name, "Phí Thuận Tiện") > -1 || strpos($value->name, "Phí Dịch Vụ") > -1)
                        $service_order['price_manage'] = $value->price;
                }
                $service_order['total_price_VAT'] = $service_order['price'] + $service_order['price'] * 0.1 - $service_order['price_manage'];
                $service_order['total_price_no_VAT'] = $service_order['price'] - $service_order['price_manage'];
            }

            $proposale = [
                'id' => $data->id,
                'date_start' => $date_time,
                'name' => $data->order->name,
                'service_order' => $service_order,
                'partner' => $data['proposale_for_partner'][0]['partner']
            ];
            $pito = $data['order']['assign_pito_admin'];
            // Mail::to($user->email)->send(new SendProposaleV2($user, $pito, $proposale, $request->sub_order_id));
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }

    /**
     * send proposale
     * @bodyParam user_id  int required id cua user. Example: 20.
     * @bodyParam sub_order_id int id cua sub order neu laf partner. Example: 20.
     */
    public function send_proposale_all(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            $order = Order::with(['proposale', 'sub_order.order_for_partner'])->find($request->order_id);
            if (!$order->proposale) {
                return AdapterHelper::sendResponse(false, 'Chưa có báo giá phù hợp', 404, 'Chưa có báo giá phù hợp');
            }
            $data = Proposale::with([
                'order', 'order.company',
                'order.type_menu', 'order.style_menu',
                'order.menu', 'order.assign_pito_admin',
                'order.order_for_customer.service',
                'order.sub_order.order_for_partner.service', 'order.assign_pito_admin', 'order.type_party',
                'proposale_for_customer',
                'order.sub_order.buffet_price.buffet',
                'proposale_for_customer.customer', 'proposale_for_customer.detail', 'proposale_for_customer.detail.child',
                'proposale_for_partner', 'proposale_for_partner.partner', 'proposale_for_partner.detail', 'proposale_for_partner.detail.child',
                'proposale_for_partner.sub_order', 'proposale_for_partner.sub_order.order_detail_customize'
            ])->find($order->proposale->id);
            $data->status = 1;
            $data->save();
            if (!$data)
                return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
            // Mail::to($user->email)->send(new SendProposale($data, $user->type_role, $user, $request->sub_order_id));

            $time_start = (int) ($data->order->start_time / 60);
            $hour = (int) ($time_start / 60);
            $minute = (int) $time_start % 60;
            if ($hour < 10) {
                $hour = "0" . $hour;
            }
            if ($minute < 10) {
                $minute = "0" . $minute;
            }
            $date_time = $hour . ":" . $minute . " " . date('d/m/Y', strtotime($data->order->date_start));
            // gửi báo giá cho khách 
            $proposale_for_customer = ProposaleForCustomer::find($data->proposale_for_customer->id);
            $proposale_for_customer->status = 1;
            $proposale_for_customer->save();
            $customer = $data->proposale_for_customer->customer;
            $request_data_token_confirm = [
                'status' => 2,
                'proposale_id' => $data->id,
                'type_role' => 'CUSTOMER',
                'user_id' => $customer->id
            ];
            $request_token_cofirm = new RequestToken("CUSTOMER.CONFIRM", $request_data_token_confirm);
            $token_confirm = $request_token_cofirm->createToken();
            $request_data_token_edit = [
                'status' => 12,
                'order_id' => $data->order->id,
                'proposale_id' => $data->id,
                'type_role' => 'CUSTOMER',
                'user_id' => $customer->id
            ];
            $request_token_edit = new RequestToken("CUSTOMER.REQUEST_CHANGE", $request_data_token_edit);
            $token_edit = $request_token_edit->createToken();
            $tokens = [
                'token_confirm' => $token_confirm,
                'token_edit' => $token_edit,
            ];
            $assign_pito_admin = $data->order->assign_pito_admin;
            MultiMail::to($customer->email)
                ->from('order@pito.vn')
                ->send(new ProposaleCustomer($tokens, $data->order));
            MultiMail::to($assign_pito_admin->email)
                ->from('order@pito.vn')
                ->send(new ProposaleCustomer($tokens, $data->order));
            // MultiMail::to('quyproi51vn@gmail.com')
            //     ->from('order@pito.vn')
            //     ->send(new ProposaleCustomer($tokens, $data->order));
            // end gửi báo giá cho khách\

            // gửi báo giá cho partner
            $sub_orders = $order->sub_order;
            foreach ($sub_orders as $sub_order) {
                $orderForPartner =  $sub_order->order_for_partner;
                $proposale_for_partner = ProposaleForPartner::with([
                    'partner',
                    'proposale.order.company',
                    'proposale.order.assign_pito_admin',
                    'proposale.order.order_for_customer',
                    'proposale.order.order_for_customer.customer',
                    'sub_order.order_for_partner.service',
                    'sub_order.order_for_partner.service_none',
                    'sub_order.order_for_partner.service_default',
                    'sub_order.order_for_partner.service_transport',
                    'sub_order.order_for_partner.detail',
                ])->where('sub_order_id', $sub_order->id)
                    ->where('proposale_id', $data->id)->first();
                $proposale_for_partner->status = 1;
                $proposale_for_partner->save();

                MultiMail::to($proposale_for_partner->partner->email)
                    ->from('order@pito.vn')
                    ->send(new ProposalePartner($proposale_for_partner));
                MultiMail::to($assign_pito_admin->email)
                    ->from('order@pito.vn')
                    ->send(new ProposalePartner($proposale_for_partner));
                // MultiMail::to('quyproi51vn@gmail.com')
                //     ->from('order@pito.vn')
                //     ->send(new ProposalePartner($proposale_for_partner));
            }
            // end send proposale partner
            if ($order->status == 1)
                $order->status = 2;
            $order->save();
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }

    /**
     * Export to pdf file
     * @bodyParam type_user ''. Example: 13
     */
    public function test_pdf(Request $request, $id)
    {
        $data = Proposale::with([
            'order', 'order.order_for_customer.service', 'order.sub_order.order_for_partner.service', 'order.assign_pito_admin', 'order.type_party',
            'proposale_for_customer', 'proposale_for_customer.customer', 'proposale_for_customer.detail', 'proposale_for_customer.detail.child',
            'proposale_for_partner', 'proposale_for_partner.partner', 'proposale_for_partner.detail', 'proposale_for_partner.detail.child',
            'proposale_for_partner.sub_order', 'proposale_for_partner.sub_order.order_detail_customize'
        ])->find($id);
        // if (!$data)
        //     return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
        return view('pdf_views.proposale.detail', ['data' => $data, 'user' => $request->user]);
    }

    private function send_proposale_when_customer_confirm($request, $id)
    {
        $user = User::find($request['user_id']);
        if (!$user) {
            return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
        }

        $data = Proposale::with([
            'order', 'order.company', 'order.type_menu', 'order.style_menu', 'order.menu', 'order.order_for_customer.service',
            'order.sub_order.order_for_partner.service', 'order.assign_pito_admin', 'order.type_party',
            'proposale_for_customer',
            'order.sub_order.buffet_price.buffet',
            'proposale_for_customer.customer', 'proposale_for_customer.detail', 'proposale_for_customer.detail.child',
            'proposale_for_partner', 'proposale_for_partner.partner', 'proposale_for_partner.detail', 'proposale_for_partner.detail.child',
            'proposale_for_partner.sub_order', 'proposale_for_partner.sub_order.order_detail_customize'
        ])->find($id);

        if (!$data)
            return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
        // Mail::to($user->email)->send(new SendProposale($data, $user->type_role, $user, $request->sub_order_id));

        $time_start = (int) ($data->order->start_time / 60);
        $hour = (int) ($time_start / 60);
        $minute = (int) ($time_start % 60);
        if ($hour < 10) {
            $hour = "0" . $hour;
        }
        if ($minute < 10) {
            $minute = "0" . $minute;
        }
        $date_time = $hour . ":" . $minute . " " . date('d/m/Y', strtotime($data->order->date_start));
        if ($user->type_role == User::$const_type_role['CUSTOMER']) {
            $services = $data->order->order_for_customer->service;
            foreach ($services as $key => $value) {
                if (strpos($value->name, "Tổng giá trị tiệc") > -1)
                    $service_order['price'] = $value->price;
                if (strpos($value->name, "Phí thuận tiện") > -1)
                    $service_order['price_manage'] = $value->price;
            }
            $service_order['total_price_VAT'] = $service_order['price'] + $service_order['price'] * 0.1 + $service_order['price_manage'];
            $service_order['total_price_no_VAT'] = $service_order['price'] + $service_order['price_manage'];
        } else {
            if (!isset($request['sub_order_id']) || $request['sub_order_id'] === null)
                return;
            $orderForPartner =  OrderForPartner::where('sub_order_id', $request['sub_order_id'])->first();
            $tmp = ServiceOrder::where('service_orderable_type', OrderForPartner::class)
                ->where('service_orderable_id', $orderForPartner->id)->get();
            foreach ($tmp as $key => $value) {
                if (strpos($value->name, "Tổng giá trị tiệc") > -1)
                    $service_order['price'] = $value->price;
                if (strpos($value->name, "Phí thuận tiện") > -1)
                    $service_order['price_manage'] = $value->price;
            }
            $service_order['total_price_VAT'] = $service_order['price'] + $service_order['price'] * 0.1 - $service_order['price_manage'];
            $service_order['total_price_no_VAT'] = $service_order['price'] - $service_order['price_manage'];
        }

        $proposale = [
            'id' => $data->id,
            'date_start' => $date_time,
            'name' => $data->order->name,
            'service_order' => $service_order,
            'partner' => $data['proposale_for_partner'][0]['partner']
        ];
        $pito = $data['order']['assign_pito_admin'];
        // Mail::to($user->email)->send(new ConfirmOrder($user, $pito, $proposale));
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }

    /**
     * Create Proposale .
     * @bodyParam order_id int required. Example: 13
     */
    public function create(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }

        $order = Order::with(['order_for_customer', 'sub_order.order_for_partner'])->find($request->order_id);
        if (!$order) {
            return AdapterHelper::sendResponse(false, 'Order not found', 404, 'Order not found - id:' . $request->order_id);
        }

        DB::beginTransaction();
        try {
            $proposale_curent = $order->proposale;
            $list_proposale_partner_old = [];
            foreach ($order->sub_order as $key => $value) {
                if ($value->proposale_for_partner)
                    $list_proposale_partner_old[$value->id] = $value->proposale_for_partner;
            }
            $order->proposale_all()->update(['status' => 3]);
            $proposale = new Proposale();
            $proposale->order_id  = $request->order_id;
            $proposale->status = 0;
            $proposale->save();
            $order_for_customer = $order->order_for_customer;
            // for customer
            $proposale_for_customer = new ProposaleForCustomer();
            $proposale_for_customer->proposale_id = $proposale->id;
            $proposale_for_customer->customer_id = $order_for_customer->customer_id;
            $proposale_for_customer->price = $order_for_customer->price;
            $proposale_for_customer->status = 0;
            $proposale_for_customer->save();
            if ($proposale_curent) {
                $proposale_for_customer_old = $proposale_curent->proposale_for_customer;
                HistoryRevenueForCustomer::where('proposale_id', $proposale_for_customer_old->id)
                    ->update(['proposale_id' => $proposale_for_customer->id]);
            }
            // for partner
            foreach ($order->sub_order as $key => $sub_order) {
                $proposale_for_partner = new ProposaleForPartner();
                $proposale_for_partner->proposale_id = $proposale->id;
                $proposale_for_partner->partner_id = $sub_order->order_for_partner->partner_id;
                $proposale_for_partner->price = $sub_order->order_for_partner->price;
                $proposale_for_partner->sub_order_id = $sub_order->id;
                $proposale_for_partner->status = 0;
                $proposale_for_partner->save();
                if (count($list_proposale_partner_old) > 0) {
                    $proposale_partner_old = $list_proposale_partner_old[$sub_order->id];
                    HistoryRevenueForPartner::where('proposale_id', $proposale_partner_old->id)
                        ->update(['proposale_id' => $proposale_for_partner->id]);
                }
            }
            $data = Proposale::with([
                'order',
                'order.order_for_customer.service',
                'order.order_for_customer.service_none',
                'order.order_for_customer.service_default',
                'order.order_for_customer.service_transport',
                'order.sub_order.order_for_partner.service',
                'order.sub_order.order_for_partner.service_none',
                'order.sub_order.order_for_partner.service_default',
                'order.sub_order.order_for_partner.service_transport',
                'order.type_party',
                'proposale_for_customer', 'proposale_for_customer.customer', 'proposale_for_customer.detail', 'proposale_for_customer.detail.child',
                'proposale_for_partner', 'proposale_for_partner.partner', 'proposale_for_partner.detail', 'proposale_for_partner.detail.child',
                'proposale_for_partner.sub_order', 'proposale_for_partner.sub_order.order_detail_customize'
            ])->find($proposale->id);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }

    public function change_status_customer($request)
    {
        try {
            //code...
            $proposaleForCustomer = ProposaleForCustomer::where('proposale_id', $request->proposale_id)
                ->where('customer_id', $request->user_id)
                ->first();
            if (!$proposaleForCustomer) {
                return AdapterHelper::sendResponse(false, 'Proposale for customer not found', 404, 'Proposale for customer not found');
            }
            $proposale = Proposale::find($request->proposale_id);
            if (!$proposale) {
                return AdapterHelper::sendResponse(false, 'Proposale  not found', 404, 'Proposale not found');
            }
            // huy
            if ($request->status == 3) {
                if (!$request->reason)
                    return AdapterHelper::sendResponse(false, 'Validation Error', 404, 'Xin hãy điền lý do nếu huỷ.');
                $proposaleForCustomer->status = 3;
                $proposaleForCustomer->reason = $request->reason;
                $proposaleForCustomer->save();

                $proposale->status = 3;
                $proposale->reason = $request->reason;
                $proposale->save();

                $order = Order::find($proposale->order_id);
                $new_notifi = new NotificationSystem();
                $new_notifi->content = 'Order PT' . $order->id . ' báo giá của khách hàng ' . User::find($request->user_id)->name . ' thay đổi trạng thái ' . ProposaleForCustomer::$const_status[$request->status];
                $new_notifi->tag = NotificationSystem::EVENT_TYPE["PROPOSAL.CUSTOMER.CHANGE"];
                $new_notifi->type = Order::class;
                $new_notifi->type_id = $order->id;
                $new_notifi->save();
                $data = [
                    'order_id' => $order->id,
                    'tag' => NotificationSystem::EVENT_TYPE["PROPOSAL.CUSTOMER.CHANGE"]
                ];
                $list_user = [
                    'role' => 'PITO_ADMIN'
                ];
                Notification::notifi_more($data, 'Order PT' . $order->id . ' báo giá của khách hàng ' . User::find($request->user_id)->name . ' thay đổi trạng thái ' . ProposaleForCustomer::$const_status[$request->status], $list_user, []);
            }
            // chua gui
            if ($request->status == 0) {
                $proposaleForCustomer->status = 0;
                $proposaleForCustomer->save();

                // kiem tra xem trang thai tong cua bao gia co huy hay ko
                $check_for_partner_cancel = Proposale::where('id', $request->proposale_id)
                    ->where('status', 3)->exists();
                if (!$check_for_partner_cancel)
                    $proposale->status = 0;
                $proposale->save();
            }

            // da gui
            if ($request->status == 1) {
                $proposaleForCustomer->status = 1;
                $proposaleForCustomer->save();

                // kiem tra xem da gui bao gia cho partner chua

                $check_for_partner_not_send = ProposaleForPartner::where('proposale_id', $request->proposale_id)
                    ->where('status', 0)->exists();
                // kiem tra xem trang thai tong cua bao gia co huy hay ko
                $check_for_partner_cancel = ProposaleForPartner::where('proposale_id', $request->proposale_id)
                    ->where('status', 3)->exists();
                if (!$check_for_partner_not_send && !$check_for_partner_cancel) {
                    $proposale->status = 1;
                    $proposale->save();
                }
            }
            // da xac nhan
            if ($request->status == 2) {
                $proposaleForCustomer->status = 2;
                $proposaleForCustomer->save();

                // kiem tra xem da gui bao gia cho partner chua
                $check_for_partner_not_confirm = ProposaleForPartner::where('proposale_id', $request->proposale_id)
                    ->where('status', '<>', 2)->exists();
                // kiem tra xem trang thai  bao gia cua co huy hay ko
                $check_for_partner_cancel = ProposaleForPartner::where('proposale_id', $request->proposale_id)
                    ->where('status', 3)->exists();
                // if (!$check_for_partner_not_confirm && !$check_for_partner_cancel) {
                $proposale->status = 2;
                $proposale->save();
                // }

                $order = Order::with([
                    'assign_pito_admin', 'pito_admin', 'company',
                    'type_party', 'type_menu', 'style_menu', 'menu', 'setting_group_menu',
                    'sub_order',
                    'sub_order.buffet_price.buffet',
                    'sub_order.order_for_partner',
                    'sub_order.order_for_partner.service',
                    'sub_order.order_for_partner.detail',
                    'sub_order.order_for_partner.detail.child',
                    'sub_order.order_for_partner.partner',
                    'sub_order.order_detail_customize',
                    'sub_order.proposale_for_partner',
                    'sub_order.proposale_for_partner.detail',
                    'sub_order.proposale_for_partner.detail.child',
                    'proposale',
                    'proposale.proposale_for_customer',
                    'proposale.proposale_for_customer.detail',
                    'proposale.proposale_for_customer.detail.child',
                    'order_for_customer',
                    'order_for_customer.detail',
                    'order_for_customer.detail.child',
                    'order_for_customer.customer',
                    'order_for_customer.service'
                ])->find($proposale->order_id);

                if (in_array($order->status, [3, 4, 5, 6, 7, 8, 9, 10, 11])) {
                    return;
                }

                $new_notifi = new NotificationSystem();
                $new_notifi->content = 'Order PT' . $order->id . ' báo giá của khách hàng ' . User::find($request->user_id)->name . ' thay đổi trạng thái ' . ProposaleForCustomer::$const_status[$request->status];
                $new_notifi->tag = NotificationSystem::EVENT_TYPE["PROPOSAL.CUSTOMER.CHANGE"];
                $new_notifi->type = Order::class;
                $new_notifi->type_id = $order->id;
                $new_notifi->save();
                $data = [
                    'order_id' => $order->id,
                    'tag' => NotificationSystem::EVENT_TYPE["PROPOSAL.CUSTOMER.CHANGE"]
                ];
                $list_user = [
                    'role' => 'PITO_ADMIN'
                ];

                $pito_admin = $order->assign_pito_admin()->first();
                $customer = $order->order_for_customer()->first()->customer()->first();
                $sub_order = $order->sub_order()->get();
                $sub_order = $sub_order->map->id->all();
                $order_partner = OrderForPartner::with('partner')->whereIn('sub_order_id', $sub_order)->get();
                // $this->send_proposale_when_customer_confirm(['user_id' => $customer->id], $request->proposale_id);
                // $this->send_proposale_when_customer_confirm(['user_id' => $pito_admin->id], $request->proposale_id);
                foreach ($order_partner as $value) {
                    if ($value) {
                        $partner = $value->partner;
                        $data_send = [
                            'sub_order_id' => $value->sub_order_id,
                            'user_id' => $partner->id
                        ];
                        // $this->send_proposale_when_customer_confirm($data_send, $order->proposale->id);
                    }
                }
                // Notification::notifi_more($data, 'Order PT' . $order->id . ' báo giá của khách hàng ' . User::find($request->user_id)->name . ' thay đổi trạng thái ' . ProposaleForCustomer::$const_status[$request->status], $list_user, []);
                // $this->send_proposale(,$request->proposale_id);
                $order->status = 3;
                $order->save();
                $order->notify(new NotificationOrderToSlack(NotificationSystem::EVENT_TYPE['PROPOSAL.CUSTOMER.ACCEPT']));

                // send mail when customer confirmed
                $assign_pito = $order->assign_pito_admin;
                $customer = $order->order_for_customer->customer;
                Log::stack(['cronjob'])->info('-------------send out mail user confirm-' . date('y-m-d H:i:s') . '-------------');
                $request_data_token_payment = [
                    'proposale_id' => $proposale->proposale_for_customer->id,
                    'type_role' => 'CUSTOMER',
                    'user_id' => $customer->id
                ];
                $request_token_payment = new RequestToken("CUSTOMER.PAYMENT", $request_data_token_payment);
                $token_payment = $request_token_payment->createToken();
                MultiMail::from('order@pito.vn')
                    ->to($customer->email)
                    ->send(new CustomerConfirmed($order, $customer, $token_payment));
                MultiMail::from('order@pito.vn')
                    ->to($assign_pito->email)
                    ->send(new CustomerConfirmed($order, $customer, $token_payment));

                // MultiMail::from('order@pito.vn')
                //     ->send(new CustomerConfirmed($order, $customer));

                foreach ($order->sub_order as $key => $sub_order) {
                    $partner = $sub_order->order_for_partner->partner;
                    $proposale_for_partner = ProposaleForPartner::with([
                        'partner',
                        'proposale.order.company',
                        'proposale.order.assign_pito_admin',
                        'proposale.order.order_for_customer',
                        'proposale.order.order_for_customer.customer',
                        'sub_order.order_for_partner.service',
                        'sub_order.order_for_partner.service_none',
                        'sub_order.order_for_partner.service_default',
                        'sub_order.order_for_partner.service_transport',
                        'sub_order.order_for_partner.detail',
                    ])->where('sub_order_id', $sub_order->id)
                        ->where('proposale_id', $proposale->id)->first();
                    MultiMail::from('order@pito.vn')
                        ->to($partner->email)
                        ->send(new PartnerWhenCustomerConfirmed($proposale_for_partner, $partner));
                    // MultiMail::from('order@pito.vn')
                    //     ->to('quyproi51vn@gmail.com')
                    //     ->send(new PartnerWhenCustomerConfirmed($proposale_for_partner, $partner));
                }
            }
        } catch (\Throwable $th) {
            throw $th;
        }
        return;
    }
    public function change_status_partner($request)
    {
        try {
            //code...
            if (!$request->sub_order_id)
                return AdapterHelper::sendResponse(false, 'Validation Error', 404, 'sub order id is required for type role is PARTNER.');
            $proposaleForPartner = ProposaleForPartner::where('proposale_id', $request->proposale_id)
                ->where('sub_order_id', $request->sub_order_id)
                ->where('partner_id', $request->user_id)
                ->first();
            if (!$proposaleForPartner) {
                return AdapterHelper::sendResponse(false, 'Proposale for partner not found', 404, 'Proposale for partner not found');
            }
            $proposale = Proposale::find($request->proposale_id);
            if (!$proposale) {
                return AdapterHelper::sendResponse(false, 'Proposale  not found', 404, 'Proposale not found');
            }
            // huy
            if ($request->status == 3) {
                if (!$request->reason)
                    return AdapterHelper::sendResponse(false, 'Validation Error', 404, 'Xin hãy điền lý do nếu huỷ.');
                $proposaleForPartner->status = 3;
                $proposaleForPartner->reason = $request->reason;
                $proposaleForPartner->save();

                $proposale->status = 3;
                $proposale->reason = $request->reason;
                $proposale->save();

                $order = Order::find($proposale->order_id);
                $new_notifi = new NotificationSystem();
                $new_notifi->content = 'Order PT' . $order->id . ' báo giá của đối tác ' . User::find($request->user_id)->name . ' thay đổi trạng thái ' . ProposaleForCustomer::$const_status[$request->status];
                $new_notifi->tag = NotificationSystem::EVENT_TYPE["PROPOSAL.PARTNER.CHANGE"];
                $new_notifi->type = Order::class;
                $new_notifi->type_id = $order->id;
                $new_notifi->save();
                $data = [
                    'order_id' => $order->id,
                    'tag' => NotificationSystem::EVENT_TYPE["PROPOSAL.PARTNER.CHANGE"]
                ];
                $list_user = [
                    'role' => 'PITO_ADMIN'
                ];
                Notification::notifi_more($data, 'Order PT' . $order->id . ' báo giá của đối tác ' . User::find($request->user_id)->name . ' thay đổi trạng thái ' . ProposaleForCustomer::$const_status[$request->status], $list_user, []);
            }
            // chua gui
            if ($request->status == 0) {
                $proposaleForPartner->status = 0;
                $proposaleForPartner->save();

                // kiem tra xem trang thai tong cua bao gia co huy hay ko
                $check_for_customer_cancel = ProposaleForCustomer::where('proposale_id', $request->proposale_id);
                $check_for_partner_cancel = ProposaleForPartner::where('proposale_id', $request->proposale_id)
                    ->where('status', 3)->exists();
                if (!$check_for_customer_cancel && !!check_for_partner_cancel)
                    $proposale->status = 0;
                $proposale->save();
            }

            // da gui
            if ($request->status == 1) {
                $proposaleForPartner->status = 1;
                $proposaleForPartner->save();

                // kiem tra xem da gui bao gia cho partner chua
                $check_for_partner_not_send = ProposaleForPartner::where('ProposaleForPartner', $request->proposale_id)
                    ->where('status', '<>', 1)->exists();
                $check_for_customer_send = ProposaleForCustomer::where('proposale_id', $request->proposale_id)
                    ->where('status', 2)->exists();
                // kiem tra xem trang thai tong cua bao gia co huy hay ko
                $check_for_partner_cancel = ProposaleForPartner::where('ProposaleForPartner', $request->proposale_id)
                    ->where('status', 3)->exists();
                if (!$check_for_partner_not_send && !$check_for_partner_cancel && $check_for_customer_send) {
                    $proposale->status = 1;
                    $proposale->save();
                }
            }
            // da xac nhan
            if ($request->status == 2) {
                $proposaleForPartner->status = 2;
                $proposaleForPartner->save();

                // kiem tra xem da gui bao gia cho partner chua
                $check_for_partner_not_confirm = ProposaleForPartner::where('proposale_id', $request->proposale_id)
                    ->where('status', '<>', 2)->exists();
                $check_for_customer_confirm = ProposaleForCustomer::where('proposale_id', $request->proposale_id)
                    ->where('status', 2)->exists();
                // kiem tra xem trang thai tong cua bao gia co huy hay ko
                $check_for_partner_cancel = ProposaleForPartner::where('proposale_id', $request->proposale_id)
                    ->where('status', 3)->exists();
                if (!$check_for_partner_not_confirm && !$check_for_partner_cancel && $check_for_customer_confirm) {
                    $proposale->status = 2;
                    $proposale->save();
                }

                $partner = User::find($request->user_id);

                $order = Order::with(['sub_order.order_for_partner.partner'])->find($proposale->order_id);
                $new_notifi = new NotificationSystem();
                $new_notifi->content = 'Order PT' . $order->id . ' báo giá của đối tác ' . $partner->name . ' thay đổi trạng thái ' . ProposaleForCustomer::$const_status[$request->status];
                $new_notifi->tag = NotificationSystem::EVENT_TYPE["PROPOSAL.PARTNER.CHANGE"];
                $new_notifi->type = Order::class;
                $new_notifi->type_id = $order->id;
                $new_notifi->save();
                $data = [
                    'order_id' => $order->id,
                    'tag' => NotificationSystem::EVENT_TYPE["PROPOSAL.PARTNER.CHANGE"]
                ];
                $list_user = [
                    'role' => 'PITO_ADMIN'
                ];
                Notification::notifi_more($data, 'Order PT' . $order->id . ' báo giá của đối tác ' . $partner->name . ' thay đổi trạng thái ' . ProposaleForCustomer::$const_status[$request->status], $list_user, []);
                // send SMS
                $pito_admin = $order->assign_pito_admin()->first();
                $customer = $order->order_for_customer()->first()->customer()->first();
                // $this->send_proposale_when_customer_confirm(['user_id' => $customer->id], $request->proposale_id);
                $this->send_proposale_when_customer_confirm(['user_id' => $pito_admin->id], $request->proposale_id);

                $data_send = [
                    'sub_order_id' => $request->sub_order_id,
                    'user_id' => $partner->id
                ];
                $this->send_proposale_when_customer_confirm($data_send, $order->proposale->id);
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }
    /**
     * Change status Proposale .
     * @bodyParam status int required 0-chua gui, 1-da gui, 2-da xac nha, 3-huy. Example: 3
     * @bodyParam proposale_id int required id cua bao gia tong . Example: 3
     * @bodyParam type_role string required PARTNER hoac CUSTOMER. Example: PARTNER
     * @bodyParam user_id int required id cua user co the cua PARTNER hoac la CUSTOMER. Example: PARTNER
     * @bodyParam sub_order_id int required id cua sub order neu type_role = PARTNER. Example: PARTNER
     * @bodyParam reason string ly do neu huy. Example: PARTNER
     */
    public function change_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
            'proposale_id' => 'required',
            // 'type_role' => 'required',
            // 'user_id' => 'required'
        ]);
        // xoas sbao gia
        if ($request->status == 4) {
            $proposale = Proposale::find($request->proposale_id);
            $proposale->status = 4;
            $proposale->save();
            return AdapterHelper::sendResponse(true, 'success', 200, 'success');
        }
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        if ($request->type_role == User::$const_type_role['CUSTOMER']) {
            $this->change_status_customer($request);
        } else {
            $this->change_status_partner($request);
        }

        // update status cho order
        $proposale = Proposale::find($request->proposale_id);
        if ($proposale->status == 2) {
            $order = Order::find($proposale->order_id);
            $order->status = 3;
            $order->save();
        }
        if ($request->_blank) {
            return view('alert.confirm-proposale');
        }
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }
}
