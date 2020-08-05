<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendContractNewPartner extends Mailable
{
    use Queueable, SerializesModels;

    public $partner;
    public $pito;
    public $contract;
    /**
     * Create a new name_contract instance.
     *
     * @return void
     */
    public function __construct($partner, $pito, $contract)
    {
        //
        $this->partner = $partner;
        $this->pito = $pito;
        $this->contract = $contract;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Hợp đồng hợp tác PITO - ' . $this->partner->name)
            ->view('mails.contract_partner');
    }
}
