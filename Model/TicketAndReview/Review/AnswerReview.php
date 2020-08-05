<?php

namespace App\Model\TicketAndReview\Review;

use Illuminate\Database\Eloquent\Model;

class AnswerReview extends Model
{

    protected $fillable = [
        'id','question_review_id','name','description','type','icon_default','icon_active','_order','created_at', 'updated_at'
    ];

    protected $hidden = [];
   
    public function question_review(){
        return $this->belongsTo(QuestionReview::class,'question_review_id');
    }
}
