<?php

namespace App\Model\HistoryRevenue;

use App\Model\Proposale\ProposaleForCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

class HistoryRevenueForCustomer extends Model
{
    public static $type = [ 0=> "Thanh Toán Online",1=>"Chuyển Khoản"];

    protected $fillable = [
        'id', 'customer_id', 'proposale_id', 'status', 'price', 'DVT', 'description', 'SKU', 'field_more', 'created_at', 'updated_at'
    ];

    protected $hidden = [];

    public function proposale()
    {
        return $this->belongsTo(ProposaleForCustomer::class, 'proposale_id');
    }
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    protected $appends = ['type'];

    public function getTypeAttribute(){
        $pieces = explode("-", $this->SKU);
        if(strlen($pieces[sizeOf($pieces)-1]) > 12)
            return HistoryRevenueForCustomer::$type[0];
        else
            return HistoryRevenueForCustomer::$type[1];
    }

}
