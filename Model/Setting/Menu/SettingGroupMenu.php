<?php

namespace App\Model\Setting\Menu;

use App\Model\MenuFood\Buffect\Buffect;
use App\Model\MenuFood\Food;
use App\Model\Setting\Menu\SettingMenu;
use Illuminate\Database\Eloquent\Model;

class SettingGroupMenu extends Model
{

    protected $fillable = [
        'id', 'name', 'created_at', 'updated_at'
    ];

    protected $hidden = [];
    public function menu()
    {
        return $this->hasMany(SettingMenu::class)->orderBy('setting_menus._order');
    }

    public function buffet()
    {
        return $this->hasMany(Buffect::class);
    }
}
