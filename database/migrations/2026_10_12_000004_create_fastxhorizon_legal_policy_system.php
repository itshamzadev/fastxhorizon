<?php

use App\Models\BusinessPage;
use App\Models\BusinessSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_pages')) {
            Schema::table('business_pages', function (Blueprint $table) {
                $columns = Schema::getColumnListing('business_pages');
                if (!in_array('hero_title', $columns, true)) $table->string('hero_title')->nullable();
                if (!in_array('hero_subtitle', $columns, true)) $table->text('hero_subtitle')->nullable();
                if (!in_array('at_a_glance', $columns, true)) $table->text('at_a_glance')->nullable();
                if (!in_array('seo_title', $columns, true)) $table->string('seo_title')->nullable();
                if (!in_array('seo_description', $columns, true)) $table->text('seo_description')->nullable();
                if (!in_array('canonical_url', $columns, true)) $table->string('canonical_url')->nullable();
                if (!in_array('policy_status', $columns, true)) $table->string('policy_status')->default('published')->index();
                if (!in_array('version', $columns, true)) $table->unsignedInteger('version')->default(1);
                if (!in_array('published_at', $columns, true)) $table->timestamp('published_at')->nullable();
                if (!in_array('published_by', $columns, true)) $table->unsignedBigInteger('published_by')->nullable();
                if (!in_array('content_blocks', $columns, true)) $table->longText('content_blocks')->nullable();
            });
        }

        if (!Schema::hasTable('legal_policy_versions')) {
            Schema::create('legal_policy_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_page_id')->constrained('business_pages')->cascadeOnDelete();
                $table->unsignedInteger('version');
                $table->string('title');
                $table->string('hero_title')->nullable();
                $table->text('hero_subtitle')->nullable();
                $table->text('at_a_glance')->nullable();
                $table->longText('description')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->string('policy_status')->default('draft')->index();
                $table->timestamp('published_at')->nullable();
                $table->unsignedBigInteger('published_by')->nullable();
                $table->timestamps();
                $table->unique(['business_page_id', 'version']);
            });
        }

        if (!Schema::hasTable('company_leadership_profiles')) {
            Schema::create('company_leadership_profiles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('role');
                $table->string('photo')->nullable();
                $table->text('bio')->nullable();
                $table->unsignedInteger('display_order')->default(0);
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('seller_compliance_records')) {
            Schema::create('seller_compliance_records', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seller_id')->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('complaint_id')->nullable();
                $table->unsignedInteger('points')->default(1);
                $table->string('source')->default('manual');
                $table->string('reason');
                $table->text('evidence')->nullable();
                $table->string('status')->default('verified')->index();
                $table->text('admin_note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->unsignedBigInteger('reversed_by')->nullable();
                $table->text('reversal_reason')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('customer_wallet_withdrawal_requests')) {
            Schema::create('customer_wallet_withdrawal_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->index();
                $table->decimal('amount', 18, 2);
                $table->decimal('withdrawable_amount', 18, 2)->default(0);
                $table->string('currency_code', 10)->default('PKR');
                $table->string('method');
                $table->text('payment_details');
                $table->string('reference')->unique();
                $table->string('status')->default('pending')->index();
                $table->text('customer_note')->nullable();
                $table->text('admin_note')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('refund_requests')) {
            Schema::table('refund_requests', function (Blueprint $table) {
                $columns = Schema::getColumnListing('refund_requests');
                if (!in_array('request_type', $columns, true)) $table->string('request_type')->default('refund');
                if (!in_array('problem_type', $columns, true)) $table->string('problem_type')->nullable();
                if (!in_array('description', $columns, true)) $table->text('description')->nullable();
                if (!in_array('condition_confirmed', $columns, true)) $table->boolean('condition_confirmed')->default(false);
                if (!in_array('preferred_resolution', $columns, true)) $table->string('preferred_resolution')->nullable();
                if (!in_array('source_type', $columns, true)) $table->string('source_type')->nullable();
                if (!in_array('seller_id', $columns, true)) $table->unsignedBigInteger('seller_id')->nullable()->index();
                if (!in_array('reviewed_by', $columns, true)) $table->unsignedBigInteger('reviewed_by')->nullable();
                if (!in_array('reviewed_at', $columns, true)) $table->timestamp('reviewed_at')->nullable();
                if (!in_array('refund_destination', $columns, true)) $table->string('refund_destination')->default('wallet');
                if (!in_array('currency_code', $columns, true)) $table->string('currency_code', 10)->nullable();
                if (!in_array('exchange_rate', $columns, true)) $table->decimal('exchange_rate', 24, 12)->nullable();
            });
        }

        $settings = [
            'legal_company_name' => 'FASTXHORIZON (PRIVATE) LIMITED',
            'company_founded_year' => '2026',
            'company_registration_type' => '',
            'company_registration_number' => '',
            'company_registration_date' => '',
            'company_public_email' => 'info@fastxhorizon.com',
            'company_public_phone' => '+92 326 5680129',
            'company_public_website' => 'www.fastxhorizon.com',
            'company_public_office_address' => '',
            'company_show_office_address' => '0',
            'wallet_withdrawal_enabled' => '0',
            'return_window_days' => '7',
            'seller_black_point_warning_threshold' => '3',
            'seller_black_point_final_warning_threshold' => '5',
            'seller_black_point_suspension_threshold' => '7',
            'seller_cancellation_review_enabled' => '1',
        ];
        foreach ($settings as $type => $value) {
            DB::table('business_settings')->updateOrInsert(['type' => $type], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }

        // These are owner-supplied public contact details, not fabricated registration data.
        DB::table('business_settings')->where('type', 'company_email')->update(['value' => 'info@fastxhorizon.com', 'updated_at' => now()]);
        DB::table('business_settings')->where('type', 'company_phone')->update(['value' => '+92 326 5680129', 'updated_at' => now()]);

        $defaults = self::policyDefaults();
        foreach ($defaults as $slug => $data) {
            $page = BusinessPage::where('slug', $slug)->first();
            if (!$page) continue;
            $page->forceFill([
                'hero_title' => $data['hero_title'],
                'hero_subtitle' => $data['hero_subtitle'],
                'at_a_glance' => $data['at_a_glance'],
                'seo_title' => $data['seo_title'],
                'seo_description' => $data['seo_description'],
                'canonical_url' => url('/business-page/' . $slug),
                'policy_status' => 'published',
                'published_at' => $page->published_at ?? now(),
                'content_blocks' => json_encode($data['blocks']),
            ]);
            if (blank($page->description) || $page->default_status) $page->description = $data['html'];
            $page->save();
        }
    }

    public function down(): void
    {
        foreach (['refund_requests' => ['request_type','problem_type','description','condition_confirmed','preferred_resolution','source_type','seller_id','reviewed_by','reviewed_at','refund_destination','currency_code','exchange_rate'], 'business_pages' => ['hero_title','hero_subtitle','at_a_glance','seo_title','seo_description','canonical_url','policy_status','version','published_at','published_by','content_blocks']] as $table => $columns) {
            if (Schema::hasTable($table)) {
                foreach ($columns as $column) if (Schema::hasColumn($table, $column)) Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
            }
        }
        Schema::dropIfExists('customer_wallet_withdrawal_requests');
        Schema::dropIfExists('seller_compliance_records');
        Schema::dropIfExists('company_leadership_profiles');
        Schema::dropIfExists('legal_policy_versions');
    }

    private static function policyDefaults(): array
    {
        return [
            'terms-and-conditions' => [
                'hero_title' => 'Marketplace terms, clearly explained.',
                'hero_subtitle' => 'The rules that help customers, sellers, and Fast X Horizon transact with confidence.',
                'at_a_glance' => 'Fast X Horizon is a multi-vendor marketplace. Sellers are responsible for their listings, quality, authenticity, inventory, and fulfilment; Fast X Horizon provides the marketplace infrastructure, moderation, ordering, support, and policy enforcement.',
                'seo_title' => 'Terms & Conditions | Fast X Horizon',
                'seo_description' => 'Terms governing use of the Fast X Horizon marketplace by customers and sellers.',
                'blocks' => [],
                'html' => '<h2>1. About the marketplace</h2><p>FASTXHORIZON (PRIVATE) LIMITED operates Fast X Horizon as a multi-vendor ecommerce marketplace. Fast X Horizon may sell selected products directly and also provides a platform for independent sellers.</p><h2>2. Seller responsibility</h2><p>Sellers are responsible for accurate listings, product quality, authenticity, stock, fulfilment, customer-facing information, and compliance with applicable law. Seller listings are subject to Fast X Horizon approval, moderation, correction requests, suspension, or removal.</p><h2>3. Orders and payments</h2><p>Available payment methods, delivery coverage, international destinations, customs, duties, and payment-gateway support may vary by product, seller, customer location, and order. Digital products require advance payment and are not eligible for cash on delivery.</p><h2>4. Returns, refunds, and cancellations</h2><p>Return, refund, and cancellation rights are described in the related policies. A customer may normally request cancellation before seller or Fast X Horizon confirmation. Approved refunds are credited to Fast X Horizon Wallet where enabled, subject to verification and applicable law.</p><h2>5. Prohibited activity</h2><p>Illegal drugs, weapons, explosives, counterfeit or stolen goods, unlawful controlled substances, prohibited hazardous items, and other unlawful marketplace content are not permitted. Fast X Horizon may preserve evidence, suspend accounts, remove listings, and cooperate with lawful investigations.</p><h2>6. Applicable law</h2><p>These terms are subject to applicable law. Any governing-law or dispute-resolution details may be updated by an authorized administrator after appropriate legal review.</p>',
            ],
            'privacy-policy' => [
                'hero_title' => 'Privacy built into the marketplace experience.',
                'hero_subtitle' => 'How Fast X Horizon uses information to operate a safer, more useful digital marketplace.',
                'at_a_glance' => 'We process information needed for accounts, orders, delivery, payments, support, security, and marketplace operations. We do not sell customers’ personal data to advertisers.',
                'seo_title' => 'Privacy Policy | Fast X Horizon',
                'seo_description' => 'How Fast X Horizon handles customer, seller, order, payment, support, and security information.',
                'blocks' => [],
                'html' => '<h2>1. Information we process</h2><p>Depending on how you use Fast X Horizon, this may include name, email, phone, account details, shipping and billing information, order history, cart and wishlist data, support and return communications, uploaded evidence, payment references, wallet transactions, device and browser information, IP address, cookies, and country-level location used for localization such as currency selection.</p><h2>2. How information is used</h2><p>We use information to create and secure accounts, process orders and payments, coordinate delivery, provide customer support, manage refunds and returns, maintain wallet and accounting records, prevent fraud, improve the service, and comply with applicable obligations.</p><h2>3. Sellers and service providers</h2><p>Sellers receive only the customer and order information reasonably necessary to fulfil their own orders and resolve related service cases. We may use payment, shipping, email, SMS, hosting, cloud, security, and other service providers when reasonably required to operate the platform.</p><h2>4. Location and cookies</h2><p>Fast X Horizon may use coarse country-level IP geolocation for localization. Precise GPS permission is not required merely to select a currency. Cookies and session storage may preserve login, cart, language, and currency preferences.</p><h2>5. Retention and deletion</h2><p>You may request account deletion. We may retain order, refund, wallet, accounting, security, dispute, and compliance records where reasonably required. Retention does not mean that we publish those records or sell them to advertisers.</p><h2>6. Seller communication privacy</h2><p>Sellers must not request unnecessary personal or off-platform payment information through marketplace communication. Verified privacy violations may be investigated under the seller compliance process.</p>',
            ],
            'refund-policy' => [
                'hero_title' => 'A refund process designed for clarity.',
                'hero_subtitle' => 'Submit evidence, follow the review path, and track the outcome from your account.',
                'at_a_glance' => 'Approved refunds are normally credited to Fast X Horizon Wallet first. Every refund remains linked to the order, item, source seller or in-house shop, amount, and historical currency snapshot.',
                'seo_title' => 'Refund Policy | Fast X Horizon',
                'seo_description' => 'Refund review, wallet credit, evidence, and resolution rules for Fast X Horizon orders.',
                'blocks' => [],
                'html' => '<h2>1. Requesting a refund</h2><p>Use the refund or return action from an eligible order item. Provide the reason, problem type, description, condition confirmation, and clear photos or other available evidence. Do not submit a request for an order or item that does not belong to your account.</p><h2>2. Review and responsibility</h2><p>Seller products are first reviewed by the responsible seller with Fast X Horizon oversight. Fast X Horizon handles cases involving its own in-house products and may escalate or override a case where policy or evidence requires it.</p><h2>3. Wallet-first destination</h2><p>When a refund is approved, the exact approved amount is credited to Fast X Horizon Wallet by default where wallet refunds are enabled. Refunds are idempotent: a repeated approval cannot create a second wallet credit.</p><h2>4. Historical amounts</h2><p>Refunds use the order’s saved currency and exchange-rate snapshot. A later rate update does not change the historical refund amount or wallet ledger entry.</p><h2>5. Digital products</h2><p>After successful delivery of a file, link, licence, or digital access, a digital purchase is normally non-returnable and non-refundable. If delivery fails, contact support for redelivery or resolution subject to applicable law.</p>',
            ],
            'return-policy' => [
                'hero_title' => 'Returns with a fair, evidence-led process.',
                'hero_subtitle' => 'Know the window, prepare the item, and send the case to the right review queue.',
                'at_a_glance' => 'Eligible returns normally must be requested within 7 days of delivery. Items should be unused and in suitable original condition, except that reasonable inspection of a defective item does not automatically remove eligibility.',
                'seo_title' => 'Return Policy | Fast X Horizon',
                'seo_description' => 'Return window, eligibility, evidence, seller routing, and resolution rules for Fast X Horizon.',
                'blocks' => [],
                'html' => '<h2>1. Seven-day request window</h2><p>Subject to the product category and applicable law, an eligible return request should be submitted within 7 days of delivery. The order record and delivery status are used to determine the window.</p><h2>2. Eligible condition</h2><p>For a change-of-mind return, the item should be unused, unworn where relevant, in suitable packaging, and with tags or seals intact where reasonably required. A normal product that has been materially used is not eligible merely because the customer changed their mind.</p><h2>3. Defective, damaged, or wrong items</h2><p>Reasonable inspection or testing to identify a defect does not automatically void a valid claim. Evidence may include photos, video, packaging, shipping labels, and order details. The responsible seller handles seller-product remedies; Fast X Horizon handles its own in-house cases.</p><h2>4. Non-returnable categories</h2><p>Digital products after delivery, personalized goods, opened hygiene-sensitive products, perishables, and other categories configured by the platform may be non-returnable. The product or category notice should be checked before purchase.</p><h2>5. Seller refusal</h2><p>If a seller improperly refuses a verified valid damaged, wrong, or defective claim, Fast X Horizon may escalate the case and record a verified compliance point after review.</p>',
            ],
            'cancellation-policy' => [
                'hero_title' => 'Cancellation rules that respect both sides.',
                'hero_subtitle' => 'Cancel early when the order is still awaiting confirmation; after that, use the return and refund process.',
                'at_a_glance' => 'Customers may normally cancel before seller or Fast X Horizon confirmation. Once processing has passed the cancellation point, the relevant delivery, return, and refund policies apply.',
                'seo_title' => 'Cancellation Policy | Fast X Horizon',
                'seo_description' => 'Customer and seller order-cancellation rules for Fast X Horizon.',
                'blocks' => [],
                'html' => '<h2>1. Customer cancellation</h2><p>The customer cancellation action is available only while the order is eligible and awaiting seller or Fast X Horizon confirmation. The server verifies the order owner, payment state, and status; hiding a button in the browser is not the only protection.</p><h2>2. After confirmation</h2><p>Once an order is confirmed or has progressed into processing, normal cancellation is not guaranteed. A delivered or defective item should be handled through the return and refund process.</p><h2>3. Seller cancellation</h2><p>If a seller cannot fulfil a prepaid order and cancels it, the customer is eligible for the applicable full advance-payment refund. A cash-on-delivery order has no paid amount to refund.</p><h2>4. Repeated seller cancellations</h2><p>Legitimate exceptional cancellations are reviewed fairly. Verified repeated or intentional abuse may be escalated as a seller compliance matter and can contribute to the warning, final-warning, and suspension thresholds.</p>',
            ],
            'about-us' => [
                'hero_title' => 'Building a smarter global marketplace.',
                'hero_subtitle' => 'FASTXHORIZON (PRIVATE) LIMITED connects customers, independent sellers, and selected Fast X Horizon products through a technology-led ecommerce marketplace.',
                'at_a_glance' => 'Founded in 2026, Fast X Horizon is building a trusted and scalable digital marketplace. Registration and license details will appear publicly only after an approved value is entered by an administrator.',
                'seo_title' => 'About Fast X Horizon | FASTXHORIZON (PRIVATE) LIMITED',
                'seo_description' => 'FASTXHORIZON (PRIVATE) LIMITED is building a modern multi-vendor ecommerce marketplace for customers and sellers.',
                'blocks' => [],
                'html' => '',
            ],
        ];
    }
};
