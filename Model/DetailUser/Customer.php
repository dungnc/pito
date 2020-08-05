<?php

namespace App\Model\DetailUser;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Customer extends Model
{
    use HasTranslations;
    //
    protected $fillable = [
        'customer_id', 'description','address','point','_lat','_long','people_contact','website','company'
    ];
    protected $casts = [
        'pito_user_id' => 'int',
        'description' => 'string',
        'address' => 'string',
        'point' => 'int'
    ];

    protected $appends = [
        'detail_company'
    ];

    public $translatable = ['description'];

    public function getDetailCompanyAttribute()
    {
        return Company::find($this->company_id);
    }
}
