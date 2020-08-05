<?php

namespace App\Model\PointAndVoucher;

use Illuminate\Database\Eloquent\Model;
use App\Model\Setting\Voucher\SettingTypeVoucher;
use App\Model\Setting\Voucher\SettingTypeDiscount;
class VoucherForPartner extends Model 
{
    
    protected $fillable = [
        'id', 'voucher_id', 'partner_id','created_at', 'updated_at'
    ];



}