<?php

namespace App\Http\Controllers\API\Order;

use App\Model\User;
use App\Mail\CancelOrder;
use App\Model\Order\Order;
use Illuminate\Support\Str;
use App\Traits\Notification;
use Illuminate\Http\Request;
use App\Model\Order\SubOrder;
use App\Traits\AdapterHelper;
use Illuminate\Support\Carbon;
use App\Mail\SendProposaleEdit;
use App\Model\Order\DetailOrder;
use App\Model\Order\ServiceOrder;
use App\Model\Proposale\Proposale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Model\Order\OrderForPartner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Model\Order\OrderForCustomer;
use Illuminate\Support\Facades\Crypt;
use App\Model\TicketAndReview\TicketEnd;
use Illuminate\Support\Facades\Validator;
use App\Model\Setting\ServiceOrderDefault;
use App\Model\TicketAndReview\TicketStart;
use App\Model\Proposale\ProposaleForPartner;
use App\Model\Proposale\ProposaleForCustomer;
use App\Model\Notification\NotificationSystem;
use App\Notifications\NotificationOrderToSlack;
use Illuminate\Contracts\Encryption\DecryptException;
use App\Model\HistoryRevenue\HistoryRevenueForPartner;
use App\Repositories\Contracts\HistoryDriverInterface;
use IWasHereFirst2\LaravelMultiMail\Facades\MultiMail;
use App\Http\Controllers\API\Order\ProposaleController;
use App\Model\HistoryRevenue\HistoryRevenueForCustomer;
use App\Model\Order\OrderCustomize\OrderDetailCustomize;
use App\Http\Controllers\API\TicketAndReview\SetDataReview;
use App\Mail\Version2\CustomerConfirmOrder\CustomerConfirmed;
use App\Mail\Version2\CustomerConfirmOrder\PartnerWhenCustomerConfirmed;
use App\Model\GenerateToken\RequestToken;
use App\Model\PointAndVoucher\VoucherForOrder;

/**
 * @group Order
 *
 * APIs for Order
 */
class OrderController extends Controller
{

    protected $history_driver;

    public function __construct(HistoryDriverInterface $driver = null)
    {
        $this->history_driver = $driver;
    }

    /**
     * Get List order.
     * @bodyParam name string Tên của order .Example: Bữa tiệc công ty.
     * @bodyParam date_start date . Example: 2020-02-03
     * @bodyParam start_time int  tổng giờ phút đổi sang giây.Example: 54000
     * @bodyParam end_time int  tổng giờ phút đổi sang giây. Example: 61200
     * @bodyParam type_party_id int id của loại tiệc. Example: 1
     * @bodyParam status int trạng thái của order . Example: 0
     * @bodyParam id int id của order. Example: 8
     */
    public function index(Request $request)
    {
        $query = Order::with([
            'order_for_customer.customer' => function ($q) {
                $q->select(['id', 'name', 'email', 'phone', 'type_role']);
            },
            'assign_pito_admin' => function ($q) {
                $q->select(['id', 'name', 'email', 'phone', 'type_role']);
            }, 'company', 'setting_group_menu'
        ])->select('*');
        $data_request = $request->all();
        unset($data_request['page']);
        unset($data_request['per_page']);
        if ($request->start_time && $request->end_time) {
            $query = $query->where('start_time', '>=', $request->start_time)
                ->where('end_time', '<=', $request->end_time);
        }
        unset($data_request['start_time']);
        unset($data_request['end_time']);
        foreach ($data_request as $key => $value) {
            if ($key == 'date_confirm_arrived') {
                if ($value === 'true' || $value === true || $value === 1) {
                    $query = $query->whereHas('sub_order.ticket_start', function ($q) {
                        $q->where('date_confirm_arrived', '<>', null);
                    });
                } else {
                    $query = $query->whereHas('sub_order.ticket_start', function ($q) {
                        $q->where('date_confirm_arrived', null);
                    });
                }
            } else {
                if ($key == 'date_start' || $key == 'status') {
                    $query = $query->where($key, $value);
                } else {
                    $query = $query->where($key, 'LIKE', '%' . $value . '%');
                }
            }
        }
        $data = $query->orderBy('id', 'desc')->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
    }

    /**
     * Get order by id.
     *
     */
    public function show(Request $request, $id)
    {
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
            'company',
            'voucher'
        ])->find($id);
        if (!$data)
            return AdapterHelper::sendResponse(false, 'Error not found', 404, 'Error not found');
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }

    public function calculation_order_tmp(Request $request)
    {
        $total_price_for_customer = 0;
        $service_json = json_decode($request->service);
        $service_transport_json = json_decode($request->service_transport);
        $sub_order_list = json_decode($request->sub_order);
        $transport_price_map = [];
        if ($service_json === null) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'json service fail');
        }
        if (!$sub_order_list) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'json sub order fail');
        } else {
            foreach ($sub_order_list as $key => $value) {
                if (
                    !isset($value->partner_id)
                    || !isset($value->total_price)
                    || !isset($value->menu)
                ) {
                    return AdapterHelper::sendResponse(false, 'Validator error', 400, 'json sub order fail');
                }

                if (!User::find($value->partner_id)) {
                    return AdapterHelper::sendResponse(false, 'User not found', 404, 'User not found');
                }
                $total_price_for_customer += (int) $value->total_price;
            }
        }
        $price = 0;
        $data_service = [];
        $data_service_none = [];
        $data_service_default = [];
        $data_service_transport = [];
        // tao service cho order
        $service = $service_json;
        foreach ($service as $key => $value) {
            $partner = null;
            if ($value->partner_id == "PITO")
                $partner = [
                    "id" => "PITO",
                    "name" => "PITO"
                ];
            else
                $partner = User::select(['id', 'name', 'email'])->find($value->partner_id);

            $data_service[] = [
                'name' => $value->name,
                'amount' => $value->amount,
                'price' => $value->price,
                'title' => null,
                'DVT' => $value->DVT,
                'note' => $value->note ? $value->note : "",
            ];

            if ($value->price * $value->amount > 0) {
                $data_service_none[] = [
                    'name' => $value->name,
                    'amount' => $value->amount,
                    'price' => $value->price,
                    'title' => null,
                    'DVT' => $value->DVT,
                    'category_id' => $value->category_id,
                    'note' => $value->note ? $value->note : "",
                    'partner' => $partner,
                    'partner_id' => $value->partner_id
                ];
            }
            $tmp = $value->price;
            $tmp = \str_replace('.', '', $tmp);
            $tmp = \str_replace(' ', '', $tmp);
            $tmp = \str_replace(',', '', $tmp);
            $tmp_price = (int) $tmp;
            $tmp_amount = (int) $value->amount;
            $price += $tmp_price * $tmp_amount;
        }

        $service_transport = $service_transport_json;

        foreach ($service_transport as $key => $value) {
            $data_service_transport[] = [
                'name' => $value->name,
                'amount' => $value->amount,
                'price' => $value->price,
                'title' => '_transport_',
                'DVT' => $value->DVT,
                'note' => $value->note,
                'partner_id' => $value->partner_id,
                'partner' => User::select(['id', 'name', 'email'])->find($value->partner_id)
            ];
            $data_service[] = [
                'name' => $value->name,
                'amount' => $value->amount,
                'price' => $value->price,
                'title' => '_transport_',
                'DVT' => $value->DVT,
                'note' => $value->note,
            ];
            $tmp = $value->price;
            $tmp = \str_replace('.', '', $tmp);
            $tmp = \str_replace(' ', '', $tmp);
            $tmp = \str_replace(',', '', $tmp);
            $tmp_price = (int) $tmp;
            $tmp_amount = (int) $value->amount;
            $transport_price_map[$value->partner_id] = $tmp_price * $tmp_amount;
        }

        $data_menu = [];
        $total_price_transport = 0;
        foreach ($sub_order_list as $key => $sub_order_item) {
            $menu = $sub_order_item->menu;
            if (count($menu) > 0) {
                foreach ($menu as $menu_item) {
                    // tạo detail cho order partner
                    $tmp_data_menu = [];
                    $tmp_data_menu['amount'] = $menu_item->amount;
                    $tmp_data_menu['type_more'] = DetailOrder::TYPE_MORE['BUFFET'];
                    $tmp_data_menu['name'] = $menu_item->name;
                    $tmp_data_menu['id_more'] = $menu_item->id;
                    $tmp_data_menu['order_sort'] = isset($menu_item->order_sort) ? $menu_item->order_sort : 1;
                    $tmp_data_menu['detail_orderable_type'] = OrderForPartner::class;
                    $tmp_data_menu['detail'] = [
                        'name' => $menu_item->buffet->title,
                        'price' => $menu_item->price,
                        'unit' => $menu_item->unit,
                        'is_select_category' => $menu_item->buffet->is_select_category,
                        'partner_id' => $menu_item->buffet->partner_id,
                        'partner' => User::find($menu_item->buffet->partner_id)
                    ];
                    $tmp_data_menu['child'] = [];
                    foreach ($menu_item->menu_buffect as $dish) {
                        // tạo detail cho order partner
                        $child_menu =
                            [
                                'name' => $dish->name,
                                'amount' => $dish->amount,
                            ];
                        if ($menu_item->buffet->is_select_category) {
                            foreach ($dish->child as $child) {
                                // tạo detail cho order partner
                                if (isset($child->check) && $child->check) {
                                    $child_menu['child'][] = [
                                        'name' => $child->name,
                                        'amount' => $child->amount,
                                    ];
                                }
                            }
                        }
                        $tmp_data_menu['child'][] = $child_menu;
                    }
                    $data_menu[] = $tmp_data_menu;
                    $price += (int) $menu_item->price * (int) $menu_item->amount;
                }
            } else {
                $foods = $sub_order_item->food;
                foreach ($foods as $menu_item) {

                    // tạo detail cho order partner
                    $tmp_data_menu = [];
                    $tmp_data_menu['amount'] = $menu_item->amount;
                    $tmp_data_menu['type_more'] = DetailOrder::TYPE_MORE['FOOD'];
                    $tmp_data_menu['name'] = $menu_item->name;
                    $tmp_data_menu['id_more'] = $menu_item->id;
                    $tmp_data_menu['order_sort'] = isset($menu_item->order_sort) ? $menu_item->order_sort : 1;
                    $tmp_data_menu['detail_orderable_type'] = OrderForPartner::class;
                    $tmp_data_menu['detail'] = [
                        'name' => $menu_item->food->name,
                        'price' => $menu_item->food->price,
                        'unit' => $menu_item->food->DVT,
                        'is_select_category' => 0,
                        'partner_id' => $menu_item->food->partner_id,
                        'partner' => User::find($menu_item->food->partner_id)
                    ];

                    $data_menu[] = $tmp_data_menu;


                    $price += (int) $menu_item->food->price * (int) $menu_item->amount;
                }
            }


            $partner_id = $sub_order_item->partner_id;

            if (isset($transport_price_map[$partner_id]))
                $total_price_transport += $transport_price_map[$partner_id];
        }



        $data_service[] = [
            'name' => 'Phí Vận Chuyển',
            'price' => $total_price_transport,
            'title' => '_default_',
            'DVT' => null,
            'amount' => null,
            'note' => null,
        ];
        $data_service_default[] = [
            'name' => 'Phí Vận Chuyển',
            'price' => $total_price_transport,
            'title' => '_default_',
            'DVT' => null,
            'amount' => null,
            'note' => null,
        ];

        $tong_gia_tri_tiec = $price + $total_price_transport;

        $data_service[] = [
            'name' => 'Tổng Giá Trị Tiệc',
            'price' => $tong_gia_tri_tiec,
            'title' => '_default_',
            'DVT' => null,
            'amount' => null,
            'note' => null,
        ];

        $data_service_default[] = [
            'name' => 'Tổng Giá Trị Tiệc',
            'price' => $tong_gia_tri_tiec,
            'title' => '_default_',
            'DVT' => null,
            'amount' => null,
            'note' => null,
        ];

        // Phí thuận tiện
        $phi_quan_ly = $tong_gia_tri_tiec * $request->percent_manage_customer / 100;
        $data_service[] = [
            'name' => 'Phí Thuận Tiện (' . $request->percent_manage_customer . '%)',
            'price' => $phi_quan_ly,
            'title' => '_default_',
            'DVT' => null,
            'amount' => null,
            'note' => null,
        ];
        $data_service_default[] = [
            'name' => 'Phí Thuận Tiện (' . $request->percent_manage_customer . '%)',
            'price' => $phi_quan_ly,
            'title' => '_default_',
            'DVT' => null,
            'amount' => null,
            'note' => null,
        ];

        // Phí ưu đãi
        $uu_dai = 0;
        $data_service[] = [
            'name' => 'Ưu Đãi',
            'price' => $uu_dai,
            'title' => '_default_',
            'DVT' => null,
            'amount' => null,
            'note' => null,
        ];
        $data_service_default[] = [
            'name' => 'Ưu Đãi',
            'price' => $uu_dai,
            'title' => '_default_',
            'DVT' => null,
            'amount' => null,
            'note' => null,
        ];
        $tong_cong_chua_VAT = $tong_gia_tri_tiec + $phi_quan_ly - $uu_dai;

        $data_service[] = [
            'name' => 'Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và chưa bao gồm VAT)',
            'price' => $tong_cong_chua_VAT,
            'title' => '_default_',
            'DVT' => null,
            'amount' => null,
            'note' => null,
        ];
        $data_service_default[] = [
            'name' => 'Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và chưa bao gồm VAT)',
            'price' => $tong_cong_chua_VAT,
            'title' => '_default_',
            'DVT' => null,
            'amount' => null,
            'note' => null,
        ];

        // VAT
        $VAT = $tong_cong_chua_VAT * 0.1;
        $data_service[] = [
            'name' => 'VAT',
            'price' => $VAT,
            'title' => '_default_',
            'DVT' => null,
            'amount' => null,
            'note' => null,
        ];
        $data_service_default[] = [
            'name' => 'VAT',
            'price' => $VAT,
            'title' => '_default_',
            'DVT' => null,
            'amount' => null,
            'note' => null,
        ];
        // VAT
        $tong_cong_VAT = $tong_cong_chua_VAT + $VAT;
        $data_service[] = [
            'name' => 'Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và  VAT)',
            'price' => $tong_cong_VAT,
            'title' => '_default_',
            'DVT' => null,
            'amount' => null,
            'note' => null,
        ];
        $data_service_default[] = [
            'name' => 'Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và  VAT)',
            'price' => $tong_cong_VAT,
            'title' => '_default_',
            'DVT' => null,
            'amount' => null,
            'note' => null,
        ];
        $data = [
            'services' => $data_service,
            'service_default' => $data_service_default,
            'service_none' => $data_service_none,
            'service_transport' => $data_service_transport,
            'menu_food' => $data_menu
        ];
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }

    private function event_create_notifi_when_create_order($order, $pito_admin)
    {
        $new_notifi = new NotificationSystem();
        $new_notifi->content = 'Order PT' . $order->id . ' đã được tạo.';
        $new_notifi->tag = NotificationSystem::EVENT_TYPE["ORDER.CREATE"];
        $new_notifi->type = Order::class;
        $new_notifi->type_id = $order->id;
        $new_notifi->save();
        $data = [
            'order_id' => $order->id,
            'tag' => NotificationSystem::EVENT_TYPE["ORDER.CREATE"]
        ];
        $list_user = [
            'role' => 'PITO_ADMIN'
        ];
        Notification::notifi_more($data, 'Order PT' . $order->id . ' đã được tạo.', $list_user, []);
        $new_notifi = new NotificationSystem();
        $new_notifi->content = 'Order PT' . $order->id . ' assign cho PITO : ' . $pito_admin->name;
        $new_notifi->tag = NotificationSystem::EVENT_TYPE["ORDER.ASSIGN"];
        $new_notifi->type = Order::class;
        $new_notifi->type_id = $order->id;
        $new_notifi->save();
        $data = [
            'order_id' => $order->id
        ];
        $list_user = [
            'role' => 'PITO_ADMIN'
        ];
        Notification::notifi_more($data, 'Order PT' . $order->id . ' assign cho PITO : ' . $pito_admin->name, $list_user, []);
    }

    private function create_service_for_order($service, $order_type, $type_role = 'CUSTOMER')
    {
        $price = 0;
        $data_service_partner = [];
        foreach ($service as $key => $value) {
            if ($value->price * $value->amount > 0) {
                if (
                    isset($value->partner_id)
                    && $value->partner_id != "PITO"
                    && $value->partner_id != ""
                ) {
                    if (!isset($data_service_partner[$value->partner_id])) {
                        $data_service_partner[$value->partner_id] = [];
                    }
                    $data_service_partner[$value->partner_id][] = $value;
                }
                $service_order = new ServiceOrder();
                $json_more = array("category_id" => $value->category_id, "partner_id" => $value->partner_id);
                $service_order->json_more = json_encode($json_more);
                $service_order->id_more = isset($value->partner_id) ? $value->partner_id : null;
                $service_order->type_more = isset($value->partner_id) ? (ServiceOrder::TYPE_MORE['PARTNER']) : null;
                $service_order->amount = $value->amount;
                $service_order->name = $value->name;
                $service_order->title = isset($value->title) ? $value->title : null;
                $service_order->DVT = $value->DVT;
                $service_order->note = $value->note;
                $service_order->price = $value->price;
                $service_order->service_orderable_type = $type_role == 'CUSTOMER' ? (OrderForCustomer::class) : (OrderForPartner::class);
                $service_order->service_orderable_id = $order_type->id;
                $service_order->save();

                $tmp = $value->price;
                $tmp = \str_replace('.', '', $tmp);
                $tmp = \str_replace(' ', '', $tmp);
                $tmp = \str_replace(',', '', $tmp);
                $tmp_price = (int) $tmp;
                $tmp_amount = (int) $value->amount;
                $price += $tmp_price * $tmp_amount;
            }
        }
        return ['data_service_partner' => $data_service_partner, 'price' => $price];
    }

    private function create_service_default($percent_manage, $order_type, $price, $price_transport, $voucher_price = 0, $type_role = 'CUSTOMER')
    {
        if ($type_role == 'CUSTOMER') {
            // phi van chuyen
            $service_order = new ServiceOrder();
            $service_order->name = 'Phí Vận Chuyển';
            $service_order->price = $price_transport;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = $type_role == 'CUSTOMER' ? (OrderForCustomer::class) : (OrderForPartner::class);
            $service_order->service_orderable_id = $order_type->id;
            $service_order->save();
        }
        // tong gia tri tiec
        $tong_gia_tri_tiec = $price + $price_transport;
        $service_order = new ServiceOrder();
        $service_order->name = 'Tổng Giá Trị Tiệc';
        $service_order->price = $tong_gia_tri_tiec;
        $service_order->title = "_default_";
        $service_order->service_orderable_type = $type_role == 'CUSTOMER' ? (OrderForCustomer::class) : (OrderForPartner::class);
        $service_order->service_orderable_id = $order_type->id;
        $service_order->save();

        // Phí thuận tiện
        $phi_quan_ly = $tong_gia_tri_tiec * $percent_manage / 100;
        $service_order = new ServiceOrder();
        $name_tmp = $type_role == 'CUSTOMER' ? ('Phí Thuận Tiện (' . $percent_manage . '%)') : ('Phí Dịch Vụ (' . $percent_manage . '%)');
        $service_order->name = $name_tmp;
        $service_order->price = $phi_quan_ly;
        $service_order->title = "_default_";
        $service_order->service_orderable_type = $type_role == 'CUSTOMER' ? (OrderForCustomer::class) : (OrderForPartner::class);
        $service_order->service_orderable_id = $order_type->id;
        $service_order->save();

        // Phí ưu đãi
        $uu_dai = 0;
        if ($type_role == "CUSTOMER") {
            if ($voucher_price) {
                $uu_dai = $voucher_price;
            }
            $service_order = new ServiceOrder();
            $service_order->name = 'Ưu Đãi';
            $service_order->price = $uu_dai;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = $type_role == 'CUSTOMER' ? (OrderForCustomer::class) : (OrderForPartner::class);
            $service_order->service_orderable_id = $order_type->id;
            $service_order->save();
        }

        // Phí Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và chưa bao gồm VAT)
        $tong_cong_chua_VAT = $type_role == 'CUSTOMER' ? ($tong_gia_tri_tiec + $phi_quan_ly - $uu_dai) : ($tong_gia_tri_tiec - $phi_quan_ly - $uu_dai);
        $service_order = new ServiceOrder();
        $name_tmp = $type_role == 'CUSTOMER' ? 'Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và chưa bao gồm VAT)' : 'Tổng Giá Trị Cần Thanh Toán (chưa bao gồm VAT)';
        $service_order->name = $name_tmp;
        $service_order->price = $tong_cong_chua_VAT;
        $service_order->title = "_default_";
        $service_order->service_orderable_type = $type_role == 'CUSTOMER' ? (OrderForCustomer::class) : (OrderForPartner::class);
        $service_order->service_orderable_id = $order_type->id;
        $service_order->save();

        // VAT
        $VAT = $tong_cong_chua_VAT * 0.1;
        $service_order = new ServiceOrder();
        $service_order->name = 'VAT';
        $service_order->price = $VAT;
        $service_order->title = "_default_";
        $service_order->service_orderable_type = $type_role == 'CUSTOMER' ? (OrderForCustomer::class) : (OrderForPartner::class);
        $service_order->service_orderable_id = $order_type->id;
        $service_order->save();

        // Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và  VAT)
        $total_VAT = $tong_cong_chua_VAT + $VAT;
        $service_order = new ServiceOrder();
        $name_tmp = $type_role == 'CUSTOMER' ? 'Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và  VAT)' : 'Tổng Giá Trị Cần Thanh Toán (đã bao gồm VAT)';
        $service_order->name = $name_tmp;
        $service_order->price = $total_VAT;
        $service_order->title = "_default_";
        $service_order->service_orderable_type = $type_role == 'CUSTOMER' ? (OrderForCustomer::class) : (OrderForPartner::class);
        $service_order->service_orderable_id = $order_type->id;
        $service_order->save();
        return ['total_VAT' => $total_VAT];
    }

    private function create_service_transport($service_transport, $order_type, $type_role = "CUSTOMER")
    {
        $total_price_transport = 0;
        $data_service_transport_partner = [];
        $transport_price_map = [];
        foreach ($service_transport as $key => $value) {
            if ($value->price * $value->amount > 0) {
                if (
                    isset($value->partner_id)
                    &&  $value->partner_id
                    && $value->partner_id != ""
                    && $value->partner_id != "PITO"
                ) {
                    if (!isset($data_service_transport_partner[$value->partner_id])) {
                        $data_service_transport_partner[$value->partner_id] = [];
                    }
                    $data_service_transport_partner[$value->partner_id][] = $value;
                }
                $service_order = new ServiceOrder();
                $json_more = array("category_id" => isset($value->category_id) ? $value->category_id : "", "partner_id" => isset($value->partner_id) ? $value->partner_id : "");
                $service_order->json_more = json_encode($json_more);
                $service_order->id_more = isset($value->partner_id) ? $value->partner_id : null;
                $service_order->type_more = isset($value->partner_id) ? (ServiceOrder::TYPE_MORE['PARTNER']) : null;
                $service_order->amount = $value->amount;
                $service_order->title = '_transport_';
                $service_order->DVT = $value->DVT;
                $service_order->note = $value->note;
                $service_order->price = $value->price;
                $service_order->service_orderable_type = $type_role == "CUSTOMER" ? (OrderForCustomer::class) : (OrderForPartner::class);
                $service_order->service_orderable_id = $order_type->id;
                $service_order->save();

                $tmp = $value->price;
                $tmp = \str_replace('.', '', $tmp);
                $tmp = \str_replace(' ', '', $tmp);
                $tmp = \str_replace(',', '', $tmp);
                $tmp_price = (int) $tmp;
                $tmp_amount = (int) $value->amount;
                $total_price_transport += $tmp_price * $tmp_amount;
                $transport_price_map[$value->partner_id] = $tmp_price * $tmp_amount;
            }
        }
        return [
            'transport_price_map' => $transport_price_map,
            'total_price_transport' => $total_price_transport,
            'data_service_transport_partner' => $data_service_transport_partner
        ];
    }

    private function create_menu_order($menu_or_food, $order_partner, $order_customer, $type_menu_or_food = 'menu')
    {
        $amount_order_for_menu = 0;
        $price = 0;
        foreach ($menu_or_food as $menu_item) {
            // tạo detail cho order partner
            $detail_order_partner_parent = new DetailOrder();
            $detail_order_partner_parent->amount = $menu_item->amount;
            $detail_order_partner_parent->type_more = DetailOrder::TYPE_MORE['BUFFET'];
            $detail_order_partner_parent->name = $menu_item->name;
            $detail_order_partner_parent->id_more = $menu_item->id;
            $detail_order_partner_parent->order_sort = isset($menu_item->order_sort) ? $menu_item->order_sort : 1;
            $detail_order_partner_parent->detail_orderable_type = OrderForPartner::class;
            $detail_order_partner_parent->detail_orderable_id = $order_partner->id;
            $detail_order_partner_parent->save();

            // tạo detail cho order customer
            $detail_order_customer_parent = new DetailOrder();
            $detail_order_customer_parent->name = $menu_item->name;
            $detail_order_customer_parent->type_more = DetailOrder::TYPE_MORE['BUFFET'];
            $detail_order_customer_parent->id_more = $menu_item->id;
            $detail_order_customer_parent->amount = $menu_item->amount;
            $detail_order_customer_parent->order_sort = isset($menu_item->order_sort) ? $menu_item->order_sort : 1;
            $detail_order_customer_parent->detail_orderable_type = OrderForCustomer::class;
            $detail_order_customer_parent->detail_orderable_id = $order_customer->id;
            $detail_order_customer_parent->save();

            $amount_order_for_menu += (int) $menu_item->amount;
            if ($type_menu_or_food == 'menu') {
                foreach ($menu_item->menu_buffect as $dish) {
                    // tạo detail cho order partner
                    $detail_order_partner = new DetailOrder();
                    $detail_order_partner->name = $dish->name;
                    $detail_order_partner->amount = $dish->amount;
                    $detail_order_partner->detail_orderable_type = OrderForPartner::class;
                    $detail_order_partner->detail_orderable_id = $order_partner->id;
                    $detail_order_partner->parent_id = $detail_order_partner_parent->id;
                    $detail_order_partner->save();
                    // tạo detail cho order customer
                    $detail_order_customer = new DetailOrder();
                    $detail_order_customer->name = $dish->name;
                    $detail_order_customer->amount = $dish->amount;
                    $detail_order_customer->detail_orderable_type = OrderForCustomer::class;
                    $detail_order_customer->detail_orderable_id = $order_customer->id;
                    $detail_order_customer->parent_id = $detail_order_customer_parent->id;
                    $detail_order_customer->save();
                    if ($menu_item->buffet->is_select_category) {
                        foreach ($dish->child as $child) {
                            // tạo detail cho order partner
                            if (isset($child->check) && $child->check) {
                                $detail_order_child = new DetailOrder();
                                $detail_order_child->name = $child->name;
                                $detail_order_child->amount = $child->amount;
                                $detail_order_child->detail_orderable_type = OrderForPartner::class;
                                $detail_order_child->detail_orderable_id = $order_partner->id;
                                $detail_order_child->parent_id = $detail_order_partner->id;
                                $detail_order_child->save();
                                // tạo detail cho order customer
                                $detail_order_child = new DetailOrder();
                                $detail_order_child->name = $child->name;
                                $detail_order_child->amount = $child->amount;
                                $detail_order_child->detail_orderable_type = OrderForCustomer::class;
                                $detail_order_child->detail_orderable_id = $order_customer->id;
                                $detail_order_child->parent_id = $detail_order_customer->id;
                                $detail_order_child->save();
                            }
                        }
                    }
                }
            }

            $price += (int) $menu_item->price * (int) $menu_item->amount;
        }
        return [
            'price' => $price,
            'amount_order_for_menu' => $amount_order_for_menu
        ];
    }

    /**
     * Create Order .
     * @bodyParam name string required .Example: Bữa tiệc công ty.
     * @bodyParam address string required .Example: 33/4/53 Đào tấn-huế
     * @bodyParam _lat string required .Example: 12.32123
     * @bodyParam _long string required .Example: 123.412
     * @bodyParam type_party_id int required Chọn loại tiệc. Example: 1
     * @bodyParam type_menu_id int required Chọn loại menu (menu co dinh, menu theo yeu cau). Example: 1
     * @bodyParam setting_style_menu_id int required Chon style menu (Món việt, món Anh.) . Example: 1
     * @bodyParam date_start date required. Example: 2020-02-03 08:56:13
     * @bodyParam start_time int required tổng giờ phút đổi sang giây.Example: 54000
     * @bodyParam end_time int required tổng giờ phút đổi sang giây. Example: 61200
     * @bodyParam clean_time int required giờ dọn dẹp tổng giờ phút đổi sang giây. Example: 61200
     * @bodyParam customer_id int required. Example: 20
     * @bodyParam assign_pito_admin_id int required assign cho 1 nhân viên pito. Example: 22
     * @bodyParam note string note cua order. Example: Setup tiệc sớm.
     * @bodyParam service string service cuar order. Example: [{"name":"Phí vận chuyển","DVT":"ban","price":"10.000","amount":20,"note":"1 gói"}]
     * @bodyParam sub_order string required json về chi tiết món ăn. Example: [{"partner_id":24,"buffet_price_id":1,"total_price":"1200000","order_detail_customize":[{"name":"so_luong_khach","value":"10","guard_name":"amount"},{"name":"gia","value":"120000","guard_name":"price"}],"menu":[{"name":"Khai vị","child":[{"name":"Gỏi sen tôm","amount":2}]}]}]
     * @bodyParam min_price int gia tri nho. Example: 100000
     * @bodyParam max_price int gia tri lon. Example: 130000
     * @bodyParam status_more string  tinh trang tiec khac. Example: cc
     * @bodyParam value_promotion string gia tri khuyen mai. Example: giam 100k
     * @bodyParam code_promotion string  ma khuyen mai. Example: 123asd
     * @bodyParam percent_manage_customer float  phi quan ly cho khach. Example: 6
     * @bodyParam percent_manage_partner float phi quan ly cho partner. Example: 20
     */
    public function create(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'address' => 'required',
            // 'name' => 'required',
            'date_start' => 'required',
            'start_time' => 'required',
            'end_time' => 'required',
            'customer_id' => 'required',
            'assign_pito_admin_id' => 'required',
            'sub_order' => 'required',
            'percent_manage_customer' => 'required',
            'percent_manage_partner' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $customer = User::find($request->customer_id);
        if (!$customer) {
            return AdapterHelper::sendResponse(false, 'User not found', 404, 'User not found');
        }
        // validate json sub order and calculation total price for customer
        $total_price_for_customer = 0;
        $service_json = json_decode($request->service);
        $service_transport_json = json_decode($request->service_transport);
        $sub_order_list = json_decode($request->sub_order);

        if ($service_json === null) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'json service fail');
        }
        if (!$sub_order_list) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'json sub order fail');
        } else {
            foreach ($sub_order_list as $key => $value) {

                if (
                    !isset($value->partner_id)
                    || !isset($value->total_price)
                    || !isset($value->menu)
                ) {
                    return AdapterHelper::sendResponse(false, 'Validator error', 400, 'json sub order fail');
                }

                if (!User::find($value->partner_id)) {
                    return AdapterHelper::sendResponse(false, 'User not found', 404, 'User not found');
                }
                $total_price_for_customer += (int) $value->total_price;
            }
        }
        $price = 0;
        $total_price_transport = 0;
        $transport_price_map = [];
        DB::beginTransaction();
        try {
            $pito_admin = User::find($request->assign_pito_admin_id);
            $data_insert_order = $request->only([
                'address', 'setting_group_menu_id',
                '_lat', '_long', 'name', 'amount', 'menu_id',
                'type_menu_id', 'setting_style_menu_id', 'date_start', 'clean_time',
                'start_time', 'end_time', 'min_price', 'max_price', 'status_more',
                'code_promotion', 'value_promotion', 'code_introducer', 'code_affiliate',
                'company_id', 'assign_pito_admin_id', 'descriptions', 'percent_manage_customer',
                'percent_manage_partner', 'voucher_code', 'voucher_price'
            ]);
            $data_insert_order['pito_admin_id'] = $user->id;
            $data_insert_order['status'] = 0;
            // tạo order
            $order = Order::create($data_insert_order);

            //tao voucher

            $vouchers = $request->vouchers;
            if (isset($vouchers)) {
                if ($vouchers != "[]") {
                    $vouchers = json_decode($vouchers);
                    if (!$vouchers) {
                        return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Json sub order fail');
                    }

                    for ($i = 0; $i < sizeOf($vouchers); $i++) {
                        VoucherForOrder::create([
                            "voucher_id" => $vouchers[$i]->id,
                            "order_id" => $order->id
                        ]);
                    }
                }
            }

            // tao notifi cho order
            $this->event_create_notifi_when_create_order($order, $pito_admin);

            // end tao notifi
            // $order->commitChange();

            // tạo order cho customer
            $order_customer = new OrderForCustomer();
            $order_customer->customer_id = $request->customer_id;
            $order_customer->order_id = $order->id;
            $order_customer->price = $total_price_for_customer;
            $order_customer->status = $order->status;
            $order_customer->save();

            // tao service cho order
            $service = $service_json;

            $price_order_detail = [];
            $res_create_service_for_order = $this->create_service_for_order($service, $order_customer);
            $data_service_partner = $res_create_service_for_order['data_service_partner'];
            $price += $res_create_service_for_order['price'];

            // tao service cho order
            $service_transport = $service_transport_json;
            $data_service_transport_partner = [];
            if ($service_transport !== null) {
                $res_create_service_transport = $this->create_service_transport($service_transport, $order_customer);
                $transport_price_map = $res_create_service_transport['transport_price_map'];
                $total_price_transport = $res_create_service_transport['total_price_transport'];
                $data_service_transport_partner = $res_create_service_transport['data_service_transport_partner'];
            }
            $amount_order_for_menu = 0;

            foreach ($sub_order_list as $key => $sub_order_item) {
                // tạo sub order cho partner
                $request_suborder = $request->only([
                    'address', 'name', 'date_start',
                    'start_time', 'end_time', 'pito_admin_id',
                    'assign_pito_admin_id', 'descriptions'
                ]);
                $request_suborder['status'] = 0;
                $request_suborder['order_id'] = $order->id;
                $request_suborder['descriptions'] = $request->note;
                $sub_order = SubOrder::create($request_suborder);

                // tạo order cho partner
                $order_partner = new OrderForPartner();
                $order_partner->partner_id = $sub_order_item->partner_id;
                $order_partner->sub_order_id = $sub_order->id;
                $order_partner->price = $sub_order_item->total_price;
                $order_partner->status = $order->status;
                $order_partner->save();

                // tạo service cho partner
                if (!isset($price_order_detail[$sub_order_item->partner_id]))
                    $price_order_detail[$sub_order_item->partner_id] = 0;
                if (isset($data_service_partner[$sub_order_item->partner_id])) {
                    $service_partner = $data_service_partner[$sub_order_item->partner_id];
                    $res_create_service_for_order_partner = $this->create_service_for_order($service_partner, $order_partner, 'PARTNER');
                    $price_order_detail[$sub_order_item->partner_id] = $res_create_service_for_order_partner['price'];
                }
                // tạo service transport cho partner
                if (isset($data_service_transport_partner[$sub_order_item->partner_id])) {
                    $service_transport_partner = $data_service_transport_partner[$sub_order_item->partner_id];
                    $this->create_service_transport($service_transport_partner, $order_partner, 'PARTNER');
                    foreach ($service_transport_partner as $key => $value) {
                        $service_order = new ServiceOrder();
                        $service_order->name = 'Phí Vận Chuyển';
                        $service_order->id_more = isset($value->partner_id) ? $value->partner_id : null;
                        $service_order->type_more = isset($value->partner_id) ? (ServiceOrder::TYPE_MORE['PARTNER']) : null;
                        $service_order->note = '';
                        $service_order->DVT = $value->DVT;
                        $service_order->note = $value->note;
                        $service_order->price = $value->price;
                        $service_order->title = "_default_";
                        $service_order->service_orderable_type = OrderForPartner::class;
                        $service_order->service_orderable_id = $order_partner->id;
                        $service_order->save();
                    }
                }

                // tao phieu di tiec
                $ticket_start = new TicketStart();
                $ticket_start->partner_id = $sub_order_item->partner_id;
                $ticket_start->sub_order_id = $sub_order->id;
                $ticket_start->field_json = $request->field_json;
                $ticket_start->save();

                // tao phieu di tiec
                $ticket_end = new TicketEnd();
                $ticket_end->partner_id = $sub_order_item->partner_id;
                $ticket_end->sub_order_id = $sub_order->id;
                $ticket_start->field_json = $request->field_json;
                $ticket_end->save();

                $menu = $sub_order_item->menu;
                if (count($menu) > 0) {
                    $data_create_menu_order = $this->create_menu_order($menu, $order_partner, $order_customer, 'menu');
                    $price += $data_create_menu_order['price'];
                    $price_order_detail[$sub_order_item->partner_id] += $data_create_menu_order['price'];
                    $amount_order_for_menu += $data_create_menu_order['amount_order_for_menu'];
                } else {
                    $foods = $sub_order_item->food;
                    $data_create_menu_order = $this->create_menu_order($foods, $order_partner, $order_customer, 'food');
                    $price += $data_create_menu_order['price'];
                    $price_order_detail[$sub_order_item->partner_id] += $data_create_menu_order['price'];
                }
            }
            if ($amount_order_for_menu) {
                $order->amount = $amount_order_for_menu;
                $order->save();
            }
            $order_customer->price = $price + $price * $order->percent_manage_customer / 100 + $price * 0.1;
            $order_customer->save();

            $sub_orders = SubOrder::with('order_for_partner')
                ->where('order_id', $order->id)
                ->get();

            // service for partner
            foreach ($sub_orders as $key => $sub_order_item) {
                $order_partner_edit = OrderForPartner::find($sub_order_item->order_for_partner->id);
                $partner_id = $sub_order_item->order_for_partner->partner_id;
                if (!isset($transport_price_map[$partner_id])) {
                    $transport_price_map[$partner_id] = 0;
                }
                $data_create_service_default_partner = $this->create_service_default(
                    $order->percent_manage_partner,
                    $order_partner_edit,
                    $price_order_detail[$partner_id],
                    $transport_price_map[$partner_id],
                    0,
                    'PARTNER'
                );
                $total_VAT = $data_create_service_default_partner['total_VAT'];
                $order_partner_edit->price = $total_VAT;
                $order_partner_edit->save();
            }
            $voucher_price = 0;
            if ($request->voucher_price) {
                $voucher_price = $request->voucher_price;
            }
            $tong_cong_VAT = $this->create_service_default(
                $order->percent_manage_customer,
                $order_customer,
                $price,
                $total_price_transport,
                $voucher_price,
                'CUSTOMER'
            )['total_VAT'];

            $order_customer->price = $tong_cong_VAT;
            $order_customer->save();
            $data = Order::with([
                'assign_pito_admin', 'pito_admin', 'company',
                'type_party', 'type_menu', 'style_menu', 'menu', 'setting_group_menu',
                'sub_order',
                'sub_order.buffet_price.buffet',
                'sub_order.order_for_partner',
                'sub_order.order_for_partner.detail',
                'sub_order.order_for_partner.detail.child',
                'sub_order.order_for_partner.partner',
                'sub_order.order_detail_customize',
                'sub_order.proposale_for_partner',
                'sub_order.proposale_for_partner.service',
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
                'order_for_customer.service',
                'order_for_customer.service_none',
                'order_for_customer.service_transport',
                'order_for_customer.service_default'
            ])->find($order->id);

            $proposale_controller = new ProposaleController();
            $request_new = $request->merge([
                'order_id' => $order->id
            ]);
            $res_proposale = $proposale_controller->create($request_new);
            if (!$res_proposale->getData()->status) {
                DB::rollBack();
                return $res_proposale;
            }
            if (config('app.env') == 'production')
                $order->notify(new NotificationOrderToSlack(NotificationSystem::EVENT_TYPE['ORDER.CREATE']));
        } catch (\Throwable $th) {
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        DB::commit();
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }

    /**
     * Change status Order .
     * @bodyParam status int required 0-gui bao gia, 1-cho xac nhan, 2-da xac nhan, 3-trien khai tiec, 4-hoan thanh, 5-huy.Example: 5.
     */

    public function change_status(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
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
        ])->find($id);
        DB::beginTransaction();
        try {
            //code...

            if (!$order)
                return AdapterHelper::sendResponse(false, 'Order not found', 404, 'Error not found');
            $order->startListenChange();
            if ($request->status == 5) {
                $text = "";
                if ($request->actor) {
                    $text .= $request->actor . ": ";
                }
                if ($request->reason) {
                    $text .=  $request->reason;
                }
                $order->reason = $text;
            }
            // if ($request->status == 1) {
            //     $proposale_curent = $order->proposale;
            //     $list_proposale_partner_old = [];
            //     foreach ($order->sub_order as $key => $value) {
            //         if ($value->proposale_for_partner)
            //             $list_proposale_partner_old[$value->id] = $value->proposale_for_partner;
            //     }
            //     $order->proposale_all()->update(['status' => 3]);
            //     $proposale = new Proposale();
            //     $proposale->order_id  = $id;
            //     $proposale->status = 0;
            //     $proposale->save();

            //     $order_for_customer = $order->order_for_customer;
            //     // for customer
            //     $proposale_for_customer = new ProposaleForCustomer();
            //     $proposale_for_customer->proposale_id = $proposale->id;
            //     $proposale_for_customer->customer_id = $order_for_customer->customer_id;
            //     $proposale_for_customer->price = $order_for_customer->price;
            //     $proposale_for_customer->status = 0;
            //     $proposale_for_customer->save();
            //     if ($proposale_curent) {
            //         $proposale_for_customer_old = $proposale->proposale_for_customer;
            //         HistoryRevenueForCustomer::where('proposale_id', $proposale_for_customer_old->id)
            //             ->update(['proposale_id' => $proposale_for_customer->id]);
            //     }
            //     // for partner
            //     foreach ($order->sub_order as $key => $sub_order) {
            //         $proposale_for_partner = new ProposaleForPartner();
            //         $proposale_for_partner->proposale_id = $proposale->id;
            //         $proposale_for_partner->partner_id = $sub_order->order_for_partner->partner_id;
            //         $proposale_for_partner->price = $sub_order->order_for_partner->price;
            //         $proposale_for_partner->sub_order_id = $sub_order->id;
            //         $proposale_for_partner->status = 0;
            //         $proposale_for_partner->save();
            //         if (count($list_proposale_partner_old) > 0) {
            //             $proposale_partner_old = $list_proposale_partner_old[$sub_order->id];
            //             HistoryRevenueForPartner::where('proposale_id', $proposale_partner_old->id)
            //                 ->update(['proposale_id' => $proposale_for_partner->id]);
            //         }
            //     }
            // }

            if ($request->status == 3) {
                $proposale = $order->proposale;
                ProposaleForCustomer::where('proposale_id', $proposale->id)->update(['status' => 2]);
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
                    $proposale_for_partner->status = 2;
                    $proposale_for_partner->save();

                    MultiMail::from('order@pito.vn')
                        ->to($partner->email)
                        ->send(new PartnerWhenCustomerConfirmed($proposale_for_partner, $partner));
                    $order->notify(new NotificationOrderToSlack(NotificationSystem::EVENT_TYPE['PROPOSAL.CUSTOMER.ACCEPT']));
                    // MultiMail::from('order@pito.vn')
                    //     ->to('quyproi51vn@gmail.com')
                    //     ->send(new PartnerWhenCustomerConfirmed($proposale_for_partner, $partner));
                }
                $proposale->status = 2;
                $proposale->save();
                $assign_pito = $order->assign_pito_admin;
                $customer = $order->order_for_customer->customer;
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
                //     ->to('quyproi51vn@gmail.com')
                //     ->send(new CustomerConfirmed($order, $customer, $token_payment));
            }

            if ($request->status == 6) {
                foreach ($order->sub_order as $key => $sub_order) {
                    $ticket_start = $sub_order->ticket_start;
                    $ticket_start->date_confirm_arrived = date('Y-m-d H:i:s');
                    $ticket_start->save();
                    if (strtotime($order->date_start) <= strtotime(date('Y-m-d H:i:s'))) {
                        $request->status = 8;
                    }
                }
            }
            if ($request->status == 9) {
                $check_proposale_customer = $order->proposale->proposale_for_customer()->where('is_pay', '<>', 1)->first();
                $check_proposale_partner = $order->proposale->proposale_for_partner()->where('is_pay', '<>', 1)->first();
                if (!$check_proposale_customer && !$check_proposale_partner) {
                    $request->status = 11;
                }
                $control_review = new SetDataReview();
                $data_request = [
                    'type' => "CUSTOMER-PARTNER",
                    'order_id' => $id,
                    'target_user_id' => null
                ];
                $control_review->send_link_review_share(json_decode(json_encode($data_request)));
            }
            OrderForCustomer::where('order_id', $order->id)->update(['status' => $order->status]);
            $order->status = $request->status;
            $order->save();
            $order->commitChange();
            $new_notifi = new NotificationSystem();
            $new_notifi->content = 'Order PT' . $order->id . ' đã thay đổi trạng thái thành: ' . Order::$const_status[$request->status];
            $new_notifi->tag = NotificationSystem::EVENT_TYPE["ORDER.CHANGE"];
            $new_notifi->type = Order::class;
            $new_notifi->type_id = $order->id;
            $new_notifi->save();

            $data = [
                "status_code" => $request->status,
                "text_status" => Order::$const_status[$request->status]
            ];
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }


    private function sendMailWhenOrEdit($request, $id)
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
                if (strpos($value->name, "Tổng Giá Trị Tiệc") > -1)
                    $service_order['price'] = $value->price;
                if (strpos($value->name, "Phí thuận tiện") > -1)
                    $service_order['price_manage'] = $value->price;
            }
            $service_order['total_price_VAT'] = $service_order['price'] + $service_order['price'] * 0.1 + $service_order['price_manage'];
            $service_order['total_price_no_VAT'] = $service_order['price'] + $service_order['price_manage'];
        } else {

            $orderForPartner =  OrderForPartner::where('sub_order_id', $request['sub_order_id'])->first();
            if ($orderForPartner) {
                $tmp = ServiceOrder::where('service_orderable_type', OrderForPartner::class)
                    ->where('service_orderable_id', $orderForPartner->id)->get();
                foreach ($tmp as $key => $value) {
                    if (strpos($value->name, "Tổng Giá Trị Tiệc") > -1)
                        $service_order['price'] = $value->price;
                    if (strpos($value->name, "Phí thuận tiện") > -1)
                        $service_order['price_manage'] = $value->price;
                }
                $service_order['total_price_VAT'] = $service_order['price'] + $service_order['price'] * 0.1 + $service_order['price_manage'];
                $service_order['total_price_no_VAT'] = $service_order['price'] + $service_order['price_manage'];
            } else {
                return AdapterHelper::sendResponse(true, 'success', 200, 'success');
            }
        }

        $proposale = [
            'id' => $data->id,
            'date_start' => $date_time,
            'name' => $data->order->name,
            'service_order' => $service_order,
            'partner' => $data['proposale_for_partner'][0]['partner'],
            'order_id' => $data['order_id']
        ];
        $pito = $data['order']['assign_pito_admin'];
        $token = bcrypt($user->email . "-" . $data['order_id'] . "-" . $user->email . "-" . ($data['order_id'] * 100));
        // Mail::to($user->email)->send(new SendProposaleEdit($user, $pito, $proposale, $token, $request['sub_order_id']));
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }
    /**
     * Change Order step1 các thông tin cơ bản, mô tả param nằm trên create order.
     *
     * @bodyParam name string .Example: Bữa tiệc công ty.
     * @bodyParam address string .Example: 33/4/53 Đào tấn-huế
     * @bodyParam date_start date. Example: 2020-02-03 08:56:13
     * @bodyParam start_time int tổng giờ phút đổi sang giây.Example: 54000
     * @bodyParam end_time int tổng giờ phút đổi sang giây. Example: 61200
     * @bodyParam clean_time int giờ dọn dẹp tổng giờ phút đổi sang giây. Example: 61200
     * @bodyParam assign_pito_admin_id int assign cho 1 nhân viên pito. Example: 22
     * @bodyParam note string note cua order. Example: Setup tiệc sớm.
     */
    public function edit_order_step_1(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::find($id);
        if (!$order) {
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Order not found');
        }
        $order->startListenChange();
        // kiem tra nhung truong naof thay doi
        $param = $request->only([
            'name', 'value_promotion', 'code_promotion',
            'setting_style_menu_id',
            'value_promotion', 'code_introducer', 'code_affiliate',
            'menu_id', 'setting_group_menu_id', 'address',
            '_lat', '_long', 'company_id', 'date_start',
            'start_time', 'end_time', 'clean_time',
            'assign_pito_admin_id', 'note', 'percent_manage_customer',
            'percent_manage_partner', 'voucher_code'
        ]);
        $field_edit = [];
        foreach ($param as $key => $value) {
            $field_edit[] = $key;
        }
        // history truyen object order tren vao bảng history. và phải biết trường nào thay đổi.

        // endhistory

        DB::beginTransaction();
        try {
            // tạo order
            // if (isset($param['date_start'])) {
            //     $date_start = $param['date_start'];
            //     // kiem tra status laf trien khai tiec, roi moi cho update ngay.
            //     if ($order->status == 3 && $date_start != $order->date_start) {
            //         $order->status = 2;
            //         $order->save();
            //     }
            // }
            if (isset($param['note'])) {
                $param['descriptions'] = $param['note'];
                unset($param['note']);
            }

            //            $order = Order::where('id', $id)->update($param);
            if (strtotime($param['date_start']) != strtotime($order->date_start)) {
                $param['is_remider'] = 0;
            }

            $order->update($param);
            $order->save();
            $order->commitChange();

            VoucherForOrder::where('order_id', $order->id)->delete();

            $vouchers = $request->vouchers;
            if (isset($vouchers)) {
                if ($vouchers != "[]") {
                    $vouchers = json_decode($vouchers);
                    if (!$vouchers) {
                        return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Json sub order fail');
                    }
                    for ($i = 0; $i < sizeOf($vouchers); $i++) {
                        VoucherForOrder::create([
                            "voucher_id" => $vouchers[$i]->id,
                            "order_id" => $order->id
                        ]);
                    }
                }
            }


            $order = Order::with([
                'assign_pito_admin', 'pito_admin', 'company',
                'type_party', 'type_menu', 'style_menu', 'menu', 'setting_group_menu',
                'sub_order',
                'sub_order.buffet_price.buffet',
                'sub_order.order_for_partner',
                'sub_order.order_for_partner.detail',
                'sub_order.order_for_partner.detail.child',
                'sub_order.order_for_partner.partner',
                'sub_order.order_detail_customize',
                'sub_order.proposale_for_partner',
                'sub_order.proposale_for_partner.service',
                'sub_order.proposale_for_partner.detail',
                'sub_order.proposale_for_partner.detail.child',
                'proposale',
                'proposale.proposale_for_customer',
                'proposale.proposale_for_customer.detail',
                'proposale.proposale_for_customer.detail.child',
                'proposale.proposale_for_partner',
                'proposale.proposale_for_partner.detail',
                'proposale.proposale_for_partner.detail.child',
                'order_for_customer',
                'order_for_customer.detail',
                'order_for_customer.detail.child',
                'order_for_customer.customer',
                'order_for_customer.service'
            ])->find($id);
            $new_notifi = new NotificationSystem();
            $new_notifi->content = 'Order PT' . $order->id . ' đã thay đổi nội dung';
            $new_notifi->tag = NotificationSystem::EVENT_TYPE["ORDER.CHANGE"];
            $new_notifi->type = Order::class;
            $new_notifi->type_id = $order->id;
            $new_notifi->save();
            $data = [
                'order_id' => $order->id,
                'tag' => NotificationSystem::EVENT_TYPE["ORDER.CHANGE"]
            ];
            $list_user = [
                'role' => 'PITO_ADMIN'
            ];
            Notification::notifi_more($data, 'Order PT' . $order->id . ' đã thay đổi nội dung', $list_user, []);

            // send SMS
            $pito_admin = $order->assign_pito_admin;
            $customer = $order->order_for_customer->customer;

            // if ($order->proposale) {
            //     $data_send = [
            //         'sub_order_id' => null,
            //         'user_id' => $customer->id
            //     ];
            //     $this->sendMailWhenOrEdit($data_send, $order->proposale->id);
            //     $data_send = [
            //         'sub_order_id' => null,
            //         'user_id' => $pito_admin->id
            //     ];
            //     $this->sendMailWhenOrEdit($data_send, $order->proposale->id);
            //     foreach ($order->sub_order as $key => $sub_order) {
            //         $partner = $sub_order->order_for_partner->partner;
            //         if ($sub_order->id) {
            //             $data_send = [
            //                 'sub_order_id' => $sub_order->id,
            //                 'user_id' => $partner->id
            //             ];
            //             $this->sendMailWhenOrEdit($data_send, $order->proposale->id);
            //         }
            //     }
            // }

            $data = $order;
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }

    /**
     * Change Order step2 các thông tin món ăn của từng sub order.
     *
     * @bodyParam order_for_partner_id required truyen id cua order partner .Example: 52.
     * @bodyParam menu string note cua order. Example: Setup tiệc sớm. Example: [{"name":"Khai vị","child":[{"name":"Gỏi sen tôm","amount":2}]}]
     */
    public function edit_order_step_2(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            // 'order_for_partner_id' => 'required',
            'sub_order' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }

        $sub_order_list = json_decode($request->sub_order);
        if (!$sub_order_list) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Json sub order fail');
        }
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
            'proposale.proposale_for_partner',
            'proposale.proposale_for_partner.detail',
            'proposale.proposale_for_partner.detail.child',
            'order_for_customer',
            'order_for_customer.detail',
            'order_for_customer.detail.child',
            'order_for_customer.customer',
            'order_for_customer.service',
        ])->find($id);

        $pre_snapshot = $order;

        $order_customer = OrderForCustomer::where('order_id', $order->id)->first();

        if (!$order || !$order_customer) {
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Order not found');
        }

        DB::beginTransaction();
        try {
            //code...
            $list_order_partner_id = [];
            $order_for_partner_map = [];
            foreach ($order->sub_order as $key => $value) {
                $list_order_partner_id[] = $value->order_for_partner->id;
                $order_for_partner_map[$value->order_for_partner->partner_id] = $value->order_for_partner;
            }


            DetailOrder::where('detail_orderable_type', OrderForPartner::class)
                ->whereIn('detail_orderable_id', $list_order_partner_id)
                ->delete();
            DetailOrder::where('detail_orderable_type', OrderForCustomer::class)
                ->where('detail_orderable_id', $order_customer->id)
                ->delete();

            $price = 0;
            $price_order_detail = [];
            $amount_order_for_menu = 0;
            foreach ($sub_order_list as $key => $sub_order_item) {
                // tạo service cho partner
                if (!isset($price_order_detail[$sub_order_item->partner_id]))
                    $price_order_detail[$sub_order_item->partner_id] = 0;
                $menu = $sub_order_item->menu;
                $order_partner = $order_for_partner_map[$sub_order_item->partner_id];
                if (count($menu) > 0) {
                    foreach ($menu as $menu_item) {
                        // tạo detail cho order partner

                        $detail_order_partner_parent = new DetailOrder();
                        $detail_order_partner_parent->amount = $menu_item->amount;
                        $detail_order_partner_parent->type_more = DetailOrder::TYPE_MORE['BUFFET'];
                        $detail_order_partner_parent->name = $menu_item->name;
                        $detail_order_partner_parent->id_more = $menu_item->id;
                        $detail_order_partner_parent->order_sort = isset($menu_item->order_sort) ? $menu_item->order_sort : 1;
                        $detail_order_partner_parent->detail_orderable_type = OrderForPartner::class;
                        $detail_order_partner_parent->detail_orderable_id = $order_partner->id;
                        $detail_order_partner_parent->save();

                        // tạo detail cho order customer
                        $detail_order_customer_parent = new DetailOrder();
                        $detail_order_customer_parent->name = $menu_item->name;
                        $detail_order_customer_parent->type_more = DetailOrder::TYPE_MORE['BUFFET'];
                        $detail_order_customer_parent->id_more = $menu_item->id;
                        $detail_order_customer_parent->amount = $menu_item->amount;
                        $detail_order_customer_parent->order_sort = isset($menu_item->order_sort) ? $menu_item->order_sort : 1;
                        $detail_order_customer_parent->detail_orderable_type = OrderForCustomer::class;
                        $detail_order_customer_parent->detail_orderable_id = $order_customer->id;
                        $detail_order_customer_parent->save();
                        $amount_order_for_menu += (int) $menu_item->amount;
                        foreach ($menu_item->menu_buffect as $dish) {
                            // tạo detail cho order partner
                            $detail_order_partner = new DetailOrder();
                            $detail_order_partner->name = $dish->name;
                            $detail_order_partner->amount = $dish->amount;
                            $detail_order_partner->detail_orderable_type = OrderForPartner::class;
                            $detail_order_partner->detail_orderable_id = $sub_order_item->partner_id;
                            $detail_order_partner->parent_id = $detail_order_partner_parent->id;
                            $detail_order_partner->save();
                            // tạo detail cho order customer
                            $detail_order_customer = new DetailOrder();
                            $detail_order_customer->name = $dish->name;
                            $detail_order_customer->amount = $dish->amount;
                            $detail_order_customer->detail_orderable_type = OrderForCustomer::class;
                            $detail_order_customer->detail_orderable_id = $order_customer->id;
                            $detail_order_customer->parent_id = $detail_order_customer_parent->id;
                            $detail_order_customer->save();
                            if ($menu_item->buffet->is_select_category) {
                                foreach ($dish->child as $child) {
                                    // tạo detail cho order partner
                                    if (isset($child->check) && $child->check) {
                                        $detail_order_child = new DetailOrder();
                                        $detail_order_child->name = $child->name;
                                        $detail_order_child->amount = $child->amount;
                                        $detail_order_child->detail_orderable_type = OrderForPartner::class;
                                        $detail_order_child->detail_orderable_id = $sub_order_item->partner_id;
                                        $detail_order_child->parent_id = $detail_order_partner->id;
                                        $detail_order_child->save();
                                        // tạo detail cho order customer
                                        $detail_order_child = new DetailOrder();
                                        $detail_order_child->name = $child->name;
                                        $detail_order_child->amount = $child->amount;
                                        $detail_order_child->detail_orderable_type = OrderForCustomer::class;
                                        $detail_order_child->detail_orderable_id = $order_customer->id;
                                        $detail_order_child->parent_id = $detail_order_customer->id;
                                        $detail_order_child->save();
                                    }
                                }
                            }
                        }
                        $price += (int) $menu_item->price * (int) $menu_item->amount;
                        // tạo service cho partner
                        $price_order_detail[$sub_order_item->partner_id] += (int) $menu_item->price * (int) $menu_item->amount;
                    }
                } else {
                    $foods = $sub_order_item->food;
                    foreach ($foods as $menu_item) {
                        // tạo detail cho order partner
                        $detail_order_partner_parent = new DetailOrder();
                        $detail_order_partner_parent->amount = $menu_item->amount;
                        $detail_order_partner_parent->type_more = DetailOrder::TYPE_MORE['FOOD'];
                        $detail_order_partner_parent->name = $menu_item->name;
                        $detail_order_partner_parent->id_more = $menu_item->id;
                        $detail_order_partner_parent->order_sort = isset($menu_item->order_sort) ? $menu_item->order_sort : 1;
                        $detail_order_partner_parent->detail_orderable_type = OrderForPartner::class;
                        $detail_order_partner_parent->detail_orderable_id = $order_partner->id;
                        $detail_order_partner_parent->save();

                        // tạo detail cho order customer
                        $detail_order_customer_parent = new DetailOrder();
                        $detail_order_customer_parent->name = $menu_item->name;
                        $detail_order_customer_parent->type_more = DetailOrder::TYPE_MORE['FOOD'];
                        $detail_order_customer_parent->id_more = $menu_item->id;
                        $detail_order_customer_parent->amount = $menu_item->amount;
                        $detail_order_customer_parent->order_sort = isset($menu_item->order_sort) ? $menu_item->order_sort : 1;
                        $detail_order_customer_parent->detail_orderable_type = OrderForCustomer::class;
                        $detail_order_customer_parent->detail_orderable_id = $order_customer->id;
                        $detail_order_customer_parent->save();

                        $price += (int) $menu_item->food->price * (int) $menu_item->amount;
                        $price_order_detail[$sub_order_item->partner_id] += (int) $menu_item->food->price * (int) $menu_item->amount;
                    }
                }
            }
            if ($amount_order_for_menu) {
                $order->amount = $amount_order_for_menu;
                $order->save();
            }
            // service for partner
            $total_price_transport = 0;
            $sub_orders = SubOrder::with(['order_for_partner', 'proposale_for_partner'])
                ->where('order_id', $order->id)
                ->get();
            // service for partner
            foreach ($sub_orders as $key => $sub_order_item) {
                $price_for_partner = 0;
                $partner_id = $sub_order_item->order_for_partner->partner_id;
                $service_for_parter_none = ServiceOrder::where('service_orderable_id', $sub_order_item->order_for_partner->id)
                    ->where('service_orderable_type', OrderForPartner::class)
                    ->where(function ($q) {
                        return $q->where('title', null)->orWhere('title', 'none');
                    })->get();
                foreach ($service_for_parter_none as $value) {
                    $price_for_partner += (int) $value->amount * (int) $value->price;
                }
                $service_for_parter_transport = ServiceOrder::where('service_orderable_id', $sub_order_item->order_for_partner->id)
                    ->where('service_orderable_type', OrderForPartner::class)
                    ->where('title', '_transport_')->get();
                $price_transport_for_partner = 0;
                foreach ($service_for_parter_transport as $value) {
                    $price_transport_for_partner += (int) $value->amount * (int) $value->price;
                }
                $total_price_transport += $price_transport_for_partner;
                ServiceOrder::where('service_orderable_type', OrderForPartner::class)
                    ->where('service_orderable_id', $sub_order_item->order_for_partner->id)
                    ->where('title', '_default_')
                    ->delete();
                if (isset($price_order_detail[$partner_id]))
                    $price_for_partner += $price_order_detail[$partner_id];
                // tong gia tri tiec
                $tong_gia_tri_tiec = $price_for_partner + $price_transport_for_partner;
                $service_order = new ServiceOrder();
                $service_order->name = 'Tổng Giá Trị Tiệc';
                $service_order->title = "_default_";
                $service_order->price = $tong_gia_tri_tiec;
                $service_order->service_orderable_type = OrderForPartner::class;
                $service_order->service_orderable_id = $sub_order_item->order_for_partner->id;
                $service_order->save();
                // Phí thuận tiện
                $phi_quan_ly = $price_for_partner * $order->percent_manage_partner / 100;
                $service_order = new ServiceOrder();
                $service_order->name = 'Phí Dịch Vụ (' . $order->percent_manage_partner . '%)';
                $service_order->title = "_default_";
                $service_order->price = $phi_quan_ly;
                $service_order->service_orderable_type = OrderForPartner::class;
                $service_order->service_orderable_id = $sub_order_item->order_for_partner->id;
                $service_order->save();

                // Phí ưu đãi
                $uu_dai = 0;
                $service_order = new ServiceOrder();
                $service_order->name = 'Ưu Đãi';
                $service_order->price = $uu_dai;
                $service_order->title = "_default_";
                $service_order->service_orderable_type = OrderForPartner::class;
                $service_order->service_orderable_id = $sub_order_item->order_for_partner->id;
                $service_order->save();

                // Phí Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và chưa bao gồm VAT)
                $tong_cong_chua_VAT = $tong_gia_tri_tiec - $phi_quan_ly - $uu_dai;
                $service_order = new ServiceOrder();
                $service_order->name = 'Tổng Giá Trị Cần Thanh Toán (chưa bao gồm VAT)';
                $service_order->price = $tong_cong_chua_VAT;
                $service_order->title = "_default_";
                $service_order->service_orderable_type = OrderForPartner::class;
                $service_order->service_orderable_id = $sub_order_item->order_for_partner->id;
                $service_order->save();

                // VAT
                $VAT = $tong_cong_chua_VAT * 0.1;
                $service_order = new ServiceOrder();
                $service_order->name = 'VAT';
                $service_order->price = $VAT;
                $service_order->title = "_default_";
                $service_order->service_orderable_type = OrderForPartner::class;
                $service_order->service_orderable_id = $sub_order_item->order_for_partner->id;
                $service_order->save();

                // Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và  VAT)
                $tong_cong_VAT = $tong_cong_chua_VAT + $VAT;
                $service_order = new ServiceOrder();
                $service_order->name = 'Tổng Giá Trị Cần Thanh Toán (Đã bao gồm VAT)';
                $service_order->price = $tong_cong_VAT;
                $service_order->title = "_default_";
                $service_order->service_orderable_type = OrderForPartner::class;
                $service_order->service_orderable_id = $sub_order_item->order_for_partner->id;
                $service_order->save();
                $sub_order_item->order_for_partner->price = $tong_cong_VAT;
                $sub_order_item->order_for_partner->save();
                if ($sub_order_item->proposale_for_partner) {
                    $sub_order_item->proposale_for_partner->price = $tong_cong_VAT;
                    $sub_order_item->proposale_for_partner->save();
                }
            }

            $price_service_customer = 0;
            $service_for_customer = ServiceOrder::where('service_orderable_id', $order_customer->id)
                ->where('service_orderable_type', OrderForCustomer::class)
                ->where(function ($q) {
                    $q->where('title', null)->orWhere('title', 'none');
                })->get();
            foreach ($service_for_customer as $value) {
                $price_service_customer += (int) $value->amount * (int) $value->price;
            }
            ServiceOrder::where('service_orderable_type', OrderForCustomer::class)
                ->where('service_orderable_id', $order_customer->id)
                ->where('title', '_default_')
                ->delete();
            $price += $price_service_customer;
            // phi van chuyen
            $service_order = new ServiceOrder();
            $service_order->name = 'Phí Vận Chuyển';
            $service_order->price = $total_price_transport;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = OrderForCustomer::class;
            $service_order->service_orderable_id = $order_customer->id;
            $service_order->save();

            // end tính giá menu 
            // tong gia tri tiec
            $tong_gia_tri_tiec = $price + $total_price_transport;
            $service_order = new ServiceOrder();
            $service_order->name = 'Tổng Giá Trị Tiệc';
            $service_order->price = $tong_gia_tri_tiec;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = OrderForCustomer::class;
            $service_order->service_orderable_id = $order_customer->id;
            $service_order->save();

            // Phí thuận tiện
            $phi_quan_ly = $tong_gia_tri_tiec * $order->percent_manage_customer / 100;
            $service_order = new ServiceOrder();
            $service_order->name = 'Phí Thuận Tiện (' . $order->percent_manage_customer . '%)';
            $service_order->price = $phi_quan_ly;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = OrderForCustomer::class;
            $service_order->service_orderable_id = $order_customer->id;
            $service_order->save();

            // Phí ưu đãi
            $uu_dai = 0;
            $service_order = new ServiceOrder();
            $service_order->name = 'Ưu Đãi';
            $service_order->price = $uu_dai;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = OrderForCustomer::class;
            $service_order->service_orderable_id = $order_customer->id;
            $service_order->save();

            // Phí Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và chưa bao gồm VAT)
            $tong_cong_chua_VAT = $tong_gia_tri_tiec + $phi_quan_ly - $uu_dai;
            $service_order = new ServiceOrder();
            $service_order->name = 'Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và chưa bao gồm VAT)';
            $service_order->price = $tong_cong_chua_VAT;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = OrderForCustomer::class;
            $service_order->service_orderable_id = $order_customer->id;
            $service_order->save();

            // VAT
            $VAT = $tong_cong_chua_VAT * 0.1;
            $service_order = new ServiceOrder();
            $service_order->name = 'VAT';
            $service_order->price = $VAT;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = OrderForCustomer::class;
            $service_order->service_orderable_id = $order_customer->id;
            $service_order->save();

            // Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và  VAT)
            $tong_cong_VAT = $tong_cong_chua_VAT + $VAT;
            $service_order = new ServiceOrder();
            $service_order->name = 'Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và  VAT)';
            $service_order->price = $tong_cong_VAT;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = OrderForCustomer::class;
            $service_order->service_orderable_id = $order_customer->id;
            $service_order->save();

            $order_customer->price = $tong_cong_VAT;
            $order_customer->save();

            if ($order->proposale) {
                $order->proposale->proposale_for_customer->price = $tong_cong_VAT;
                $order->proposale->proposale_for_customer->save();
            }

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
                'proposale.proposale_for_partner',
                'proposale.proposale_for_partner.detail',
                'proposale.proposale_for_partner.detail.child',
                'order_for_customer',
                'order_for_customer.detail',
                'order_for_customer.detail.child',
                'order_for_customer.customer',
                'order_for_customer.service'
            ])->find($id);
            $order->commitChange(null, $order, $pre_snapshot);
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $order, 200, 'success');
    }

    /**
     * Change Order step3 cacs thông tin giá dịch vụ.
     * @bodyParam service string service cuar oder. Example: [{"name":"Phí vận chuyển","DVT":"ban","price":"10.000","amount":20,"note":"1 gói"}]
     */
    public function edit_order_step_3(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'service' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }

        // sau nay sẽ tách các phần này ra
        $service_json = json_decode($request->service);
        $service_transport_json = json_decode($request->service_transport);
        if ($service_json === null) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'json service fail');
        }

        $order = Order::with([
            'assign_pito_admin', 'pito_admin',
            'type_party',
            'sub_order',
            'setting_group_menu',
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
            'proposale.proposale_for_partner',
            'proposale.proposale_for_partner.detail',
            'proposale.proposale_for_partner.detail.child',
            'order_for_customer',
            'order_for_customer.detail',
            'order_for_customer.detail.child',
            'order_for_customer.customer',
            'order_for_customer.service'
        ])->find($id);
        DB::beginTransaction();
        try {

            $pre_snapshot = $order;
            //code...
            // xoa tat ca các service của khách hàng
            ServiceOrder::where('service_orderable_type', OrderForCustomer::class)
                ->where('service_orderable_id', $order->order_for_customer->id)
                ->delete();

            // xoa tất cả các service của partner.
            $list_id_order_for_partner = [];
            foreach ($order->sub_order as $key => $value) {
                $list_id_order_for_partner[] = $value->order_for_partner->id;
            }
            ServiceOrder::where('service_orderable_type', OrderForPartner::class)
                ->whereIn('service_orderable_id', $list_id_order_for_partner)
                ->delete();

            // tao service cho order
            $service = $service_json;
            $price = 0;
            $total_price_transport = 0;
            $transport_price_map = [];
            $data_service_partner = [];
            $order_customer = $order->order_for_customer;
            foreach ($service as $key => $value) {
                if ((int) $value->price * (int) $value->amount > 0) {
                    if (
                        isset($value->partner_id)
                        && $value->partner_id != "PITO"
                        && $value->partner_id != ""
                    ) {
                        if (!isset($data_service_partner[$value->partner_id])) {
                            $data_service_partner[$value->partner_id] = [];
                        }
                        $data_service_partner[$value->partner_id][] = $value;
                    }
                    $service_order = new ServiceOrder();
                    $json_more = array("category_id" => $value->category_id, "partner_id" => $value->partner_id);
                    $service_order->json_more = json_encode($json_more);
                    $service_order->id_more = isset($value->partner_id) ? $value->partner_id : null;
                    $service_order->type_more = isset($value->partner_id) ? (ServiceOrder::TYPE_MORE['PARTNER']) : null;
                    $service_order->name = $value->name;
                    $service_order->amount = $value->amount;
                    $service_order->DVT = $value->DVT;
                    $service_order->note = $value->note;
                    $service_order->price = $value->price;
                    $service_order->service_orderable_type = OrderForCustomer::class;
                    $service_order->service_orderable_id = $order_customer->id;
                    $service_order->save();

                    $tmp = $value->price;
                    $tmp = \str_replace('.', '', $tmp);
                    $tmp = \str_replace(' ', '', $tmp);
                    $tmp = \str_replace(',', '', $tmp);
                    $tmp_price = (int) $tmp;
                    $tmp_amount = (int) $value->amount;
                    $price += $tmp_price * $tmp_amount;
                }
            }

            // tao service transport cho order
            $service_transport = $service_transport_json;
            $data_service_transport_partner = [];
            if ($service_transport !== null) {
                foreach ($service_transport as $key => $value) {
                    if ((int) $value->price * (int) $value->amount > 0) {
                        if (
                            isset($value->partner_id)
                            &&  $value->partner_id
                            && $value->partner_id != ""
                            && $value->partner_id != "PITO"
                        ) {
                            if (!isset($data_service_transport_partner[$value->partner_id])) {
                                $data_service_transport_partner[$value->partner_id] = [];
                            }
                            $data_service_transport_partner[$value->partner_id][] = $value;
                        }
                        $service_order = new ServiceOrder();
                        $json_more = array("category_id" => isset($value->category_id) ? $value->category_id : "", "partner_id" => isset($value->partner_id) ? $value->partner_id : "");
                        $service_order->json_more = json_encode($json_more);
                        $service_order->id_more = isset($value->partner_id) ? $value->partner_id : null;
                        $service_order->type_more = isset($value->partner_id) ? (ServiceOrder::TYPE_MORE['PARTNER']) : null;
                        $service_order->amount = $value->amount;
                        $service_order->title = '_transport_';
                        $service_order->DVT = $value->DVT;
                        $service_order->note = $value->note;
                        $service_order->price = $value->price;
                        $service_order->service_orderable_type = OrderForCustomer::class;
                        $service_order->service_orderable_id = $order_customer->id;
                        $service_order->save();

                        $tmp = $value->price;
                        $tmp = \str_replace('.', '', $tmp);
                        $tmp = \str_replace(' ', '', $tmp);
                        $tmp = \str_replace(',', '', $tmp);
                        $tmp_price = (int) $tmp;
                        $tmp_amount = (int) $value->amount;
                        $total_price_transport += $tmp_price * $tmp_amount;
                        $transport_price_map[$value->partner_id] = $tmp_price * $tmp_amount;
                    }
                }
            }
            $price_order_detail = [];
            $sub_order_list = $order->sub_order;
            foreach ($sub_order_list as $key => $sub_order_item) {
                $order_partner = $sub_order_item->order_for_partner;
                // tạo service cho partner
                $price_order_detail[$order_partner->partner_id] = 0;
                if (isset($data_service_partner[$order_partner->partner_id])) {
                    $service_partner = $data_service_partner[$order_partner->partner_id];
                    foreach ($service_partner as $key => $value) {
                        if ((int) $value->price * (int) $value->amount > 0) {
                            $service_order = new ServiceOrder();
                            $json_more = array("category_id" => $value->category_id, "partner_id" => $value->partner_id);
                            $service_order->json_more = json_encode($json_more);
                            $service_order->id_more = isset($value->partner_id) ? $value->partner_id : null;
                            $service_order->type_more = isset($value->partner_id) ? (ServiceOrder::TYPE_MORE['PARTNER']) : null;
                            $service_order->name = $value->name;
                            $service_order->amount = $value->amount;
                            $service_order->DVT = $value->DVT;
                            $service_order->note = $value->note;
                            $service_order->price = $value->price;
                            $service_order->service_orderable_type = OrderForPartner::class;
                            $service_order->service_orderable_id = $order_partner->id;
                            $service_order->save();

                            $tmp = $value->price;
                            $tmp = \str_replace('.', '', $tmp);
                            $tmp = \str_replace(' ', '', $tmp);
                            $tmp = \str_replace(',', '', $tmp);
                            $tmp_price = (int) $tmp;
                            $tmp_amount = (int) $value->amount;
                            $price_order_detail[$order_partner->partner_id] += $tmp_price * $tmp_amount;
                        }
                    }
                }
                // tạo service stransport cho partner
                if (isset($data_service_transport_partner[$order_partner->partner_id])) {
                    $service_transport_partner = $data_service_transport_partner[$order_partner->partner_id];
                    foreach ($service_transport_partner as $key => $value) {
                        $service_order = new ServiceOrder();
                        $service_order->json_more = json_encode($json_more);
                        $service_order->id_more = isset($value->partner_id) ? $value->partner_id : null;
                        $service_order->type_more = isset($value->partner_id) ? (ServiceOrder::TYPE_MORE['PARTNER']) : null;
                        $service_order->name = $value->name;
                        $service_order->title = '_transport_';
                        $service_order->amount = $value->amount;
                        $service_order->DVT = $value->DVT;
                        $service_order->note = $value->note;
                        $service_order->price = $value->price;
                        $service_order->service_orderable_type = OrderForPartner::class;
                        $service_order->service_orderable_id = $order_partner->id;
                        $service_order->save();

                        $service_order = new ServiceOrder();
                        $service_order->name = 'Phí Vận Chuyển';
                        $service_order->id_more = isset($value->partner_id) ? $value->partner_id : null;
                        $service_order->type_more = isset($value->partner_id) ? (ServiceOrder::TYPE_MORE['PARTNER']) : null;
                        $service_order->note = '';
                        $service_order->DVT = $value->DVT;
                        $service_order->note = $value->note;
                        $service_order->price = $value->price;
                        $service_order->title = "_default_";
                        $service_order->service_orderable_type = OrderForPartner::class;
                        $service_order->service_orderable_id = $order_partner->id;
                        $service_order->save();
                    }
                }
            }

            // service for partner
            foreach ($order->sub_order as $key => $sub_order_item) {
                // phí vận chuyển.
                //code...
                $order_partner_edit = OrderForPartner::find($sub_order_item->order_for_partner->id);
                $price_for_partner = 0;

                $price_service_partner = 0;
                $service_for_partner = ServiceOrder::where('service_orderable_id', $order_partner_edit->id)
                    ->where('service_orderable_type', OrderForPartner::class)
                    ->where(function ($q) {
                        $q->where('title', null)
                            ->orWhere('title', 'none');
                    })->get();
                foreach ($service_for_partner as $value) {
                    $price_service_partner += (int) $value->amount * (int) $value->price;
                }
                $price_menu_partner = 0;
                if ($order->type_menu_id == 1) {
                    $price_menu = DetailOrder::where('type_more', DetailOrder::TYPE_MORE['BUFFET'])
                        ->where('detail_orderable_type', OrderForPartner::class)
                        ->where('detail_orderable_id', $order_partner_edit->id)
                        ->get();
                } else {
                    $price_menu = DetailOrder::where('type_more', DetailOrder::TYPE_MORE['FOOD'])
                        ->where('detail_orderable_type', OrderForPartner::class)
                        ->where('detail_orderable_id', $order_partner_edit->id)
                        ->get();
                }
                foreach ($price_menu as $value) {
                    $price_menu_partner += (int) $value->amount * (int) $value->detail['price'];
                }

                $price_for_partner = $price_menu_partner + $price_service_partner;

                $partner_id = $order_partner_edit->partner_id;
                if (!isset($transport_price_map[$partner_id])) {
                    $transport_price_map[$partner_id] = 0;
                }
                $order_partner_edit->price = ($price_for_partner + $transport_price_map[$partner_id])
                    - ($price_for_partner) * $order->percent_manage_partner / 100
                    + ($price_for_partner + $transport_price_map[$partner_id]) * 0.1;
                $order_partner_edit->save();

                // tong gia tri tiec
                $tong_gia_tri_tiec = $price_for_partner + $transport_price_map[$partner_id];
                $service_order = new ServiceOrder();
                $service_order->name = 'Tổng Giá Trị Tiệc';
                $service_order->title = "_default_";
                $service_order->price = $tong_gia_tri_tiec;
                $service_order->service_orderable_type = OrderForPartner::class;
                $service_order->service_orderable_id = $order_partner_edit->id;
                $service_order->save();
                // Phí thuận tiện
                $phi_quan_ly = $price_for_partner * $order->percent_manage_partner / 100;
                $service_order = new ServiceOrder();
                $service_order->name = 'Phí Dịch Vụ (' . $order->percent_manage_partner . '%)';
                $service_order->title = "_default_";
                $service_order->price = $phi_quan_ly;
                $service_order->service_orderable_type = OrderForPartner::class;
                $service_order->service_orderable_id = $order_partner_edit->id;
                $service_order->save();

                // Phí ưu đãi
                $uu_dai = 0;
                // $service_order = new ServiceOrder();
                // $service_order->name = 'Ưu Đãi';
                // $service_order->price = $uu_dai;
                // $service_order->title = "_default_";
                // $service_order->service_orderable_type = OrderForPartner::class;
                // $service_order->service_orderable_id = $order_partner_edit->id;
                // $service_order->save();

                // Phí Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và chưa bao gồm VAT)
                $tong_cong_chua_VAT = $tong_gia_tri_tiec - $phi_quan_ly - $uu_dai;
                $service_order = new ServiceOrder();
                $service_order->name = 'Tổng Giá Trị Cần Thanh Toán (chưa bao gồm VAT)';
                $service_order->price = $tong_cong_chua_VAT;
                $service_order->title = "_default_";
                $service_order->service_orderable_type = OrderForPartner::class;
                $service_order->service_orderable_id = $order_partner_edit->id;
                $service_order->save();

                // VAT
                $VAT = $tong_cong_chua_VAT * 0.1;
                $service_order = new ServiceOrder();
                $service_order->name = 'VAT';
                $service_order->price = $VAT;
                $service_order->title = "_default_";
                $service_order->service_orderable_type = OrderForPartner::class;
                $service_order->service_orderable_id = $order_partner_edit->id;
                $service_order->save();

                // Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và  VAT)
                $tong_cong_VAT = $tong_cong_chua_VAT + $VAT;
                $service_order = new ServiceOrder();
                $service_order->name = 'Tổng Giá Trị Cần Thanh Toán (đã bao gồm VAT)';
                $service_order->price = $tong_cong_VAT;
                $service_order->title = "_default_";
                $service_order->service_orderable_type = OrderForPartner::class;
                $service_order->service_orderable_id = $order_partner_edit->id;
                $service_order->save();
                $order_partner_edit->price = $tong_cong_VAT;
                $order_partner_edit->save();

                if ($sub_order_item->proposale_for_partner) {
                    $sub_order_item->proposale_for_partner->price = $tong_cong_VAT;
                    $sub_order_item->proposale_for_partner->save();
                }
            }
            $price_service_for_customer = 0;
            $service_for_customer = ServiceOrder::where('service_orderable_id', $order_customer->id)
                ->where('service_orderable_type', OrderForCustomer::class)
                ->where(function ($q) {
                    $q->where('title', 'none')
                        ->orWhere('title', null);
                })->get();
            foreach ($service_for_customer as $value) {
                $price_service_for_customer += (int) $value->amount * (int) $value->price;
            }

            $price_for_customer = 0;
            if ($order->type_menu_id == 1) {
                $price_menu = DetailOrder::where('type_more', DetailOrder::TYPE_MORE['BUFFET'])
                    ->where('detail_orderable_type', OrderForCustomer::class)
                    ->where('detail_orderable_id', $order_customer->id)
                    ->get();
            } else {
                $price_menu = DetailOrder::where('type_more', DetailOrder::TYPE_MORE['FOOD'])
                    ->where('detail_orderable_type', OrderForCustomer::class)
                    ->where('detail_orderable_id', $order_customer->id)
                    ->get();
            }

            foreach ($price_menu as $value) {
                $price_for_customer += (int) $value->amount * (int) $value->detail['price'];
            }

            $price = $price_for_customer + $price_service_for_customer;
            // phi van chuyen
            $service_order = new ServiceOrder();
            $service_order->name = 'Phí Vận Chuyển';
            $service_order->price = $total_price_transport;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = OrderForCustomer::class;
            $service_order->service_orderable_id = $order_customer->id;
            $service_order->save();

            // end tính giá menu 
            // tong gia tri tiec
            $tong_gia_tri_tiec = $price + $total_price_transport;
            $service_order = new ServiceOrder();
            $service_order->name = 'Tổng Giá Trị Tiệc';
            $service_order->price = $tong_gia_tri_tiec;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = OrderForCustomer::class;
            $service_order->service_orderable_id = $order_customer->id;
            $service_order->save();


            // Phí thuận tiện
            $phi_quan_ly = $tong_gia_tri_tiec * $order->percent_manage_customer / 100;
            $service_order = new ServiceOrder();
            $service_order->name = 'Phí Thuận Tiện (' . $order->percent_manage_customer . '%)';
            $service_order->price = $phi_quan_ly;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = OrderForCustomer::class;
            $service_order->service_orderable_id = $order_customer->id;
            $service_order->save();

            // Phí ưu đãi
            if (isset($request->voucher_price))
                $uu_dai = $request->voucher_price;
            else
                $uu_dai = 0;
            $service_order = new ServiceOrder();
            $service_order->name = 'Ưu Đãi';
            $service_order->price = $uu_dai;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = OrderForCustomer::class;
            $service_order->service_orderable_id = $order_customer->id;
            $service_order->save();

            // Phí Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và chưa bao gồm VAT)
            $tong_cong_chua_VAT = $tong_gia_tri_tiec + $phi_quan_ly - $uu_dai;
            $service_order = new ServiceOrder();
            $service_order->name = 'Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và chưa bao gồm VAT)';
            $service_order->price = $tong_cong_chua_VAT;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = OrderForCustomer::class;
            $service_order->service_orderable_id = $order_customer->id;
            $service_order->save();

            // VAT
            $VAT = $tong_cong_chua_VAT * 0.1;
            $service_order = new ServiceOrder();
            $service_order->name = 'VAT';
            $service_order->price = $VAT;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = OrderForCustomer::class;
            $service_order->service_orderable_id = $order_customer->id;
            $service_order->save();

            // Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và  VAT)
            $tong_cong_VAT = $tong_cong_chua_VAT + $VAT;
            $service_order = new ServiceOrder();
            $service_order->name = 'Tổng Cộng (Đã bao gồm Phí thuận tiện, Ưu đãi và  VAT)';
            $service_order->price = $tong_cong_VAT;
            $service_order->title = "_default_";
            $service_order->service_orderable_type = OrderForCustomer::class;
            $service_order->service_orderable_id = $order_customer->id;
            $service_order->save();
            //
            $order_for_customer = $order->order_for_customer;
            $order_for_customer->price = $tong_cong_VAT;
            $order_for_customer->save();
            // for customer
            if ($order->proposale) {
                $order->proposale->proposale_for_customer->price = $tong_cong_VAT;
                $order->proposale->proposale_for_customer->save();
            }


            // if ($order->proposale != null) {
            //     $proposale_for_customer = ProposaleForCustomer::find($order->proposale->proposale_for_customer->id);
            //     $proposale_for_customer->price = $order_for_customer->price;
            //     $proposale_for_customer->save();


            //     // for partner
            //     foreach ($order->sub_order as $key => $sub_order_item) {
            //         $order_for_partner = $sub_order_item->order_for_partner;
            //         $proposale_for_partner = ProposaleForPartner::find($sub_order_item->proposale_for_partner->id);
            //         $proposale_for_partner->price = $order_for_partner->price;
            //         $proposale_for_partner->save();
            //     }
            // }
            // if ($order->proposale) {
            //     $customer = $order->order_for_customer->customer;
            //     $data_send = [
            //         'sub_order_id' => null,
            //         'user_id' => $customer->id
            //     ];
            //     $this->sendMailWhenOrEdit($data_send, $order->proposale->id);
            //     $pito_admin = $order->assign_pito_admin;
            //     $data_send = [
            //         'sub_order_id' => null,
            //         'user_id' => $pito_admin->id
            //     ];
            //     $this->sendMailWhenOrEdit($data_send, $order->proposale->id);
            //     foreach ($order->sub_order as $key => $sub_order) {
            //         $partner = $sub_order->order_for_partner->partner;
            //         if ($sub_order->id) {
            //             $data_send = [
            //                 'sub_order_id' => $sub_order->id,
            //                 'user_id' => $partner->id
            //             ];
            //             $this->sendMailWhenOrEdit($data_send, $order->proposale->id);
            //         }
            //     }
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
                'proposale.proposale_for_partner',
                'proposale.proposale_for_partner.detail',
                'proposale.proposale_for_partner.detail.child',
                'order_for_customer',
                'order_for_customer.detail',
                'order_for_customer.detail.child',
                'order_for_customer.customer',
                'order_for_customer.service'
            ])->find($id);
            // if (count($order->order_for_customer->service) <= 7) {
            //     DB::commit();
            //     return AdapterHelper::sendResponse(false, $order, 500, 'fail');
            // }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined', 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, $order, 200, 'success');
    }
}
