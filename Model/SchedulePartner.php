<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SchedulePartner extends Model {
    protected $fillable = [
        'partner_id', 'day', 'time_json', 'start_time', 'created_at', 'updated_at',
        'end_time'
    ];

    const DAY_STR = [
        "monday" => "Monday",
        "tuesday" => "Tuesday",
        "wednesday" => "Wednesday",
        "webnesday" => "Wednesday",
        "thursday" => "Thursday",
        "friday" => "Friday",
        "saturday" => "Saturday",
        "sunday" => "Sunday",
    ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [

    ];


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];


    function getDayAttribute($value){
        $value = strtolower($value);
        $day = self::DAY_STR[$value];
        return $day;
    }
    function getTimeJsonAttribute($value){
        return  json_decode($value);
    }

}
