# Fast X Horizon Legal & Policy System

## Public company identity

The public brand is **Fast X Horizon** and the legal company name is **FASTXHORIZON (PRIVATE) LIMITED**. The company profile is configured as founded in **2026**. Public contact details are editable in Admin → Website/Business Setup and currently use `info@fastxhorizon.com`, `+92 326 5680129`, and `www.fastxhorizon.com`. No physical office address, registration number, incorporation date, leadership claim, tax number, licence number, or certificate is fabricated or displayed while its setting is blank.

## CMS and publication

The existing `business_pages` CMS remains the canonical page store. The six policy pages are now enriched with hero copy, at-a-glance summaries, SEO fields, canonical URLs, draft/published state, publication timestamps, and version numbers. `legal_policy_versions` stores snapshots before an edit. Public policy routes only render published pages; administrators can edit them from the existing Pages & Media area and open version history. Timestamps are stored by Laravel and displayed in Pakistan time with UTC available from the stored value.

The existing custom About Us page remains the active About route. It reads company identity settings and renders leadership only from `company_leadership_profiles` rows that an administrator explicitly activates. The leadership CMS is available under the Pages & Media administration area and is empty/inactive by default.

## Return, refund and cancellation workflow

`refund_requests` remains the canonical workflow table. New metadata records request type, problem type, description, condition confirmation, preferred resolution, source (`seller` or `inhouse`), seller ID, destination, currency, and historical exchange rate. A customer can request an eligible physical item return/refund only for their own delivered/completed order item, within the configured seven-day window, and only once. Digital products are excluded from the standard return flow after delivery.

Customers can cancel while the order is still eligible. For a prepaid cancellation, the order is cancelled and a pending wallet-directed refund request is created; the payment/refund review path remains responsible for final crediting. Existing seller and admin review queues remain in use, and seller products retain seller routing through the existing seller/order-detail filters.

Wallet refunds use the existing `CustomerWalletLedgerService`. A refund credit uses an idempotency key based on the refund ID, so repeated approval or callback processing cannot create a second credit. The ledger records the refund, order, balance-before/after, currency, source, and description. Existing seller settlement and commission calculations are not rewritten by this policy layer.

## Wallet and withdrawal safeguards

The existing customer wallet and ledger are reused. Wallet balance changes are transactionally locked and must be ledger entries. The wallet ledger is single-currency in this phase and uses the configured Fast X Horizon base currency (PKR by default); storefront display currency does not alter wallet math.

Customer wallet withdrawals are implemented behind `wallet_withdrawal_enabled`, which is seeded **off**. If enabled later, a customer request is stored in `customer_wallet_withdrawal_requests` and remains pending until authorized admin review. Wallet funds are deducted only when an authorized administrator marks the request paid, using a ledger idempotency key. There is no peer-to-peer transfer or automatic customer cash withdrawal.

## Seller responsibility and compliance

Seller responsibility is documented in the Terms, Privacy, Return, Refund, and Cancellation policies. `seller_compliance_records` provides an auditable foundation for verified complaints, prohibited listings, privacy issues, cancellation issues, and manual findings. Each record stores the seller, points, source, reason, evidence, administrator, and reversal details. Configurable thresholds are seeded at 3 (warning), 5 (final warning), and 7 (suspension). Reaching the suspension threshold sets the existing seller status to `suspended`; reversals are recorded rather than deleting evidence.

The seller compliance screen is an administrative control, not a customer-facing allegation list. No seller is penalized by the migration itself.

## Account, digital products and privacy

The public Privacy Policy describes account/order/payment/support/wallet data, seller-minimum disclosure, coarse IP country localization, cookies, retention, deletion requests, and seller privacy obligations. The existing account deletion endpoint remains the deletion entry point; financial, order, security, dispute, and compliance records may be retained where required. Digital products require advance payment and are excluded from standard post-delivery return/cash-on-delivery behavior.

## Security and legal review

Existing authentication, CSRF, permissions, payment verification, wallet row locking, refund idempotency, seller routing, and admin authorization remain in force. No legal registration, tax, licence, address, leadership, or regulatory claim should be published until Fast X Horizon supplies and verifies the real value. Before production publication, the final policy text and governing-law/dispute language should be reviewed by qualified Pakistan counsel.

## Main implementation locations

- `database/migrations/2026_10_12_000004_create_fastxhorizon_legal_policy_system.php`
- `app/Models/BusinessPage.php`, `LegalPolicyVersion.php`, `CompanyLeadershipProfile.php`, `SellerComplianceRecord.php`, `CustomerWalletWithdrawalRequest.php`
- `app/Http/Controllers/Web/PageController.php`
- `app/Http/Controllers/Web/UserProfileController.php`
- `app/Services/CustomerWalletLedgerService.php`
- `resources/themes/default/web-views/pages/legal-policy.blade.php`
- `resources/themes/default/web-views/pages/business_page.blade.php`
- `app/Http/Controllers/Admin/Settings/PagesController.php`
- `app/Http/Controllers/Admin/Settings/CompanyLeadershipController.php`
- `app/Http/Controllers/Admin/Vendor/SellerComplianceController.php`

All database changes are additive and were applied without reset, truncate, or demo-catalog deletion.
