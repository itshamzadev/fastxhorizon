<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Mail\Concerns\UsesFastXHorizonMailIdentity;
use Illuminate\Queue\SerializesModels;

class OrderReceivedNotifySeller extends Mailable
{
    use Queueable, SerializesModels, UsesFastXHorizonMailIdentity;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $o_id;

    public function __construct($o_id)
    {
        $this->o_id = $o_id;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $o_id = $this->o_id;
        return $this->subject(translate('Order_Received_Notify_Seller'))->view('email-templates.order-received-notify-seller', ['id' => $o_id])->applyFastXHorizonIdentity('sellers', 'sellers');
    }
}
