<?php

namespace App\Model\TicketAndReview;

use Illuminate\Database\Eloquent\Model;

class TicketEnd extends Model
{

    protected $fillable = [
        'id','partner_id','image_confirm','field_json','description','sub_order_id','created_at', 'updated_at'
    ];

    public static $path = '/upload/ticket_end/';

    protected $hidden = [];
    
    protected $appends = ['field','token'];

    public function getFieldAttribute(){
        return json_decode($this->field_json);
    }

    public function getTokenAttribute(){
        return bcrypt($this->partner_id."-".$this->sub_order_id);
    }

    public function partner(){
        return $this->belongsTo('App\Model\User','partner_id');
    }

    public function sub_order(){
        return $this->belongsTo('App\Model\Order\SubOrder','sub_order_id');
    }
}
