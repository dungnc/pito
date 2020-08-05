<?php

namespace App\Model\TypeParty;

use Illuminate\Database\Eloquent\Model;

class TypeParty extends Model
{

    protected $fillable = [
        'id','name', 'created_at', 'updated_at'
    ];
   

    protected $hidden = [];
    
    public function order_field_customize(){
        return $this->hasMany('App\Model\Order\OrderCustomize\OrderFieldCustomize','type_party_id');
    }
}
