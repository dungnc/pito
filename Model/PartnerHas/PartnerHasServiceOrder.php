<?php

namespace App\Model\PartnerHas;

use Illuminate\Database\Eloquent\Model;

class PartnerHasServiceOrder extends Model
{

    protected $fillable = [
        'id','partner_id','setting_service_order_id', 'created_at', 'updated_at'
    ];

    protected $hidden = [];
    
}
