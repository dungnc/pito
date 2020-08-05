<?php

namespace App\Model\Contract;

use App\Model\User;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{

    protected $fillable = [
        'id', 'user_id', 'owner_name', 'branch', 'is_active', 'bank_key', 'bank_name', 'card_number', 'created_at', 'updated_at'
    ];
    protected $hidden = [];
    public static $path = '/upload/contract_for_partner/';
    public function user()
    {
        return $this->belongsTo(User::class, 'user');
    }
}
