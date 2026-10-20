<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

class MailCenterMessage extends Mailable
{
    use Queueable;

    public function __construct(public string $html, public string $mailSubject, public ?string $plainText = null) {}

    public function build(): self
    {
        return $this->subject($this->mailSubject)->html($this->html);
    }
}
