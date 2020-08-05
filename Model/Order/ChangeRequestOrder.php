<?php

namespace App\Model\Order;

use Illuminate\Database\Eloquent\Model;
use App\Model\User;

class ChangeRequestOrder extends Model
{
    protected $fillable = [
        'id', 'order_id', 'user_id', 'content', 'is_handle', 'created_at', 'updated_at'
    ];

    protected $appends = [];
}
