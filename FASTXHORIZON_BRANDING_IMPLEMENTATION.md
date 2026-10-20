# Fast X Horizon Branding Implementation

Status: **PARTIAL — branding changes applied; Flutter build and browser screenshot QA are pending because Flutter and a browser automation runtime are not installed on this machine.**

Date: 2026-10-11

## Project baseline

- 6Valley version: 16.5
- Laravel: 12.66.0
- PHP: 8.2.12
- Active storefront: legacy `default` theme
- Admin and seller: Laravel Blade panels with v2 shell styles
- Mobile sources: sibling Customer/User app and Vendor app directories
- Delivery app: not present as a sibling project
- PWA manifest: not present; Firebase service worker remains unchanged

## Applied brand configuration

The official `business_settings` architecture was used for customer-facing web branding:

- `company_name`: `Fast X Horizon`
- `company_copyright_text`: `© Fast X Horizon. All rights reserved.`
- `company_web_logo`: `company/fastxhorizon-web.webp` — 1000×308
- `company_web_logo_png`: `company/fastxhorizon-invoice.png` — 1000×308 PNG
- `company_mobile_logo`: `company/fastxhorizon-mobile.webp` — 325×100
- `company_footer_logo`: `company/fastxhorizon-footer.webp` — 325×100
- `company_fav_icon`: `company/fastxhorizon-favicon.png` — 512×512
- `loader_gif`: `company/fastxhorizon-loader.webp` — 512×512 mark
- `colors`: primary `#1769E0`, secondary/navy `#0B1F3A`, panel sidebar `#0B1F3A`, primary light `#EAF3FF`, app primary `#1769E0`, app secondary `#20C8E8`
- Existing phone, email, address and seller/shop records were not invented or overwritten. The existing `Copy@6amtech.com` remains pending a real business email.
- Existing demo social rows were retained but set inactive (`active_status=0`) until real Fast X Horizon URLs are supplied.

## Web and panel changes

- Replaced the active legacy admin logo, compact mark and invoice logo plus their `public/assets/new` duplicates.
- Replaced theme_aster logo and white-logo fallbacks.
- Replaced auction logo/footer fallbacks in theme and public copies.
- Added Fast X Horizon installer logo/favicon derivatives and updated the installer layout references without changing installation logic, activation logic or version identifiers.
- Removed the visible vendor/demo purchase announcement from admin and seller shells.
- Updated the live title fallback to `Fast X Horizon | Online Marketplace`.
- Updated default and theme_aster mobile-banner logo alt text and auction checkout platform label.
- Updated v2 admin/seller shell tokens to Fast X Horizon navy/blue/cyan palette.
- Updated the visible software update/installation headings to Fast X Horizon while preserving update routes and activation infrastructure.

## Mobile app changes

### Customer/User app

- Square mark: `assets/images/logo.png` — 512×512 PNG
- Wordmark: `assets/images/logo_with_name.png` — 780×242 PNG
- White wordmark: `assets/images/logo_with_name_white.png` — 780×242 PNG
- Splash wordmark: `assets/images/logo_splash.png` — 780×242 PNG
- Android launcher densities replaced for mdpi/hdpi/xhdpi/xxhdpi/xxxhdpi.
- Android notification icon replaced with the approved monochrome mark.
- iOS AppIcon set regenerated from the approved app icon master.
- Visible app label/name changed to `Fast X Horizon`.
- Primary/secondary/light theme colors updated to `#1769E0`, `#20C8E8`, and `#F5F8FC`.
- Firebase configuration, package/application IDs, bundle ID, deep-link identifiers and notification channel IDs were preserved.

### Vendor app

- Square mark: `assets/images/logo.png` — 512×512 PNG
- White splash mark: `assets/images/logo_white.png` — 512×512 transparent PNG
- Wordmark: `assets/images/logo_with_app_name.png` — 780×242 PNG
- Favicon/compact asset: `assets/images/6vally-favicon.png` — 512×512 PNG
- Android launcher densities and notification icon replaced.
- iOS AppIcon set regenerated from the approved app icon master.
- Visible app label/name changed to `Fast X Horizon Seller`.
- Vendor primary/secondary/light theme colors updated to `#1769E0`, `#20C8E8`, and `#F5F8FC`.
- Seller API paths, package/application IDs, bundle ID, Firebase configuration and other technical identifiers were preserved.

## Approved source masters

The supplied masters under `logo/` were not modified:

- `logo/fastxhorizon-logo.png`
- `logo/fastxhorizon-logo-white.png`
- `logo/fastxhorizon-mark.png`
- `logo/fastxhorizon-app-icon.png`
- `logo/fastxhorizon-notification.png`

All derivatives were proportional contain/resize outputs; no logo was stretched into a different aspect ratio.

## Backup

Because the project is not a Git repository, a pre-change file backup was created at:

`branding-backup/20261011/`

The file manifest is `branding-backup/20261011/REPLACED_FILES.txt`.

## Validation

- `php artisan optimize:clear`: passed.
- Storefront request `http://127.0.0.1:8000/`: HTTP 200.
- Web title, OG site name, favicon, OG image, mobile logo, header logo, loader, footer logo and copyright render as Fast X Horizon values.
- Main web branding assets and installer logo HTTP validation: all checked URLs returned HTTP 200.
- `composer validate --no-check-publish`: passed with existing dependency-constraint warnings.
- `php artisan route:list`: blocked by the existing placeholder Firebase server-key configuration (`Put your firebase server key here.`), not by branding changes.
- Flutter validation: blocked because `flutter` is not installed/on PATH.
- Browser screenshot automation: not available because Playwright/browser runtime is not installed. An HTML QA snapshot and HTTP report are in `artifacts/fastxhorizon-branding/web/`.

## Intentionally preserved references

The following were not renamed because they are technical compatibility identifiers or owned content: route names, namespaces, database keys/table names, composer package names, module identifiers, cache keys, Firebase IDs/configuration, Android application IDs, iOS bundle IDs, deep-link domains, notification channel IDs, cookie names, activation/licensing/update infrastructure, seller shop logos, seller product/category/customer content, and the existing real-value placeholder contact email.

## Remaining operator inputs

Before publishing, provide the real Fast X Horizon phone, email, address, social URLs and any legal/SEO copy. Then run Flutter `pub get`, `analyze`, platform builds, and visual QA on Android/iOS. If a PWA manifest is added later, use the 512×512 square app icon and 180×180 Apple-touch derivative from the generated asset set.
