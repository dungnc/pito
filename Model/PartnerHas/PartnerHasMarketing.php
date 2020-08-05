<?php

namespace App\Model\PartnerHas;

use Illuminate\Database\Eloquent\Model;

class PartnerHasMarketing extends Model
{

    protected $fillable = [
        'id','partner_id','marketing_id', 'created_at', 'updated_at'
    ];

    protected $hidden = [];
    
}
