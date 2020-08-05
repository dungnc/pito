<?php

namespace App\Model\Setting\Menu;

use App\Model\User;
use App\Model\MenuFood\Food;
use App\Model\MenuFood\Buffect\Buffect;
use Illuminate\Database\Eloquent\Model;
use App\Model\Setting\Menu\SettingGroupMenu;

class SettingMenu extends Model
{

    protected $fillable = [
        'id', 'name', 'descriptions', '_order', 'created_at', 'updated_at'
    ];

    protected $hidden = ['pivot'];

    public function style()
    {
        return $this->belongsToMany('App\Model\Setting\SettingStyleMenu', 'menu_has_styles', 'menu_id', 'style_id')->orderBy('id', 'asc');
    }

    public function food()
    {
        return $this->belongsToMany(Food::class);
    }

    public function group_menu()
    {
        return $this->belongsTo(SettingGroupMenu::class);
    }

    public function order_field_customize()
    {
        return $this->hasMany('App\Model\Order\OrderCustomize\OrderFieldCustomize', 'menu_id');
    }

    public function buffet()
    {
        return $this->hasMany(Buffect::class, 'menu_id');
    }

    public function partner()
    {
        return $this->belongsToMany(User::class, 'partner_has_setting_menus', 'setting_menu_id', 'partner_id');
    }
}
