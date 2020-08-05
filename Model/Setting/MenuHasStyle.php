<?php

namespace App\Model\Setting;

use Illuminate\Database\Eloquent\Model;

class MenuHasStyle extends Model
{

    protected $fillable = [
        'id','menu_id','style_id','created_at', 'updated_at'
    ];

    protected $hidden = [];
    
}
