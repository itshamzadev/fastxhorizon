<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PartnerProgramNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $program, private readonly string $state, private readonly string $code = '', private readonly string $reference = '', private readonly string $reason = '') {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $identity = $this->program === 'affiliate' ? 'affiliates' : 'resellers';
        $address = config("fastxhorizon.mail_addresses.{$identity}");
        $name = config("fastxhorizon.mail_identity_display_names.{$identity}", 'FastXHorizon');
        $subject = match ($this->state) {
            'submitted' => 'FastXHorizon ' . ucfirst($this->program) . ' Application Received',
            'approved' => 'Your FastXHorizon ' . ucfirst($this->program) . ' Application Has Been Approved',
            'rejected' => 'Update on Your FastXHorizon ' . ucfirst($this->program) . ' Application',
            default => 'FastXHorizon ' . ucfirst($this->program) . ' Application Update',
        };
        $message = (new MailMessage)->from($address, $name)->replyTo(config('fastxhorizon.mail_addresses.support'), config('fastxhorizon.mail_identity_display_names.support', 'FastXHorizon - Support'))
            ->subject($subject)
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . ',')
            ->line($this->state === 'submitted' ? 'We have received your application and will review the information provided. Submission does not guarantee approval.' : 'Your FastXHorizon ' . $this->program . ' application status is: ' . str_replace('_', ' ', $this->state) . '.');
        if ($this->reference !== '') $message->line('Application reference: ' . $this->reference);
        if ($this->code !== '') $message->line('Your partner code is: ' . $this->code);
        if ($this->reason !== '' && $this->state === 'rejected') $message->line('Applicant-facing reason: ' . $this->reason);
        if ($this->state === 'approved') $message->action('Open your dashboard', route($this->program . '.dashboard'));
        return $message->line('Please contact support if you need assistance.');
    }
}
