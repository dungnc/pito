<?php

namespace App\Model\Setting;

use App\Model\Setting\SettingTypeFood;
use App\Model\Setting\Menu\SettingMenu;
use App\Model\Setting\SettingStyleMenu;
use Illuminate\Database\Eloquent\Model;

class MenuAndStyleHasSetPrice extends Model
{

    protected $fillable = [
        'id', 'menu_id', 'style_menu_id', 'set', 'price', 'json_condition', 'created_at', 'updated_at'
    ];

    protected $hidden = [];

    protected $appends = ['condition'];

    public function getConditionAttribute() {
        return \json_decode($this->json_condition);
    }

    public function style_menu()
    {
        return $this->belongsTo(SettingStyleMenu::class, 'style_menu_id');
    }

    public function menu()
    {
        return $this->belongsTo(SettingMenu::class, 'menu_id');
    }
}
