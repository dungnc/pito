<?php

namespace App\Model\Setting\Menu;

use Illuminate\Database\Eloquent\Model;

class SettingTypeMenu extends Model
{

    protected $fillable = [
        'id','name','descriptions','parent_id', 'created_at', 'updated_at'
    ];

    protected $hidden = [];
    
    public function child()
    {
        return $this->hasMany('App\Model\Setting\Menu\SettingTypeMenu','parent_id','id');
    }
}
