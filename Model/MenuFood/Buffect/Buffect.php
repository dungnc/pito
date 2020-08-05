<?php

namespace App\Model\MenuFood\Buffect;

use App\Model\Setting\Menu\SettingMenu;
use App\Model\Setting\SettingStyleMenu;
use Illuminate\Database\Eloquent\Model;
use App\Model\Setting\Menu\SettingTypeMenu;
use App\Model\MenuFood\Buffect\BuffectPrice;
use App\Model\Setting\Menu\SettingGroupMenu;
use Illuminate\Database\Eloquent\SoftDeletes;

class Buffect extends Model
{
    use SoftDeletes;
    public static $path = '/upload/buffect/';

    protected $fillable = [
        'id', 'title', 'descriptions', 'setting_group_menu_id', 'menu_id', 'image', 'created_at', 'updated_at'
    ];

    protected $hidden = [];
    public function buffect_price()
    {
        return $this->hasMany(BuffectPrice::class, 'buffect_id', 'id');
    }
    // public function menu_buffect()
    // {
    //     return $this->hasMany('App\Model\MenuFood\Buffect\MenuBuffect', 'buffect_id', 'id')->where('parent_id', null);
    // }
    public function menu()
    {
        return $this->belongsTo(SettingMenu::class, 'menu_id');
    }
    public function setting_group_menu()
    {
        return $this->belongsTo(SettingGroupMenu::class, 'setting_group_menu_id');
    }
    public function style_menu()
    {
        return $this->belongsTo(SettingStyleMenu::class, 'style_menu_id');
    }
    public function type_menu()
    {
        return $this->belongsTo(SettingTypeMenu::class, 'type_menu_id');
    }
}
