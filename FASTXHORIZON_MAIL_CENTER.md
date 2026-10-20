# Fast X Horizon Mail Center

## Architecture

Mail Center extends the installed 6Valley mail stack. Existing Laravel
`Mailable` classes, `EmailTemplate`, SMTP/SendGrid business settings, and the
existing `subscriptions` table remain the source of truth for current flows.
The new center adds a single admin surface instead of replacing those flows.

## Admin navigation

The new Mail module is permission-aware through the existing
`system_settings` module permission and provides Dashboard, Email Templates,
Campaigns, Newsletter Subscribers, Delivery Logs, Mail Queue, and Settings.
Settings opens the existing SMTP configuration page.

## Templates and variables

Fifty namespaced `fxh_*` templates cover account, order, refund, wallet,
withdrawal, seller, support, newsletter, promotion, launch, deal, and
announcement messages. Existing template editing remains available. Preview
and test-send use safe sample data and a restricted placeholder renderer;
arbitrary PHP/scripts are never evaluated.

Supported variables include customer, order, seller, wallet, refund,
withdrawal, support-ticket, and centralized company fields such as
`{{company_name}}`, `{{company_email}}`, `{{company_phone}}`, and
`{{website_url}}`.

## SMTP and sender identity

The current 6Valley SMTP/SendGrid settings are reused. Configure provider,
host, port, encryption, username, password, sender, and reply-to in the
existing SMTP screen. Credentials are stored server-side by the existing
settings architecture and are never written to delivery logs.

## Campaigns and consent

Campaign drafts support newsletter, special offer, product launch, deal,
seasonal sale, and announcement types. Sends use existing `subscriptions`
records, skip invalid/unsubscribed recipients, create one campaign-recipient
record per email, and include a signed/tokenized unsubscribe URL. Transactional
mail remains separate from marketing opt-out behavior.

## Logs and queue

Each Mail Center send creates a delivery log with status, recipient, subject,
attempt count, timestamps, and sanitized failure reason. Campaign recipients
are dispatched through `SendMailCampaignRecipient`, with three attempts and a
120-second timeout. Run a production queue worker (`php artisan queue:work`)
and scheduler/cron according to the configured queue driver.

## Local testing

Without real SMTP credentials, test sends will be logged as failed rather than
reported as delivered. Use the preview route for design checks and configure a
local `log`/Mailpit transport for development. SMTP acceptance is not the same
as inbox delivery.

## Migration and seeders

`2026_10_13_000010_create_mail_center_tables.php` adds delivery logs, campaign
records/recipients, and unsubscribe tokens. `FastXHorizonMailCenterSeeder`
adds the 50 reusable template records idempotently. No existing subscribers,
mail history, orders, or settings are deleted.

