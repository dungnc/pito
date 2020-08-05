<?php

namespace App\Http\Controllers\API\Food;

use App\Model\User;
use Illuminate\Support\Str;
use App\Model\MenuFood\Food;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Model\MenuFood\Buffect\Buffect;
use Illuminate\Support\Facades\Validator;
use App\Model\MenuFood\Buffect\MenuBuffect;
use App\Model\MenuFood\Buffect\BuffectPrice;
use App\Model\Setting\Menu\SettingGroupMenu;
use App\Model\Setting\MenuAndStyleHasSetPrice;
use App\Model\Setting\MenuAndStyleHasTypeFood;

/**
 * @group Buffect
 *
 * APIs for Buffect
 */
class BuffectController extends Controller
{

    /**
     * Get List and search buffect for partner.
     * @bodyParam name string search tên món hoặc danh mục của món ăn. Example: Ếch xào
     * @bodyParam menu_id int search theo menu. Example: 1
     * @bodyParam type_menu_id int search theo type_menu cố định hay không cố định. Example: 1
     * @bodyParam option string neu laf category_menu ra danh muc category. Example: category_menu
     */

    public function index(Request $request)
    {
        $user = $request->user();
        if ($request->partner_id) {
            $user = User::find($request->partner_id);
            if (!$user) {
                return AdapterHelper::sendResponse(false, "Not found", 404, "Not found");
            }
        }
        $data = [];
        $group_menu = SettingGroupMenu::get();
        $request->name = convert_unicode($request->name);
        foreach ($group_menu as $key => $value) {
            $tmp = $value->toArray();
            $tmp['menu'] = $user->menu()->where('setting_group_menu_id', $value->id)
                ->with(['buffet' => function ($q) use ($request, $user) {
                    if ($request->name) {
                        $q->where('title', 'LIKE', '%' . $request->name . '%');
                    }
                    return $q->where('partner_id', $user->id)->orderBy('title', 'asc');
                }, 'buffet.menu', 'buffet.style_menu', 'buffet.buffect_price'])
                ->whereHas('buffet', function ($q) use ($request, $user) {
                    if ($request->name) {
                        $q->where('title', 'LIKE', '%' . $request->name . '%');
                    }
                    return $q->where('partner_id', $user->id);
                })
                ->orderBy('name', 'asc')->get();
            $data[] = $tmp;
        }
        if ($request->option == 'category_menu') {
            $data_menu_tmp = $user->menu()->with(['style' => function ($q) use ($user) {
                if ($user) {
                    if ($user->type_role == 'PARTNER') {
                        $q->where('partner_id', $user->id)
                            ->orWhere('partner_id', null)
                            ->get();
                    }
                }
            }])->get()->toArray();
            $data = $data_menu_tmp;
        }
        // $data = []
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }


    /**
     * create buffect.
     * @bodyParam image string required string Anh cua buffect base65.
     * @bodyParam setting_group_menu_id int required nhóm menu. Example: 1
     * @bodyParam menu_id int required danh mục menu. Example: 1
     * @bodyParam title string required Tên buffect. Example: Buffet Nhanh Món Âu
     * @bodyParam description string Mô tả. Example: Buffet Nhanh với các món Âu được chọn lựa phối hợp món. Dụng cụ ăn uống ly, chén, nĩa bằng giấy
     * @bodyParam buffect_price string required json danh sach price. Example: [{"unit":"nguoi","price":190000,"set":10,"json_condition":[{"name":"Nướng","value":2},{"name":"Kèm","value":1},{"name":"Salad","value":1}],"menu_buffect":[{"name":"nuong","description":"","child":[{"name":"ga nuong","description":""}]}]}]
     * @bodyParam min_time_setup integer required thoi gian đặt truoc toi thieu, parse về giây. Example: 2000
     */

    public function create(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            // 'image' => 'required',
            'setting_group_menu_id' => 'required',
            'menu_id' => 'required',
            'title' => 'required',
            // 'min_time_setup' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        if ($request->partner_id) {
            $user = User::find($request->partner_id);
            if (!$user) {
                return AdapterHelper::sendResponse(false, 'Not found', 404, 'Partner Not Found');
            }
        }
        $buffect_prices = json_decode($request->buffect_price);
        if (!$buffect_prices) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Buffect price json fail');
        }

        DB::beginTransaction();
        try {
            //code...
            $buffect = new Buffect();
            $buffect->menu_id = $request->menu_id;
            $buffect->setting_group_menu_id = $request->setting_group_menu_id;
            $buffect->title = convert_unicode($request->title);
            $buffect->min_time_setup = $request->min_time_setup;
            $buffect->is_select_category = $request->is_select_category;
            $buffect->partner_id = $user->id;
            $buffect->description = $request->description;
            // upload file
            if ($request->image) {
                $fileName = $user->id . Str::random(4) . "-" . time();
                $dir = Buffect::$path . $fileName;
                $dir = AdapterHelper::upload_file($request->image, $dir);
                $buffect->image = env('APP_URL') . 'storage/' . $dir;
            }

            $buffect->save();
            $buffect->SKU = AdapterHelper::createSKU([convert_unicode($request->title), $user->id], $buffect->id);
            $buffect->save();
            $this->create_buffect_price($buffect_prices, $buffect->id, $request->is_select_category);

            // $data_option = [
            //     'menu_id' => $request->menu_id,
            //     'style_menu_id' => $request->style_menu_id,
            //     'is_select_food' => $request->is_select_food,
            // ];
            $data = Buffect::with(['buffect_price.menu_buffect.child'])->find($buffect->id);
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, 'Mobile', $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Error undefine', 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }
    /**
     * Delete buffect price.
     * @queryParam id int required id dưa trên option, nếu option là all thì id này là buffect_id ngược lại là id của buffect_price. Example: 1
     * @queryParam option string required option: all laf xoa het price, nguoc lai laf xoa 1 cai. Example: one
     */
    private function delete_buffect_price($id, $option = null)
    {
        if ($option == 'all') {
            $buffet_prices = BuffectPrice::where('buffect_id', $id)->get();
            MenuBuffect::whereIn('buffect_price_id', $buffet_prices->map->id->all())->delete();
            $buffet_prices->delete();
        } else {
            MenuBuffect::whereIn('buffect_price_id', [$id])->delete();
            BuffectPrice::where('id', $id)->delete();
        }
    }

    /**
     * detail buffect.
     */
    public function detail($id)
    {
        $data = Buffect::with(['buffect_price.menu_buffect.child', 'style_menu', 'menu'])->find($id);
        if (!$data) {
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Buffect not found');
        }
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }

    /**
     * Delete buffect menu.
     * @queryParam id int required id dưa trên option, nếu option là all thì id này là buffect_id ngược lại là id của buffect_menu. Example: 1
     * @queryParam option string required option: all laf xoa het menu, nguoc lai laf xoa 1 cai. Example: one
     */
    private function delete_menu_buffect($id, $option = null)
    {
        if ($option == 'all') {
            MenuBuffect::where('buffect_price_id', $id)->delete();
        } else {
            MenuBuffect::where('id', $id)->delete();
        }
    }

    /**
     * create buffect menu.
     * @queryParam id int required id dưa trên option, nếu option là all thì id này là buffect_price_id ngược lại là id của buffect_menu. Example: 1
     * @queryParam option string required option: all laf xoa het menu, nguoc lai laf xoa 1 cai. Example: one
     */
    private function create_menu_buffect($menu_buffects, $buffect_price_id, $option = null)
    {
        try {
            //code...
            // là tiệc bàn hoặc buffet
            if ($option) {
                foreach ($menu_buffects as $key => $menu_buffect_item) {
                    $menu_buffect = new MenuBuffect();
                    $menu_buffect->buffect_price_id = $buffect_price_id;
                    $menu_buffect->name = $menu_buffect_item->name;
                    $menu_buffect->amount = $menu_buffect_item->amount;
                    $menu_buffect->description = $menu_buffect_item->description;
                    $menu_buffect->save();
                    foreach ($menu_buffect_item->child as $key => $child_item) {
                        $child = new MenuBuffect();
                        $child->buffect_price_id = $buffect_price_id;
                        $child->name = $child_item->name;
                        $child->amount = isset($child_item->amount) ? $child_item->amount : null;
                        $child->quantitative = isset($child_item->quantitative) ? $child_item->quantitative : null;
                        $child->description = $child_item->description;
                        $child->parent_id = $menu_buffect->id;
                        $child->food_id = $child_item->food_id;
                        $child->save();
                    }
                }
            } else {
                foreach ($menu_buffects as $key => $child_item) {
                    $child = new MenuBuffect();
                    $child->buffect_price_id = $buffect_price_id;
                    $child->name = $child_item->name;
                    $child->amount = isset($child_item->amount) ? $child_item->amount : null;
                    $child->quantitative = isset($child_item->quantitative) ? $child_item->quantitative : null;
                    $child->description = $child_item->description;
                    $child->food_id = $child_item->food_id;
                    $child->save();
                }
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    private function create_buffect_price($buffect_prices, $buffect_id, $is_select_category = false)
    {
        $buffet = Buffect::find($buffect_id);
        try {
            //code...
            foreach ($buffect_prices as $key => $buffect_price_item) {
                if (isset($buffect_price_item->id) &&  $buffect_price_item->id)
                    $buffect_price = BuffectPrice::find($buffect_price_item->id);
                else
                    $buffect_price = new BuffectPrice();
                $buffect_price->buffect_id = $buffect_id;
                $buffect_price->unit = $buffect_price_item->unit;
                $buffect_price->price = AdapterHelper::CurrencyIntToString($buffect_price_item->price);
                $buffect_price->set = $buffect_price_item->set;
                $buffect_price->description = "";
                $buffect_price->name = "Set " . ($key + 1);
                $amount_dish = 0;
                foreach ($buffect_price_item->json_condition as $key => $description_condition) {
                    $buffect_price->description .= $description_condition->value . " " . $description_condition->name . "/ ";
                    $amount_dish += (int) $description_condition->value;
                }
                $buffect_price->description = $amount_dish . " món: " . $buffect_price->description;
                $buffect_price->description = trim($buffect_price->description, "/ ");
                $buffect_price->json_condition = json_encode($buffect_price_item->json_condition);
                $buffect_price->save();
                $menu_buffects = $buffect_price_item->menu_buffect;
                if ($menu_buffects === null) {
                    return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Buffect price json fail');
                }
                $this->delete_menu_buffect($buffect_price->id, 'all');
                $this->create_menu_buffect($menu_buffects, $buffect_price->id, $is_select_category);
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Update info basic buffect.
     * @bodyParam image string required string Anh cua buffect base64.
     * @bodyParam menu_id int required danh mục menu. Example: 1
     * @bodyParam is_select_food int 1 là có chọn món, không là cố định ko chọn. Example: 1
     * @bodyParam title string required Tên buffect. Example: Buffet Nhanh Món Âu
     * @bodyParam description string Mô tả. Example: Buffet Nhanh với các món Âu được chọn lựa phối hợp món. Dụng cụ ăn uống ly, chén, nĩa bằng giấy
     */
    public function update_step_1(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            //code...
            $user = $request->user();
            $buffect = Buffect::find($id);
            if (!$buffect)
                return AdapterHelper::sendResponse(false, 'Not found', 404, 'Buffect không tồn tại');
            $buffect->menu_id = $request->menu_id;
            $buffect->setting_group_menu_id = $request->setting_group_menu_id;
            $buffect->title = convert_unicode($request->title);
            $buffect->min_time_setup = $request->min_time_setup;
            $buffect->description = $request->description;
            $dir = dirname($_SERVER["SCRIPT_FILENAME"]) . Buffect::$path;
            // upload file
            if ($request->image) {
                $dir_change = str_replace(env('APP_URL') . 'storage/', '', $buffect->image);
                $fileName = $user->id . Str::random(4) . "-" . time();
                $dir = Buffect::$path . $fileName;
                $dir = AdapterHelper::upload_file($request->image, $dir, $dir_change);
                $buffect->image = env('APP_URL') . 'storage/' . $dir;
            }
            $buffect->save();
            $buffect->SKU = AdapterHelper::createSKU([$request->title, $user->id], $buffect->id);
            $buffect->save();
            $data = Buffect::with(['buffect_price.menu_buffect.child', 'style_menu', 'menu'])->find($id);
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, 'Mobile', $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(false, $data, 200, 'success');
    }

    /**
     * update buffect price.
     * @bodyParam buffect_price string required json danh sach price. Example: [{"unit":"nguoi","price":190000,"set":10,"json_condition":[{"name":"Nướng","value":2},{"name":"Kèm","value":1},{"name":"Salad","value":1}]}]
     */
    public function update_step_2(Request $request, $id)
    {
        $buffect_prices = json_decode($request->buffect_price);
        if (!$buffect_prices) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Buffect price json fail');
        }
        DB::beginTransaction();
        try {
            //code...
            $list_id_buffet_price = BuffectPrice::where('buffect_id', $id)->get();
            $list_id_request = [];
            foreach ($buffect_prices as $key => $buffect_price_item) {
                if (isset($buffect_price_item->id) &&  $buffect_price_item->id)
                    $list_id_request[] = $buffect_price_item->id;
            }
            foreach ($list_id_buffet_price as $key => $value) {
                if (!in_array($value->id, $list_id_request))
                    $this->delete_buffect_price($value->id);
            }
            $data = Buffect::find($id);
            $this->create_buffect_price($buffect_prices, $id, $data->is_select_category);
            $data = Buffect::with(['buffect_price.menu_buffect.child', 'style_menu', 'menu'])->find($id);
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, 'Mobile', $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(false, $data, 200, 'success');
    }

    /**
     * update menu buffect .
     * * @bodyParam menu_buffect string required json menu cua buffect. Example: [{"name":"nuong","description":"","child":[{"name":"ga nuong","description":""}]}]
     */
    public function update_step_3(Request $request, $id)
    {
        $menu_buffects = json_decode($request->menu_buffect);
        if (!$menu_buffects) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Menu Buffect json fail');
        }
        DB::beginTransaction();
        try {
            //code...
            $data_option = Buffect::find($id);
            $this->delete_menu_buffect($id, 'all');
            $this->create_menu_buffect($menu_buffects, $id, $data_option);
            $data = Buffect::with(['buffect_price.menu_buffect.child', 'style_menu', 'menu'])->find($id);
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, 'Mobile', $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(false, $data, 200, 'sucess');
    }

    /**
     * delete buffect.
     * @queryParam id int required id của buffect. Example: 1
     */
    public function delete(Request $request, $id)
    {
        $buffect = Buffect::find($id);
        if (!$buffect)
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Buffect không tồn tại');
        DB::beginTransaction();
        try {
            //code...
            // $this->delete_buffect_price($id, 'all');
            $buffect->delete();
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, 'Mobile', $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Error undefine', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, 'Success', 200, 'Success');
    }
}
