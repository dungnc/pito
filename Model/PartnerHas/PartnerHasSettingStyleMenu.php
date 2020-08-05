<?php

namespace App\Model\PartnerHas;

use Illuminate\Database\Eloquent\Model;

class PartnerHasSettingStyleMenu extends Model
{

    protected $fillable = [
        'id','setting_style_menu_id', 'partner_id','created_at', 'updated_at'
    ];

    protected $hidden = [];
    
}
