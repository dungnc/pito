<?php

namespace App\Model\PartnerHas;

use Illuminate\Database\Eloquent\Model;

class CategoryServiceOrder extends Model
{

    protected $fillable = [
        'id','partner_id','name','created_at','updated_at'
    ];

    protected $hidden = [];
    
    public function service_order(){
        return $this->hasMany(ServiceOrderOfPartner::class,'category_id');
    }
}
