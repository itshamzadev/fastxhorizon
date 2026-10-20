<?php

namespace App\Jobs;

use App\Models\MailCampaignRecipient;
use App\Models\MailDeliveryLog;
use App\Services\MailCenterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMailCampaignRecipient implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public int $timeout = 120;
    public int $uniqueFor = 3600;

    public function __construct(public int $recipientId) {}

    public function uniqueId(): string
    {
        return 'mail-campaign-recipient:' . $this->recipientId;
    }

    public function handle(MailCenterService $service): void
    {
        $recipient = MailCampaignRecipient::find($this->recipientId);
        if (! $recipient || $recipient->status === 'sent') return;
        $campaign = $recipient->campaign()->first();
        if (! $campaign || $service->isUnsubscribed($recipient->email)) {
            $recipient->update(['status' => 'skipped']);
            return;
        }
        $html = $campaign->html_body . '<p style="font-size:12px"><a href="' . e($service->unsubscribeUrl($recipient->email)) . '">Unsubscribe</a></p>';
        $log = $service->send($recipient->email, $campaign->subject, $html, 'marketing', null, (string) $campaign->id);
        $recipient->update(['status' => $log->status, 'delivery_log_id' => $log->id, 'sent_at' => $log->sent_at]);
    }
}
