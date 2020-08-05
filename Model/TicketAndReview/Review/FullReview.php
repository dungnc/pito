<?php

namespace App\Model\TicketAndReview\Review;

use App\Model\Order\Order;
use App\Model\Order\SubOrder;
use Illuminate\Database\Eloquent\Model;

class FullReview extends Model
{

    protected $fillable = [
        'id', 'user_id', 'target_user_id', 'field_more', 'type', 'order_id', 'created_at', 'updated_at'
    ];

    const TYPE = [
        'CUSTOMER-PARTNER' => 'CUSTOMER-PARTNER',
        'CUSTOMER-PITO' => 'CUSTOMER-PTIO',
        'PARTNER-CUSTOMER' => 'PARTNER-CUSTOMER',
        'PARTNER-PTIO' => 'PARTNER-PITO'
    ];

    protected $hidden = [];

    public function answer_review()
    {
        return $this->hasMany('App\Model\TicketAndReview\Review\AnswerReview', 'question_review_id');
    }
    protected $appends = ['field'];

    public function getFieldAttribute()
    {
        return json_decode($this->field_more);
    }

    public function user()
    {
        return $this->belongsTo('App\Model\User', 'user_id');
    }
    public function target_user()
    {
        return $this->belongsTo('App\Model\User', 'target_user_id');
    }
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
