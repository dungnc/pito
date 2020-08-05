<?php

namespace App\Model\PartnerHas;

use App\Model\User;
use Illuminate\Database\Eloquent\Model;

class ServiceOrderOfPartner extends Model
{

    protected $fillable = [
        'id', 'partner_id', 'category_id', 'name', 'price', 'description', 'created_at', 'updated_at'
    ];

    protected $hidden = [];

    protected $appends = ['amount_price'];

    public function getAmountPriceAttribute()
    {
        $data = json_decode($this->json_amount_price);
        usort($data, function ($a, $b) {
            return strcmp($a->amount, $b->amount);
        });
        return $data;
    }

    public function category()
    {
        return $this->belongsTo(CategoryServiceOrder::class, 'category_id');
    }

    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}
