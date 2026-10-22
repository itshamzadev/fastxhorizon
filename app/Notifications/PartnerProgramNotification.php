<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PartnerProgramNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $program, private readonly string $state, private readonly string $code = '') {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $identity = $this->program === 'affiliate' ? 'affiliates' : 'resellers';
        $address = config("fastxhorizon.mail_addresses.{$identity}");
        $name = config("fastxhorizon.mail_identity_display_names.{$identity}", 'FastXHorizon');
        $message = (new MailMessage)->from($address, $name)->replyTo(config('fastxhorizon.mail_addresses.support'), config('fastxhorizon.mail_identity_display_names.support', 'FastXHorizon - Support'))
            ->subject('FastXHorizon ' . ucfirst($this->program) . ' update')
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . ',')
            ->line('Your FastXHorizon ' . $this->program . ' application status is: ' . $this->state . '.');
        if ($this->code !== '') $message->line('Your partner code is: ' . $this->code);
        return $message->line('Please contact support if you need assistance.');
    }
}
