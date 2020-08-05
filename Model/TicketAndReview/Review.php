<?php

namespace App\Model\TicketAndReview;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{

    protected $fillable = [
        'id','customer_id','content','star','sub_order_id','created_at', 'updated_at'
    ];

    protected $hidden = [];
   
    public function customer(){
        return $this->belongsTo('App\Model\User','customer_id');
    }

    public function sub_order(){
        return $this->belongsTo('App\Model\Order\SubOrder','sub_order_id');
    }
}
