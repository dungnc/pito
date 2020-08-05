<?php

namespace App\Model\TypeParty;

use Illuminate\Database\Eloquent\Model;

class PartnerHasParty extends Model
{

    protected $fillable = [
        'id','partner_id','type_party_id','created_at', 'updated_at'
    ];

   

    protected $hidden = [];
    
   
    public function partner(){
        return $this->belongsTo('App\Model\User','partner_id');
    }

    public function type_party(){
        return $this->belongsTo('App\Model\TypeParty\TypeParty','type_party_id');
    }
}
