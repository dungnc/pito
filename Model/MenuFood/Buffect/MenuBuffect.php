<?php

namespace App\Model\MenuFood\Buffect;

use App\Model\MenuFood\Food;
use Illuminate\Database\Eloquent\Model;

class MenuBuffect extends Model
{

    protected $fillable = [
        'id', 'buffect_id', 'descriptions', 'image', 'name', 'parent_id', 'created_at', 'amount', 'food_id', 'updated_at'
    ];

    protected $hidden = [];

    public function buffect()
    {
        return $this->belongsTo('App\Model\MenuFood\Buffect\MenuBuffect', 'category_id');
    }

    public function child()
    {
        return $this->hasMany('App\Model\MenuFood\Buffect\MenuBuffect', 'parent_id', 'id');
    }
    protected $appends = ['food_detail'];

    public function getFoodDetailAttribute()
    {
        return Food::find($this->food_id);
    }

    public function food()
    {
        return $this->belongsTo(Food::class, 'food_id');
    }
}
