<?php

namespace App\Model\PointAndVoucher;

use Illuminate\Database\Eloquent\Model;
use App\Model\Setting\Voucher\SettingTypeVoucher;
use App\Model\Setting\Voucher\SettingTypeDiscount;
use App\Model\PointAndVoucher\VoucherForPartner;
class Voucher extends Model 
{

    public static $const_status = [
        0 => 'Đang Triển Khai',
        1 => 'Hết Hiệu Lực',
        2 => 'Sắp Diễn Ra',
    ];

    protected $fillable = [
        'id', 'voucher_code', 'type_voucher_id', 'value_discount','type_discount_id','orders_price','start_date','end_date','is_active', 'created_at', 'updated_at'
    ];

    public function type_voucher()
    {
        return $this->belongsTo(SettingTypeVoucher::class, 'type_voucher_id');
    }

    public function type_discount()
    {
        return $this->belongsTo(SettingTypeDiscount::class, 'type_discount_id');
    }

    /**
         * The vouchers that belong to the partners.
    */
    public function partners()
    {
        return $this->belongsToMany('App\Model\User','voucher_for_partners','voucher_id','partner_id');
    }



    protected $appends = ['apply_type','partner_id','status'];

    /**
     * get apply type attribute
     */
    public function getApplyTypeAttribute()
    {
        $apply_type = [
            "all" => false,
            "value_from" => false,
            "partner" => false
        ];
        if(sizeOf($this->partners) >0 || $this->orders_price){
            if($this->orders_price){
                $apply_type["value_from"] = true;
            }
            if(sizeOf($this->partners) > 0){
                $apply_type["partner"] = true;
            }
        }else{
            $apply_type["all"] = true;
        }
        return $apply_type;
    }

    /**
     * get partner id attribute
     */
    public function getPartnerIdAttribute(){
        $partner_id = null;
        if(sizeOf($this->partners) > 0){
            $partner_id = $this->partners[0]["id"];
        }
        return $partner_id;
    }

    /**
     * 
     */
    public function getStatusAttribute(){
        $status = [
            "status_code" => 0,
            "text_status" => Voucher::$const_status[0] 
        ];
        $now = strtotime(date('Y-m-d'));
        $start_date = strtotime($this->start_date);
        $end_date = strtotime($this->end_date);
        if($start_date <= $now && $end_date >= $now){
            $status = [
                "status_code" => 0,
                "text_status" => Voucher::$const_status[0] 
            ];
        }
        if($end_date < $now){
            $status = [
                "status_code" => 1,
                "text_status" => Voucher::$const_status[1] 
            ];
        }
        if($start_date > $now){
            $status = [
                "status_code" => 2,
                "text_status" => Voucher::$const_status[2] 
            ];
        }
        return $status;
    }
}