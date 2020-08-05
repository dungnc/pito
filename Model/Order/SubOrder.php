<?php

namespace App\Model\Order;

use Illuminate\Database\Eloquent\Model;
use App\Model\TicketAndReview\TicketEnd;
use App\Model\TicketAndReview\TicketStart;
use App\Model\MenuFood\Buffect\BuffectPrice;

class SubOrder extends Model
{
    public static $const_status = [
        0 => 'gửi báo giá',
        1 => 'chờ xác nhận',
        2 => 'đã xác nhận',
        3 => 'triển khai tiệc',
        4 => 'hoàn thành',
        5 => 'huỷ'
    ];

    protected $fillable = [
        'id', 'name', 'date_start', 'address',
        'order_id', 'reason', 'buffet_price_id',
        'start_time', 'end_time', 'pito_admin_id', 'assign_pito_admin_id',
        'status', 'descriptions', 'type', 'created_at', 'updated_at'
    ];

    protected $hidden = [];

    protected $appends = ['text_status'];

    public function getTextStatusAttribute()
    {
        return SubOrder::$const_status[$this->status];
    }

    public function detail()
    {
        return $this->morphMany('App\Model\Order\DetailOrder', 'detail_orderable')->where('parent_id', null);
    }

    public function assign_pito_admin()
    {
        return $this->belongsTo('App\Model\User', 'assign_pito_admin_id');
    }

    public function order_for_partner()
    {
        return $this->hasOne('App\Model\Order\OrderForPartner', 'sub_order_id');
    }
    public function proposale_for_partner()
    {
        return $this->hasOne('App\Model\Proposale\ProposaleForPartner', 'sub_order_id')
            ->whereNotIn('status', [3, 4])->whereHas('proposale', function ($q) {
                $q->whereNotIn('status', [3, 4]);
            });
    }

    public function ticket_start()
    {
        return $this->hasOne(TicketStart::class, 'sub_order_id');
    }

    public function ticket_end()
    {
        return $this->hasOne(TicketEnd::class, 'sub_order_id');
    }

    public function pito_admin()
    {
        return $this->belongsTo('App\Model\User', 'pito_admin_id');
    }

    public function type_party()
    {
        return $this->belongsTo('App\Model\TypeParty\TypeParty', 'type_party_id');
    }

    public function order()
    {
        return $this->belongsTo('App\Model\Order\Order', 'order_id');
    }

    public function order_detail_customize()
    {
        return $this->hasMany('App\Model\Order\OrderCustomize\OrderDetailCustomize', 'sub_order_id');
    }

    public function buffet_price()
    {
        return $this->belongsTo(BuffectPrice::class, 'buffet_price_id');
    }
}
