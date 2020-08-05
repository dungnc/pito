<?php

namespace App\Model\PointAndVoucher;

use Illuminate\Database\Eloquent\Model;
use App\Model\Setting\Voucher\SettingTypeVoucher;
use App\Model\Setting\Voucher\SettingTypeDiscount;
class VoucherForOrder extends Model 
{
    
    protected $fillable = [
        'id', 'voucher_id', 'order_id','created_at', 'updated_at'
    ];



}