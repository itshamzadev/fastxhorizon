<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Mail\Concerns\UsesFastXHorizonMailIdentity;

class MailCenterMessage extends Mailable
{
    use Queueable, UsesFastXHorizonMailIdentity;

    public $htmlBody;
    public $mailSubject;
    public $plainText;
    public $identity;

    public function __construct($html, $mailSubject, $plainText = null, $identity = 'info')
    {
        $this->htmlBody = $html;
        $this->mailSubject = $mailSubject;
        $this->plainText = $plainText;
        $this->identity = $identity;
    }

    public function build(): self
    {
        $replyTo = $this->identity === 'news' ? 'info' : ($this->identity === 'noreply' ? 'support' : $this->identity);
        $message = $this->subject($this->mailSubject)
            ->view('email-templates.mail-center-message', [
                'html' => $this->htmlBody,
                'mailSubject' => $this->mailSubject,
            ])
            ->applyFastXHorizonIdentity($this->identity, $replyTo);

        if ($this->plainText !== null) {
            $message->text('email-templates.partials.fastxhorizon-footer-text', [
                'plainTextBody' => $this->plainText,
            ]);
        }

        return $message;
    }
}
