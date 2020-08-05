<?php

namespace App\Exports;

use App\Model\MenuFood\Food;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FoodExport implements FromArray, WithHeadings
{
    protected $list_id;
    protected $all;
    protected $partner_id;

    public function __construct($partner_id, array $list_id, $all = false)
    {
        $this->list_id = $list_id;
        $this->partner_id = $partner_id;
        $this->all = $all;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Tên',
            'Danh Mục Món',
            'Phong Cách Ẩm Thực',
            'Đơn Giá',
            'Định Lượng Mỗi Đơn Vị',
            'Thời Gian Đặt Trước Tối Thiểu',
            'Mô Tả'
        ];
    }
    public function array(): array
    {
        $res = [];
        try {
            //code...
            $data = [];
            if ($this->all) {
                $data = Food::where('partner_id', $this->partner_id)->get();
            } else {
                $data = Food::with(['category', 'style_menu'])
                    ->where('partner_id', $this->partner_id)
                    ->whereIn('id', $this->list_id)->get();
            }
            foreach ($data as $key => $value) {
                $category_name = $value->category->map->name->all();
                $category = "";
                foreach ($category_name as $value_name) {
                    $category .= $value_name . ",";
                }
                $category = trim($category, ',');
                $tmp = [
                    'ID' => $value->id,
                    'Tên' => $value->name,
                    'Danh Mục Món' => $category,
                    'Phong Cách Ẩm Thực' => $value->style_menu ? $value->style_menu->name : '',
                    'Đơn Giá' => number_format($value->price),
                    'Định Lượng Mỗi Đơn Vị' => $value->quantitative,
                    'Thời Gian Đặt Trước Tối Thiểu' => $value->min_time_setup / 3600,
                    'Mô Tả' => $value->description
                ];
                $res[] = $tmp;
            }
        } catch (\Throwable $th) {
            throw $th;
        }
        return $res;
    }
}
