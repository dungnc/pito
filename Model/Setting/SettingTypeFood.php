<?php

namespace App\Model\Setting;

use Illuminate\Database\Eloquent\Model;

class SettingTypeFood extends Model
{

    protected $fillable = [
        'id', 'name', 'descriptions', '_order', 'created_at', 'updated_at'
    ];

    protected $hidden = [];
}
