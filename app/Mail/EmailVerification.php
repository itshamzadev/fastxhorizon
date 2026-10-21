<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Mail\Concerns\UsesFastXHorizonMailIdentity;
use Illuminate\Queue\SerializesModels;

class EmailVerification extends Mailable
{
    use Queueable, SerializesModels, UsesFastXHorizonMailIdentity;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject(translate('Email_Verification'))->view('email-templates.email-verification', ['token' => $this->token])->applyFastXHorizonIdentity('noreply', 'support');
    }
}
