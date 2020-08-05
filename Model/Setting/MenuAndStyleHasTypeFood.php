<?php

namespace App\Model\Setting;

use App\Model\Setting\SettingTypeFood;
use App\Model\Setting\Menu\SettingMenu;
use App\Model\Setting\SettingStyleMenu;
use Illuminate\Database\Eloquent\Model;

class MenuAndStyleHasTypeFood extends Model
{

    protected $fillable = [
        'id','menu_id','style_menu_id','type_food_id','created_at', 'updated_at'
    ];

    protected $hidden = [];
    
    public function style_menu(){
        return $this->belongsTo(SettingStyleMenu::class,'style_menu_id');
    }

    public function menu(){
        return $this->belongsTo(SettingMenu::class,'menu_id');
    }

    public function type_food(){
        return $this->belongsTo(SettingTypeFood::class,'type_food_id');
    }
}
