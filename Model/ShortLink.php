<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ShortLink extends Model
{
    //
    protected $fillable = [
        'id', 'url', 'accessed', 'hits', 'created_at', 'updated_at'
    ];
}
