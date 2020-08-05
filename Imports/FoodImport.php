<?php

namespace App\Imports;

use Exception;
use App\Model\MenuFood\Food;
use App\Traits\AdapterHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Model\MenuFood\CategoryFood;
use App\Model\Setting\Menu\SettingMenu;
use App\Model\Setting\SettingStyleMenu;
use Maatwebsite\Excel\Concerns\Importable;
use App\Model\Setting\Menu\SettingTypeMenu;
use Maatwebsite\Excel\Concerns\ToCollection;

class FoodImport implements ToCollection
{
    use Importable;
    //		Mô Tả
    protected $partner_id = null;
    protected $food_error = "";
    protected $count_success = 0;

    /**
     * Class constructor.
     */

    public function __construct($partner_id)
    {
        $this->partner_id = $partner_id;
    }

    public function getFoodError()
    {
        return $this->food_error;
    }
    public function getCountSuccess()
    {
        return $this->count_success;
    }
    public function map(Collection $header): array
    {
        $map = [];
        foreach ($header as $key => $row) {
            switch ($row) {
                case "ID":
                    $map['id'] = $key;
                    break;
                case "Tên":
                    $map['name'] = $key;
                    break;
                case "Danh Mục Món":
                    $map['category'] = $key;
                    break;
                case "Phong Cách Ẩm Thực":
                    $map['style_menu'] = $key;
                    break;
                case "Đơn Giá":
                    $map['price'] = $key;
                    break;
                case "Định Lượng Mỗi Đơn Vị":
                    $map['quantitative'] = $key;
                    break;
                case "Thời Gian Đặt Trước Tối Thiểu":
                    $map['min_time_setup'] = $key;
                    break;
                case "Mô Tả":
                    $map['description'] = $key;
                    break;
            }
        }
        return $map;
    }
    public function collection(Collection $rows)
    {
        $map_header = $this->map($rows[0]);
        if (count($map_header) < 8) {
            return;
        }
        foreach ($rows as $key => $row) {
            if ($key > 0) {
                try {
                    DB::beginTransaction();
                    if (!$row[$map_header['name']]) {
                        continue;
                    }
                    //code...
                    if (!$row[$map_header['id']]) {
                        $food = new Food();
                    } else {
                        $food = Food::find($row[$map_header['id']]);

                        if (!$food || $food->partner_id != $this->partner_id) {
                            $food = new Food();
                        } else {
                            DB::table('category_food_food')->where('food_id', $food->id)->delete();
                        }
                    }

                    $food->name = convert_unicode($row[$map_header['name']]);
                    $food->price = AdapterHelper::CurrencyIntToString($row[$map_header['price']]);
                    $food->partner_id = $this->partner_id;

                    $food->description = $row[$map_header['description']];
                    $food->status = 1;

                    $food->quantitative = $row[$map_header['quantitative']];
                    $food->min_time_setup = (float) $row[$map_header['min_time_setup']] ? (float) $row[$map_header['min_time_setup']] * 3600 : 4 * 3600;

                    $food->save();
                    $food->SKU = AdapterHelper::createSKU([$row[$map_header['name']]], $food->id);
                    $food->save();

                    $categorys = explode(",", convert_unicode($row[$map_header['category']]));
                    foreach ($categorys as $key => $value) {
                        $category = CategoryFood::where('name', 'LIKE', trim($value))->first();
                        if ($category) {
                            $food->category()->attach($category);
                        } else {
                            $category = new CategoryFood();
                            $category->name = $value;
                            $category->partner_id = $this->partner_id;
                            $category->_order = 1;
                            $category->save();
                            $food->category()->attach($category);
                        }
                    }
                    if (!$row[$map_header['style_menu']] || trim($row[$map_header['style_menu']]) == '') {
                        $row[$map_header['style_menu']] = 'Thuần Việt';
                    }
                    $style_menu = SettingStyleMenu::where('name', 'LIKE', convert_unicode($row[$map_header['style_menu']]))->first();
                    if (!$style_menu) {
                        $this->food_error .= " " . $row[$map_header['name']] . ": Phong cách ẩm thực không tồn tại,";
                        DB::rollBack();
                        continue;
                    }
                    $style_menu_id = $style_menu ? $style_menu->id : 1;
                    $food->style_menu_id = $style_menu_id;

                    $food->save();
                    $this->count_success++;
                    DB::commit();
                } catch (\Throwable $th) {
                    $this->food_error .= " " . $row[$map_header['name']] . ": Không xác định,";
                    throw $th;
                }
            }
        }
    }
}
