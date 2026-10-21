<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Mail\Concerns\UsesFastXHorizonMailIdentity;

class MailCenterMessage extends Mailable
{
    use Queueable, UsesFastXHorizonMailIdentity;

    public $mailSubject;
    public $plainText;
    public $identity;

    public function __construct($html, $mailSubject, $plainText = null, $identity = 'info')
    {
        $this->html = $html;
        $this->mailSubject = $mailSubject;
        $this->plainText = $plainText;
        $this->identity = $identity;
    }

    public function build(): self
    {
        $replyTo = $this->identity === 'news' ? 'info' : ($this->identity === 'noreply' ? 'support' : $this->identity);
        return $this->subject($this->mailSubject)->html($this->html)->applyFastXHorizonIdentity($this->identity, $replyTo);
    }
}
