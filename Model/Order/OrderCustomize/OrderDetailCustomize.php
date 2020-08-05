<?php

namespace App\Model\Order\OrderCustomize;

use Illuminate\Database\Eloquent\Model;

class OrderDetailCustomize extends Model
{
    protected $fillable = [
        'id','sub_order_id','name','name','value','created_at', 'updated_at'
    ];
    protected $hidden = [];

    public function sub_order(){
        return $this->belongsTo('App\Model\Order\SubOrder','sub_order_id');
    }
}
