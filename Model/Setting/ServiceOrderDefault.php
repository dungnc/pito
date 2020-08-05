<?php

namespace App\Model\Setting;


class ServiceOrderDefault
{
    private static $const_service = [
        [
            'title' => 'Nhân sự',
            'select' => [
                [
                    "name" => "Nhân viên bình thường",
                    "DVT" => "Người",
                    "price" => 20000
                ]
            ]
        ],
        [
            'title' => 'Loại ghế phục vụ',
            'select' => [
                [
                    "name" => "Ghế bình thường",
                    "DVT" => "Cái",

                    "price" => 10000
                ],
                [
                    "name" => "Ghế cao",
                    "DVT" => "Cái",
                    "price" => 20000
                ]
            ]
        ],
        [
            'title' => 'Loại bàn phục vụ',
            'select' => [
                [
                    "name" => "Bàn bình thường",
                    "DVT" => "Cái",
                    "price" => 10000
                ],
                [
                    "name" => "Bàn cao",
                    "DVT" => "Cái",
                    "price" => 20000
                ],
                [
                    "name" => "Bàn bar cao",
                    "DVT" => "Cái",
                    "price" => 10000
                ],
                [
                    "name" => "Bàn bar thấp",
                    "DVT" => "Cái",
                    "price" => 20000
                ]
            ]
        ],
        [
            "title" => 'Dụng cụ tiệc',
            "select" => [
                [
                    "name" => "Dụng cụ tiệc sứ loại 1",
                    "DVT" => "Cái",
                    "price" => 3000
                ],
                [
                    "name" => "Dụng cụ tiệc sứ loại 2",
                    "DVT" => "Cái",
                    "price" => 5000
                ]
            ]
        ],
        [
            "title" => 'Vận chuyển',
            "select" => [
                [
                    "name" => "Vận chuyển",
                    "DVT" => 'chuyến đi,về',
                    "price" => 25000
                ]
            ]
        ]
    ];
    public static function get()
    {
        return ServiceOrderDefault::$const_service;
    }
    public static function distance($to, $from)
    {
        $pi80 = M_PI / 180;
        $lat1 = $to['_lat'] * $pi80;
        $lon1 = $to['_long'] * $pi80;
        $lat2 = $from['_lat'] * $pi80;
        $lon2 = $from['_long'] * $pi80;

        $r = 6372.797; // mean radius of Earth in km
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
        $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlon / 2) * sin($dlon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $r * $c;
    }
    public static function getSeviceTransport($to, $from)
    {
        $first_two_km = 15000;
        $per_km = 5000;
        $distance = static::distance($to, $from);
        if ($distance <= 2) {
            return [
                'distance' => $distance,
                'money' => $first_two_km
            ];
        }
        return [
            'distance' => $distance,
            // 'money' => $first_two_km + ($distance - 2) * $per_km,
            'money' => 0,
        ];
    }
}
