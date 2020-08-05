<?php

namespace App\Model\MenuFood;

use App\Model\User;
use App\Model\MenuFood\CategoryFood;
use App\Model\Setting\Menu\SettingMenu;
use App\Model\Setting\SettingStyleMenu;
use Illuminate\Database\Eloquent\Model;
use App\Model\Setting\Menu\SettingTypeMenu;
use Illuminate\Database\Eloquent\SoftDeletes;

class Food extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'id', 'category_id', 'min_time_setup', 'status', 'descriptions', 'type', 'created_at', 'updated_at'
    ];
    public static $path = '/upload/food/';
    protected $hidden = [];

    public function category()
    {
        return $this->belongsToMany(CategoryFood::class);
    }

    // public function type_food()
    // {
    //     return $this->belongsTo('App\Model\Setting\SettingTypeFood', 'type_food_id');
    // }

    // public function menu()
    // {
    //     return $this->belongsToMany(SettingMenu::class);
    // }

    public function style_menu()
    {
        return $this->belongsTo(SettingStyleMenu::class, 'style_menu_id');
    }
    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}
