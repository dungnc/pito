<?php

namespace App\Model\Setting\Marketing;

use Illuminate\Database\Eloquent\Model;

class GiftOfPartner extends Model
{

    protected $fillable = [
        'id','partner_id','name','condition','descriptions','created_at', 'updated_at'
    ];

    protected $hidden = [];
    
}
