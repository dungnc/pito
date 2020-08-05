<?php

namespace App\Model\MenuFood;

use App\Model\MenuFood\Food;
use Illuminate\Database\Eloquent\Model;

class CategoryFood extends Model
{

    protected $fillable = [
        'id', 'partner_id', 'parent_id', '_order', 'status', 'descriptions', 'type', 'created_at', 'updated_at'
    ];

    protected $hidden = [];

    public function food()
    {
        return $this->belongsToMany(Food::class);
    }

    public function child()
    {
        return $this->hasMany('App\Model\MenuFood\CategoryFood', 'parent_id', 'id');
    }


}
