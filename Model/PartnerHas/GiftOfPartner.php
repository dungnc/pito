<?php

namespace App\Model\PartnerHas;

use Illuminate\Database\Eloquent\Model;

class GiftOfPartner extends Model
{

    protected $fillable = [
        'id','partner_id','name','condition','description', 'created_at', 'updated_at'
    ];

    protected $hidden = [];
    
}
