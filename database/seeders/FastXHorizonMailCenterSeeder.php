<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FastXHorizonMailCenterSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $templates = [
            'welcome_email','email_verification','password_reset','password_changed','account_login_alert','account_deleted',
            'order_placed','order_confirmed','order_processing','order_shipped','out_for_delivery','order_delivered','order_cancelled','seller_cancelled_order','payment_successful','payment_failed',
            'return_request_received','return_approved','return_rejected','refund_request_received','refund_approved','refund_credited_wallet','refund_rejected',
            'wallet_credit','wallet_debit','withdrawal_submitted','withdrawal_approved','withdrawal_rejected','withdrawal_paid',
            'seller_registration_received','seller_approved','seller_rejected','seller_warning','seller_final_warning','seller_suspended','seller_reactivated','product_approved','product_rejected','new_order_for_seller',
            'support_ticket_created','support_reply','ticket_closed',
            'newsletter_welcome','promotional_campaign','special_offer','product_launch','deal_announcement','back_in_stock','seasonal_sale','marketplace_announcement',
        ];
        foreach ($templates as $key) {
            $type = str_starts_with($key, 'seller_') || $key === 'new_order_for_seller' || str_starts_with($key, 'product_') ? 'vendor' : (in_array($key, ['seller_registration_received'], true) ? 'admin' : 'customer');
            $title = ucwords(str_replace('_', ' ', $key));
            DB::table('email_templates')->updateOrInsert(
                ['template_name' => 'fxh_' . $key, 'user_type' => $type],
                [
                    'template_design_name' => 'fxh-mail-center',
                    'title' => $title . ' | {{company_name}}',
                    'body' => '<h2 style="color:#12345b">' . $title . '</h2><p>Hello {{customer_name}},</p><p>This is your FastXHorizon account and marketplace update.</p><p><strong>Reference:</strong> {{order_id}}</p><p>For help, contact {{company_email}} or {{company_phone}}.</p><p><a href="{{website_url}}">Visit FastXHorizon</a></p>',
                    'footer_text' => 'FastXHorizon · {{company_email}} · {{company_phone}}',
                    'copyright_text' => '© FastXHorizon',
                    'hide_field' => json_encode([]), 'pages' => json_encode([]), 'social_media' => json_encode([]),
                    'button_content_status' => 1, 'product_information_status' => 1, 'order_information_status' => 1,
                    'status' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]
            );
        }
        $this->command?->info('Fast X Horizon Mail Center templates seeded: ' . count($templates));
    }
}
