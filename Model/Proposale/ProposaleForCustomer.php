<?php

namespace App\Model\Proposale;

use Illuminate\Database\Eloquent\Model;
use App\Model\HistoryRevenue\HistoryRevenueForCustomer;

class ProposaleForCustomer extends Model
{

    protected $fillable = [
        'id', 'customer_id', 'proposale_id', 'price',
        'status', 'created_at', 'updated_at', 'reason',
        'descriptions', 'is_pay', 'is_remider_payment',
        'is_remider_thanks'
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
        return ProposaleForCustomer::$const_status[$this->status];
    }

    public function detail()
    {
        return $this->morphMany('App\Model\Order\DetailOrder', 'detail_orderable')->where('parent_id', null);
    }
    public function service()
    {
        return $this->morphMany('App\Model\Order\ServiceOrder', 'service_orderable');
    }

    public function customer()
    {
        return $this->belongsTo('App\Model\User', 'customer_id');
    }
    public function proposale()
    {
        return $this->belongsTo('App\Model\Proposale\Proposale', 'proposale_id')->whereNotIn('status', [4]);
    }
    public function history_payment()
    {
        return $this->hasMany(HistoryRevenueForCustomer::class, 'proposale_id');
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
