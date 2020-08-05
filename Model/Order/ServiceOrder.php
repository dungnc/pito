<?php

namespace App\Model\Order;

use Illuminate\Database\Eloquent\Model;
use App\Model\User;

class ServiceOrder extends Model
{
    protected $fillable = [
        'id', 'service_orderable_id', 'service_orderable_type', 'name', 'price', 'created_at', 'updated_at', 'note', 'id_more', 'type_more', 'json_more'
    ];

    protected $appends = ['partner', 'partner_id', 'category_id'];

    const TYPE_MORE = [
        // Discount's event
        "PARTNER" => "partner"
    ];
    protected $hidden = [];
    public function imageable()
    {
        return $this->morphTo();
    }

    public function getPartnerAttribute()
    {

        $partner = [
            'id' => null,
            'name' => null,
            'email' => null
        ];
        $partner_id = null;
        if ($this->json_more != "" && $this->json_more) {
            $json_more = json_decode($this->json_more);
            $partner_id = $json_more->partner_id;

        }else{
            $partner_id = $this->id_more;
            
        }
        
        if ($partner_id == "PITO") {
                $partner = [
                    'id' => "PITO",
                    'name' => "PITO",
                    'email' => "support@pito.vn"
                ];
            } else {
                $user = User::find($partner_id);
                if ($user) {
                    $partner = [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email
                    ];
                }
            }

        return $partner;
    }

    public function getPartnerIdAttribute()
    {
        $partner_id = null;
        if ($this->json_more != "" && $this->json_more) {
            $json_more = json_decode($this->json_more);
            $partner_id = $json_more->partner_id;
            if (!is_string($partner_id))
                $partner_id = (int) $partner_id;
        } else {
            $partner_id = $this->id_more;
        }
        return $partner_id;
    }

    public function getCategoryIdAttribute()
    {
        $category_id = null;
        if ($this->json_more != "" && $this->json_more) {
            $json_more = json_decode($this->json_more);
            $category_id = (int) $json_more->category_id;
        }
        return $category_id;
    }
}
