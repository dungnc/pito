<?php

namespace App\Model\TicketAndReview\Review;

use Illuminate\Database\Eloquent\Model;

class QuestionReview extends Model
{

    protected $fillable = [
        'id','name','description','_order','created_at', 'updated_at'
    ];

    protected $hidden = [];
   
    public function answer_review(){
        return $this->hasMany(AnswerReview::class,'question_review_id');
    }
}
