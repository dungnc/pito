<?php

namespace App\Model\DetailUser;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Admin extends Model
{
    use HasTranslations;
    //
    protected $fillable = [
        'pito_user_id', 'description'
    ];
    protected $casts = [
        'pito_user_id' => 'int',
        'description' => 'string'
    ];

    public $translatable = ['description'];
}
