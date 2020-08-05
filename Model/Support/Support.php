<?php

namespace App\Model\Support;

use Illuminate\Database\Eloquent\Model;
use App\Model\User;
class Support extends Model 
{

    public static $type_connect = [
        1 => 'Email',
        2 => 'Số Điện Thoại',
    ];

    protected $fillable = [
        'id', 'name', 'email', 'phone','connect_type','message','user_id', 'created_at', 'updated_at'
    ];


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected $appends = ['connect_type_text'];

    public function getConnectTypeTextAttribute(){
        return Support::$type_connect[$this->connect_type];
    }

}