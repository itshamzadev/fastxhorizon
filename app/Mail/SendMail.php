<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Mail\Concerns\UsesFastXHorizonMailIdentity;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendMail extends Mailable
{
    use Queueable, SerializesModels, UsesFastXHorizonMailIdentity;

    protected $data;
    protected $template;
    protected $socialMedia;

    /**
     * Create a new message instance.
     */
    public function __construct($data, $template, $socialMedia)
    {
        $this->data = $data;
        $this->template = $template;
        $this->socialMedia = $socialMedia;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $identity = $this->data['mail_identity'] ?? config('fastxhorizon.mail_identity_by_user_type.' . ($this->data['user_type'] ?? 'customer'), 'noreply');
        return $this->fastXHorizonEnvelope($this->data['subject'], $identity, $identity === 'news' ? 'info' : 'support');
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'email-templates.index', with: ['data' => $this->data, 'template' => $this->template, 'socialMedia' => $this->socialMedia]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (isset($this->data['attachmentPath'])) {
            return [
                Attachment::fromPath($this->data['attachmentPath'])
                    ->as('invoice.pdf')
                    ->withMime('application/pdf')
            ];
        }
        return [];
    }
}
