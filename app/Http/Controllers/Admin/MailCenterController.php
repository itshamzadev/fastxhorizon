<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendMailCampaignRecipient;
use App\Models\EmailTemplate;
use App\Models\MailCampaign;
use App\Models\MailCampaignRecipient;
use App\Models\MailDeliveryLog;
use App\Models\MailUnsubscribeToken;
use App\Models\Subscription;
use App\Services\MailCenterService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MailCenterController extends Controller
{
    public function __construct(private readonly MailCenterService $mailService) {}

    public function index()
    {
        return $this->dashboard();
    }

    public function dashboard()
    {
        return view('admin-views.mail-center.dashboard', [
            'sentToday' => MailDeliveryLog::where('status', 'sent')->whereDate('sent_at', today())->count(),
            'failedToday' => MailDeliveryLog::where('status', 'failed')->whereDate('failed_at', today())->count(),
            'queued' => MailDeliveryLog::whereIn('status', ['queued', 'processing'])->count(),
            'campaigns' => MailCampaign::count(),
            'subscribers' => Subscription::count(),
            'unsubscribed' => MailUnsubscribeToken::whereNotNull('unsubscribed_at')->count(),
            'failures' => MailDeliveryLog::where('status', 'failed')->latest()->limit(10)->get(),
        ]);
    }

    public function templates()
    {
        $templates = EmailTemplate::query()->latest('updated_at')->paginate(25);
        $warnings = [];
        foreach ($templates as $template) {
            $warnings[$template->id] = $this->mailService->unsupportedVariables(($template->title ?? '') . ' ' . ($template->body ?? ''));
        }
        return view('admin-views.mail-center.templates', ['templates' => $templates, 'warnings' => $warnings, 'variables' => MailCenterService::VARIABLES]);
    }

    public function preview(int $id)
    {
        $template = EmailTemplate::findOrFail($id);
        [, $html] = $this->mailService->renderTemplate($template, $this->sampleVariables());
        return view('admin-views.mail-center.preview', ['template' => $template, 'html' => $html]);
    }

    public function testTemplate(Request $request, int $id): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $template = EmailTemplate::findOrFail($id);
        [$subject, $html] = $this->mailService->renderTemplate($template, $this->sampleVariables());
        $log = $this->mailService->send($request->email, $subject, $html, 'test', $template->template_name);
        $log->status === 'sent' ? ToastMagic::success('Test email accepted by the configured mail transport.') : ToastMagic::error('Test email failed. Check Delivery Logs and SMTP settings.');
        return back();
    }

    public function campaigns()
    {
        return view('admin-views.mail-center.campaigns', ['campaigns' => MailCampaign::latest()->paginate(20)]);
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'], 'type' => ['required', 'in:newsletter,special_offer,product_launch,deal_announcement,seasonal_sale,announcement'],
            'subject' => ['required', 'string', 'max:191'], 'preheader' => ['nullable', 'string', 'max:191'],
            'html_body' => ['required', 'string', 'max:200000'], 'cta_text' => ['nullable', 'string', 'max:100'], 'cta_url' => ['nullable', 'url', 'max:500'],
        ]);
        $data['html_body'] = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $data['html_body']);
        $data['status'] = 'draft'; $data['created_by'] = auth('admin')->id();
        MailCampaign::create($data);
        ToastMagic::success('Campaign saved as draft.');
        return back();
    }

    public function sendCampaign(int $id): RedirectResponse
    {
        $campaign = MailCampaign::findOrFail($id);
        abort_if(in_array($campaign->status, ['sending', 'completed'], true), 422, 'Campaign cannot be sent in its current state.');
        $campaign->update(['status' => 'sending', 'started_at' => now()]);
        $subscribers = Subscription::query()->whereNotNull('email')->get()->filter(fn ($row) => filter_var($row->email, FILTER_VALIDATE_EMAIL) && ! $this->mailService->isUnsubscribed($row->email));
        foreach ($subscribers as $subscriber) {
            $recipient = MailCampaignRecipient::firstOrCreate(['campaign_id' => $campaign->id, 'email' => strtolower(trim($subscriber->email))]);
            if ($recipient->status !== 'sent') SendMailCampaignRecipient::dispatch($recipient->id);
        }
        ToastMagic::success('Campaign recipients queued.');
        return back();
    }

    public function logs()
    {
        return view('admin-views.mail-center.logs', ['logs' => MailDeliveryLog::latest()->paginate(40)]);
    }

    public function retry(int $id): RedirectResponse
    {
        $log = MailDeliveryLog::findOrFail($id);
        abort_if($log->status !== 'failed', 422, 'Only failed messages can be retried.');
        if ($log->campaign_id) {
            $recipient = MailCampaignRecipient::where('campaign_id', $log->campaign_id)->where('email', $log->recipient)->first();
            if ($recipient) {
                $recipient->update(['status' => 'queued']);
                SendMailCampaignRecipient::dispatch($recipient->id);
                ToastMagic::success('Failed campaign email queued for retry.');
            }
        }
        return back();
    }

    public function subscribers()
    {
        return view('admin-views.mail-center.subscribers', ['subscribers' => Subscription::latest()->paginate(40), 'unsubscribed' => MailUnsubscribeToken::whereNotNull('unsubscribed_at')->count()]);
    }

    public function settings(): RedirectResponse
    {
        return redirect()->route('admin.third-party.mail.index');
    }

    private function sampleVariables(): array
    {
        return ['customer_name' => 'Amina Khan', 'customer_email' => 'customer@example.com', 'order_id' => 'FXH-100245', 'order_total' => '4,500.00', 'order_currency' => 'PKR', 'order_status' => 'Confirmed', 'product_name' => 'Nexora AirBeat Headphones', 'seller_name' => 'NovaGear Store', 'shop_name' => 'NovaGear Store', 'tracking_number' => 'FXH-TRK-9921', 'verification_link' => url('/customer/auth/verify'), 'reset_link' => url('/customer/auth/forgot-password'), 'wallet_amount' => '1,500.00', 'wallet_balance' => '3,250.00', 'refund_amount' => '1,500.00', 'withdrawal_amount' => '3,000.00', 'support_ticket_id' => 'FXH-TKT-1001'];
    }
}
