<?php

namespace App\Model\Setting;

use Illuminate\Database\Eloquent\Model;

class SettingServiceOrder extends Model
{

    protected $fillable = [
        'id', 'name', 'descriptions', 'partner_id', 'created_at', 'updated_at'
    ];

    protected $hidden = [];
}
