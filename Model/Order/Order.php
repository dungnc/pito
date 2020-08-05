<?php

namespace App\Model\Order;

use App\Traits\HistoryStorage;
use App\Model\Order\DetailOrder;
use App\Model\DetailUser\Company;
use App\Model\Proposale\Proposale;
use App\Model\Order\OrderForCustomer;
use App\Model\Setting\Menu\SettingMenu;
use App\Model\Setting\SettingStyleMenu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Model\Setting\Menu\SettingTypeMenu;
use App\Model\Setting\Menu\SettingGroupMenu;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HistoryStorage, SoftDeletes, Notifiable;

    // Blacklist (not save to history even if it change)
    private $blacklist = [
        'created_at',
        'updated_at',
        'sub_order',
        'proposale'
    ];

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
        'id', 'name', 'date_start', 'clean_time', 'is_remider',
        'start_time', 'end_time', 'pito_admin_id', 'assign_pito_admin_id',
        'address', '_lat', '_long', 'company_id', 'min_price', 'max_price',
        'setting_group_menu_id', 'menu_id', 'type_menu_id', 'status_more',
        'customer_id', 'status', 'descriptions', 'type', 'created_at', 'updated_at',
        'code_promotion', 'value_promotion', 'code_introducer', 'code_affiliate', 'voucher_code',
        'setting_style_menu_id', 'amount', 'percent_manage_customer', 'percent_manage_partner'
    ];


    protected $hidden = [];


    protected $appends = ['text_status'];

    public function getTextStatusAttribute()
    {

        if (!isset($this->status))
            return null;
        return Order::$const_status[$this->status];
    }

    // public function getAmountAttribute()
    // {
    //     $sum = DetailOrder::where('type_more', DetailOrder::TYPE_MORE['BUFFET'])
    //         ->where('detail_orderable_type', OrderForCustomer::class)
    //         ->where('detail_orderable_id', $this->order_for_customer->id)
    //         ->sum('amount');

    //     return (int) $sum;
    // }

    public function assign_pito_admin()
    {
        return $this->belongsTo('App\Model\User', 'assign_pito_admin_id');
    }

    public function order_for_customer()
    {
        return $this->hasOne('App\Model\Order\OrderForCustomer', 'order_id');
    }

    public function sub_order()
    {
        return $this->hasMany('App\Model\Order\SubOrder', 'order_id');
    }

    public function proposale()
    {
        return $this->hasOne(Proposale::class, 'order_id')->whereNotIn('status', [3, 4]);
    }

    public function proposale_all()
    {
        return $this->hasMany(Proposale::class, 'order_id')->orderBy('id', 'desc');
    }

    public function proposale_delete()
    {
        return $this->hasMany(Proposale::class, 'order_id')->whereIn('status', [3, 4]);
    }

    public function pito_admin()
    {
        return $this->belongsTo('App\Model\User', 'pito_admin_id');
    }

    public function type_party()
    {
        return $this->belongsTo('App\Model\TypeParty\TypeParty', 'type_party_id');
    }

    public function type_menu()
    {
        return $this->belongsTo(SettingTypeMenu::class, 'type_menu_id');
    }
    public function style_menu()
    {
        return $this->belongsTo(SettingStyleMenu::class, 'setting_style_menu_id');
    }
    public function menu()
    {
        return $this->belongsTo(SettingMenu::class, 'menu_id');
    }

    public function setting_group_menu()
    {
        return $this->belongsTo(SettingGroupMenu::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // public function detail()
    // {
    //     return $this->morphMany('App\Model\Order\DetailOrder', 'detail_orderable')->where('parent_id',null);
    // }
    public function service()
    {
        return $this->morphMany('App\Model\Order\ServiceOrder', 'service_orderable');
    }

    public function routeNotificationForSlack($notification)
    {
        return config('slack.webhook_url_slack');
    }


    /**
     * The vouchers that belong to the partners.
     */
    public function voucher()
    {
        return $this->belongsToMany('App\Model\PointAndVoucher\Voucher', 'voucher_for_orders', 'order_id', 'voucher_id');
    }
}
