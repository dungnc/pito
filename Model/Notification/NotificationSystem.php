<?php

namespace App\Model\Notification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class NotificationSystem extends Model
{

    protected $fillable = [
        'id', 'status', 'field_more', 'content', 'tag', 'type', 'type_id', 'user_id_append', 'created_at', 'updated_at'
    ];

    protected $hidden = [];

    protected $appends = ['field_more_object', 'is_seen'];

    protected $casts = [
        "created_at" => "timestamp",
        "updated_at" => "timestamp",
    ];

    /**
     * All Event in system and its tag name
     */
    const EVENT_TYPE = [
        // Discount's event
        "DISCOUNT.NEW" => "New Discount",

        //Payment's event
        "PAYMENT.ADD.HISTORY" => "Add history payment",
        "PAYMENT.DELETE.HISTORY" => "Delete history payment",

        //Order's Event
        "ORDER.ASSIGN" => "Assign Order",
        "ORDER.CHANGE" => "Change Order",
        "ORDER.CREATE" => "Create Order",
        "ORDER.CANCEL" => "Cancel Order",

        // Proposal's event
        "PROPOSAL.CUSTOMER.SEND" => "Send Proposal Customer",
        "PROPOSAL.CUSTOMER.ACCEPT" => "Accept Proposal Customer",
        "PROPOSAL.CUSTOMER.CHANGE" => "Proposal Customer Change Status",

        "PROPOSAL.PARTNER.SEND" => "Send Proposal Partner",
        "PROPOSAL.PARTNER.ACCEPT" => "Accept Proposal Customer",
        "PROPOSAL.PARTNER.CHANGE" => "Proposal Partner Change Status",

        // Party's Event
        "PARTY.START" => "Party start",
        "PARTY.COMPETE" => "Party complete",
    ];

    /**
     * Define which event will receive for each role
     */

    const ADMIN_EVENT = ["*"]; // all event
    //    const ADMIN_EVENT = [
    //        "DISCOUNT.*",
    //        "ORDER.*",
    //        "PAYMENT.*",
    //        "PROPOSAL.*",
    //        "PARTY.*"
    //    ];
    const PARTNER_EVENT = [
        "PARTY.*",
        "ORDER.*",
        "PROPOSAL.PARTNER.*",
        "DISCOUNT.*",
    ];
    const CUSTOMER_EVENT = [
        "DISCOUNT.*",
        "PAYMENT.*",
        "ORDER.*",
        "PROPOSAL.CUSTOMER.*",
        "PARTY.*"
    ];

    public function getFieldMoreObjectAttribute()
    {
        return json_decode($this->field_more);
    }

    public function getIsSeenAttribute()
    {
        $user = Auth::user();
        if (!$user) return false;
        if ($this->user_id_seen == null) return false;
        try {
            return strpos($this->user_id_seen, "-" . $user->id . "-") > -1;
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function type_object()
    {
        return $this->morphTo(null, 'type', 'type_id');
    }
}
