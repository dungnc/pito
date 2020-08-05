<?php

namespace App\Model\Setting\Voucher;

use Illuminate\Database\Eloquent\Model;

class SettingTypeDiscount extends Model
{
    
    protected $fillable = [
        'id', 'name', 'descriptions', 'type_voucher_id','created_at', 'updated_at'
    ];

    protected $hidden = [];

    
}
