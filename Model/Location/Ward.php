<?php

namespace App\Model\Location;

use Illuminate\Database\Eloquent\Model;

class Ward extends Model
{

    protected $fillable = [
        'id','district_id','name','type', 'created_at', 'updated_at'
    ];

    protected $hidden = [];
    
  
}
