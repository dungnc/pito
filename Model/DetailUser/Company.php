<?php

namespace App\Model\DetailUser;

use App\Model\Order\Order;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Company extends Model
{
    use HasTranslations;
    //
    protected $fillable = [
        'id','company','address','status','_lat','_long','tax_code'
    ];
    
    protected $appends = ['total_order'];

    public function order() {
        return $this->hasMany(Order::class, 'company_id');
    }
    public function getTotalOrderAttribute(){
        return $this->order()->count();
    }
}
