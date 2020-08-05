<?php

namespace App\Model\Contract;

use App\Model\User;
use Illuminate\Database\Eloquent\Model;

class ContractForPartner extends Model
{

    protected $fillable = [
        'id','partner_id','name','description','file','created_at', 'updated_at'
    ];
    protected $hidden = [];
    public static $path = '/upload/contract_for_partner/';
    public function partner(){
        return $this->belongsTo(User::class,'partner_id');
    }
}
