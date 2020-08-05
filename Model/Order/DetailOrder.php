<?php

namespace App\Model\Order;

use App\Model\User;
use Illuminate\Database\Eloquent\Model;
use App\Model\MenuFood\Buffect\BuffectPrice;
use App\Model\MenuFood\Food;
class DetailOrder extends Model
{
    protected $fillable = [
        'id', 'detail_orderable_id', 'detail_orderable_type', 'name', 'DVT', 'amount', 'unit_price', 'total_price', 'created_at', 'updated_at', 'note', 'parent_id'
    ];
    protected $hidden = [];
    protected $appends = ['detail'];
    public function imageable()
    {
        return $this->morphTo();
    }

    public function child()
    {
        return $this->hasMany(DetailOrder::class, 'parent_id', 'id');
    }

    const TYPE_MORE = [
        // Discount's event
        "BUFFET" => "buffet_price",
        "FOOD" => 'food'
    ];




    public function getDetailAttribute()
    {
        $detail = [
            'id' => null,
            'name' => null,
            'price' => null,
            'unit' => null,
        ];
        if ($this->type_more == self::TYPE_MORE['BUFFET']) {
            $buffet_price = BuffectPrice::with('buffet')->find($this->id_more);
            if ($buffet_price) {
                $detail = [
                    'id' => $buffet_price->id,
                    'name' => $buffet_price->buffet->title,
                    'price' => $buffet_price->price,
                    'unit' => $buffet_price->unit,
                    'is_select_category' => $buffet_price->buffet->is_select_category,
                    'partner_id' => $buffet_price->buffet->partner_id,
                    'partner' => User::find($buffet_price->buffet->partner_id)
                ];
            }
        }
        if ($this->type_more == self::TYPE_MORE['FOOD']) {
            $food = Food::with('partner')->find($this->id_more);
            $detail = $food;
            // if ($detail) {
            //     $detail = [
            //         'id' => $buffet_price->id,
            //         'name' => $buffet_price->buffet->title,
            //         'price' => $buffet_price->price,
            //         'unit' => $buffet_price->unit,
            //         'is_select_category' => $buffet_price->buffet->is_select_category,
            //         'partner_id' => $buffet_price->buffet->partner_id,
            //         'partner' => User::find($buffet_price->buffet->partner_id)
            //     ];
            // }
        }
        return $detail;
    }
}
