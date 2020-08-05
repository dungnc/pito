<?php

namespace App\Http\Controllers\API\Food;

use App\Model\Role;
use App\Model\User;
use App\Model\Permission;
use App\Exports\FoodExport;
use App\Imports\FoodImport;
use Illuminate\Support\Str;
use App\Model\MenuFood\Food;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use App\Model\SchedulePartner;
use App\Model\DetailUser\Partner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Model\MenuFood\CategoryFood;
use Maatwebsite\Excel\Facades\Excel;
use App\Model\MenuFood\Buffect\Buffect;
use App\Model\Setting\Menu\SettingMenu;
use Illuminate\Support\Facades\Storage;
use App\Model\TypeParty\PartnerHasParty;
use Illuminate\Support\Facades\Validator;
use App\Model\MenuFood\Buffect\BuffectPrice;
use App\Model\Setting\MenuAndStyleHasTypeFood;
use Maatwebsite\Excel\Excel as MaatwebsiteExcel;

/**
 * @group Food
 *
 * APIs for Food
 */
class FoodController2 extends Controller
{

    /**
     * Get List and search food of partner.
     * @bodyParam food string search tên món hoặc danh mục của món ăn. Example: Ếch xào
     * @bodyParam name string search tên set hoặc tên thực đơn. Example: Buffet nhanh cao cấp Âu
     * @bodyParam type_menu_id string truyền id loại menu của cố định hay là menu theo yêu cầu... . Example: 1
     * @bodyParam menu_id int truyền id menu Buffect cao cap, buffect nhanh chi truyen doi voi menu co dinh. Example: 1
     * @bodyParam style_menu_id int truyền id phong cach menu Mon viet, Mon nhat chi truyen doi voi menu co dinh. Example: 1
     * @bodyParam min_time_setup int thời gian đặt trước tối thiểu pass qua giây. Example: 36000 
     * @bodyParam option string neu laf food thì trả về food ngược lại là buffet. Example: food 
     * @bodyParam list_name_category json 1 array name ve category food. Example: food 
     */

    public function index(Request $request)
    {
        //

        $validator = Validator::make($request->all(), [
            // 'image' => 'required',
            'partner_id' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $list_partner_id = json_decode($request->partner_id);
        if (!$list_partner_id) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Json partner fail');
        }
        $user = $request->user();
        if (!$user) {
            return AdapterHelper::sendResponse(false, 'User Not found', 404, 'User Not found');
        }
        if ($request->option == "food") {
            $query = CategoryFood::whereIn('partner_id', $list_partner_id);
            if ($request->list_name_category) {
                $json_list_name_category = json_decode($request->list_name_category);
                if ($json_list_name_category !== null && count($json_list_name_category) > 0) {
                    $query->whereIn('name', $json_list_name_category);
                }
            }
            if ($request->food || $request->style_menu_id || $request->menu_id) {
                if ($request->food) {
                    $food = convert_unicode($request->food);
                    $query = $query->where(function ($q) use ($food) {
                        $q->where('name', 'LIKE', "%" . $food . "%")
                            ->orWhere(function ($qr) use ($food) {
                                $qr->whereHas('food', function ($q) use ($food) {
                                    $q->where('foods.name', 'LIKE', "%" . $food . "%");
                                });
                            });
                    });
                }
                if ($request->style_menu_id) {
                    $style_menu_id = $request->style_menu_id;
                    $query = $query->whereHas('food', function ($q) use ($style_menu_id) {
                        $q->where('style_menu_id',  $style_menu_id);
                    });
                }

                $data_query = [
                    'food' => convert_unicode($request->food),
                    'style_menu_id' => $request->style_menu_id,
                    // 'menu_id' => $request->menu_id,
                    // 'type_food_id' => $request->type_food_id,
                    'is_select_food' => $request->is_select_food,
                    // 'type_menu_id' => $request->type_menu_id,
                ];
                $query = $query->with(['food' => function ($q) use ($data_query) {
                    if ($data_query['food'])
                        $q->where('foods.name', 'LIKE', "%" . $data_query['food'] . "%");
                    if ($data_query['style_menu_id'])
                        $q->where('foods.style_menu_id', $data_query['style_menu_id']);
                    // if ($data_query['type_food_id'])
                    //     $q->where('foods.type_food_id', $data_query['type_food_id']);
                    if ($data_query['is_select_food'] !== null)
                        $q->where('foods.is_select_food', $data_query['is_select_food']);
                    // if ($data_query['type_menu_id'] !== null)
                    //     $q->where('foods.type_menu_id', $data_query['type_menu_id']);
                    $q->orderBy('name', 'asc');
                }, 'food.style_menu']);
            } else {
                $query->with(['food.style_menu']);
            }
            $data = $query->orderBy('name', 'asc')->paginate($request->per_page ? $request->per_page : 15);
        } else {

            $query = BuffectPrice::with(['buffet', 'menu_buffect'])
                ->whereHas('buffet', function ($q) use ($list_partner_id) {
                    return $q->whereIn('partner_id', $list_partner_id);
                });

            $name = convert_unicode($request->name);
            if ($name && $name !== "") {
                $query = $query->where(function ($q) use ($name) {
                    $q->orWhere(function ($qr) use ($name) {
                        $qr->whereHas('buffet', function ($q) use ($name) {
                            return $q->where('title', 'LIKE', '%' . $name . '%');
                        });
                    })->orWhere('name', 'LIKE', '%' . $name . '%');
                    return $q;
                });
            }

            $food = convert_unicode($request->food);
            if ($food && $food !== "") {
                $query = $query->where(function ($query) use ($food) {
                    $query = $query->where(function ($q) use ($food) {
                        $q->whereHas('buffet', function ($q) use ($food) {
                            $q->where('title', 'LIKE', '%' . $food . '%');
                        });
                    });
                    $query = $query->orWhere(function ($q) use ($food) {
                        $q->whereHas('menu_buffect', function ($q) use ($food) {
                            $q->where('name', 'LIKE', '%' . $food . '%');
                        })->orWhere(function ($qr) use ($food) {
                            $qr->whereHas('menu_buffect.child', function ($q) use ($food) {
                                $q->where('name', 'LIKE', '%' . $food . '%');
                            });
                        });
                    });
                });
            }

            if ($request->group_menu_id && $request->group_menu_id !== "") {
                $group_menu_id = $request->group_menu_id;
                $query = $query->whereHas('buffet', function ($q) use ($group_menu_id) {
                    return $q->where('setting_group_menu_id', $group_menu_id);
                });
            }
            if ($request->menu_id && $request->menu_id !== "") {
                $menu_id = $request->menu_id;
                $query = $query->whereHas('buffet', function ($q) use ($menu_id) {
                    return $q->where('menu_id', $menu_id);
                });
            }
            $amount = $request->amount;

            $query = $query->whereHas('buffet')->with(['menu_buffect.child' => function ($q) {
                $q->orderBy('name', 'asc');
            }, 'menu_buffect' => function ($q) {
                $q->orderBy('name', 'asc');
            }]);
            $data = $query->orderBy('set', 'desc')->get();
            $res['suitable'] = [];
            $res['not_suitable'] = [];
            if (!$request->amount || $request->amount == "") {
                $res['suitable'] = $data;
            } else {
                $amount_floor = [];
                foreach ($data as $key => $value) {

                    if (
                        $value->set > $amount || (isset($amount_floor[$value->buffet->partner_id])
                            && $value->set != $amount_floor[$value->buffet->partner_id])
                    ) {
                        $res['not_suitable'][] = $value;
                    } else {
                        $res['suitable'][] = $value;
                        $amount_floor[$value->buffet->partner_id] = $value->set;
                    }
                }
            }
        }
        return AdapterHelper::sendResponse(true, $res, 200, 'Success');
    }

    /**
     * detail food.
     */
    public function detail($id)
    {
        $data = Food::with(['category', 'style_menu'])->find($id);
        if (!$data) {
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Food not found');
        }
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }


    /**
     * index food partner.
     * @bodyParam name string search ten mon an. Example: ech
     */
    public function index_food_of_partner(Request $request, $id)
    {
        $partner = User::find($id);
        if (!$partner || $partner->type_role != User::$const_type_role['PARTNER'])
            return AdapterHelper::sendResponse(false, 'Partner Not found', 404, 'Partner Not found');
        $query = Food::with(['style_menu'])->where('partner_id', $partner->id);
        $request->name = convert_unicode($request->name);
        if ($request->name) {
            $query = $query->where('name', 'LIKE', "%" . $request->name . "%");
        }
        // dd($query->orderBy('name', 'ASC')->toSql());
        $data = $query->orderBy('name', 'ASC')->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'Success');
    }

    /**
     * index all food
     */
    public function list_partner_has_food(Request $request)
    {
        $query = Food::select(['name']);
        if ($request->name) {
            $query = $query->where('name', 'LIKE', "%" . convert_unicode($request->name) . "%");
        }
        $query = $query->whereHas('partner')->where('price', '<>', null)->where('price', '>', 0);
        $data = $query->groupBy('name')->get();
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    /**
     * index all food
     */

    public function partner_price(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'image' => 'required',
            'list_food_name' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $list_food_name = json_decode($request->list_food_name);
        if (!$list_food_name) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Json name food fail');
        }
        foreach ($list_food_name as $key => $value) {
            $food = Food::select(['id', 'name', 'image', 'price', 'SKU', 'DVT', 'quantitative', 'partner_id'])
                ->with(['partner' => function ($q) {
                    $q->select('id', 'name', 'email', 'phone');
                }])->whereHas('partner')
                ->where('name', 'LIKE', '%' . $value->name . '%')
                ->where('price', '<>', null)
                ->where('price', '>', 0)
                ->orderBy('price', 'asc')
                ->get()->toArray();
            usort($food, function ($a, $b) {
                if ((int) $a['price'] == (int) $b['price']) {
                    return strcmp(strtoupper($a['partner']['name']), strtoupper($b['partner']['name']));
                }
                return (int) $a['price'] > (int) $b['price'];
            });
            $list_food_name[$key]->list_food = $food;
            if (isset($value->detail)) {
                $list_food_name[$key]->food = Food::with('partner')->find($value->detail->id);
            } else
                $list_food_name[$key]->food = isset($food[0]) ? $food[0] : [];
        }
        return AdapterHelper::sendResponse(true, $list_food_name, 200, 'Success');
    }

    /**
     * create food.
     * @bodyParam image file required truyen len base64.
     * @bodyParam name string required Ten mon an. Example: Ech xao xa ot
     * @bodyParam category_id int required danh muc mon an. Example: [2,1,3]
     * @bodyParam description string Mo ta thong tin mon an. Example: Mon an ngon
     * @bodyParam menu_id int Id của menu, buffet nhanh, cao cấp. Example: 1
     * @bodyParam style_menu_id int id của style menu Món việt, món hàn. Example: 2
     * @bodyParam price int gia mon an. Example: 50000
     * @bodyParam min_time_setup int thoi gian toi thieu set up. Example: 2000
     * @bodyParam quantitative string Định lượng. Example: 500gam
     * @bodyParam DVT string đon vi tính. Example: khay
     */
    public function create_food(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'image' => 'required',
            'name' => 'required',
            'style_menu_id' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $partner = $request->user();
        if ($request->partner_id) {
            $partner = User::find($request->partner_id);
        }
        if (!$partner || $partner->type_role != User::$const_type_role['PARTNER'])
            return AdapterHelper::sendResponse(false, 'Partner Not found', 404, 'Partner Not found');
        DB::beginTransaction();
        try {
            //code...
            $food = new Food();
            $food->name = convert_unicode($request->name);
            $food->price = AdapterHelper::CurrencyIntToString($request->price);
            $food->partner_id = $partner->id;
            $food->description = $request->description;
            $food->style_menu_id = $request->style_menu_id;
            $food->quantitative = $request->quantitative;
            $food->min_time_setup = $request->min_time_setup ? $request->min_time_setup : 0;
            $food->DVT = $request->DVT;
            $food->status = 1;
            if ($request->image) {
                $dir = Food::$path . $partner->id . "-" . Str::random(4) . time();
                $dir = AdapterHelper::upload_file($request->image, $dir);
                $food->image = env('APP_URL') . 'storage/' . $dir;
            }
            $food->save();
            $food->SKU = AdapterHelper::createSKU([$request->name, $partner->id], $food->id);
            $food->save();
            if ($request->category_id !== null) {
                $category_foods = json_decode($request->category_id);
                foreach ($category_foods as $key => $value) {
                    $category = CategoryFood::find($value);
                    if ($category) {
                        $food->category()->attach($category);
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $food, 200, 'Success');
    }


    /**
     * Update food.
     * @bodyParam image file truyen len base64.
     * @bodyParam name string Ten mon an. Example: Ech xao xa ot
     * @bodyParam description string Mo ta thong tin mon an. Example: Mon an ngon
     * @bodyParam price int gia mon an. Example: 50000
     * @bodyParam style_menu_id int id của style menu Món việt, món hàn. Example: 2
     * @bodyParam min_time_setup int thoi gian toi thieu set up. Example: 2000
     * @bodyParam quantitative string Định lượng. Example: 500gam
     * @bodyParam DVT string đon vi tính. Example: khay
     */
    public function update_food(Request $request, $id)
    {
        $partner = $request->user();
        if ($request->partner_id) {
            $partner = User::find($request->partner_id);
        }
        if (!$partner || $partner->type_role != User::$const_type_role['PARTNER'])
            return AdapterHelper::sendResponse(false, 'Partner Not found', 404, 'Partner Not found');

        DB::beginTransaction();
        try {
            //code...
            $food = Food::find($id);
            $food->name = convert_unicode($request->name);
            $food->price = AdapterHelper::CurrencyIntToString($request->price);
            $food->partner_id = $partner->id;
            $food->description = $request->description;
            $food->style_menu_id = $request->style_menu_id;
            $food->quantitative = $request->quantitative;
            $food->min_time_setup = $request->min_time_setup ? $request->min_time_setup : 0;
            $food->DVT = $request->DVT;
            $food->status = 1;
            if ($request->image) {
                $dir_change = str_replace(env('APP_URL') . 'storage/', '', $food->image);
                $dir = Food::$path . $partner->id . "-" . Str::random(4) . time();
                $dir = AdapterHelper::upload_file($request->image, $dir, $dir_change);
                $food->image = env('APP_URL') . 'storage/' . $dir;
            }

            $food->save();
            $food->SKU = AdapterHelper::createSKU([$request->name, $partner->id], $food->id);
            $food->save();

            DB::table('category_food_food')->where('food_id', $food->id)->delete();
            $category_foods = json_decode($request->category_id);
            foreach ($category_foods as $key => $value) {
                $category = CategoryFood::find($value);
                if ($category) {
                    $food->category()->attach($category);
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $food, 200, 'Success');
    }

    /**
     * Get list category.
     * @bodyParam partner_id int required truyen id partner.Example: 1
     * @bodyParam name string search name cate.Example: qxx
     */
    public function index_category(Request $request)
    {
        // $validator = Validator::make($request->all(), [
        //     'partner_id' => 'required',
        // ]);
        // if ($validator->fails()) {
        //     return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        // }

        $cate_food = new CategoryFood;
        if ($request->name) {
            $cate_food = $cate_food->where('name', "LIKE", "%" . $request->name . "%");
        }
        $cate_food = $cate_food->orderBy('name', 'ASC')->get();
        $data = [];
        foreach ($cate_food->unique('name') as $cate) {
            array_push($data, $cate);
        }

        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    /**
     * create category food
     * @bodyParam name string required ten danh muc. Example: Món nướng
     * @bodyParam icon file icon cua danh muc khong bat buoc (base64).
     * 
     */
    public function create_category_food(Request $request)
    {
        $partner = $request->user();
        if ($request->partner_id) {
            $partner = User::find($request->partner_id);
        }
        if (!$partner || $partner->type_role != User::$const_type_role['PARTNER'])
            return AdapterHelper::sendResponse(false, 'Partner Not found', 404, 'Partner Not found');
        return $this->onCreate($request, $partner);
    }

    public function onCreate($request, $partner)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $name = convert_unicode($request->name);

        $check_exists = CategoryFood::whereRaw("BINARY `name`= ?", [$name])->first();
        if ($check_exists) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, "Danh Mục Món này đã tồn tại");
        }

        DB::beginTransaction();
        try {
            //code...
            $cate_food = new CategoryFood();
            $cate_food->name = convert_unicode($request->name);
            $cate_food->partner_id = $partner->id;
            if ($request->icon) {
                $dir = Food::$path . "/category_food/" . $partner->id . "-" . Str::random(4) . time();
                $dir = AdapterHelper::upload_file($request->icon, $dir);
                $cate_food->icon = env('APP_URL') . 'storage/' . $dir;
            }
            $cate_food->save();
            $cate_food->_order = $cate_food->id;
            $cate_food->save();
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $cate_food, 200, 'Success');
    }

    /**
     * Update category food
     * @bodyParam name string required ten danh muc. Example: Món nướng
     * @bodyParam icon file icon cua danh muc khong bat buoc (base64).
     */
    public function update_category_food(Request $request, $id)
    {
        $partner = $request->user();
        if ($request->partner_id) {
            $partner = User::find($request->partner_id);
        }
        if (!$partner || $partner->type_role != User::$const_type_role['PARTNER'])
            return AdapterHelper::sendResponse(false, 'Partner Not found', 404, 'Partner Not found');

        DB::beginTransaction();
        try {
            //code...
            $cate_food = CategoryFood::find($id);
            $cate_food->name = $request->name;
            if ($request->file) {
                $dir_change = str_replace(env('APP_URL') . 'storage/', '', $cate_food->image);
                $dir = Food::$path . "/category_food/" . $partner->id . "-" . Str::random(4) . time();
                $dir = AdapterHelper::upload_file($request->file, $dir, $dir_change);
                $cate_food->icon = env('APP_URL') . 'storage/' . $dir;
            }

            $cate_food->save();
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $cate_food, 200, 'Success');
    }

    /**
     * Delete category food
     */
    public function delete_category(Request $request, $id)
    {
        $cate_food = CategoryFood::find($id);
        if (!$cate_food)
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Danh mục món ăn không tồn tại');
        DB::beginTransaction();
        try {
            //code...
            $cate_food->delete();
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Error undefine', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, 'Success', 200, 'Success');
    }

    /**
     * Delete food
     */
    public function delete_food(Request $request, $id)
    {
        $food = Food::find($id);
        if (!$food)
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Món ăn không tồn tại');
        DB::beginTransaction();
        try {
            //code...
            $food->delete();
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Error undefine', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, 'success', 200, 'Success');
    }

    /**
     * Update category food
     * @bodyParam file file required file import base64.
     * 
     */
    public function import_excel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $food_error = "";
        DB::beginTransaction();
        try {
            //code...
            $partner = $request->user();
            if ($request->partner_id) {
                $partner = User::find($request->partner_id);
            }
            if (!$partner || $partner->type_role != User::$const_type_role['PARTNER'])
                return AdapterHelper::sendResponse(false, 'Partner Not found', 404, 'Partner Not found');
            $file = AdapterHelper::upload_file($request->file, 'import_food/' . Str::uuid()->toString());
            $food_import = (new FoodImport($partner->id));
            $food_import->import($file, 'public', MaatwebsiteExcel::XLSX);
            if (Storage::disk('public')->exists($file)) {
                Storage::disk('public')->delete($file);
            }
            $food_error = $food_import->getFoodError();
            $food_success = $food_import->getCountSuccess();
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        $message = "Success";
        if ($food_error !== "") {
            $message .= ". Các món ăn bị lỗi: " . $food_error;
        }
        if ($food_success > 0) {
            $message .= ". Số món ăn nhập thành công: " . $food_success;
        }
        return AdapterHelper::sendResponse(true, $message, 200, 'Success');
    }

    public function export_excel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'partner_id' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $list_food = [];
        if (!$request->all) {
            $list_food = json_decode($request->list_food);
            if (!$list_food) {
                return AdapterHelper::sendResponse(false, 'Validation Error', 400, 'Json list food fail');
            }
        }
        DB::beginTransaction();
        try {
            //code...
            $food_export = (new FoodExport($request->partner_id, $list_food, $request->all));
            return Excel::download($food_export, 'FoodExport-' . time() . '.xlsx');
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
    }
}
