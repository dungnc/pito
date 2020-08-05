<?php

namespace App\Model\Proposale;

use Illuminate\Database\Eloquent\Model;

class Proposale extends Model
{

    protected $fillable = [
        'id', 'order_id', 'descriptions', 'status', 'created_at', 'updated_at'
    ];

    public static $const_status = [
        0 => 'Mới Tạo',
        1 => 'Đã Gửi',
        2 => 'Đã Xác Nhận',
        3 => 'Hết Hiệu Lực',
        4 => 'Hết Hiệu Lực'
    ];

    public static $const_partner_status = [
        [
            "id" => "",
            "name" => "Tất cả"
        ],
        [
            "id" => 2,
            "name" => "Mới - Chờ xác nhận"
        ],
        [
            "id" => 3,
            "name" => "Đã xác nhận"
        ],
        [
            "id" => 11,
            "name" => "Đã hoàn thành"
        ],
        [
            "id" => 5,
            "name" => "Đã huỷ"
        ],

    ]; 

    protected $hidden = [];

    protected $appends = ['text_status'];

    public function getTextStatusAttribute()
    {
        return Proposale::$const_status[$this->status];
    }

    public function detail()
    {
        return $this->morphMany('App\Model\Order\DetailOrder', 'detail_orderable')->where('parent_id', null);
    }

    public function proposale_for_customer()
    {
        return $this->hasOne('App\Model\Proposale\ProposaleForCustomer', 'proposale_id');
    }

    public function proposale_for_partner()
    {
        return $this->hasMany('App\Model\Proposale\ProposaleForPartner', 'proposale_id');
    }

    public function order()
    {
        return $this->belongsTo('App\Model\Order\Order', 'order_id');
    }
}
