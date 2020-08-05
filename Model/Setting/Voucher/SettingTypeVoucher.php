<?php

namespace App\Model\Setting\Voucher;

use Illuminate\Database\Eloquent\Model;
use App\Model\Setting\Voucher\SettingTypeDiscount;
class SettingTypeVoucher extends Model
{
    protected $fillable = [
        'id', 'name', 'descriptions', 'created_at', 'updated_at'
    ];

    protected $hidden = [];
    
    public function type_voucher()
    {
        return $this->hasMany(SettingTypeDiscount::class, 'type_voucher_id');
    }
}
