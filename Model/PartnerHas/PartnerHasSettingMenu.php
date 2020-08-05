<?php

namespace App\Model\PartnerHas;

use Illuminate\Database\Eloquent\Model;

class PartnerHasSettingMenu extends Model
{

    protected $fillable = [
        'id','partner_id','setting_menu_id', 'created_at', 'updated_at'
    ];

    protected $hidden = [];
    
}
