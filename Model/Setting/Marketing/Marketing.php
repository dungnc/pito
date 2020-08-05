<?php

namespace App\Model\Setting\Marketing;

use Illuminate\Database\Eloquent\Model;

class Marketing extends Model
{

    protected $fillable = [
        'id','name','description','created_at', 'updated_at'
    ];

    protected $hidden = [];
    
}
