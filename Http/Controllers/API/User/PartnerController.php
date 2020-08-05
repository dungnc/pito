<?php

namespace App\Http\Controllers\API\User;

use App\Model\User;
use Mockery\Exception;
use App\Model\Order\Order;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use App\Mail\CoopratePartner;
use App\Model\Order\SubOrder;
use App\Traits\AdapterHelper;
use App\Model\SchedulePartner;
use Illuminate\Validation\Rule;
use App\Model\DetailUser\Company;
use App\Model\DetailUser\Partner;
use App\Traits\NotificationHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Model\MenuFood\CategoryFood;
use Illuminate\Support\Facades\Mail;
use App\Model\Order\OrderForCustomer;
use App\Mail\SendWelcomePartnerSignUp;
use App\Model\Setting\SettingTypeFood;
use App\Model\Setting\Menu\SettingMenu;
use App\Model\Setting\SettingStyleMenu;
use Illuminate\Support\Facades\Validator;
use App\Model\Contract\ContractForPartner;
use App\Model\Setting\SettingServiceOrder;
use App\Model\Setting\Menu\SettingTypeMenu;
use App\Model\PartnerHas\PartnerHasMarketing;
use App\Model\PartnerHas\CategoryServiceOrder;
use App\Model\Setting\Marketing\GiftOfPartner;
use App\Model\PartnerHas\PartnerHasSettingMenu;
use App\Model\PartnerHas\PartnerHasServiceOrder;
use App\Http\Controllers\API\User\BankController;
use App\Model\PartnerHas\PartnerHasSettingTypeMenu;
use App\Model\PartnerHas\PartnerHasSettingStyleMenu;
use IWasHereFirst2\LaravelMultiMail\Facades\MultiMail;
use App\Mail\Version2\PartnerRegisterProcess\AcceptPartner;
use App\Mail\Version2\PartnerRegisterProcess\RejectPartner;
use App\Mail\Version2\PartnerRegisterProcess\RegisterPartner;

/**
 * @group Partner
 *
 * APIs for Partner
 */
class PartnerController extends Controller
{


    /**
     * Get search Partner.
     * @bodyParam _lat string  The _lat của địa chỉ nhập vào. Example: 12.32
     * @bodyParam _long string  The _lat của địa chỉ nhập vào. Example: 13.32
     * @bodyParam type_party_id int required id của loại tiệc . Example: 1
     * @bodyParam type_menu_id int required id của loai menu ( menu cố định, menu theo yêu cầu) . Example: 2
     * @bodyParam food string  tên món ăn hoặc tên danh mục . Example: Ga nuong
     * @bodyParam menu_id int  id cua menu (Buffect nhanh, buffect BBQ) . Example: 1
     * @bodyParam style_menu_id int  id cua style menu (Món Á món âu) . Example: 1
     * @bodyParam start_time int  ngày giờ bắt đầu timestamp. Example: 1582533559
     * @bodyParam end_time int  ngày giờ bắt đầu timestamp. Example: 1582544359
     * @bodyParam service_order_default_id int service order search . Example: 1
     * @bodyParam name string  Tên partner. Example: Tà vẹt
     * @bodyParam phone string số điện thoại của partner. Example: 0974922xxx
     * @bodyParam email string email của partner. Example: tavet@gmail.com
     * @bodyParam min_price int gia nho nhat dùng để search nếu menu cố định. Example: 120000
     * @bodyParam max_price int gia lon nhat dùng để search nếu menu cố định. Example: 200000
     * @bodyParam amount int số lượng người hoặc bàn nếu là menu cố định. Example: 1
     * @bodyParam min_time_setup int thời gian đặt trước tối thiểu pass qua giây. Example: 36000 
     */

    public function index(Request $request)
    {

        $user = $request->user();
        $type_party = $request->type_party;
        $query = User::with(['type_party', 'type_menu', 'service_order_default', 'style_menu', 'service_order', 'menu'])
            ->where('type_role', User::$const_type_role['PARTNER']);
        // dd($query->get());
        $query = $query->whereHas('partner', function ($q) use ($type_party) {
            $q->where('_lat', '<>', null);
            $q->where('_long', '<>', null);
            $q->where('address', '<>', null);
            $q->where('is_active', 1);
        });
        if ($type_party) {
            $query = $query->whereHas('type_party', function ($q) use ($type_party) {
                $q->where('type_parties.id', $type_party);
            });
        }

        // if ($request->type_menu_id) {
        //     $type_menu = $request->type_menu_id;
        //     $query = $query->whereHas('type_menu', function ($q) use ($type_menu) {
        //         $q->where('setting_type_menus.id', $type_menu);
        //     });
        // }

        if ($request->style_menu_id) {
            $style_menu = $request->style_menu_id;
            $query = $query->whereHas('style_menu', function ($q) use ($style_menu) {
                $q->where('setting_style_menus.id', $style_menu);
            });
        }
        if ($request->service_order_default_id) {
            $service_order_default = $request->service_order_default_id;
            $query = $query->whereHas('service_order_default', function ($q) use ($service_order_default) {
                $q->where('setting_service_orders.id', $service_order_default);
            });
        }
        if ($request->start_time && $request->end_time) {
            // start time
            $carbonDayStart = CarbonImmutable::createFromTimestamp($request->start_time);
            $dayOfWeek = $carbonDayStart->format('l');
            $start_time = $carbonDayStart->format('H') * 60 * 60 + $carbonDayStart->format('i') * 60;
            //end time
            $carbonDayEnd = CarbonImmutable::createFromTimestamp($request->end_time);
            $end_time = $carbonDayEnd->format('H') * 60 * 60 + $carbonDayStart->format('i') * 60;
            $data_shedule = ['dayOfWeek' => $dayOfWeek, 'startTime' => $start_time, 'endTime' => $end_time];
            $query = $query->whereHas('schedule', function ($q) use ($data_shedule) {
                $q->where('day', $data_shedule['dayOfWeek'])
                    ->where('start_time', '<=', $data_shedule['startTime'])
                    ->where('end_time', '>=', $data_shedule['endTime']);
            });
        }
        if ($request->min_time_setup) {
            $min_time_setup = $request->min_time_setup;
            // if ($request->type_menu == 1) {
            $query = $query->whereHas('buffect.buffect_price', function ($q) use ($min_time_setup) {
                $q->where('min_time_setup', '<=', $min_time_setup);
            });
            // } else {
            //     $query = $query->whereHas('partner', function ($q) use ($min_time_setup) {
            //         $q->where('min_time_setup', '<=', $min_time_setup);
            //     });
            // }
        }
        if ($request->food) {
            $food = $request->food;
            $query = $query->where(function ($q) use ($food) {
                $q->whereHas('category_food', function ($q) use ($food) {
                    $q->where('category_foods.name', 'LIKE', '%' . $food . '%');
                })->orWhere(function ($q) use ($food) {
                    $q->whereHas('food', function ($q) use ($food) {
                        $q->where('foods.name', 'LIKE', '%' . $food . '%');
                    });
                });
            });
        }
        if ($request->min_price && $request->max_price && $request->type_menu == 1) {
            $price = [
                'min_price' => AdapterHelper::CurrencyIntToString($request->min_price),
                'max_price' => AdapterHelper::CurrencyIntToString($request->max_price)
            ];
            $query = $query->whereHas('buffect.buffect_price', function ($q) use ($price) {
                $q->where('price', '>=', $price['min_price']);
                $q->where('price', '<=', $price['max_price']);
            });
        }


        if ($request->type_menu_id == 1) {
            $query = $query->whereHas('buffect', function ($q) use ($request) {
                if ($request->group_menu_id && $request->group_menu_id != "NaN") {
                    $menu_id = $request->menu_id;
                    $q = $q->whereHas('menu', function ($q) use ($request) {
                        $q->where('setting_group_menu_id', $request->group_menu_id);
                    });
                }
                if ($request->menu_id  && $request->menu_id != "NaN"  && $request->group_menu_id &&  $request->group_menu_id != "NaN") {
                    $menu_id = $request->menu_id;
                    $q = $q->where('menu_id', $menu_id);
                }
                if ($request->amount) {
                    $amount = $request->amount;
                    $q = $q->whereHas('buffect_price', function ($q) use ($amount) {
                        $q->where('set', '<=', $amount);
                    });
                }
                if ($request->min_price && $request->max_price) {
                    $price = [
                        'min_price' => AdapterHelper::CurrencyIntToString($request->min_price),
                        'max_price' => AdapterHelper::CurrencyIntToString($request->max_price)
                    ];
                    $q = $q->whereHas('buffect_price', function ($q) use ($price, $request) {
                        $q->where('price', '>=', $price['min_price']);
                        $q->where('price', '<=', $price['max_price']);
                        if ($request->style_menu_id) {
                            $style_menu = $request->style_menu_id;
                            $q = $q->whereHas('menu_buffect', function ($q) use ($style_menu) {
                                $q->where(function ($q) use ($style_menu) {
                                    $q->whereHas('food', function ($q) use ($style_menu) {
                                        $q->where('style_menu_id', $style_menu);
                                    });
                                })->orWhere(function ($q) use ($style_menu) {
                                    $q->whereHas('child.food', function ($q) use ($style_menu) {
                                        $q->where('style_menu_id', $style_menu);
                                    });
                                });
                            });
                        }
                    });
                }
                return $q;
            });
        } else {
            if ($request->food) {
                $food = $request->food;
                $query = $query->whereHas('food', function ($q) use ($food) {
                    $q->where('name', 'LIKE', '%' . $food . "%");
                });
            }
            if ($request->style_menu_id) {
                $style_menu_id = $request->style_menu_id;
                $query = $query->whereHas('food', function ($q) use ($style_menu_id) {
                    $q->where('style_menu_id', $style_menu_id);
                });
            }
        }
        if ($request->_lat != null && $request->_long != null) {
            // query distane
            $query = $query->join('partners', 'users.id', '=', 'partners.partner_id');
            $query = $query->select('users.*');

            $haversine = "Round(1000*(6371 * acos(cos(radians(" . $request->_lat . ")) 
            * cos(radians(`_lat`)) 
            * cos(radians(`_long`) 
            - radians(" . $request->_long . ")) 
            + sin(radians(" . $request->_lat . ")) 
            * sin(radians(`_lat`)))))";
            $query = $query->selectRaw("{$haversine} AS distance");
        }

        if ($request->name) {
            $query = $query->where('name', 'LIKE', '%' . $request->name . '%');
        }

        if ($request->address) {
            $query = $query->where('address', 'LIKE', '%' . $request->address . '%');
        }

        if ($request->phone) {
            $query = $query->where('phone', 'LIKE', '%' . $request->phone . '%');
        }

        if ($request->email) {
            $query = $query->where('email', 'LIKE', '%' . $request->email . '%');
        }

        if ($request->sort_by && $request->sort_type) {
            $query = $query->orderBy($request->sort_by, $request->sort_type);
        } else {
            $query = $query->orderBy('name', 'asc');
        }
        $data = $query->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'Success');
    }

    public function index_not_suitable(Request $request)
    {
        $user = $request->user();
        $type_party = $request->type_party;
        $query = User::with(['type_party', 'type_menu', 'service_order_default', 'style_menu', 'service_order', 'menu'])
            ->where('type_role', User::$const_type_role['PARTNER']);
        // dd($query->get());
        $query = $query->whereHas('partner', function ($q) use ($type_party) {
            $q->where('_lat', '<>', null);
            $q->where('_long', '<>', null);
            $q->where('address', '<>', null);
            $q->where('is_active', 1);
        });
        if ($type_party) {
            $query = $query->whereHas('type_party', function ($q) use ($type_party) {
                $q->where('type_parties.id', $type_party);
            });
        }
        // if ($request->type_menu_id) {
        //     $type_menu = $request->type_menu_id;
        //     $query = $query->whereHas('type_menu', function ($q) use ($type_menu) {
        //         $q->where('setting_type_menus.id', $type_menu);
        //     });
        // }
        if ($request->style_menu_id) {
            $style_menu = $request->style_menu_id;
            $query = $query->whereHas('style_menu', function ($q) use ($style_menu) {
                $q->where('setting_style_menus.id', $style_menu);
            });
        }
        if ($request->service_order_default_id) {
            $service_order_default = $request->service_order_default_id;
            $query = $query->whereHas('service_order_default', function ($q) use ($service_order_default) {
                $q->where('setting_service_orders.id', $service_order_default);
            });
        }
        if ($request->start_time && $request->end_time) {
            // start time
            $carbonDayStart = CarbonImmutable::createFromTimestamp($request->start_time);
            $dayOfWeek = $carbonDayStart->format('l');
            $start_time = $carbonDayStart->format('H') * 60 * 60 + $carbonDayStart->format('i') * 60;
            //end time
            $carbonDayEnd = CarbonImmutable::createFromTimestamp($request->end_time);
            $end_time = $carbonDayEnd->format('H') * 60 * 60 + $carbonDayStart->format('i') * 60;
            $data_shedule = ['dayOfWeek' => $dayOfWeek, 'startTime' => $start_time, 'endTime' => $end_time];
            $query = $query->whereHas('schedule', function ($q) use ($data_shedule) {
                $q->where('day', $data_shedule['dayOfWeek'])
                    ->where('start_time', '<=', $data_shedule['startTime'])
                    ->where('end_time', '>=', $data_shedule['endTime']);
            });
        }
        if ($request->min_time_setup) {
            $min_time_setup = $request->min_time_setup;
            if ($request->type_menu == 1) {
                $query = $query->whereHas('buffect.buffect_price', function ($q) use ($min_time_setup) {
                    $q->where('min_time_setup', '<=', $min_time_setup);
                });
            } else {
                $query = $query->whereHas('partner', function ($q) use ($min_time_setup) {
                    $q->where('min_time_setup', '<=', $min_time_setup);
                });
            }
        }
        if ($request->food) {
            $food = $request->food;
            $query = $query->where(function ($q) use ($food) {
                $q->whereHas('category_food', function ($q) use ($food) {
                    $q->where('category_foods.name', 'LIKE', '%' . $food . '%');
                })->orWhere(function ($q) use ($food) {
                    $q->whereHas('food', function ($q) use ($food) {
                        $q->where('foods.name', 'LIKE', '%' . $food . '%');
                    });
                });
            });
        }
        if ($request->min_price && $request->max_price && $request->type_menu == 1) {
            $price = [
                'min_price' => AdapterHelper::CurrencyIntToString($request->min_price),
                'max_price' => AdapterHelper::CurrencyIntToString($request->max_price)
            ];
            $query = $query->whereHas('buffect.buffect_price', function ($q) use ($price) {
                $q->where('price', '>=', $price['min_price']);
                $q->where('price', '<=', $price['max_price']);
            });
        }

        if ($request->type_menu_id == 1) {
            $query = $query->whereHas('buffect', function ($q) use ($request) {
                if ($request->group_menu_id &&  $request->group_menu_id != "NaN") {
                    // dd("zx");
                    $menu_id = $request->menu_id;
                    $q = $q->whereHas('menu', function ($q) use ($request) {
                        // dd("xx");
                        $q->where('setting_group_menu_id', $request->group_menu_id);
                    });
                }
                if ($request->menu_id &&  $request->menu_id != "NaN" && $request->group_menu_id &&  $request->group_menu_id != "NaN") {
                    $menu_id = $request->menu_id;
                    $q = $q->where('menu_id', $menu_id);
                }
                if ($request->amount) {
                    $amount = $request->amount;
                    $q = $q->whereHas('buffect_price', function ($q) use ($amount) {
                        $q->where('set', '<=', $amount);
                    });
                }
                if ($request->min_price && $request->max_price) {
                    $price = [
                        'min_price' => AdapterHelper::CurrencyIntToString($request->min_price),
                        'max_price' => AdapterHelper::CurrencyIntToString($request->max_price)
                    ];
                    $q = $q->whereHas('buffect_price', function ($q) use ($price, $request) {
                        $q->where('price', '>=', $price['min_price']);
                        $q->where('price', '<=', $price['max_price']);
                        if ($request->style_menu_id) {
                            $style_menu = $request->style_menu_id;
                            $q = $q->whereHas('menu_buffect', function ($q) use ($style_menu) {
                                $q->where(function ($q) use ($style_menu) {
                                    $q->whereHas('food', function ($q) use ($style_menu) {
                                        $q->where('style_menu_id', $style_menu);
                                    });
                                })->orWhere(function ($q) use ($style_menu) {
                                    $q->whereHas('child.food', function ($q) use ($style_menu) {
                                        $q->where('style_menu_id', $style_menu);
                                    });
                                });
                            });
                        }
                    });
                }
                return $q;
            });
        } else {
            if ($request->food) {
                $food = $request->food;
                $query = $query->whereHas('food', function ($q) use ($food) {
                    $q->where('name', 'LIKE', '%' . $food . "%");
                });
            }
            if ($request->style_menu_id) {
                $style_menu_id = $request->style_menu_id;
                $query = $query->whereHas('food', function ($q) use ($style_menu_id) {
                    $q->where('style_menu_id', $style_menu_id);
                });
            }
        }

        $list_suitable = $query->get();

        $list_partner_id_suitable = $list_suitable->map->id->all();
        // 
        $query = User::with(['type_party', 'type_menu', 'service_order_default', 'style_menu', 'service_order', 'menu'])
            ->where('type_role', User::$const_type_role['PARTNER']);
        // dd($query->get());
        $query = $query->whereHas('partner', function ($q) use ($type_party) {
            $q->where('_lat', '<>', null);
            $q->where('_long', '<>', null);
            $q->where('address', '<>', null);
            $q->where('is_active', 1);
        });

        if ($request->_lat != null && $request->_long != null) {
            // query distane
            $query = $query->join('partners', 'users.id', '=', 'partners.partner_id');
            $query = $query->select('users.*');

            $haversine = "Round(1000*(6371 * acos(cos(radians(" . $request->_lat . ")) 
            * cos(radians(`_lat`)) 
            * cos(radians(`_long`) 
            - radians(" . $request->_long . ")) 
            + sin(radians(" . $request->_lat . ")) 
            * sin(radians(`_lat`)))))";
            $query = $query->selectRaw("{$haversine} AS distance");
        }

        $query = $query->whereNotIn('id', $list_partner_id_suitable);

        if ($request->name) {
            $query = $query->where('name', 'LIKE', '%' . $request->name . '%');
        }

        if ($request->address) {
            $query = $query->where('address', 'LIKE', '%' . $request->address . '%');
        }

        if ($request->phone) {
            $query = $query->where('phone', 'LIKE', '%' . $request->phone . '%');
        }

        if ($request->email) {
            $query = $query->where('email', 'LIKE', '%' . $request->email . '%');
        }

        if ($request->sort_by && $request->sort_type) {

            $query = $query->orderBy($request->sort_by, $request->sort_type);
        } else {
            $query = $query->orderBy('name', 'asc');
        }

        $data = $query->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'Success');
    }

    public function not_suitable_detail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'partner_id' => 'required',
            // 'info_bank' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $query = User::with(['type_party', 'type_menu', 'service_order_default', 'style_menu', 'service_order', 'menu'])
            ->where('type_role', User::$const_type_role['PARTNER']);
        $data = $query->whereHas('partner', function ($q) {
            $q->where('_lat', '<>', null);
            $q->where('_long', '<>', null);
            $q->where('address', '<>', null);
            $q->where('is_active', 1);
        })->where('id', $request->partner_id)->first();
        if (!$data) {
            return AdapterHelper::sendResponse(false, 'Error not found', '404', 'Partner not found');
        }
        $res = [];
        if ($request->type_menu_id) {
            $type_menu = $request->type_menu_id;
            $check_type_menu = User::whereHas('type_menu', function ($q) use ($type_menu) {
                $q->where('setting_type_menus.id', $type_menu);
            })->find($request->partner_id);
            $type_menu = SettingTypeMenu::find($request->type_menu_id);
            if (!$check_type_menu) {
                $res[] = [
                    'name' => 'Loại Thực Đơn',
                    'value' => $type_menu ? $type_menu->name : 'none'
                ];
            }
        }
        if ($request->style_menu_id) {
            $style_menu = $request->style_menu_id;
            $check_style_menu = User::whereHas('style_menu', function ($q) use ($style_menu) {
                $q->where('setting_style_menus.id', $style_menu);
            })->find($request->partner_id);
            $style_menu = SettingStyleMenu::find($request->style_menu_id);
            if (!$check_style_menu) {
                $res[] = [
                    'name' => 'Phong Cách Ẩm Thực',
                    'value' => $style_menu ? $style_menu->name : 'none'
                ];
            };
        }

        if ($request->start_time && $request->end_time) {
            // start time
            $carbonDayStart = CarbonImmutable::createFromTimestamp($request->start_time);
            $dayOfWeek = $carbonDayStart->format('l');
            $start_time = $carbonDayStart->format('H') * 60 * 60 + $carbonDayStart->format('i') * 60;
            //end time
            $carbonDayEnd = CarbonImmutable::createFromTimestamp($request->end_time);
            $end_time = $carbonDayEnd->format('H') * 60 * 60 + $carbonDayStart->format('i') * 60;
            $data_shedule = ['dayOfWeek' => $dayOfWeek, 'startTime' => $start_time, 'endTime' => $end_time];
            $check_time = User::whereHas('schedule', function ($q) use ($data_shedule) {
                $q->where('day', $data_shedule['dayOfWeek'])
                    ->where('start_time', '<=', $data_shedule['startTime'])
                    ->where('end_time', '>=', $data_shedule['endTime']);
            })->find($request->partner_id);
            if (!$check_time) {
                $res[] = [
                    'name' => 'Thời Gian Tiệc',
                    'value' => date('Y-m-d H:i:s', $request->start_time) . " " . date('Y-m-d H:i:s', $request->end_time)
                ];
            };
        }
        if ($request->min_price && $request->max_price) {
            $price = [
                'min_price' => AdapterHelper::CurrencyIntToString($request->min_price),
                'max_price' => AdapterHelper::CurrencyIntToString($request->max_price)
            ];
            $check_price = User::whereHas('buffect.buffect_price', function ($q) use ($price) {
                $q->where('price', '>=', $price['min_price']);
                $q->where('price', '<=', $price['max_price']);
            })->find($request->partner_id);
            if (!$check_price) {
                $res[] = [
                    'name' => 'Khoảng Tiền',
                    'value' => number_format($price['min_price']) . " - " . number_format($price['max_price'])
                ];
            };
        }

        if ($request->amount) {
            $amount = $request->amount;
            $check_amout = User::whereHas('buffect.buffect_price', function ($q) use ($amount) {
                $q->where('set', '<=', $amount);
            })->find($request->partner_id);
            if (!$check_amout) {
                $res[] = [
                    'name' => 'Số Lượng',
                    'value' => $amount
                ];
            };
        }
        if ($request->menu_id) {
            $check_menu = User::whereHas('buffect', function ($q) use ($request) {
                if ($request->menu_id) {
                    $menu_id = $request->menu_id;
                    $q = $q->where('menu_id', $menu_id);
                }
            })->find($request->partner_id);

            if (!$check_menu) {
                $res[] = [
                    'name' => 'Thực Đơn Theo Danh Mục',
                    'value' => 'Thực đơn theo ' . SettingMenu::find($request->menu_id)->name
                ];
            };
        }
        $check_buffet_not_menu = User::whereHas('buffect')->find($request->partner_id);
        if (!$check_buffet_not_menu) {
            $res[] = [
                'name' => 'Thực Đơn',
                'value' => 'Không có'
            ];
        };

        $check_full = User::whereHas('buffect', function ($q) use ($request) {
            if ($request->group_menu_id) {
                // dd("zx");
                $menu_id = $request->menu_id;
                $q = $q->whereHas('menu', function ($q) use ($request) {
                    // dd("xx");
                    $q->where('setting_group_menu_id', $request->group_menu_id);
                });
            }
            if ($request->menu_id) {
                $menu_id = $request->menu_id;
                $q = $q->where('menu_id', $menu_id);
            }
            if ($request->amount) {
                $amount = $request->amount;
                $q = $q->whereHas('buffect_price', function ($q) use ($amount) {
                    $q->where('set', '<=', $amount);
                });
            }
            if ($request->min_price && $request->max_price) {
                $price = [
                    'min_price' => AdapterHelper::CurrencyIntToString($request->min_price),
                    'max_price' => AdapterHelper::CurrencyIntToString($request->max_price)
                ];
                $q = $q->whereHas('buffect_price', function ($q) use ($price, $request) {
                    $q->where('price', '>=', $price['min_price']);
                    $q->where('price', '<=', $price['max_price']);
                    if ($request->style_menu_id) {
                        $style_menu = $request->style_menu_id;
                        $q = $q->whereHas('menu_buffect', function ($q) use ($style_menu) {
                            $q->where(function ($q) use ($style_menu) {
                                $q->whereHas('food', function ($q) use ($style_menu) {
                                    $q->where('style_menu_id', $style_menu);
                                });
                            })->orWhere(function ($q) use ($style_menu) {
                                $q->whereHas('child.food', function ($q) use ($style_menu) {
                                    $q->where('style_menu_id', $style_menu);
                                });
                            });
                        });
                    }
                });
            }
            return $q;
        })->find($request->menu_id);
        if (!$check_full) {
            $tmp = [
                'name' => 'Gộp ',
                'value' => 'Không có thực đơn: '
            ];
            if ($request->menu_id) {
                $tmp['name'] .= 'danh mục thực đơn, ';
                $tmp['value'] .= SettingMenu::find($request->menu_id)->name . ", ";
            }
            if ($request->amount) {
                $tmp['name'] .= 'số lượng, ';
                $tmp['value'] .= $request->amount . ", ";
            }

            if ($request->min_price && $request->max_price) {
                $tmp['name'] .= 'khoảng giá, ';
                $tmp['value'] .= number_format($request->min_price) . ' - ' . number_format($request->max_price) . ", ";
            }
            if ($request->style_menu_id) {
                $tmp['name'] .= 'phong cách ẩm thực, ';
                $tmp['value'] .= SettingStyleMenu::find($request->style_menu_id)->name . ", ";
            }
            $tmp['name'] = trim($tmp['name'], ', ') . ":";
            $tmp['value'] = trim($tmp['value'], ', ') . ".";
            $res[] = $tmp;
        }
        return AdapterHelper::sendResponse(true, $res, 200, 'Success');
    }

    /**
     * Get Partner by id.
     * @bodyParam id string required The id of user. Example: 1
     */
    public function show(Request $request, $id)
    {
        $user = User::with([
            'shedule',
            'type_party', 'type_menu',
            'menu', 'style_menu',
            'style_menu', 'marketing',
            'service_order_default', 'marketing',
            'gift', 'list_bank'

        ])->find($id);
        if (!$user) {
            return AdapterHelper::sendResponse(false, 'Not found', 404, "Partner not found");
        }
        return AdapterHelper::sendResponse(true, $user, 200, 'success');
    }

    /**
     * Partner Sign up .
     * @bodyParam name string required Tên đối tác. Example: Thích nhậu quán
     * @bodyParam address string required địa chỉ khách hàng. Example: 33/4 dao tan
     * @bodyParam _lat string required _lat. Example: 107.141421
     * @bodyParam _long string required _long. Example: 106.12313
     * @bodyParam email string required email partner. Example: thicnhau@gmail.com
     * @bodyParam phone string required so dien thoai của partner. Example: 0974922032
     * @bodyParam website string web của partner. Example: now.vn
     * @bodyParam info_bank string required thông tin thẻ ngân hàng của đối tác. Example: 16t1021144 - vietcom - Nguyen Quy
     * @bodyParam KD_image file giấy phép kinh doanh. Example: 0974922xxx
     * @bodyParam VSATTP_image file giấy phép an toàn vệ sinh thực phẩm. Example: tavet@gmail.com
     * @bodyParam category_menu json required json array về các thực đơn. Example: [{"id":1,"name":"Buffect nhanh"},{"id":2,"name":"Buffect cao cấp"}]
     * @bodyParam type_menu json required json array về các loại menu. Example: [{"id":1,"name":"Menu cố định"},{"id":3,"name":"Menu theo yêu cầu"}]
     * @bodyParam style_menu json nếu chọn menu theo yêu cầu thì phải chọn loại. Example: [{"id":4,"name":"Món Việt Nam"},{"id":5,"name":"Món Hoa"}]
     * @bodyParam service json các loại dichj vụ thêm trong order. Example: [{"id":1,"name":"Chỉ trang trí cơ bản theo thức ăn"}]
     * @bodyParam file file file upload menu. Example:
     * @bodyParam VAT string required có hỗ trợ VAT ko. Example: 1
     * @bodyParam promotion string json về marketing. Example: [{"id":1,"name":"tham gia khuuyến mãi"},{"id":2,"name":"quà tặng cho pito"}]
     * @bodyParam gift string quà tặng và điều kiện nếu chọn điều kiện 2. Example: tặng 500000 nếu order trên 20 triệu.
     * @bodyParam KD_type int Loại hình kinh doanh 1:Công ty, 2: hộ gia đình. Example:1
     * @bodyParam VSATTP_status int trạng thái giấy phép 1: có, 2:đang tiến hàng, 3:chưa có. Example: 3
     */

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'address' => 'required',
            '_lat' => 'required',
            '_long' => 'required',
            'email' => ['required', Rule::unique('users')],
            'phone' => ['required', Rule::unique('users')],
            // 'info_bank' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }

        DB::beginTransaction();
        try {
            //code...
            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->name = convert_unicode($request->name);
            $user->email_verified_at = date('Y-m-d');
            $user->password = $request->password ? bcrypt($request->password) : bcrypt('password');
            $user->type_role = User::$const_type_role['PARTNER'];
            $user->social_type = User::$const_social_type['DEFAULT'];
            $user->save();
            $user->assignRole(User::$const_type_role['PARTNER']);
            $partner = new Partner();
            $partner->partner_id = $user->id;
            $partner->address = $request->address;
            $partner->_lat = $request->_lat;
            $partner->_long = $request->_long;
            $partner->people_contact = $request->people_contact;
            $partner->status = 1;
            $partner->website = $request->website;
            $partner->link_facebook = $request->link_facebook;
            $partner->min_time_setup = 12 * 60 * 60;
            // $partner->info_bank = $request->info_bank;
            // $partner->key_bank = $request->key_bank;
            // $partner->owner_name = $request->owner_name;
            $partner->business_name = convert_unicode($request->business_name);
            // $partner->card_number = $request->card_number;
            // $partner->bank_name = $request->bank_name;
            $partner->confirm_VSATTP = $request->confirm_VSATTP;
            $partner->link_facebook = $request->link_facebook;
            $partner->is_admin_create = $request->is_admin_create;
            $partner->vendor_id = AdapterHelper::createSKU([$user->name], $user->id);
            $partner->save();
            $banks = new BankController();
            $request_new = $request->merge([
                'user_id' => $user->id
            ]);
            $res = $banks->update_or_create_full_of_user($request_new);
            if (!$res->getData()->status) {
                DB::rollBack();
                return $res;
            }
            $res = $this->update_step_2($request, $user->id);
            if (!$res->getData()->status) {
                DB::rollBack();
                return $res;
            }
            $res = $this->update_step_3($request, $user->id);
            $res = response()->json($res)->original;
            if (!$res->getData()->status) {
                DB::rollBack();
                return $res;
            }
            $type_foods = SettingTypeFood::get();
            foreach ($type_foods as $key => $type_food) {
                $cate_food = new CategoryFood();
                $cate_food->name = $type_food->name;
                $cate_food->partner_id = $user->id;
                $cate_food->_order = $type_food->_order;
                $cate_food->save();
            }

            $service_orders = ['Nhân sự', 'Bàn ghế', 'Dụng cụ', 'Vận chuyển', 'Khác'];
            foreach ($service_orders as $key => $value) {
                $cate_food = new CategoryServiceOrder();
                $cate_food->name = $value;
                $cate_food->partner_id = $user->id;
                $cate_food->save();
            }
            $pito = User::where('type_role', User::$const_type_role['PITO_ADMIN'])->first();
            $message = "Chào mừng bạn đã đến với PITO.";
            $partner = [
                'email' => $user->email,
                'name' => $user->name,
            ];

            // Mail::to($user->email)->send(new SendWelcomePartnerSignUp($partner, $message, $pito));
            $user = User::with([
                'shedule',
                'type_party', 'type_menu',
                'menu', 'style_menu',
                'style_menu', 'marketing',
                'service_order_default', 'marketing',
                'gift'
            ])->find($user->id);
            $user['token'] = $user->createToken('Login Token')->accessToken;
            MultiMail::from('partners@pito.vn')->to($user->email)->send(new RegisterPartner($user));
            // MultiMail::from('partners@pito.vn')->to('quyproi51vn@gmail.com')->send(new RegisterPartner($user));
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());

            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $user, 200, 'success');
    }

    /**
     * Update step 1 infobasic .
     * @bodyParam name string required Tên đối tác. Example: Thích nhậu quán
     * @bodyParam address string required địa chỉ khách hàng. Example: 33/4 dao tan
     * @bodyParam _lat string required _lat. Example: 107.141421
     * @bodyParam _long string required _long. Example: 106.12313
     * @bodyParam email string required email partner. Example: thicnhau@gmail.com
     * @bodyParam phone string required so dien thoai của partner. Example: 0974922032
     * @bodyParam website string web của partner. Example: now.vn
     * @bodyParam info_bank string required thông tin thẻ ngân hàng của đối tác. Example: 16t1021144 - vietcom - Nguyen Quy
     */
    public function update_step_1(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                Rule::unique('users')->ignore($id),
            ],
            'phone' => [
                'required',
                Rule::unique('users')->ignore($id),
            ],
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            $user = User::find($id);
            if (!$user)
                return AdapterHelper::sendResponse(false, "Not found", 404, "User not found");
            $user->email = $request->email ? $request->email : $user->email;
            $user->phone = $request->phone ? $request->phone : $user->phone;
            $user->name = convert_unicode($request->name ? $request->name : $user->name);
            if ($request->password) {
                $user->password = bcrypt($request->password);
            }
            $user->save();
            $user->assignRole(User::$const_type_role['PARTNER']);
            $partner = Partner::where('partner_id', $id)->first();
            $partner->address = $request->address ? $request->address : $partner->address;
            $partner->_lat = $request->_lat ? $request->_lat : $partner->_lat;
            $partner->_long = $request->_long ? $request->_long : $partner->_long;
            $partner->people_contact = $request->people_contact ? $request->people_contact : $partner->people_contact;
            $partner->website = $request->website ? $request->website : $partner->website;
            $partner->link_facebook = $request->link_facebook ? $request->link_facebook : $partner->link_facebook;
            $partner->business_name = convert_unicode($request->business_name ? $request->business_name : $partner->business_name);
            $partner->card_number = $request->card_number ? $request->card_number : $partner->card_number;
            $partner->min_time_setup = $request->min_time_setup ? $request->min_time_setup : $partner->min_time_setup;;
            $partner->vendor_id = AdapterHelper::createSKU([$user->name], $user->id);
            $partner->save();
            $banks = new BankController();
            $request_new = $request->merge([
                'user_id' => $user->id
            ]);
            $res = $banks->update_or_create_full_of_user($request_new);
            if (!$res->getData()->status) {
                DB::rollBack();
                return $res;
            }
            $user = User::with([
                'shedule', 'list_bank',
                'type_party', 'type_menu',
                'menu', 'style_menu',
                'style_menu', 'marketing',
                'service_order_default', 'marketing',
                'gift'
            ])->find($user->id);
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $user, 200, 'success');
    }

    /**
     *  Update step 2, image KD
     * @bodyParam KD_image file giấy phép kinh doanh. Example: 0974922xxx
     * @bodyParam VSATTP_image file giấy phép an toàn vệ sinh thực phẩm. Example: tavet@gmail.com
     * @bodyParam KD_type int Loại hình kinh doanh 1:Công ty, 2: hộ gia đình. Example:1
     * @bodyParam VSATTP_status int trạng thái giấy phép 1: có, 2:đang tiến hàng, 3:chưa có. Example: 3
     */
    public function update_step_2(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            //code...
            $user = User::find($id);
            if (!$user)
                return AdapterHelper::sendResponse(false, "Not found", 404, "User not found");
            $partner = Partner::where('partner_id', $id)->first();

            if ($request->KD_image) {
                $fileName = $user->id . "-1-" . rand(1, 1000) . "-" . time();
                $dir = Partner::$path . $fileName;
                $dir = AdapterHelper::upload_file($request->KD_image, $dir, $partner->KD_image);
                $partner->KD_image = env('APP_URL') . 'storage/' . $dir;
            }
            if ($request->BHT_image) {
                $fileName = $user->id . "-1-" . rand(1, 1000) . "-" . time();
                $dir = Partner::$path . $fileName;
                $dir = AdapterHelper::upload_file($request->BHT_image, $dir, $partner->BHT_image);
                $partner->BHT_image = env('APP_URL') . 'storage/' . $dir;
            }
            if ($request->VSATTP_image) {
                $fileName = $user->id . "-1-" . rand(1, 1000) . "-" . time();
                $dir = Partner::$path . $fileName;
                $dir = AdapterHelper::upload_file($request->VSATTP_image, $dir, $partner->VSATTP_image);
                $partner->VSATTP_image = env('APP_URL') . 'storage/' . $dir;
            }
            $partner->confirm_VSATTP = $request->confirm_VSATTP;
            $partner->KD_type = $request->KD_type;
            $partner->KD_status = $request->KD_status;
            $partner->VSATTP_status = $request->VSATTP_status;
            $partner->BHT_status = $request->BHT_status;
            $partner->save();
            $user = User::with([
                'shedule',
                'type_party', 'type_menu',
                'menu', 'style_menu',
                'style_menu', 'marketing',
                'service_order_default', 'marketing',
                'gift'
            ])->find($user->id);
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());

            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $user, 200, 'success');
    }

    /**
     * Partner Sign up .
     * @bodyParam service json các loại dichj vụ thêm trong order. Example: [{"id":1,"name":"Chỉ trang trí cơ bản theo thức ăn"}]
     * @bodyParam category_menu json required json array về các thực đơn. Example: [{"id":1,"name":"Buffect nhanh"},{"id":2,"name":"Buffect cao cấp"}]
     * @bodyParam type_menu json required json array về các loại menu. Example: [{"id":1,"name":"Menu cố định"},{"id":3,"name":"Menu theo yêu cầu"}]
     * @bodyParam style_menu json nếu chọn menu theo yêu cầu thì phải chọn loại. Example: [{"id":4,"name":"Món Việt Nam"},{"id":5,"name":"Món Hoa"}]
     */
    public function update_step_3(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            //code...
            $user = User::find($id);
            $service_order_json = json_decode($request->service);
            $category_menu_json = json_decode($request->category_menu);
            // $type_menu_json = json_decode($request->type_menu);
            $style_menu_json = json_decode($request->style_menu);
            if (!$user)
                return AdapterHelper::sendResponse(false, "Not found", 404, "User not found");
            if ($category_menu_json !== null) {
                PartnerHasSettingMenu::where('partner_id', $user->id)->delete();
                foreach ($category_menu_json as $key => $value) {
                    $has_category_menu = new PartnerHasSettingMenu();
                    $has_category_menu->partner_id = $user->id;
                    $has_category_menu->setting_menu_id = $value->id;
                    $has_category_menu->save();
                }
            }
            $json_more = [
                'menu_as_required' => $request->menu_as_required,
                'style_menu' => $style_menu_json,
                'service' => $service_order_json
            ];
            Partner::where('partner_id', $user->id)->update(['json_more' => json_encode($json_more)]);
            // if ($type_menu_json !== null) {
            //     PartnerHasSettingTypeMenu::where('partner_id', $user->id)->delete();
            //     foreach ($type_menu_json as $key => $value) {
            //         $has_category_menu = new PartnerHasSettingTypeMenu();
            //         $has_category_menu->partner_id = $user->id;
            //         $has_category_menu->setting_type_menu_id = $value->id;
            //         $has_category_menu->save();
            //     }
            // }

            // if ($style_menu_json !== null) {
            //     PartnerHasSettingStyleMenu::where('partner_id', $user->id)->delete();
            //     foreach ($style_menu_json as $key => $value) {
            //         $has_category_menu = new PartnerHasSettingStyleMenu();
            //         $has_category_menu->partner_id = $user->id;
            //         $has_category_menu->setting_style_menu_id = $value->id;
            //         $has_category_menu->save();
            //     }
            // }

            // if ($service_order_json !== null) {
            //     PartnerHasServiceOrder::where('partner_id', $user->id)->delete();
            //     foreach ($service_order_json as $key => $value) {
            //         if (!$value->id) {
            //             $value = SettingServiceOrder::create([
            //                 'name' => $value->name,
            //                 'description' => "",
            //                 'partner_id' => $user->id
            //             ]);
            //         }
            //         $has_service_order = new PartnerHasServiceOrder();
            //         $has_service_order->partner_id = $user->id;
            //         $has_service_order->setting_service_order_id = $value->id;
            //         $has_service_order->save();
            //     }
            // }
            $user = User::with([
                'shedule',
                'type_party', 'type_menu',
                'menu', 'style_menu',
                'style_menu', 'marketing',
                'service_order_default', 'marketing',
                'gift'
            ])->find($user->id);
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $user, 200, 'success');
    }

    /**
     * Update step 4, service .
     * @bodyParam service json các loại dichj vụ thêm trong order. Example: [{"id":1,"name":"Chỉ trang trí cơ bản theo thức ăn"}]
     * @bodyParam file file file upload menu. Example:
     * @bodyParam VAT string required có hỗ trợ VAT ko. Example: 1
     * @bodyParam promotion string json về marketing. Example: [{"id":1,"name":"tham gia khuuyến mãi"},{"id":2,"name":"quà tặng cho pito"}]
     * @bodyParam gift string quà tặng và điều kiện nếu chọn điều kiện 2. Example: tặng 500000 nếu order trên 20 triệu.
     */
    public function update_step_4(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            //code...
            $user = User::find($id);
            $promotion_json = json_decode($request->promotion);
            if (!$user)
                return AdapterHelper::sendResponse(false, "Not found", 404, "User not found");

            if ($promotion_json !== null) {
                PartnerHasMarketing::where('partner_id', $user->id)->delete();
                foreach ($promotion_json as $key => $value) {
                    $has_marketing = new PartnerHasMarketing();
                    $has_marketing->partner_id = $user->id;
                    $has_marketing->marketing_id = $value->id;
                    $has_marketing->save();
                }
            }
            if ($request->gift !== null) {
                GiftOfPartner::where('partner_id', $user->id)->delete();
                GiftOfPartner::create([
                    'partner_id' => $user->id,
                    'descriptions' => $request->gift
                ]);
            }
            $partner = Partner::where('partner_id', $user->id)->first();
            $partner->VAT = $request->VAT == "1" ? 1 : 0;
            $partner->save();
            $user = User::with([
                'shedule',
                'type_party', 'type_menu',
                'menu', 'style_menu',
                'style_menu', 'marketing',
                'service_order_default', 'marketing',
                'gift'
            ])->find($user->id);
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $user, 200, 'success');
    }

    /**
     * Update active.
     * @bodyParam list_id json required list id cac user. Example: [1,2,3,4]
     * @bodyParam is_active int required active: 1, deactive: 0. Example: 1
     */

    public function update_active(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'list_id' => 'required',
            'is_active' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            $list_id = json_decode($request->list_id);
            if (!$list_id) {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Json fail');
            }

            $list_partners = Partner::whereIn('partner_id', $list_id)->get();
            foreach ($list_partners as $key => $value) {
                if (!$request->is_active || $request->is_active == 2) {
                    $partner = User::find($value->partner_id);
                    MultiMail::from('partners@pito.vn')->to($partner->email)->send(new RejectPartner($partner));
                    // MultiMail::from('partners@pito.vn')->to('quyproi51vn@gmail.com')->send(new RejectPartner($partner));
                }
                if ($request->is_active == 1) {
                    $partner = User::find($value->partner_id);
                    $contract = ContractForPartner::where('partner_id', $partner->id)->first();
                    if (!$contract) {
                        $url = null;
                        return AdapterHelper::sendResponse(false, 'Vui lòng bổ sung Hợp Đồng của PITO và Đối Tác!', 404, 'Vui lòng bổ sung Hợp Đồng của PITO và Đối Tác!');
                    } else {
                        $url = explode('storage/', $contract->file);
                        if (count($url) > 1) {
                            $url = $url[1];
                        } else {
                            $url = $url[0];
                        }
                    }
                    // MultiMail::from('partners@pito.vn')->to('quyproi51vn@gmail.com')->send(new AcceptPartner($url, $partner));
                    MultiMail::from('partners@pito.vn')->to($partner->email)->send(new AcceptPartner($url, $partner));
                }
            }
            Partner::whereIn('partner_id', $list_id)->update(['is_active' => $request->is_active]);
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }


    /**
     * Get schedule of partner .
     */
    public function get_schedule(Request $request)
    {

        $partner = $request->user();

        $data = $partner->schedule()->get();
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }

    /**
     * Update schedule of partner .
     * @bodyParam schedule json chứa lịch làm việc từ thứ hai đến chủ nhật . Example: [{"day":"Monday","time_json":null,"start_time":25200,"end_time":79200},{"day":"Tuesday","time_json":null,"start_time":25200,"end_time":79200},{"day":"Wednesday","time_json":null,"start_time":25200,"end_time":79200},{"day":"Thursday","time_json":null,"start_time":25200,"end_time":79200},{"day":"Friday","time_json":null,"start_time":25200,"end_time":79200},{"day":"Saturday","time_json":null,"start_time":25200,"end_time":79200},{"day":"Sunday","time_json":null,"start_time":25200,"end_time":79200}]
     */
    public function update_schedule(Request $request)
    {

        $partner = $request->user();

        $new_schedule = $request->schedule;
        if (!$new_schedule) return AdapterHelper::sendResponse(false, "Invalid", 400, "Schedule is invalid");

        $current_schedule = $partner->schedule()->get();

        // Update
        foreach ($new_schedule as $key => $new) {
            foreach ($current_schedule as $current) {
                if (strtolower($new['day']) == strtolower($current->day)) {
                    $current->time_json = json_encode($new['time_json']) ?? null;
                    if (($new['start_time'] != null && $new['end_time'] == null)
                        || ($new['start_time'] == null && $new['end_time'] != null)
                    )
                        return AdapterHelper::sendResponse(false, "Invalid", 400, "Schedule is invalid");

                    $current->start_time = $new['start_time'] ?? 0;
                    $current->end_time = $new['end_time'] ?? 0;

                    //save
                    $current->save();
                    //remove from $new_schedule
                    unset($new_schedule[$key]);
                    //exit current's loop
                    break;
                }
            }
        }

        // Insert new
        if (count($new_schedule) > 0) {
            $list = [];

            foreach ($new_schedule as $schedule) {
                $data = [];
                $data['partner_id'] = $partner->id;
                $data['day'] = $schedule['day'];
                $data['time_json'] = json_encode($schedule['time_json']);
                $data['start_time'] = $schedule['start_time'];
                $data['end_time'] = $schedule['end_time'];
                $list[] = $data;
            }

            SchedulePartner::insert($list);
        }

        $data = $partner->schedule()->get();
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }

    /**
     * Update schedule of partner .
     * @bodyParam date_stop_from date truyen len ngay bat dau ngung nhan tiec. Example: 2020-01-21 07:41:21
     * @bodyParam date_stop_to date truyen len ngay ket thuc ngung nhan tiec. Example: 2020-01-21 07:41:21
     */
    public function update_schedule_v2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_stop_from' => 'required',
            'date_stop_to' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $partner = $request->user();
        $updates = $request->only(['date_stop_from', 'date_stop_to']);
        $partner->partner()->update($updates);

        return AdapterHelper::sendResponse(true, $partner, 200, 'success');
    }

    /**
     * Update promotion .
     * @bodyParam promotion string truyen len promotion dang html. Example: 123ac
     */
    public function promotion(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            //code...
            $user = User::find($id);
            $partner = Partner::where('partner_id', $id)->first();
            $partner->promotion = $request->promotion;
            $partner->save();
            $user = User::with([
                'shedule',
                'type_party', 'type_menu',
                'menu', 'style_menu',
                'style_menu', 'marketing',
                'service_order_default', 'marketing',
                'gift'
            ])->find($user->id);
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $user, 200, 'success');
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
     * recent company
     * @bodyParam partner_id int required id cua partner_id. Example: 12
     */
    public function recent_company(Request $request)
    {
        $partner_id = $request->partner_id;
        $data = Company::whereHas('order.sub_order.order_for_partner', function ($q) use ($partner_id) {
            $q->where('partner_id', $partner_id);
        })->get();
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }

    /**
     * recent customer
     * @bodyParam partner_id int required id cua partner_id. Example: 12
     */
    public function recent_customer(Request $request)
    {
        $partner_id = $request->partner_id;
        $sub_orders = OrderForCustomer::whereHas('order.sub_order.order_for_partner', function ($q) use ($partner_id) {
            $q->where('partner_id', $partner_id);
        })->get();
        $list_id_customer = $sub_orders->map->customer_id->all();
        $data = User::whereIn('id', $list_id_customer)->get();
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }

    /**
     * check email partner exist
     * @bodyParam email int required id cua email. Example: 122@gmail.com
     */
    public function check_email(Request $request)
    {
        $data = User::where('email', $request->email)->where('id', '<>', $request->user_id)->first();
        if ($data != null) {
            return AdapterHelper::sendResponse(false, 'Email đã tồn tại', 400, 'error');
        }
        $data = User::where('phone', $request->phone)->where('id', '<>', $request->user_id)->first();
        if ($data != null) {
            return AdapterHelper::sendResponse(false, 'Số điện thoại đã tồn tại', 400, 'error');
        }
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }
}
