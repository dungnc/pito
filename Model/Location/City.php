<?php

namespace App\Model\Location;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{

    protected $fillable = [
        'id','name','type', 'created_at', 'updated_at'
    ];

    protected $hidden = [];
    
    public function district(){
        return $this->hasMany('App\Model\Location\District','city_id');
    }
}
