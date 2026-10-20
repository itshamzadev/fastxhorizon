# Fast X Horizon Seller Terminology Audit

## Scope

Customer-facing and administrator-visible English terminology has been standardized from **Vendor/Vendors** to **Seller/Sellers**. Technical 6Valley identifiers were deliberately preserved.

## Web and admin result

- Updated the English web translation values in `resources/lang/en/messages.php` and `resources/lang/en/new-messages.php`.
- 349 English translation value lines were covered by the original terminology pass: 253 in `messages.php` and 96 in `new-messages.php`.
- Translation keys remain unchanged for compatibility. For example, `vendor_login` still exists as a key but now renders **Seller Login**.
- Corrected hard-coded visible text in the admin clearance-offer view, admin tab-menu placeholders, the seller-settings tooltip, and the public About page.
- Updated the local `business_settings` display records for `vendor_registration_header` and `download_vendor_app` from Vendor wording to Seller wording without changing their technical setting types.
- Admin navigation, product/report/filter labels, seller registration text, withdrawal/commission wording, and storefront seller wording now resolve through the English Seller values.

## Storefront and seller panel

The active default theme uses the updated English translations for header links, seller registration/login, seller information, seller store/product references, cart/order wording, reviews, and customer account pages. The seller panel also resolves its visible labels through the updated values.

The technical seller-panel URLs remain unchanged, including `/vendor/auth/...`, because they are part of the existing routing contract.

## Flutter applications

Updated English visible localization values in:

- `..\\User app\\assets\\language\\en.json`
- `..\\Vendor app\\assets\\language\\en.json`

Customer and seller app visible strings now use Seller terminology. Dart keys, class names, package identifiers, route constants, API fields, and application identifiers were not renamed.

## Email, notification, and report wording

Email templates, notification text, invoice/report labels, seller filters, and related UI copy use the updated English translation values. Mail transport, notification classes, event names, and delivery channels were not changed.

## Technical references intentionally preserved

The following remain unchanged by design:

- `/vendor` URLs and route names
- Vendor controller/repository/service namespaces
- `resources/views/vendor-views` and related technical view paths
- Vendor middleware and permission identifiers
- Database tables, columns, relationships, and fields such as `vendor_id`
- API response keys, request keys, cache keys, Firebase identifiers, and event names
- Flutter package/application identifiers and technical Dart symbols
- Composer/framework `vendor/` dependency directories
- Translation keys containing `vendor`, preserving compatibility with existing callers

## Remaining source matches

Remaining `vendor` matches are expected technical references, including routes, folders, variables, IDs, HTML IDs/classes, API fields, asset filenames, package names, comments, and translation keys. They are not customer-facing labels.

Other language packs were not mechanically translated into English. Their localized seller terminology remains language-specific; the active English web/app surfaces are the scope of this pass.

## Validation

- `php -l resources/lang/en/messages.php`: passed
- `php -l resources/lang/en/new-messages.php`: passed
- Flutter English JSON parsing with PHP `JSON_THROW_ON_ERROR`: passed for both apps
- `php artisan optimize:clear`: passed
- `php artisan view:cache`: passed
- HTTP 200: `/`, `/login/admin`, `/customer/auth/sign-up`, `/customer/auth/recover-password`, `/vendor/auth/registration/index`, `/vendor/auth/forgot-password/index`, `/vendor/auth/login`
- Rendered seller registration page shows **Seller Registration**; remaining HTML matches are technical URLs, IDs, asset paths, or scripts.
