<?php

namespace App\Model;

use App\Mail\VerifyMail;
use App\Model\Contract\Bank;
use App\Model\MenuFood\Food;
use App\Model\DetailUser\Admin;
use App\Model\DetailUser\Partner;
use App\Model\DetailUser\Customer;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\Mail;
use App\Model\Order\OrderForCustomer;
use Spatie\Permission\Traits\HasRoles;
use App\Model\Setting\Menu\SettingMenu;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Model\PartnerHas\CategoryServiceOrder;
use App\Model\PartnerHas\ServiceOrderOfPartner;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable, HasApiTokens, HasRoles, SoftDeletes;

    protected $guard_name = 'api';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'created_at', 'updated_at',
        'social_id', 'phone', 'social_type', 'type_role', 'phone_code', 'verify_code', 'verify_expires'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $appends = ['detail', 'total_order_customer'];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public static $path = '/upload/user/';

    public static $const_type_role = [
        'CUSTOMER' => 'CUSTOMER',
        'PITO_ADMIN' => 'PITO_ADMIN',
        'PARTNER' => 'PARTNER'
    ];
    public static $const_social_type = [
        'DEFAULT' => 'DEFAULT',
        'FACEBOOK' => 'FACEBOOK',
        'GOOGLE' => 'GOOGLE_GMAIL'
    ];

    public function customer()
    {
        return $this->hasOne('App\Model\DetailUser\Customer', 'customer_id');
    }

    public function admin()
    {
        return $this->hasOne('App\Model\DetailUser\Admin', 'pito_user_id');
    }

    public function partner()
    {
        return $this->hasOne('App\Model\DetailUser\Partner', 'partner_id');
    }

    public function newDetail($type)
    {
        if ($this::$const_type_role['CUSTOMER'] == $type)
            return new Customer();
        if ($this::$const_type_role['PITO_ADMIN'] == $type)
            return new Admin();
        if ($this::$const_type_role['PARTNER'] == $type)
            return new Partner();
        return null;
    }
    public function getDetailAttribute()
    {
        if ($this->type_role == $this::$const_type_role['CUSTOMER']) {
            return Customer::where('customer_id', $this->id)->first();
        }
        if ($this->type_role == $this::$const_type_role['PITO_ADMIN']) {
            return Admin::where('pito_user_id', $this->id)->first();
        }
        if ($this->type_role == $this::$const_type_role['PARTNER']) {
            return Partner::where('partner_id', $this->id)->first();
        }
        return null;
    }

    public function type_party()
    {
        return $this->belongsToMany('App\Model\TypeParty\TypeParty', 'partner_has_parties', 'partner_id', 'type_party_id');
    }

    public function shedule()
    {
        return $this->hasOne('App\Model\SchedulePartner', 'partner_id', 'id');
    }

    public function category_food()
    {
        return $this->hasMany('App\Model\MenuFood\CategoryFood', 'partner_id', 'id');
    }

    public function food()
    {
        return $this->hasMany(Food::class, 'partner_id');
    }
    public function buffect()
    {
        return $this->hasMany('App\Model\MenuFood\Buffect\Buffect', 'partner_id', 'id');
    }

    public function list_bank()
    {
        return $this->hasMany(Bank::class, 'user_id', 'id')->orderBy('is_active', 'desc');
    }

    public function type_menu()
    {
        return $this->belongsToMany('App\Model\Setting\Menu\SettingTypeMenu', 'partner_has_setting_type_menus', 'partner_id', 'setting_type_menu_id')->where('parent_id', null);
    }

    public function menu()
    {
        return $this->belongsToMany(SettingMenu::class, 'partner_has_setting_menus', 'partner_id', 'setting_menu_id')->orderBy('_order', 'asc');
    }

    public function style_menu()
    {
        return $this->belongsToMany('App\Model\Setting\SettingStyleMenu', 'partner_has_setting_style_menus', 'partner_id', 'setting_style_menu_id');
    }

    public function service_order_default()
    {
        return $this->belongsToMany('App\Model\Setting\SettingServiceOrder', 'partner_has_service_orders', 'partner_id', 'setting_service_order_id');
    }

    public function service_order()
    {
        return $this->hasMany(ServiceOrderOfPartner::class, 'partner_id');
    }

    public function marketing()
    {
        return $this->belongsToMany('App\Model\Setting\Marketing\Marketing', 'partner_has_marketings', 'partner_id', 'marketing_id');
    }
    public function gift()
    {
        return $this->hasOne('App\Model\Setting\Marketing\GiftOfPartner', 'partner_id');
    }

    public function company()
    {
        return $this->belongsToMany('App\Model\DetailUser\Company', 'user_has_companies', 'user_id', 'company_id');
    }

    public function order_for_customer()
    {
        return $this->hasMany(OrderForCustomer::class, 'customer_id');
    }

    public function category_service_order()
    {
        return $this->hasMany(CategoryServiceOrder::class, 'partner_id');
    }

    public function getTotalOrderCustomerAttribute()
    {
        return $this->order_for_customer()->count();
    }


    public function routeNotificationForSlack($notification)
    {
        return config('slack.webhook_url_slack');
    }
}
