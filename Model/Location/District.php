<?php

namespace App\Model\Location;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{

    protected $fillable = [
        'id','city_id','name','type', 'created_at', 'updated_at'
    ];

    protected $hidden = [];
    
    public function ward(){
        return $this->hasMany('App\Model\Location\Ward','district_id');
    }
}
