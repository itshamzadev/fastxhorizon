<?php

namespace App\Services;

use App\Mail\MailCenterMessage;
use App\Models\EmailTemplate;
use App\Models\MailDeliveryLog;
use App\Models\MailUnsubscribeToken;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MailCenterService
{
    public const VARIABLES = ['customer_name','customer_email','order_id','order_total','order_currency','order_status','product_name','seller_name','shop_name','tracking_number','verification_link','reset_link','wallet_amount','wallet_balance','refund_amount','withdrawal_amount','support_ticket_id','company_name','company_email','company_phone','website_url'];

    public function renderTemplate(?EmailTemplate $template, array $data = [], bool $marketing = false): array
    {
        $subject = $template?->title ?: ($data['subject'] ?? getCompanyIdentity('brand'));
        $body = $template?->body ?: ($data['html_body'] ?? '<p>{{company_name}}</p>');
        $values = array_merge($this->companyValues(), $data);
        $values['unsubscribe_url'] = $data['unsubscribe_url'] ?? '#';
        foreach ($values as $key => $value) {
            $subject = str_replace(['{{'.$key.'}}', '{{ '.$key.' }}'], e((string) $value), $subject);
            $body = str_replace(['{{'.$key.'}}', '{{ '.$key.' }}'], e((string) $value), $body);
        }
        $body = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $body);
        $body = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/is', '', $body);
        $body = str_replace('{{unsubscribe_url}}', e($values['unsubscribe_url']), $body);
        return [$subject, $body];
    }

    public function send(string $recipient, string $subject, string $html, string $type = 'transactional', ?string $templateKey = null, ?string $campaignId = null): MailDeliveryLog
    {
        $log = MailDeliveryLog::create([
            'message_id' => (string) Str::uuid(), 'template_key' => $templateKey, 'campaign_id' => $campaignId,
            'recipient' => $recipient, 'subject' => $subject, 'type' => $type, 'status' => 'processing', 'attempts' => 1, 'queued_at' => now(),
        ]);
        try {
            $identity = $type === 'marketing' ? 'news' : 'noreply';
            Mail::to($recipient)->send(new MailCenterMessage($html, $subject, null, $identity));
            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
            $log->update(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => Str::limit($e->getMessage(), 1000)]);
        }
        return $log->refresh();
    }

    public function unsubscribeUrl(string $email): string
    {
        $token = MailUnsubscribeToken::firstOrCreate(['email' => strtolower(trim($email))], ['token' => Str::random(64)]);
        return url('/mail/unsubscribe/' . $token->token);
    }

    public function isUnsubscribed(string $email): bool
    {
        return MailUnsubscribeToken::where('email', strtolower(trim($email)))->whereNotNull('unsubscribed_at')->exists();
    }

    public function unsupportedVariables(string $content): array
    {
        preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $content, $matches);
        return array_values(array_diff(array_unique($matches[1] ?? []), array_merge(self::VARIABLES, ['unsubscribe_url'])));
    }

    private function companyValues(): array
    {
        return [
            'company_name' => getCompanyIdentity('brand'),
            'company_email' => getWebConfig(name: 'company_email') ?: 'info@fastxhorizon.com',
            'company_phone' => getWebConfig(name: 'company_phone') ?: '+923265680129',
            'website_url' => url('/'),
        ];
    }
}
