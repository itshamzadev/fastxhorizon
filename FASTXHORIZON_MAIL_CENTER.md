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

The existing Laravel SMTP configuration is reused. Production authenticates
with the Google Workspace mailbox `info@fastxhorizon.com`; approved aliases
are configured centrally in `config/fastxhorizon.php`. Credentials remain
server-side and are never written to logs or source control.

Approved identities are `info`, `support`, `contact`, `orders`, `sellers`,
`resellers`, `affiliates`, `returns`, `billing`, `news`, `noreply`,
`security`, and `careers`. Their display names follow the Gmail “Send mail
as” convention, for example `FastXHorizon - Orders`,
`FastXHorizon - Support`, and `FastXHorizon - News`; the exact mapping is
centralized in `config/fastxhorizon.php`. Transactional template mail selects
an identity from the user/mail type; orders use `orders`, seller
notifications use `sellers`, marketing campaigns use `news`, and
account/system messages use `noreply`. Reply-To values use a response alias
where appropriate.

Safe identity checks can be rendered without sending:

```text
php artisan fastxhorizon:mail-test test@example.com --from=orders --render-only
php artisan fastxhorizon:mail-test test@example.com --from=orders
```

The command validates only approved identities and never prints SMTP
credentials.

## Canonical logo and email footer

`fastxhorizon.png` in the project root is the canonical logo source. The
branding sync command derives the web, invoice, mobile, and footer PNG
variants from it while retaining the separate favicon and loader assets.
Mail templates resolve the active logo through the centralized
`company_web_logo` setting.

The preferred formal footer is:

```text
FASTXHORIZON (PRIVATE) LIMITED
E-Commerce Marketplace
fastxhorizon.com | info@fastxhorizon.com
```

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

