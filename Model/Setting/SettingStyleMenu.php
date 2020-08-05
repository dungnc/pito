<?php

namespace App\Model\Setting;

use Illuminate\Database\Eloquent\Model;

class SettingStyleMenu extends Model
{

    protected $fillable = [
        'id', 'name', 'descriptions', 'created_at', 'updated_at'
    ];

    protected $hidden = [];
}
