<?php

namespace App\Model\HistoryRevenue;

use App\Model\Proposale\ProposaleForPartner;
use App\Model\User;
use Illuminate\Database\Eloquent\Model;

class HistoryRevenueForPartner extends Model
{

    protected $fillable = [
        'id', 'partner_id', 'proposale_id', 'status', 'price', 'DVT', 'description', 'SKU', 'field_more', 'created_at', 'updated_at'
    ];

    protected $hidden = [];

    public function proposale()
    {
        return $this->belongsTo(ProposaleForPartner::class, 'proposale_id');
    }
    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}
