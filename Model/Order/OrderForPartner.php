<?php

namespace App\Model\Order;

use App\Model\HistoryRevenue\HistoryRevenueForPartner;
use Illuminate\Database\Eloquent\Model;

class OrderForPartner extends Model
{
    public static $const_status = [
        0 => 'Mới tạo',
        1 => 'Chưa gửi báo giá',
        2 => 'Chờ xác nhận',
        3 => 'Đã xác nhận',
        4 => 'Quá Hạn Xác Nhận',
        5 => 'Huỷ',
        6 => 'Đối Tác Đã Tới',
        7 => 'Đối Tác Tới Trễ',
        8 => 'Đang Triển Khai',
        9 => 'Chờ Thanh Toán',
        10 => 'Đối Tác Vắng Mặt',
        11 => 'Đã Hoàn Thành',
        12 => 'Cần Chỉnh Sửa'
    ];

    protected $fillable = [
        'id', 'partner_id', 'order_id', 'price', 'status', 'created_at', 'updated_at'
    ];
    protected $hidden = [];

    protected $appends = ['text_status'];

    public function getTextStatusAttribute()
    {
        return OrderForPartner::$const_status[$this->status];
    }

    public function detail()
    {
        return $this->morphMany('App\Model\Order\DetailOrder', 'detail_orderable')->where('parent_id', null);
    }
    public function partner()
    {
        return $this->belongsTo('App\Model\User', 'partner_id');
    }
    public function service()
    {
        return $this->morphMany('App\Model\Order\ServiceOrder', 'service_orderable');
    }
    public function service_default()
    {
        return $this->morphMany('App\Model\Order\ServiceOrder', 'service_orderable')->where('title', '_default_');
    }
    public function service_none()
    {
        return $this->morphMany('App\Model\Order\ServiceOrder', 'service_orderable')->where(function ($q) {
            $q->where('title', null)->orWhere('title', 'none');
        });
    }
    public function service_transport()
    {
        return $this->morphMany('App\Model\Order\ServiceOrder', 'service_orderable')->where('title', '_transport_');
    }
}
