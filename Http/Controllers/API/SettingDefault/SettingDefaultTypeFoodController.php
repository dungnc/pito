<?php

namespace App\Http\Controllers\API\SettingDefault;

use App\Model\User;
use App\Model\Order\Order;
use App\Model\MenuFood\Food;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Support\Carbon;
use App\Model\Order\DetailOrder;
use App\Model\DetailUser\Company;
use App\Model\DetailUser\Partner;
use App\Model\Proposale\Proposale;
use App\Model\TypeParty\TypeParty;
use Illuminate\Support\Facades\DB;
use App\Model\Setting\MenuHasStyle;
use App\Http\Controllers\Controller;
use App\Model\MenuFood\CategoryFood;
use App\Model\Order\OrderForPartner;
use App\Model\Order\OrderForCustomer;
use App\Model\Setting\SettingTypeFood;
use App\Model\MenuFood\Buffect\Buffect;
use App\Model\Setting\Menu\SettingMenu;
use App\Model\Setting\SettingStyleMenu;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Model\Setting\Marketing\Marketing;
use App\Model\Setting\ServiceOrderDefault;
use App\Model\Setting\SettingServiceOrder;
use App\Model\Setting\Menu\SettingTypeMenu;
use App\Model\Proposale\ProposaleForPartner;
use App\Model\Setting\Menu\SettingGroupMenu;
use App\Model\Proposale\ProposaleForCustomer;
use App\Model\Setting\Voucher\SettingTypeVoucher;
use App\Model\Setting\Voucher\SettingTypeDiscount;

/**
 * @group Setting default
 *
 * APIs for setting default system
 */
class SettingDefaultTypeFoodController extends Controller
{

    /**
     * Get List setting.
     */

    public function index(Request $request)
    {
        //
        $data = [];
        $data['type_food'] = SettingTypeFood::orderBy('name', 'asc')->get();
        $data['type_party'] = TypeParty::get();
        $data['status_order'] = Order::$const_status;
        $data['status_order_for_customer'] = OrderForCustomer::$const_status;
        $data['status_order_for_partner'] = OrderForPartner::$const_status;
        $data['status_order_partner'] = Proposale::$const_partner_status;
        $data['status_proposale'] = Proposale::$const_status;
        $data['status_proposale_for_customer'] = ProposaleForCustomer::$const_status;

        $data['setting_type_menu'] = SettingTypeMenu::where('parent_id', null)->get();
        $data['setting_group_menu'] = SettingGroupMenu::with(['menu' => function ($q) use ($request) {
            if ($request->user_id) {
                $q->whereHas('partner', function ($q) use ($request) {
                    return $q->where('users.id', $request->user_id);
                });
            }
            return $q;
        }])->whereHas('menu', function ($q) use ($request) {
            if ($request->user_id) {
                $q->whereHas('partner', function ($q) use ($request) {
                    return $q->where('users.id', $request->user_id);
                });
            }
            return $q;
        })->get();
        $data['setting_style_menu'] = SettingStyleMenu::get();
        $user = null;
        // if ($request->user_id) {
        //     $user = User::with(['style_menu'])->find($request->user_id);
        //     if ($user) {
        //         if ($user->type_role == User::$const_type_role['PARTNER']) {
        //             $data['setting_style_menu'] = $user->style_menu;
        //         } else {
        //             $data['setting_style_menu'] = SettingStyleMenu::get();
        //         }
        //     }
        // }
        $data['setting_marketing'] = Marketing::get();
        $data['setting_service_order_default'] = SettingServiceOrder::where('partner_id', null)->get();
        $data['setting_service_order'] = ServiceOrderDefault::get();
        $data['setting_menu'] = [];

        $data['setting_type_vouchers'] = SettingTypeVoucher::get();
        $data['setting_type_discount'] = SettingTypeDiscount::get();
        $data_menu_tmp = SettingMenu::with(['style' => function ($q) use ($user) {
            if ($user) {
                if ($user->type_role == 'PARTNER') {
                    $q->where('partner_id', $user->id)
                        ->orWhere('partner_id', null)
                        ->get();
                }
            }
        }, 'order_field_customize'])->orderBy('_order', 'asc')->get()->toArray();

        $data['setting_menu'] = $data_menu_tmp;
        $data['param'] = $request->all();
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    /**
     * Script change data for develop.
     */

    public function dashboard_pito(Request $request)
    {
        //
        $data = [];
        $data['total_order'] = Order::count();
        $data['order_not_confirm'] = Order::where('status', 1)->count();
        $data['order_pending']     = Order::where('status', 2)->count();
        $data['order_done']        = Order::where('status', 4)->count();
        $data['order_doing']       = Order::where('status', 3)->count();

        $data['order_doing_partner_not_confirm_ticket']   = Order::where('status', 3)
            ->whereHas('sub_order.ticket_start', function ($q) {
                $q->where('date_confirm_arrived', null);
            })
            ->count();
        // khong đuocwj where has date_confirm khác null vì đây là sub_order nhiều partner, sẽ có trường hợp 1 partner chưa xác nhận,partner khác xác nhận
        $data['order_doing_partner_confirm_ticket']       = $data['order_doing']  - $data['order_doing_partner_not_confirm_ticket'];
        $data['customer']          = User::where('type_role', User::$const_type_role['CUSTOMER'])->count();
        $data['partner']           = User::where('type_role', User::$const_type_role['PARTNER'])->count();
        $data['total_order_customer_paymented'] = Order::whereHas('proposale.proposale_for_customer', function ($q) {
            $q->where('is_pay', 1);
        })->count();
        // $data['order_new']         = Order::with(['order_for_customer.customer', 'type_party', 'sub_order.order_for_partner.partner'])
        //     ->select('*')
        //     ->orderBy('id', 'DESC')
        //     ->take(5)->get();

        $date_now = date('Y-m-d H-i-s');
        $time_now_parse_int = (int) date('H') * 60 + (int) date('i') * 60;
        $data['section1'] = [
            'order_of_the_day' => [
                'total_amount' => Order::where('status', 3)->count(),
                'total_price' => ProposaleForCustomer::whereHas('proposale', function ($q) {
                    $q->whereNotIn('proposales.status', [4]);
                })->whereHas('proposale.order', function ($q) {
                    $q->where('orders.status', 3);
                })->sum('proposale_for_customers.price')
            ],
            'order_doing' => [
                'total_amount' => Order::where('status', 3)
                    // ->whereDate('date_start', date('Y-m-d'))
                    ->where('start_time', '<=', $time_now_parse_int)
                    ->where('end_time', '>=', $time_now_parse_int)->count(),
                'total_price' => ProposaleForCustomer::whereHas('proposale', function ($q) {
                    $q->whereNotIn('proposales.status', [3]);
                })->whereHas('proposale.order', function ($q) {
                    $q->where('orders.status', 3);
                })->sum('proposale_for_customers.price')
            ],
            'order_done' => [
                'total_amount' => Order::where('status', 3)
                    // ->whereDate('date_start', date('Y-m-d'))
                    ->where('end_time', '<', $time_now_parse_int)->count(),
                'total_price' => ProposaleForCustomer::whereHas('proposale', function ($q) {
                    $q->whereNotIn('proposales.status', [3]);
                })->whereHas('proposale.order', function ($q) use ($time_now_parse_int) {
                    $q->where('orders.status', 3);
                    $q->where('end_time', '<', $time_now_parse_int);
                })->sum('proposale_for_customers.price')
            ]
        ];

        $orders = Order::with([
            'sub_order.ticket_start',
            'sub_order.proposale_for_partner',
            'sub_order.proposale_for_partner.proposale.proposale_for_customer'
        ])->whereHas('sub_order.proposale_for_partner.proposale')
            ->whereHas('sub_order.proposale_for_partner.proposale.proposale_for_customer')
            ->where('status', 3)->get();
        $data['section1']['order_arrived_right'] = [
            'total_amount' => 0,
            'total_price' => 0
        ];
        $data['section1']['order_arriving_late'] = [
            'total_amount' => 0,
            'total_price' => 0
        ];

        $time_now = time();
        foreach ($orders as $key => $value) {
            $date_start = date('Y-m-d', strtotime($value->date_start));
            $start_time = $value->start_time;
            $minute = $value->start_time / 60;
            $hour_start = (int) $minute / 60;
            $minute_start = (int) $minute % 60;
            $start_time = $hour_start . ":" . $minute_start . ":00";
            $date_start_time = $date_start . " " . $start_time;
            $time_order = strtotime($date_start_time);
            foreach ($value->sub_order as $key => $sub_order) {
                // $data['section1']['order_arrived'];
                $ticket_start = $sub_order->ticket_start;
                if ($ticket_start->date_confirm_arrived !== null) {
                    $time_ticket = strtotime($ticket_start->date_confirm_arrived);
                    if ($time_ticket <= $time_order && $sub_order->proposale_for_partner->proposale) {
                        $data['section1']['order_arrived_right']['total_amount'] += 1;
                        $data['section1']['order_arrived_right']['total_price'] += $sub_order
                            ->proposale_for_partner
                            ->proposale
                            ->proposale_for_customer->price;
                    } else {
                        if ($sub_order->proposale_for_partner->proposale != null) {
                            $data['section1']['order_arriving_late']['total_amount'] += 1;
                            $data['section1']['order_arriving_late']['total_price'] += $sub_order
                                ->proposale_for_partner
                                ->proposale
                                ->proposale_for_customer->price;
                        }
                    }
                } else {
                    if ($time_now > $time_order && $sub_order->proposale_for_partner->proposale != null) {
                        $data['section1']['order_arriving_late']['total_amount'] += 1;
                        $data['section1']['order_arriving_late']['total_price'] += $sub_order
                            ->proposale_for_partner
                            ->proposale
                            ->proposale_for_customer->price;
                    }
                }
            }
        }

        $data['section2'] = [
            'order_not_proposale' => [
                'total_amount' => Order::where('status', 0)->count(),
                'total_price' => 0
            ],
            'order_proposale_customer_not_confirm' => [
                'total_amount' => Order::where('status', 1)
                    ->whereHas('proposale.proposale_for_customer', function ($q) {
                        $q->whereIn('proposale_for_customers.status', [0, 1]);
                    })->count(),
                'total_price' => ProposaleForCustomer::whereHas('proposale', function ($q) {
                    $q->whereNotIn('proposales.status', [4]);
                })->whereHas('proposale.order', function ($q) {
                    $q->where('orders.status', 1);
                })->whereIn('proposale_for_customers.status', [0, 1])
                    ->sum('proposale_for_customers.price')
            ],
            'order_proposale_confirm' => [
                'total_amount' => Order::where('status', 2)
                    ->whereHas('proposale.proposale_for_customer', function ($q) {
                        $q->whereIn('proposale_for_customers.status', [0, 1]);
                    })->count(),
                'total_price' => ProposaleForCustomer::whereHas('proposale', function ($q) {
                    $q->whereNotIn('proposales.status', [4]);
                })->whereHas('proposale.order', function ($q) {
                    $q->where('orders.status', 2);
                })->sum('proposale_for_customers.price')
            ]
        ];
        // doanh thu trong nam
        $data_revenue = [];

        for ($i = 6; $i >= 0; $i--) {
            $date_start = date('Y-m-d', strtotime('-' . $i . ' day'));
            $tmp = ProposaleForPartner::where('status', '>=', 1)
                ->whereHas('proposale.proposale_for_customer', function ($q) {
                    $q->where('status', 2);
                })->whereHas('sub_order.order', function ($q) use ($date_start) {
                    $q->whereDate('date_start', $date_start);
                    $q->where('status', '>=', 1);
                })
                ->get();
            $tmp = $tmp->map->price->all();
            $data_revenue[] = [
                'key' => $date_start,
                'value' => array_sum($tmp)
            ];
        }

        // Khách hàng mới tạo
        $data_customer_created = [];

        for ($i = 6; $i >= 0; $i--) {
            $date_start = date('Y-m-d', strtotime('-' . $i . ' day'));
            $tmp = User::where('type_role', User::$const_type_role['CUSTOMER'])
                ->whereDate('created_at', $date_start)
                ->count();
            $data_customer_created[] = [
                'key' => $date_start,
                'value' => $tmp
            ];
        }

        $data['section3'] = [
            'data_revenue' => $data_revenue,
            'data_customer_created' => $data_customer_created,
        ];

        $data['section4'] = [
            'data_revenue' => User::where('type_role', User::$const_type_role['CUSTOMER'])->count(),
            'data_customer_created' => Company::select('*')->count(),
        ];

        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    public function dashboard_partner(Request $request)
    {
        //
        if ($request->user()->type_role != User::$const_type_role['PARTNER']) {
            return AdapterHelper::sendResponse(true, 'Not found', 404, 'Partner không tồn tại');
        }
        $partner_id = $request->user()->id;

        $data = [];
        $data['total_order'] = ProposaleForPartner::where('status', '>=', 1)
            ->whereHas('proposale.proposale_for_customer', function ($q) {
                $q->where('status', 2);
            })->whereHas('proposale', function ($q) {
                $q->whereNotIn('status', [4]);
            })->where('partner_id', $partner_id)
            ->whereHas('proposale.proposale_for_customer', function ($q) {
                $q->where('status', 2);
            })->count();
        $data['total_revenue'] = 0;
        $list_price = ProposaleForPartner::where('status', '>=', 1)
            ->whereHas('proposale.proposale_for_customer', function ($q) {
                $q->where('status', 2);
            })->where('partner_id', $partner_id)->get();
        foreach ($list_price as $value) {
            $data['total_revenue'] += (int) $value->price;
        }

        $data['order_done'] = ProposaleForPartner::with(['sub_order.order'])
            ->where('partner_id', $partner_id)
            ->whereHas('proposale', function ($q) {
                $q->whereNotIn('status', [4]);
            })
            ->whereHas('proposale.proposale_for_customer', function ($q) {
                $q->where('status', 2);
            })
            ->whereHas('sub_order.order', function ($q) {
                $q->where('status', 4);
            })->where('status', '>=', 1)
            ->count();

        $data['order_doing'] = ProposaleForPartner::with(['sub_order.order'])
            ->where('partner_id', $partner_id)
            ->whereHas('proposale', function ($q) {
                $q->whereNotIn('status', [4]);
            })
            ->whereHas('proposale.proposale_for_customer', function ($q) {
                $q->where('status', 2);
            })
            ->whereHas('sub_order.order', function ($q) {
                $q->where('status', 3);
            })->where('status', '>=', 1)
            ->count();

        $data['order_doing_detail'] = ProposaleForPartner::with([
            'proposale.order',
            'proposale.proposale_for_customer.customer' => function ($q) {
                $q->select(['id', 'name', 'email', 'phone', 'type_role']);
            },
        ])->select('*')
            ->whereHas('sub_order.order', function ($q) {
                $q->where('status', 3);
            })
            ->where('partner_id', $partner_id)
            ->orderBy('id', 'DESC')
            ->take(3)
            ->get();
        // doanh thu trong nam
        $data_revenue = [];
        $months = array(
            1 => 'Jan', 2 => 'Feb',
            3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct',
            11 => 'Nov', 12 => 'Dec'
        );
        $year_revenue = $request->year_revenue ?? date("Y");
        for ($i = 1; $i <= 12; $i++) {
            $tmp = ProposaleForPartner::where('status', '>=', 1)
                ->where('partner_id', $partner_id)
                ->whereHas('proposale.proposale_for_customer', function ($q) {
                    $q->where('status', 2);
                })
                ->whereHas('sub_order.order', function ($q) use ($i, $year_revenue) {
                    $q->whereMonth('date_start',  $i);
                    $q->whereYear('date_start', $year_revenue);
                    $q->where('status', '>=', 1);
                })
                ->get();

            $tmp = $tmp->map->price->all();
            $data_revenue[] = [
                'key' => $months[$i],
                'value' => array_sum($tmp)
            ];
        }

        $month_now = $request->month_now ?? date("m");
        $year_now = $request->year_now ?? date("Y");

        // dump(ProposaleForPartner::with(['sub_order.order'])
        //     ->where('partner_id', $partner_id)
        //     ->whereHas('sub_order.order', function ($q) use ($month_now, $year_now) {
        //         $q->whereMonth('date_start',  $month_now);
        //         $q->whereYear('date_start', $year_now);
        //         $q->where('status', 4);
        //     })->get());
        $data['revenue'] = $data_revenue;
        $data['order_confirmed_now'] = ProposaleForPartner::with(['sub_order.order'])
            ->where('partner_id', $partner_id)
            ->whereHas('sub_order.order', function ($q) use ($month_now, $year_now) {
                $q->whereMonth('date_start',  $month_now);
                $q->whereYear('date_start', $year_now);
                $q->where('status', 2);
            })->count();
        $data['order_doing_now'] = ProposaleForPartner::with(['sub_order.order'])
            ->where('partner_id', $partner_id)
            ->whereHas('sub_order.order', function ($q) use ($month_now, $year_now) {
                $q->whereMonth('date_start',  $month_now);
                $q->whereYear('date_start', $year_now);
                $q->where('status', 3);
            })->count();
        $data['order_done_now'] = ProposaleForPartner::with(['sub_order.order'])
            ->where('partner_id', $partner_id)
            ->whereHas('sub_order.order', function ($q) use ($month_now, $year_now) {
                $q->whereMonth('date_start',  $month_now);
                $q->whereYear('date_start', $year_now);
                $q->where('status', 4);
            })->count();
        $data['order_cancel_now'] = ProposaleForPartner::with(['sub_order.order'])
            ->where('partner_id', $partner_id)
            ->whereHas('sub_order.order', function ($q) use ($month_now, $year_now) {
                $q->whereMonth('date_start',  $month_now);
                $q->whereYear('date_start', $year_now);
                $q->where('status', 5);
            })->count();


        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    private function cal_total_order_group_menu($partner, $group_menu_id, $type_date, $diffrent_date)
    {
        $total = ProposaleForPartner::with(['proposale.order'])
            ->whereHas('proposale', function ($q) {
                $q->whereNotIn('status', [3, 4]);
            })->whereHas('sub_order.order', function ($q) use ($group_menu_id, $type_date, $diffrent_date) {
                if ($type_date == 'days') {
                    $q->whereDate('date_start', '=',  $diffrent_date['start_date']);
                } else {
                    $q->whereDate('date_start', ">=", $diffrent_date['start_date']);
                    $q->whereDate('date_start', "<=", $diffrent_date['end_date']);
                }
                $q->where('setting_group_menu_id', $group_menu_id);
            })->where('partner_id', $partner->id)->get();

        return count($total);
    }

    private function cal_total_revenue_group_menu($partner, $group_menu_id, $type_date, $diffrent_date)
    {
        $total = ProposaleForPartner::whereHas('proposale', function ($q) {
            $q->whereNotIn('status', [3, 4]);
            $q->whereHas('order', function ($q) {
                $q->whereNotIn('status', [4, 5, 10]);
            });
        })->whereHas('sub_order.order', function ($q) use ($group_menu_id, $type_date, $diffrent_date) {
            if ($type_date == 'days') {
                $q->whereDate('date_start',  $diffrent_date['start_date']);
            } else {
                $q->whereDate('date_start', ">=", $diffrent_date['start_date']);
                $q->whereDate('date_start', "<=", $diffrent_date['end_date']);
            }
            $q->where('setting_group_menu_id', $group_menu_id);
        })->where('partner_id', $partner->id)->sum('price');
        return $total;
    }
    private function cal_total_customer_group_menu($partner, $group_menu_id, $type_date, $diffrent_date)
    {
        $list = ProposaleForPartner::with('sub_order.order.order_for_customer')->whereHas('proposale', function ($q) {
            $q->whereNotIn('status', [3, 4]);
        })->whereHas('sub_order.order', function ($q) use ($group_menu_id, $type_date, $diffrent_date) {
            if ($type_date == 'days') {
                $q->whereDate('date_start',  $diffrent_date['start_date']);
            } else {
                $q->whereDate('date_start', ">=", $diffrent_date['start_date']);
                $q->whereDate('date_start', "<=", $diffrent_date['end_date']);
            }
            $q->where('setting_group_menu_id', $group_menu_id);
        })->where('partner_id', $partner->id)->get();
        $total = 0;
        $map_customer_exist = [];
        foreach ($list as $key => $value) {
            if (!isset($map_customer_exist[$value->sub_order->order->order_for_customer->customer_id])) {
                $total++;
                $map_customer_exist[$value->sub_order->order->order_for_customer->customer_id] = 1;
            }
        }

        return $total;
    }

    private function cal_total_customer_new_group_menu($partner, $group_menu_id, $type_date, $diffrent_date)
    {
        $list_old = ProposaleForPartner::with('sub_order.order.order_for_customer')->whereHas('proposale', function ($q) {
            $q->whereNotIn('status', [3, 4]);
        })->whereHas('sub_order.order', function ($q) use ($group_menu_id, $type_date, $diffrent_date) {
            $q->whereDate('date_start', '<', $diffrent_date['start_date']);
            $q->where('setting_group_menu_id', $group_menu_id);
        })->where('partner_id', $partner->id)->get();
        $list_customer_old = [];
        foreach ($list_old as $value) {
            $list_customer_old[] = $value->sub_order->order->order_for_customer->customer_id;
        }
        $list = ProposaleForPartner::with('sub_order.order.order_for_customer')->whereHas('proposale', function ($q) {
            $q->whereNotIn('status', [3, 4]);
        })->whereHas('sub_order.order', function ($q) use ($group_menu_id, $type_date, $diffrent_date, $list_customer_old) {
            if ($type_date == 'days') {
                $q->whereDate('date_start',  $diffrent_date['start_date']);
            } else {
                $q->whereDate('date_start', ">=", $diffrent_date['start_date']);
                $q->whereDate('date_start', "<=", $diffrent_date['end_date']);
            }
            $q->where('setting_group_menu_id', $group_menu_id);
            $q->whereHas('order_for_customer', function ($q) use ($list_customer_old) {
                $q->whereNotIn('customer_id', $list_customer_old);
            });
        })->where('partner_id', $partner->id)->get();
        $total = 0;
        $map_customer_exist = [];
        foreach ($list as $key => $value) {
            if (!isset($map_customer_exist[$value->sub_order->order->order_for_customer->customer_id])) {
                $total++;
                $map_customer_exist[$value->sub_order->order->order_for_customer->customer_id] = 1;
            }
        }

        return $total;
    }

    private function cal_total_order_cancel_group_menu($partner, $group_menu_id, $type_date, $diffrent_date)
    {
        $list = ProposaleForPartner::with('sub_order.order.order_for_customer')->whereHas('proposale', function ($q) {
            $q->whereNotIn('status', [3, 4]);
        })->whereHas('sub_order.order', function ($q) use ($group_menu_id, $type_date, $diffrent_date) {
            if ($type_date == 'days') {
                $q->whereDate('date_start',  $diffrent_date['start_date']);
            } else {
                $q->whereDate('date_start', ">=", $diffrent_date['start_date']);
                $q->whereDate('date_start', "<=", $diffrent_date['end_date']);
            }
            $q->where('setting_group_menu_id', $group_menu_id);
            $q->where(function ($q) {
                $q->where(function ($q) {
                    $q->whereIn('status', [5]);
                    $q->where('reason', 'LIKE', '%Đối Tác:%');
                });
                $q->orWhereIn('status', [10]);
            });
        })->where('partner_id', $partner->id)->get();

        return count($list);
    }

    private function cal_total_order_cancel_by_customer_group_menu($partner, $group_menu_id, $type_date, $diffrent_date)
    {
        $list = ProposaleForPartner::with('sub_order.order.order_for_customer')->whereHas('proposale', function ($q) {
            $q->whereNotIn('status', [3, 4]);
        })->whereHas('sub_order.order', function ($q) use ($group_menu_id, $type_date, $diffrent_date) {
            if ($type_date == 'days') {
                $q->whereDate('date_start',  $diffrent_date['start_date']);
            } else {
                $q->whereDate('date_start', ">=", $diffrent_date['start_date']);
                $q->whereDate('date_start', "<=", $diffrent_date['end_date']);
            }
            $q->where('setting_group_menu_id', $group_menu_id);
            $q->where(function ($q) {
                $q->where(function ($q) {
                    $q->whereIn('status', [5]);
                    $q->where('reason', 'LIKE', '%Khách hàng:%');
                });
                $q->orWhereIn('status', [4]);
            });
        })->where('partner_id', $partner->id)->get();
        return count($list);
    }

    private function cal_index_increase_reduce($old, $new)
    {
        $status = "";
        $status_type = "";
        if (!$old) {
            if (!$new) {
                $status = '0%';
            } else {
                $status = '100%';
            }
            $status_type = "TANG";
        } else {
            $tmp = ($new - $old) * 100 / $old;
            if ($tmp < 0) {
                $status_type = "GIAM";
                $status = (-round($tmp, 2)) . "%";
            } else {
                $status_type = "TANG";
                $status = (round($tmp, 2)) . "%";
            }
        }
        return ['status' => $status, 'status_type' => $status_type];
    }

    private function cal_index_increase_reduce_of_group_menu($olds, $news, $list_group_menu)
    {
        $data = [];
        foreach ($list_group_menu as $key => $value) {
            $tmp = $this->cal_index_increase_reduce($olds[$value], $news[$value]);
            $data[$value] = [
                'value' => number_format((int) $news[$value]),
                'status' => $tmp['status'],
                'status_type' => $tmp['status_type']
            ];
        }

        return $data;
    }

    private function field_search_default_data_chart_dashboard(Request $request)
    {
        $data = [];
        $partner = $request->user();
        $status = [];
        $status_type = [];
        $old_revenue = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $new_revenue = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $old_customer = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $new_customer = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $old_customer_new = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $new_customer_new = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $old_order_cancel = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $new_order_cancel = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $old_order_cancel_by_customer = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $new_order_cancel_by_customer = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $type_date = 'days';
        $date_now = date('Y-m-d');
        $floor_index = -1;
        $ceiling_index = 0;
        $lable_chart = "";
        $request->field_chart_default = $request->field_chart_default ? $request->field_chart_default : 'today';
        switch ($request->field_chart_default) {
            case 'today':
                $type_date = 'days';
                $floor_index = 0;
                break;
            case 'yesterday':
                $type_date = 'days';
                break;
            case '7 days ago':
                $type_date = 'days';
                $floor_index = -6;
                break;
            case '30 days ago':
                $type_date = 'weeks';
                $floor_index = -3;
                break;
            case '90 days ago':
                $type_date = 'months';
                $floor_index = -3;
                // $ceiling_index = 0;
                break;
        }

        for ($i = $floor_index; $i <= $ceiling_index; $i++) {
            $curent = Carbon::now()->add($i, $type_date);
            $diffrent_date = ['start_date' => null, 'end_date' => null];
            switch ($type_date) {
                case 'days':
                    $diffrent_date['start_date'] =  $curent->format('Y-m-d');
                    $diffrent_date['end_date'] =  $curent->format('Y-m-d');
                    $key_label = $curent->startOfWeek()->format('d/m/Y');
                    break;
                case 'weeks':
                    $diffrent_date['start_date'] = $curent->startOfWeek()->format('Y-m-d');
                    $diffrent_date['end_date'] = $curent->endOfWeek()->format('Y-m-d');
                    $key_label = $curent->startOfWeek()->format('d/m/Y') . " - " . $curent->endOfWeek()->format('d/m/Y');
                    break;
                case 'months':
                    $diffrent_date['start_date'] = $curent->startOfMonth()->format('Y-m-d');
                    $diffrent_date['end_date'] = $curent->endOfMonth()->format('Y-m-d');
                    $key_label = $curent->format('m/Y');
                    break;
                case 'years':
                    $diffrent_date['start_date'] = $curent->startOfYear()->format('Y-m-d');
                    $diffrent_date['end_date'] = $curent->endOfYear()->format('Y-m-d');
                    $key_label = $curent->format('Y');
                    break;
            }
            $tong_don_hang_dat_ngay = $this->cal_total_order_group_menu($partner, 1, $type_date, $diffrent_date);
            $tong_don_hang_tiec = $this->cal_total_order_group_menu($partner, 2, $type_date, $diffrent_date);
            $tong_don_hang_bua_an_hang_ngay = $this->cal_total_order_group_menu($partner, 3, $type_date, $diffrent_date);

            $data_res_chart[] = [
                'key' => $key_label,
                'value' => [
                    'dat_ngay' => $tong_don_hang_dat_ngay,
                    'tiec' => $tong_don_hang_tiec,
                    'bua_an_hang_ngay' => $tong_don_hang_bua_an_hang_ngay
                ]
            ];
        }
        $tmp = [];
        foreach ($data_res_chart as $key => $value) {
            $tmp['label'][] = $value['key'];
            $tmp['value']['dat_ngay'][] = $value['value']['dat_ngay'];
            $tmp['value']['tiec'][] = $value['value']['tiec'];
            $tmp['value']['bua_an_hang_ngay'][] = $value['value']['bua_an_hang_ngay'];
        }
        $chart = $tmp;
        return $chart;
    }

    private function field_search_default_dashboard(Request $request)
    {
        $data = [];
        $partner = $request->user();
        $status = [];
        $status_type = [];
        $old_revenue = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $new_revenue = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $old_customer = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $new_customer = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $old_customer_new = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $new_customer_new = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $old_order_cancel = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $new_order_cancel = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $old_order_cancel_by_customer = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $new_order_cancel_by_customer = [
            'dat_ngay' => 0, 'tiec' => 0, 'bua_an_hang_ngay' => 0,
        ];
        $type_date = 'days';
        $date_now = date('Y-m-d');
        $floor_mid = 0;
        $ceiling_mid = 0;
        $floor_index = -1;
        $ceiling_index = 0;
        $lable_chart = "";
        $request->field_chart_default = $request->field_chart_default ? $request->field_chart_default : 'today';
        switch ($request->field_chart_default) {
            case 'today':
                $type_date = 'days';
                $floor_index = -1;
                $floor_mid = -1;
                $ceiling_mid = 0;
                break;
            case 'yesterday':
                $type_date = 'days';
                $ceiling_mid = -2;
                $floor_mid = -2;
                $ceiling_mid = -1;
                $ceiling_index = -1;
                break;
            case '7 days ago':
                $type_date = 'weeks';
                $ceiling_mid = -1;
                break;
            case '30 days ago':
                $type_date = 'months';
                $ceiling_mid = -1;
                break;
            case '90 days ago':
                $type_date = 'months';
                $ceiling_mid = -3;
                $floor_mid = -2;
                $floor_index = -6;
                $ceiling_index = 0;
                break;
        }

        for ($i = $floor_index; $i <= $ceiling_index; $i++) {
            $curent = Carbon::now()->add($i, $type_date);
            $diffrent_date = ['start_date' => null, 'end_date' => null];
            switch ($type_date) {
                case 'days':
                    $diffrent_date['start_date'] =  $curent->format('Y-m-d');
                    $diffrent_date['end_date'] =  $curent->format('Y-m-d');
                    $key_label = $diffrent_date['start_date'];
                    break;
                case 'weeks':
                    $diffrent_date['start_date'] = $curent->startOfWeek()->format('Y-m-d');
                    $diffrent_date['end_date'] = $curent->endOfWeek()->format('Y-m-d');
                    $key_label = $diffrent_date['start_date'] . " - " . $diffrent_date['end_date'];
                    break;
                case 'months':
                    $diffrent_date['start_date'] = $curent->startOfMonth()->format('Y-m-d');
                    $diffrent_date['end_date'] = $curent->endOfMonth()->format('Y-m-d');
                    $key_label = $curent->format('Y-m');
                    break;
                case 'years':
                    $diffrent_date['start_date'] = $curent->startOfYear()->format('Y-m-d');
                    $diffrent_date['end_date'] = $curent->endOfYear()->format('Y-m-d');
                    $key_label = $curent->format('Y');
                    break;
            }
            $tong_tien_dat_ngay = $this->cal_total_revenue_group_menu($partner, 1, $type_date, $diffrent_date);
            $tong_tien_tiec = $this->cal_total_revenue_group_menu($partner, 2, $type_date, $diffrent_date);
            $tong_tien_bua_an_hang_ngay = $this->cal_total_revenue_group_menu($partner, 3, $type_date, $diffrent_date);

            $tong_customer_new_dat_ngay = $this->cal_total_customer_new_group_menu($partner, 1, $type_date, $diffrent_date);
            $tong_customer_new_tiec = $this->cal_total_customer_new_group_menu($partner, 2, $type_date, $diffrent_date);
            $tong_customer_new_bua_an_hang_ngay = $this->cal_total_customer_new_group_menu($partner, 3, $type_date, $diffrent_date);

            $tong_customer_dat_ngay = $this->cal_total_customer_group_menu($partner, 1, $type_date, $diffrent_date);
            $tong_customer_tiec = $this->cal_total_customer_group_menu($partner, 2, $type_date, $diffrent_date);
            $tong_customer_bua_an_hang_ngay = $this->cal_total_customer_group_menu($partner, 3, $type_date, $diffrent_date);

            $tong_order_cancel_dat_ngay = $this->cal_total_order_cancel_group_menu($partner, 1, $type_date, $diffrent_date);
            $tong_order_cancel_tiec = $this->cal_total_order_cancel_group_menu($partner, 2, $type_date, $diffrent_date);
            $tong_order_cancel_bua_an_hang_ngay = $this->cal_total_order_cancel_group_menu($partner, 3, $type_date, $diffrent_date);

            $tong_order_cancel_by_customer_dat_ngay = $this->cal_total_order_cancel_by_customer_group_menu($partner, 1, $type_date, $diffrent_date);
            $tong_order_cancel_by_customer_tiec = $this->cal_total_order_cancel_by_customer_group_menu($partner, 2, $type_date, $diffrent_date);
            $tong_order_cancel_by_customer_bua_an_hang_ngay = $this->cal_total_order_cancel_by_customer_group_menu($partner, 3, $type_date, $diffrent_date);

            if ($i <= $ceiling_mid) {
                dump($diffrent_date);
                $old_revenue = [
                    'dat_ngay' => $old_revenue['dat_ngay'] + $tong_tien_dat_ngay,
                    'tiec' => $old_revenue['tiec'] + $tong_tien_tiec,
                    'bua_an_hang_ngay' => $old_revenue['bua_an_hang_ngay'] + $tong_tien_bua_an_hang_ngay
                ];

                $old_customer = [
                    'dat_ngay' => $old_customer['dat_ngay'] + $tong_customer_dat_ngay,
                    'tiec' => $old_customer['tiec'] + $tong_customer_tiec,
                    'bua_an_hang_ngay' => $old_customer['bua_an_hang_ngay'] + $tong_customer_bua_an_hang_ngay
                ];

                $old_customer_new = [
                    'dat_ngay' => $old_customer_new['dat_ngay'] + $tong_customer_new_dat_ngay,
                    'tiec' => $old_customer_new['tiec'] + $tong_customer_new_tiec,
                    'bua_an_hang_ngay' => $old_customer_new['bua_an_hang_ngay'] + $tong_customer_new_bua_an_hang_ngay
                ];

                $old_order_cancel = [
                    'dat_ngay' => $old_order_cancel['dat_ngay'] + $tong_order_cancel_dat_ngay,
                    'tiec' => $old_order_cancel['tiec'] + $tong_order_cancel_tiec,
                    'bua_an_hang_ngay' => $old_order_cancel['bua_an_hang_ngay'] + $tong_order_cancel_bua_an_hang_ngay
                ];
                $old_order_cancel_by_customer = [
                    'dat_ngay' => $old_order_cancel_by_customer['dat_ngay'] + $tong_order_cancel_by_customer_dat_ngay,
                    'tiec' => $old_order_cancel_by_customer['tiec'] + $tong_order_cancel_by_customer_tiec,
                    'bua_an_hang_ngay' => $old_order_cancel_by_customer['bua_an_hang_ngay'] + $tong_order_cancel_by_customer_bua_an_hang_ngay
                ];
            }

            if ($i >= $floor_mid) {
                dump($diffrent_date);
                $new_revenue = [
                    'dat_ngay' => $new_revenue['dat_ngay'] + $tong_tien_dat_ngay,
                    'tiec' => $new_revenue['tiec'] + $tong_tien_tiec,
                    'bua_an_hang_ngay' => $new_revenue['bua_an_hang_ngay'] + $tong_tien_bua_an_hang_ngay
                ];
                $new_customer = [
                    'dat_ngay' => $new_customer['dat_ngay'] + $tong_customer_dat_ngay,
                    'tiec' => $new_customer['tiec'] + $tong_customer_tiec,
                    'bua_an_hang_ngay' => $new_customer['bua_an_hang_ngay'] + $tong_customer_bua_an_hang_ngay
                ];
                $new_customer_new = [
                    'dat_ngay' => $new_customer_new['dat_ngay'] + $tong_customer_new_dat_ngay,
                    'tiec' => $new_customer_new['tiec'] + $tong_customer_new_tiec,
                    'bua_an_hang_ngay' => $new_customer_new['bua_an_hang_ngay'] + $tong_customer_new_bua_an_hang_ngay
                ];
                $new_order_cancel = [
                    'dat_ngay' => $new_order_cancel['dat_ngay'] + $tong_order_cancel_dat_ngay,
                    'tiec' => $new_order_cancel['tiec'] + $tong_order_cancel_tiec,
                    'bua_an_hang_ngay' => $new_order_cancel['bua_an_hang_ngay'] + $tong_order_cancel_bua_an_hang_ngay
                ];
                $new_order_cancel_by_customer = [
                    'dat_ngay' => $new_order_cancel_by_customer['dat_ngay'] + $tong_order_cancel_by_customer_dat_ngay,
                    'tiec' => $new_order_cancel_by_customer['tiec'] + $tong_order_cancel_by_customer_tiec,
                    'bua_an_hang_ngay' => $new_order_cancel_by_customer['bua_an_hang_ngay'] + $tong_order_cancel_by_customer_bua_an_hang_ngay
                ];
            }
        }
        $index_revenue = $this->cal_index_increase_reduce_of_group_menu($old_revenue, $new_revenue, ['dat_ngay', 'tiec', 'bua_an_hang_ngay']);

        $index_customer = $this->cal_index_increase_reduce_of_group_menu($old_customer, $new_customer, ['dat_ngay', 'tiec', 'bua_an_hang_ngay']);
        $index_customer_new = $this->cal_index_increase_reduce_of_group_menu($old_customer_new, $new_customer_new, ['dat_ngay', 'tiec', 'bua_an_hang_ngay']);
        $index_order_cancel = $this->cal_index_increase_reduce_of_group_menu($old_order_cancel, $new_order_cancel, ['dat_ngay', 'tiec', 'bua_an_hang_ngay']);
        $index_order_cancel_by_customer = $this->cal_index_increase_reduce_of_group_menu($old_order_cancel_by_customer, $new_order_cancel_by_customer, ['dat_ngay', 'tiec', 'bua_an_hang_ngay']);

        $data['chart'] = $this->field_search_default_data_chart_dashboard($request);
        $data['index_revenue'] = $index_revenue;
        $data['index_customer'] = $index_customer;
        $data['index_customer_new'] = $index_customer_new;
        $data['index_order_cancel'] = $index_order_cancel;
        $data['index_order_cancel_by_customer'] = $index_order_cancel_by_customer;
        return $data;
    }

    private function field_search_default_dashboard_v2(Request $request)
    {
        $end_date = Carbon::now();
        $start_date = Carbon::now();
        switch ($request->field_chart_default) {
            case 'today':
                break;
            case 'yesterday':
                $end_date = Carbon::now()->add('-1', 'days');
                $start_date = Carbon::now()->add('-1', 'days');
                break;
            case '7 days ago':
                $start_date = Carbon::now()->add('-6', 'days');
                break;
            case '30 days ago':
                $start_date = Carbon::now()->add('-29', 'days');
                break;
            case '90 days ago':
                $start_date = Carbon::now()->add('-89', 'days');
                break;
        }
        $request_new = $request->merge([
            'end_date' => $end_date->format("Y/m/d"),
            'start_date' => $start_date->format("Y/m/d")
        ]);
        return $this->field_search_custom_dashboard($request_new);
    }

    private function field_search_custom_data_chart_dashboard(Request $request)
    {
        $data = [];
        $partner = $request->user();
        $type_date = 'days';
        $start_date = Carbon::parse($request->start_date);
        $end_date = Carbon::parse($request->end_date);
        $diff_in_day = $start_date->diffInDays($end_date) + 1;
        $type_date = 'days';
        $modulo = 0;
        $diff = 0;
        $type_date_int = 1;
        switch (true) {
            case ($diff_in_day <= 7):
                $type_date = 'days';
                $modulo = 0;
                $type_date_int = 0;
                $diff = $diff_in_day;
                break;
            case ($diff_in_day <= 30):
                $type_date = 'weeks';
                $type_date_int = 6;
                $modulo = $diff_in_day % ($type_date_int + 1);
                $diff = (int) ($diff_in_day / ($type_date_int + 1));
                break;
            case ($diff_in_day <= 365):
                $type_date = 'months';
                $type_date_int = 29;
                $modulo = $diff_in_day % ($type_date_int + 1);
                $diff = (int) ($diff_in_day / ($type_date_int + 1));
                break;
            case ($diff_in_day >= 365):
                $type_date = 'years';
                $type_date_int = 364;
                $modulo = $diff_in_day % ($type_date_int + 1);
                $diff = (int) ($diff_in_day / ($type_date_int + 1));
                break;
        }
        $index_date = $request->start_date;
        // dump($modulo);
        // dump($diff_in_day);
        if ($modulo) {
            $start_date = Carbon::parse($index_date);
            $end_date = Carbon::parse($index_date)->add(($modulo - 1), 'days');
            $index_date = Carbon::parse($index_date)
                ->add("+" . ($modulo), 'days')
                ->format('Y-m-d');
            if ($modulo === 1) {
                $key_label = $start_date->format('d/m/Y');
            } else {
                $key_label = $start_date->format('d/m/Y') . " - " . $end_date->format('d/m/Y');
            }

            $diffrent_date = [
                'start_date' => $start_date->format('Y-m-d'),
                'end_date' => $end_date->format('Y-m-d'),
            ];
            $tong_don_hang_dat_ngay = $this->cal_total_order_group_menu($partner, 1, $type_date, $diffrent_date);
            $tong_don_hang_tiec = $this->cal_total_order_group_menu($partner, 2, $type_date, $diffrent_date);
            $tong_don_hang_bua_an_hang_ngay = $this->cal_total_order_group_menu($partner, 3, $type_date, $diffrent_date);
            $data_res_chart[] = [
                'key' => $key_label,
                'value' => [
                    'dat_ngay' => $tong_don_hang_dat_ngay,
                    'tiec' => $tong_don_hang_tiec,
                    'bua_an_hang_ngay' => $tong_don_hang_bua_an_hang_ngay
                ]
            ];
        } else { }
        if ($diff) {
            for ($i = -$diff; $i < 0; $i++) {
                if ($type_date == 'days') {
                    $start_date = Carbon::parse($index_date)->add($type_date_int, 'days');
                    $end_date = Carbon::parse($index_date)->add($type_date_int, 'days');
                    $key_label = $end_date->format('d/m/Y');
                } else {
                    $start_date = Carbon::parse($index_date);
                    $end_date = Carbon::parse($index_date)->add(($type_date_int), 'days');
                    $key_label = $start_date->format('d/m/Y')
                        . " - " . $end_date->format('d/m/Y');
                }
                $index_date = Carbon::parse($index_date)
                    ->add(($type_date_int + 1), 'days')
                    ->format('Y-m-d');

                $diffrent_date = [
                    'start_date' => $start_date->format('Y-m-d'),
                    'end_date' => $end_date->format('Y-m-d'),
                ];
                $tong_don_hang_dat_ngay = $this->cal_total_order_group_menu($partner, 1, $type_date, $diffrent_date);
                $tong_don_hang_tiec = $this->cal_total_order_group_menu($partner, 2, $type_date, $diffrent_date);
                $tong_don_hang_bua_an_hang_ngay = $this->cal_total_order_group_menu($partner, 3, $type_date, $diffrent_date);
                $data_res_chart[] = [
                    'key' => $key_label,
                    'value' => [
                        'dat_ngay' => $tong_don_hang_dat_ngay,
                        'tiec' => $tong_don_hang_tiec,
                        'bua_an_hang_ngay' => $tong_don_hang_bua_an_hang_ngay
                    ]
                ];
            }
        } else {
            if ($type_date == 'days') {
                $start_date = Carbon::parse($index_date);
                $end_date = Carbon::parse($index_date);
                $key_label = $end_date->format('d/m/Y');
            } else {
                $start_date = Carbon::parse($index_date);
                $end_date = Carbon::parse($index_date)->add(($type_date_int - 1), 'days');
                $key_label = $start_date->format('d/m/Y')
                    . " - " . $end_date->format('d/m/Y');
            }
            $diffrent_date = [
                'start_date' => $start_date->format('Y-m-d'),
                'end_date' => $end_date->format('Y-m-d'),
            ];
            $tong_don_hang_dat_ngay = $this->cal_total_order_group_menu($partner, 1, $type_date, $diffrent_date);
            $tong_don_hang_tiec = $this->cal_total_order_group_menu($partner, 2, $type_date, $diffrent_date);
            $tong_don_hang_bua_an_hang_ngay = $this->cal_total_order_group_menu($partner, 3, $type_date, $diffrent_date);
            $data_res_chart[] = [
                'key' => $key_label,
                'value' => [
                    'dat_ngay' => $tong_don_hang_dat_ngay,
                    'tiec' => $tong_don_hang_tiec,
                    'bua_an_hang_ngay' => $tong_don_hang_bua_an_hang_ngay
                ]
            ];
        }

        $chart = [];
        foreach ($data_res_chart as $key => $value) {
            $chart['label'][] = $value['key'];
            $chart['value']['dat_ngay'][] = $value['value']['dat_ngay'];
            $chart['value']['tiec'][] = $value['value']['tiec'];
            $chart['value']['bua_an_hang_ngay'][] = $value['value']['bua_an_hang_ngay'];
        }
        return $chart;
    }

    private function call_full_increase_dashboard($start_date, $end_date, $partner)
    {
        $data = [];
        $type_date = "weeks";
        $diffrent_date = [
            'start_date' => $start_date,
            'end_date' => $end_date,
        ];
        $tong_tien_dat_ngay = $this->cal_total_revenue_group_menu($partner, 1, $type_date, $diffrent_date);
        $tong_tien_tiec = $this->cal_total_revenue_group_menu($partner, 2, $type_date, $diffrent_date);
        $tong_tien_bua_an_hang_ngay = $this->cal_total_revenue_group_menu($partner, 3, $type_date, $diffrent_date);

        $tong_customer_new_dat_ngay = $this->cal_total_customer_new_group_menu($partner, 1, $type_date, $diffrent_date);
        $tong_customer_new_tiec = $this->cal_total_customer_new_group_menu($partner, 2, $type_date, $diffrent_date);
        $tong_customer_new_bua_an_hang_ngay = $this->cal_total_customer_new_group_menu($partner, 3, $type_date, $diffrent_date);

        $tong_customer_dat_ngay = $this->cal_total_customer_group_menu($partner, 1, $type_date, $diffrent_date);
        $tong_customer_tiec = $this->cal_total_customer_group_menu($partner, 2, $type_date, $diffrent_date);
        $tong_customer_bua_an_hang_ngay = $this->cal_total_customer_group_menu($partner, 3, $type_date, $diffrent_date);

        $tong_order_cancel_dat_ngay = $this->cal_total_order_cancel_group_menu($partner, 1, $type_date, $diffrent_date);
        $tong_order_cancel_tiec = $this->cal_total_order_cancel_group_menu($partner, 2, $type_date, $diffrent_date);
        $tong_order_cancel_bua_an_hang_ngay = $this->cal_total_order_cancel_group_menu($partner, 3, $type_date, $diffrent_date);

        $tong_order_cancel_by_customer_dat_ngay = $this->cal_total_order_cancel_by_customer_group_menu($partner, 1, $type_date, $diffrent_date);
        $tong_order_cancel_by_customer_tiec = $this->cal_total_order_cancel_by_customer_group_menu($partner, 2, $type_date, $diffrent_date);
        $tong_order_cancel_by_customer_bua_an_hang_ngay = $this->cal_total_order_cancel_by_customer_group_menu($partner, 3, $type_date, $diffrent_date);

        $data['revenue'] = [
            'dat_ngay' => $tong_tien_dat_ngay,
            'tiec' => $tong_tien_tiec,
            'bua_an_hang_ngay' =>  $tong_tien_bua_an_hang_ngay
        ];
        $data['customer'] = [
            'dat_ngay' => $tong_customer_dat_ngay,
            'tiec' => $tong_customer_tiec,
            'bua_an_hang_ngay' => $tong_customer_bua_an_hang_ngay
        ];
        $data['customer_new'] = [
            'dat_ngay' => $tong_customer_new_dat_ngay,
            'tiec' => $tong_customer_new_tiec,
            'bua_an_hang_ngay' =>  $tong_customer_new_bua_an_hang_ngay
        ];
        $data['order_cancel'] = [
            'dat_ngay' => $tong_order_cancel_dat_ngay,
            'tiec' => $tong_order_cancel_tiec,
            'bua_an_hang_ngay' => $tong_order_cancel_bua_an_hang_ngay
        ];
        $data['order_cancel_by_customer'] = [
            'dat_ngay' => $tong_order_cancel_by_customer_dat_ngay,
            'tiec' => $tong_order_cancel_by_customer_tiec,
            'bua_an_hang_ngay' => $tong_order_cancel_by_customer_bua_an_hang_ngay
        ];
        return $data;
    }

    private function field_search_custom_increase_dashboard(Request $request)
    {
        $data = [];
        $start_date = Carbon::parse($request->start_date);
        $end_date = Carbon::parse($request->end_date);
        $diff_in_day = $start_date->diffInDays($end_date);

        $data_new = $this->call_full_increase_dashboard(
            $start_date->format('Y-m-d'),
            $end_date->format('Y-m-d'),
            $request->user()
        );
        // dump($start_date->format('Y-m-d') . " " . $end_date->format('Y-m-d'));
        // dump($data_new);
        $start_date = Carbon::parse($request->start_date)->add(-($diff_in_day + 1), 'days');
        $end_date =  Carbon::parse($request->start_date)->add('-1', 'days');
        $data_old = $this->call_full_increase_dashboard(
            $start_date->format('Y-m-d'),
            $end_date->format('Y-m-d'),
            $request->user()
        );
        // dump($start_date->format('Y-m-d') . " " . $end_date->format('Y-m-d'));
        // dump($data_old);
        $data['index_revenue'] = $this->cal_index_increase_reduce_of_group_menu(
            $data_old['revenue'],
            $data_new['revenue'],
            ['dat_ngay', 'tiec', 'bua_an_hang_ngay']
        );
        $data['index_customer'] = $this->cal_index_increase_reduce_of_group_menu(
            $data_old['customer'],
            $data_new['customer'],
            ['dat_ngay', 'tiec', 'bua_an_hang_ngay']
        );
        $data['index_customer_new'] = $this->cal_index_increase_reduce_of_group_menu(
            $data_old['customer_new'],
            $data_new['customer_new'],
            ['dat_ngay', 'tiec', 'bua_an_hang_ngay']
        );
        $data['index_order_cancel'] = $this->cal_index_increase_reduce_of_group_menu(
            $data_old['order_cancel'],
            $data_new['order_cancel'],
            ['dat_ngay', 'tiec', 'bua_an_hang_ngay']
        );
        $data['index_order_cancel_by_customer'] = $this->cal_index_increase_reduce_of_group_menu(
            $data_old['order_cancel_by_customer'],
            $data_new['order_cancel_by_customer'],
            ['dat_ngay', 'tiec', 'bua_an_hang_ngay']
        );
        return $data;
    }
    private function field_search_custom_dashboard(Request $request)
    {
        $data = [];
        $data = $this->field_search_custom_increase_dashboard($request);
        $data['chart'] = $this->field_search_custom_data_chart_dashboard($request);
        return $data;
    }

    public function dashboard_partnerv2(Request $request)
    {
        if ($request->user()->type_role != User::$const_type_role['PARTNER']) {
            return AdapterHelper::sendResponse(true, 'Not found', 404, 'Partner không tồn tại');
        }
        if ($request->field_chart_default != 'custom') {
            $data = $this->field_search_default_dashboard_v2($request);
        } else {
            $data = $this->field_search_custom_dashboard($request);
        }
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }
    public function lazy_job(Request $request)
    {
        DB::beginTransaction();
        try {
            // update nhung đơn hàng nào setting_group_menu_id null sang tieecj
            $orders = Order::where('setting_group_menu_id', null)->update(['setting_group_menu_id' => 2]);
            // cap nhật duy nhất 1 báo giá cho đơn.
            $orders = Order::with(['proposale_all' => function ($q) {
                $q->whereNotIn('status', [3, 4]);
            }])->get();
            foreach ($orders as $key => $value) {
                if (count($value->proposale_all) > 1) {
                    $proposale_active = $value->proposale_all[0];
                    $value->proposale_all()
                        ->where('id', '<>', $proposale_active->id)
                        ->update(['status' => 3]);
                }
            }
            // foreach ($cate as $key => $value) {
            //     $menu = SettingMenu::find($value->menu_id);
            //     if ($menu) {
            //         $value->setting_group_menu_id = $menu->setting_group_menu_id;
            //         $value->save();
            //     }
            // }
            // fix loi thieu ten business
            $user = User::where('type_role', 'PARTNER')->get();
            foreach ($user as $key => $value) {
                if (!$value->detail->business_name) {
                    Partner::where('partner_id', $value->id)->update(['business_name' => $value->name]);
                }
            }
            // update ten 
            $users = User::get();
            foreach ($users as $key => $value) {
                $value->name = convert_unicode($value->name);
                $value->save();
            }

            $partners = Partner::get();
            foreach ($partners as $key => $value) {
                $value->business_name = convert_unicode($value->business_name);
                $value->save();
            }

            // fix full name cua food và category food
            $list_food = Food::get();
            foreach ($list_food as $key => $value) {
                if ($value->name) {
                    $value->name = convert_unicode($value->name);
                    $value->save();
                }
            }
            $list_cate_food = CategoryFood::get();
            foreach ($list_cate_food as $key => $value) {
                if ($value->name) {
                    $value->name = convert_unicode($value->name);
                    $value->save();
                }
            }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());

            return AdapterHelper::sendResponse(false, 'Undefined', 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }
    /**
     * Download file
     * @bodyParam url string required đường dẫn file. Example: ht
     */
    public function download(Request $request)
    {
        if (!$request->url) {
            return;
        }
        $url = explode('storage/', $request->url);
        if (count($url) > 1) {
            $url = $url[1];
        } else {
            $url = $url[0];
        }
        return Storage::download('/public/' . $url);
    }

    /**
     * create setting_style partner.
     * @bodyParam partner_id int required id của partner.Example: 1
     * @bodyParam name string required name.Example: hello
     * @bodyParam menu_id int menu id nếu truyền thì style assign cho menu đó.Example: 1
     */
    public function create_setting_style_menu(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'partner_id' => 'required',
            'name' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            $style = new SettingStyleMenu();
            $style->name = $request->name;
            // $style->partner_id = $request->partner_id;
            $style->descriptions = $request->descriptions ? $request->descriptions : '';
            $style->save();

            $menus = SettingMenu::get();
            foreach ($menus as $key => $menu) {
                $menu->style()->attach($style);
            }
            // if ($request->menu_id) {
            //     $request->style_id = $style->id;
            //     $this->menu_has_style(['style_id' => $style->id, 'menu_id' => $request->menu_id]);
            // }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $style, 200, 'Success');
    }

    public function menu_has_style($request)
    {
        DB::beginTransaction();
        try {
            //code...
            $new_revenue = new MenuHasStyle();
            $new_revenue->menu_id = $request['menu_id'];
            $new_revenue->style_id = $request['style_id'];
            $new_revenue->save();
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, 'success', 200, 'Success');
    }
}
