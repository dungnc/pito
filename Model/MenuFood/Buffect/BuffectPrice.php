<?php

namespace App\Model\MenuFood\Buffect;

use App\Model\MenuFood\Buffect\Buffect;
use Illuminate\Database\Eloquent\Model;
use App\Model\MenuFood\Buffect\MenuBuffect;
use Illuminate\Database\Eloquent\SoftDeletes;

class BuffectPrice extends Model
{

    use SoftDeletes;
    protected $fillable = [
        'id', 'buffect_id', 'descriptions', 'image', 'unit', 'price', 'set', 'json_condition', 'created_at', 'updated_at'
    ];

    protected $hidden = [];
    protected $appends = ['condition'];

    public function getConditionAttribute()
    {
        return json_decode($this->json_condition);
    }
    public function buffet()
    {
        return $this->belongsTo(Buffect::class, 'buffect_id');
    }

    public function menu_buffect()
    {
        return $this->hasMany(MenuBuffect::class)->where('parent_id', null);
    }
}
