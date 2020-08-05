<?php

namespace App\Model\Proposale;

use Illuminate\Database\Eloquent\Model;
use App\Model\HistoryRevenue\HistoryRevenueForPartner;

class ProposaleForPartner extends Model
{

    protected $fillable = [
        'id', 'partner_id', 'proposale_id', 'price',
        'status', 'created_at', 'updated_at', 'sub_order_id',
        'reason', 'descriptions', 'is_pay', 'is_remider_payment'
    ];

    public static $const_status = [
        0 => 'Mới Tạo',
        1 => 'Đã Gửi',
        2 => 'Đã Xác Nhận',
        3 => 'Hết Hiệu Lực'
    ];

    protected $hidden = [];

    protected $appends = ['text_status', 'paymented'];

    public function getTextStatusAttribute()
    {
        return ProposaleForPartner::$const_status[$this->status];
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

    public function sub_order()
    {
        return $this->belongsTo('App\Model\Order\SubOrder', 'sub_order_id');
    }
    public function proposale()
    {
        return $this->belongsTo('App\Model\Proposale\Proposale', 'proposale_id')->whereNotIn('status', [4]);
    }
    public function service_default()
    {
        return $this->morphMany('App\Model\Order\ServiceOrder', 'service_orderable')->where('title', '_default_');
    }
    public function service_none()
    {
        return $this->morphMany('App\Model\Order\ServiceOrder', 'service_orderable')->where('title', null)->orWhere('title', 'none');
    }
    public function service_transport()
    {
        return $this->morphMany('App\Model\Order\ServiceOrder', 'service_orderable')->where('title', '_transport_');
    }
    public function history_payment()
    {
        return $this->hasMany(HistoryRevenueForPartner::class, 'proposale_id');
    }
    public function getPaymentedAttribute()
    {
        $histories = $this->history_payment()
            ->where('status', '00')->get();
        $total = 0;
        foreach ($histories as $value) {
            $total += $value->price;
        }
        return $total;
    }
}
