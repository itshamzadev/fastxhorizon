<?php

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

trait UsesFastXHorizonMailIdentity
{
    protected function fastXHorizonAddress(string $key): Address
    {
        $address = config('fastxhorizon.mail_addresses.' . $key);
        if (!is_string($address) || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid FastXHorizon mail identity: ' . $key);
        }

        return new Address($address, 'FastXHorizon');
    }

    protected function fastXHorizonEnvelope(string $subject, string $from = 'noreply', ?string $replyTo = 'support'): Envelope
    {
        return new Envelope(
            subject: $subject,
            from: $this->fastXHorizonAddress($from),
            replyTo: $replyTo ? [$this->fastXHorizonAddress($replyTo)] : [],
        );
    }

    protected function applyFastXHorizonIdentity(string $from = 'noreply', ?string $replyTo = 'support'): static
    {
        $this->from($this->fastXHorizonAddress($from));
        if ($replyTo) {
            $this->replyTo($this->fastXHorizonAddress($replyTo));
        }

        return $this;
    }
}
