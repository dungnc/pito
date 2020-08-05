<?php

namespace App\Model\Setting;

use Illuminate\Database\Eloquent\Model;

class PatternMailTool extends Model
{

    protected $fillable = [
        'id','content','name','json_regex','created_at', 'updated_at'
    ];

    protected $hidden = [];
    
}
