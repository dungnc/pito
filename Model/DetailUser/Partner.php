<?php

namespace App\Model\DetailUser;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Partner extends Model
{
    use HasTranslations;
    //
    protected $fillable = [
        'partner_id', 'address', '_lat', '_long', 'description',
        'status', 'point', 'info_bank',
        'VAT', 'company', 'website', 'KD_image', 'VSATTP_image',
        'link_facebook', 'BHT_image', 'BHT_status', 'json_more',
        'business_name'
    ];
    protected $casts = [
        'pito_user_id' => 'int',
        'description' => 'string',
        'point' => 'int',
        'status' => 'string'
    ];

    public $translatable = ['description'];
    public static $path = '/upload/partner/';
    protected $appends = ['json_more_decode'];

    public static $const_status = [
        0 => 'CLOSE',
        1 => 'OPEN',
        2 => 'BUSY'
    ];

    public static $const_is_active = [
        0 => 'Chưa xác thưc',
        1 => 'Xác thực',
        2 => 'Không đạt yêu cầu'
    ];

    public static $const_KD_type = [
        1 => 'Công ty',
        2 => 'Hộ gia đình'
    ];
    public static $const_VSATTP_status = [
        1 => 'Có',
        2 => 'Đang tiến hành đăng ký',
        3 => 'Chưa có'
    ];

    public function getJsonMoreDecodeAttribute()
    {


        return json_decode($this->json_more);
    }
}
