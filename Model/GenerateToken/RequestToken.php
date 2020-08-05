<?php

namespace App\Model\GenerateToken;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RequestToken extends Model
{

    protected $prop_type = NULL;
    protected $prop_request = NULL;
    protected $fillable = [
        'id', 'request', 'token', 'type', 'created_at', 'updated_at'
    ];

    /**
     * All Event in system and its tag name
     */
    const TYPE = [
        // Discount's event
        "CUSTOMER.CONFIRM" => "Chờ Khách Xác Nhận Báo Giá",
        "CUSTOMER.REQUEST_CHANGE" => "Khách Yêu Cầu Chỉnh Sửa Đơn Hàng",
        "CUSTOMER.REVIEW" => "Khách Đánh Giá Cuối Cùng",
        "CUSTOMER.PAYMENT" => "Khách Hàng Thanh Toán",
        "CUSTOMER.RESPONSE_PAYMENT" => "Kiểm Tra SKU Thanh Toán Có Tồn Tại Hay Không",
        // Partner
        "PARTNER.RESPONSE_PAYMENT" => "Kiểm Tra SKU Thanh Toán Có Tồn Tại Hay Không"
    ];

    public function __construct($type = null, $request = null)
    {
        $this->prop_type = $type;
        $this->prop_request = json_encode($request);
    }
    protected $hidden = [];

    public function createToken()
    {
        DB::beginTransaction();
        try {
            //code...
            $this->request = $this->prop_request;
            $this->type = $this->prop_type;
            $this->token = bcrypt($this->prop_request);
            $this->save();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        return $this->token;
    }
    public function scopeFindToken($query, $token)
    {
        return $query->where('token', $token);
    }
}
