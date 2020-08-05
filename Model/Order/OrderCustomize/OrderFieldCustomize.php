<?php

namespace App\Model\Order\OrderCustomize;

use Illuminate\Database\Eloquent\Model;

class OrderFieldCustomize extends Model
{
    protected $fillable = [
        'id','type_party_id','name','variable','type','is_required','descriptions','created_at', 'updated_at'
    ];
    protected $hidden = [];
    
    protected $appends = ['value'];

    public function getValueAttribute(){
        return null;
    }

    public function type_party(){
        return $this->belongsTo('App\Model\TypeParty\TypeParty','type_party_id');
    }
}
