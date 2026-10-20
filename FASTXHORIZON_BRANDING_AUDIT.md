# Fast X Horizon — 6Valley Branding Asset Audit

Audit date: 2026-10-11  
Scope: current installed project only. No Fast X Horizon logo, image, color, architecture, framework dependency, or `vendor/` file was changed.

## Executive result

- Installed 6Valley version: **16.5**.
- Live local storefront is rendering the legacy `default` Blade theme. `theme_aster` is also shipped as an alternate theme.
- The Laravel database currently contains demo/local branding settings, but the referenced company uploads are missing from `storage/app/public/company`. The live page therefore renders fallback placeholders and empty favicon/OG URLs.
- Separate Flutter source exists for the customer app (`User app`) and seller app (`Vendor app`). No separate delivery Flutter app was found beside the project; deliveryman functionality exists in the backend/API.
- No standard PWA web manifest (`site.webmanifest`/`manifest.webmanifest`) was found. A Firebase web push service worker exists.
- **No branding replacement has been applied.**

## 1. Installed project and version

| Item | Finding | Evidence/location |
|---|---|---|
| 6Valley | **16.5** | `app/Library/Constant.php` → `SOFTWARE_VERSION` |
| Software ID | `MzE0NDg1OTc=` | `app/Library/Constant.php` |
| Laravel | Laravel Framework **12.66.0** at runtime; Composer constraint `^12.0` | `composer.json`, `php artisan --version` |
| PHP | **8.2.12** in the local runtime | local PHP CLI |
| Database | MySQL; local database is `6valley` | `.env`, installed database |
| Frontend | Laravel Blade server-rendered storefront with theme assets; Vite/React/Inertia/Builder code is also present | `resources/themes`, `resources/views`, `resources/js`, `package.json` |
| Admin | Blade admin views/controllers and admin routes; v2 shell assets are present | `routes/admin`, `app/Http/Controllers/Admin`, `resources/views/admin-views`, `resources/views/layouts/admin` |
| Seller | Blade vendor/seller views/controllers and vendor routes; seller v2 shell is present | `routes/vendor`, `app/Http/Controllers/Vendor`, `resources/views/vendor-views`, `resources/views/layouts/vendor` |
| Customer website | Web/customer routes, auth, checkout, catalog, order, blog and auction views | `routes/web`, `app/Http/Controllers/Web`, `app/Http/Controllers/Customer`, `resources/themes` |
| Modules | Blog, Gateways, TaxModule, AI, Auction, Builder and Courier are enabled | `modules_statuses.json` |
| Mobile source | Customer/User Flutter app and Vendor Flutter app are present as sibling folders | `..\User app`, `..\Vendor app` |
| PWA | No standard install manifest found; `firebase-messaging-sw.js` is present for push messaging | project root/public |

### Branding architecture

1. Primary web branding is DB-driven through `business_settings` and read using `getWebConfig()`.
2. Website Setup stores uploaded images in `company/` and creates WebP/PNG variants according to `WebsiteSetupController`.
3. The same DB web logo is reused by customer auth, admin login/header, seller login/header, email templates, checkout/business data and many PDF reports.
4. The active legacy storefront theme injects DB colors into CSS variables. The admin/seller panels also have static fallback assets and v2 shell defaults.
5. Flutter apps have their own bundled branding assets and hardcoded app labels/themes; web admin settings do not automatically replace these files.

## 2. Current DB branding settings

| Setting | Current value | Current state | Later Fast X Horizon action |
|---|---|---|---|
| `company_name` | `Local Store` | Customer-facing DB value | Set to `Fast X Horizon` |
| `company_email` | `Copy@6amtech.com` | Customer-facing/demo sender value | Replace with the real Fast X Horizon sender address |
| `company_copyright_text` | `CopyRight 6amTech@2021` | Visible in live footer | Replace with approved copyright text |
| `company_web_logo` | `2021-05-25-60ad1b313a9d4.png` | Referenced file missing from `storage/app/public/company` | Upload horizontal Fast X Horizon logo through Website Setup |
| `company_mobile_logo` | `2021-02-20-6030c88c91911.png` | Referenced file missing | Upload mobile/header logo through Website Setup |
| `company_footer_logo` | `2021-02-20-6030c8a02a5f9.png` | Referenced file missing | Upload footer/dark-background logo through Website Setup |
| `company_fav_icon` | `2021-03-02-603df1634614f.png` | Referenced file missing; live favicon links are empty | Upload square Fast X Horizon mark |
| `company_web_logo_png` | Not populated in current DB output | Invoice/report PNG slot is missing/empty | It is generated from the web-logo upload; verify after upload |
| `loader_gif` | Not populated | Theme fallback loader is used | Upload a Fast X Horizon loader only if desired |
| `colors` | `{"primary":"#1b7fed","secondary":"black","primary_light":"#CFDFFB"}` | Active legacy storefront values | Change later after logo approval |

The local live HTML confirmed the current title as `Local Store Online Shopping | Local Store Ecommerce`, the footer as `CopyRight 6amTech@2021`, and empty favicon/OG image URLs caused by missing uploads.

## 3. Complete web/admin/seller branding asset inventory

Dimensions below are pixel dimensions found in this project. “Recommended” follows the v16.5 UI/constants and safe source-master practice; where the code has no hard dimension rule, the recommendation is not a hard requirement.

| # | Purpose / slot | Current file or setting and dimensions | Fast X Horizon recommendation | Format / transparency / variants | Status and edit path |
|---:|---|---|---|---|---|
| 1 | Main website/header logo | DB `company_web_logo`; referenced upload missing. v16.5 constant says **1000×308**. Static theme logo: `resources/themes/theme_aster/public/assets/img/logo.png` **512×137** and `logo-white.png` **512×137** | **1000×308**, ratio 3.247:1 | PNG/WebP; transparent yes; dark-on-light plus white-on-dark preferred; wide yes | Active DB slot; Admin → Website Setup |
| 2 | Mobile website header logo | DB `company_mobile_logo`; referenced upload missing. Website Setup preview says **325×100**; validation message says at least **100×60** | **325×100 minimum**, preferably same 1000×308 master | PNG/WebP; transparent yes; wide yes; exact dimensions not mandatory beyond validation | Active DB slot; Admin → Website Setup |
| 3 | Website footer logo | DB `company_footer_logo`; referenced upload missing. UI says **325×100** | **325×100** or same 1000×308 ratio | PNG/WebP; transparent yes; white/light version required for the dark footer; wide yes | Active DB slot; Admin → Website Setup |
| 4 | Admin login and admin header logo | Reuses DB `company_web_logo`; static fallback `public/assets/back-end/img/logo.png` **229×69** | 1000×308 master; rendered responsively | PNG/WebP; transparent yes; dark-on-light wide version | Active DB slot; static fallback remains in code/assets |
| 5 | Seller login and seller panel logo | Reuses DB `company_web_logo`; seller shop image can override the seller header after a shop logo is uploaded | 1000×308 master plus square mark for collapsed panel | PNG/WebP; transparent yes; wide + square | Active DB slot; seller shop logo is separate seller data |
| 6 | Customer login/register/reset branding | Reuses DB `company_web_logo` and `company_fav_icon` | 1000×308 plus 512×512 mark | PNG/WebP; transparent yes; wide + square | Active DB slot; Admin → Website Setup |
| 7 | Email template logo | Email templates read `company_web_logo` | 1000×308; keep legible at email width | PNG/WebP; transparent yes; dark and white versions useful depending template | Admin setting; source templates already use DB value |
| 8 | Invoice/receipt/report logo | `company_web_logo_png`; static fallback `public/assets/back-end/img/invoice/logo.png` **474×125** | **1000×308 source**, with generated PNG checked in PDF rendering | PNG; transparent yes; wide; exact output dimensions not mandatory | Generated by `WebsiteSetupController` from web logo upload; reports use this slot |
| 9 | Browser favicon | DB `company_fav_icon` is missing; `public/favicon.ico` is **0 bytes/unreadable** | **512×512 source**; browser derivatives 32×32 and 48×48 | PNG/ICO; transparent yes; square; no wide variant | Active but currently broken; Admin → Website Setup plus optional static favicon repair |
| 10 | Apple touch icon | Same DB favicon is linked with `apple-touch-icon` size **180×180** | 180×180 derivative from a **512×512** master | PNG; transparent or solid background depending mark; square | Linked by theme; no separate DB setting |
| 11 | Page loader/preloader | DB `loader_gif` empty; fallback `public/assets/front-end/img/loader.gif` **512×512**, theme_aster loader **512×512 GIF** | **512×512** square animated mark | GIF/WebP accepted as image; transparent preferred; square; animation optional | Admin upload converts/stores WebP; no exact dimension rule |
| 12 | OpenGraph/Twitter/social-share image | No dedicated global upload found. Templates frequently use `$web_config['web_logo']['path']`; live OG image is empty | **1200×600**, ratio 2:1, matching v16.5 Meta Thumbnail guidance | JPG/PNG; transparency no; wide; light/white background preferred for social cards | Requires later SEO/source wiring or a configured media slot; not a logo-only upload |
| 13 | Default marketplace/business logo returned to APIs/checkout/builder | `getWebConfig('company_web_logo')` is used by API, payment and Builder providers | Use the same 1000×308 transparent master | PNG/WebP; transparent yes; wide | DB-driven; no architecture change required |
| 14 | Admin collapsed/vertical logo fallback | `public/assets/back-end/img/vertical_logo.png` **164×197**; `header-logo.png` and `footer-logo.png` are **18×14** tiny assets | **512×512** square mark for collapsed/compact use | PNG; transparent yes; square; no wordmark required | Static legacy fallback/utility assets; no direct Website Setup replacement |
| 15 | Admin invoice/demo duplicate bundle | `public/assets/new/back-end/img/logo.png`, `invoice/logo.png`, `vertical_logo.png` duplicate corresponding `public/assets/back-end` files byte-for-byte | Replace only if static fallbacks are intentionally rebranded | Same as source slot | Duplicate deployment bundle; do not delete before checking active references |
| 16 | Installer logo | `public/assets/installation/assets/img/logo.svg`, SVG viewBox about **100×38** | Horizontal **1000×308** source; static installer derivative may remain SVG | SVG preferred for installer; transparent yes; wide | Installer-only, not post-install business setting |
| 17 | Installer favicon | `public/assets/installation/assets/img/favicon.svg`, SVG viewBox **52×52** | **512×512** square mark source; installer derivative 52×52 or SVG | SVG/PNG; transparent yes; square | Installer-only |
| 18 | Auction header logo | `resources/themes/theme_aster/public/assets/auction/images/icons/logo.png` **129×40**; public copy also exists | **~325×100** wide derivative or the 1000×308 master | PNG/SVG; transparent yes; wide | Feature-specific static asset/fallback; inspect after theme activation |
| 19 | Auction footer logo | `resources/themes/theme_aster/public/assets/auction/images/icons/footer-logo.png` **150×48**; public copy also exists | **325×100** white/dark-footer derivative | PNG/SVG; transparent yes; white version required | Feature-specific static asset/fallback |
| 20 | Blog branding marks | `public/assets/front-end/img/blogs/logo.png` **60×68**, `blog-logo.png` **54×55**, `shareit.png` **30×30**; theme_aster has matching copies | Use **512×512** square mark for logo-like marks; social share image remains 1200×600 if used as a card | PNG; transparent yes for marks | Feature UI assets; some are generic blog/brand placeholders; do not delete blindly |
| 21 | Storage/application fallback logo | `storage/app/logo/logo.png` **493×129** and `storage/app/icon/logo.png` **493×129**; duplicates differ from static web logo | Replace only if this storage fallback is confirmed active; use 1000×308 master | PNG/WebP; transparent yes; wide | Existing storage assets; not the normal Website Setup DB path |
| 22 | Storage notification icon | `storage/app/icon/notification.png` **166×207**; `notification 2.png` **245×274** | Not a required Fast X logo; if branded, use **512×512** monochrome mark | PNG; transparent yes; square preferred | Utility/notification fallback; verify before changing |
| 23 | Storage share icon | `storage/app/icon/share.png` **123×134** | Not a required logo; if used as brand share mark, use **512×512** | PNG; transparent yes; square preferred | Utility/placeholder; verify before changing |
| 24 | Public front-end share icon | `public/assets/front-end/img/icons/share.png` **61×61** | Keep generic unless it contains marketplace branding; branded alternative **512×512** | PNG/SVG; transparent yes; square | Functional share icon, not normally a logo slot |

### v16.5 marketing/image slots that are not logos

These are not mandatory Fast X Horizon logo files, but they are the project’s real visual brand surfaces and should be reviewed if the marketplace is to be fully rebranded:

| Slot | v16.5 guidance found | Recommendation |
|---|---|---|
| Main banner | Default ratio **3:1** | 2000×667 or another 3:1 image |
| Footer banner | Ratio **2:1** | 1200×600 or larger |
| Popup banner | **1:1, 1200×1200** | 1200×1200 |
| Theme Aster main section banner | **2000×618** | 2000×618 |
| Store cover | **2000×500**, ratio 4:1 | 2000×500 |
| Theme Aster store banner | **2000×377** | 2000×377 |
| Header banner | **585×160** | 585×160 |
| Sidebar banner | **280×500** | 280×500 |
| Top-side banner | **205×500** | 205×500 |
| Product/category/brand/seller images | Product/seller guidance **500×500** | These are catalog content, not global logo assets |

## 4. Mobile app branding audit

The mobile projects are siblings of the Laravel root, not Laravel subdirectories. They must be branded and rebuilt separately later.

### Customer/User app

| Purpose | Current path | Current dimensions/content | Fast X Horizon requirement |
|---|---|---|---|
| In-app/splash square logo | `..\User app\assets\images\logo.png` | 512×512; file header is WebP despite `.png` extension; splash screen uses `Images.logo` at about 150 logical px | Provide a clean **1024×1024 square** master; transparent preferred for in-app use; a solid background may be used by the app theme |
| In-app wide logo | `..\User app\assets\images\logo_with_name.png` | 780×242 WebP; used in headers/auth | Provide **780×242 minimum** or 1200×372; transparent; wide |
| White/dark-background logo | `..\User app\assets\images\logo_with_name_white.png` | 780×242 WebP; used by auth/splash constants | Provide matching white version; transparent; wide |
| SVG logo | `..\User app\assets\svg\6valley_logo.svg` | Bundled vector logo | Provide Fast X vector only if you want to preserve vector use in app |
| Android launcher | `..\User app\android\app\src\main\res\mipmap-*\ic_launcher.png` | Existing density outputs: 48, 72, 96, 144, 192 px | Provide **1024×1024 master**; generate Android density set later; app icon should normally be opaque/brand-background rather than transparent |
| Android notification icon | `..\User app\android\app\src\main\res\drawable\notification_icon.png` | 512×512 PNG; wired as `notification_icon` | Provide **512×512 monochrome/white mark on transparent background**; no wordmark |
| Android launch background | `..\User app\android\app\src\main\res\drawable\launch_background.xml` | Currently plain white; `splash.png` is present at 1440×3200 but not referenced by this XML | Provide a chosen splash background color/graphic later; current screen uses Flutter splash code and logo asset |
| iOS launcher | `..\User app\ios\Runner\Assets.xcassets\AppIcon.appiconset` | Full iOS set including 1024×1024 source and device sizes | Provide **1024×1024 app icon master**, opaque/solid background recommended |
| Android label | `AndroidManifest.xml` | `6Valley` | Later source change to Fast X Horizon / approved app label |
| iOS display name | `Runner.xcodeproj/project.pbxproj`, `Info.plist` | `6Valley` / `6valley` | Later source change; bundle ID and Firebase identifiers are technical and must not be changed casually |
| App internal name | `lib/utill/app_constants.dart` | `appName = '6valley'` | Later source change to approved customer-facing app name |

### Seller/Vendor app

| Purpose | Current path | Current dimensions/content | Fast X Horizon requirement |
|---|---|---|---|
| In-app square logo | `..\Vendor app\assets\images\logo.png` | 512×512 WebP in a `.png` file | **1024×1024** square master; transparent preferred for in-app use |
| White logo/splash | `..\Vendor app\assets\images\logo_white.png` | 512×512 WebP; splash screen uses `Images.whiteLogo` at about 80 logical px | **1024×1024 white/monochrome** mark; transparent; no wordmark required |
| Wide app-name logo | `..\Vendor app\assets\images\logo_with_app_name.png` | 780×242 WebP; used in home/auth/language views | **780×242 minimum** or 1200×372; transparent; wide |
| Misnamed favicon asset | `..\Vendor app\assets\images\6vally-favicon.png` | 780×242 WebP, so it is actually a wide logo rather than a square favicon | Replace/rename only during the later app-brand pass; provide square 1024×1024 favicon/app mark if this use is retained |
| Android launcher | `..\Vendor app\android\app\src\main\res\mipmap-*\ic_launcher.png` | Existing density outputs: 48, 72, 96, 144, 192 px | Provide **1024×1024 master**; generate density set later; opaque/brand-background preferred |
| Android notification icon | `..\Vendor app\android\app\src\main\res\drawable\notification_icon.png` | 512×512 PNG | Provide **512×512 monochrome/white mark on transparent background** |
| Android launch background | `..\Vendor app\android\app\src\main\res\drawable\launch_background.xml` and `drawable-v21` | Plain white; splash Flutter screen uses white square logo and primary-color background | Provide the white square logo; choose splash background later |
| iOS launcher | `..\Vendor app\ios\Runner\Assets.xcassets\AppIcon.appiconset` | Full iOS set including 1024×1024 | Provide **1024×1024 app icon master**, opaque/solid background recommended |
| Android label | `AndroidManifest.xml` | `6Valley Vendor` | Later source change to approved seller app label |
| iOS display name | `Runner.xcodeproj/project.pbxproj`, `Info.plist` | `6Valley Seller` | Later source change; preserve/plan bundle ID and Firebase changes separately |

### Delivery app

No separate delivery/rider Flutter source folder was found at the inspected sibling level. The backend contains deliveryman APIs and seller deliveryman functionality. If a delivery app exists elsewhere, it needs a separate audit before branding.

### Mobile theme colors found

Both Flutter apps currently hardcode:

- Primary: `#1455AC`
- Secondary: `#F58300`
- Info/outline: `#5C8FFC`
- Light background examples: `#F7F8FA`, `#F4F8FF`
- Dark surfaces: `#242424` / `#2D2D2D`

Locations: `..\User app\lib\theme\light_theme.dart`, `dark_theme.dart`, and corresponding files under `..\Vendor app\lib\theme`. These are source-code values; the web admin color setting does not update them.

## 5. Current brand colors and configurability

| Color role | Current value | Location | Admin-editable? |
|---|---|---|---|
| Website primary / buttons / links / header accents | `#1b7fed` | DB `business_settings.type=colors`; injected in active theme CSS variables | Yes, Website Setup |
| Website secondary | `black` | Same DB row; active CSS `--web-secondary` | Yes, Website Setup |
| Website primary light / cards/sections | `#CFDFFB` | Same DB row | Yes, Website Setup |
| Panel sidebar | Missing/empty in current DB colors JSON | Website Setup controller supports `panel-sidebar`; admin v2 CSS fallback is light surface | Yes, if populated through Website Setup |
| Admin v2 primary fallback | `#1F4FD6` | `public/assets/back-end/css/admin-v2.css` | Inline settings can override; source fallback remains |
| Admin v2 secondary fallback | `#2BB0EE` | `public/assets/back-end/css/admin-v2.css` | Inline settings can override; source fallback remains |
| Admin Bootstrap/framework palette | Includes `#377dff`, `#71869d`, success/cyan/etc. | `public/assets/back-end/css/bootstrap.min.css` and vendor CSS | Framework palette is not the brand setting; do not edit globally |
| Mobile app primary | `#1455AC` | Flutter theme files | No; source modification required |
| Mobile app secondary | `#F58300` | Flutter theme files | No; source modification required |

Fast X Horizon’s intended direction (Deep Navy / Strong Blue / Cyan / White / Light Gray) is **not applied** in this audit.

## 6. Text branding audit

### Customer-facing values that should later become Fast X Horizon branding

| Surface | Current reference/location | Later value |
|---|---|---|
| Website company/store name | DB `company_name = Local Store`; used through `getWebConfig('company_name')` | `Fast X Horizon` |
| Website title/SEO title | Live title: `Local Store Online Shopping | Local Store Ecommerce`; theme SEO partials and page SEO fields | Approved Fast X Horizon title |
| Meta description | Live content includes demo text: `this is about us page. hello and hi from about page description..`; page/category/product/blog SEO fields also exist | Approved Fast X Horizon description |
| Admin title | Blade `@yield('title')`; many titles are page-specific; global name fallback is `config/app.php` | Review customer-visible admin title strings; do not rename framework identifiers |
| Seller title | Blade `@yield('title')` and vendor pages | Review customer-visible seller title strings |
| Emails | `resources/views/email-templates/*` use DB company name and company logo | `Fast X Horizon`, Fast X Horizon logo |
| Default sender/contact identity | DB `company_email = Copy@6amtech.com` | Real Fast X Horizon sender address |
| Invoice/PDF footer | PDFs use `company_name`, `company_email`, `company_phone`, and `company_copyright_text` | Fast X Horizon values |
| Footer copyright | DB `company_copyright_text = CopyRight 6amTech@2021` | Approved Fast X Horizon copyright |
| Website app download banner | Uses DB company name and mobile logo | `Fast X Horizon Mobile App` or approved wording |
| Web notification/API brand fields | API config returns company logo/favicon; push topic is `sixvalley` in `PushNotificationTrait` | Review customer-facing notification title/body; keep protocol topics stable unless migration is planned |
| Cookie/demo text | Live JS contains `6valley_cookie_consent` cookie key | Visible text can be rebranded; cookie key should not be changed without migration need |
| Update page | `resources/views/update/update-software.blade.php` contains `6valley Software Update` | Replace only if this page is customer/admin-visible; no framework update logic change |

### References that must not be blindly replaced

- `config/app.php` default fallback `6valley`.
- `config/builder.php` `default_platform_name = 6valley` and host-adapter comments for 6Valley/6amMart.
- `config/system-addons.php` activation/add-on domain `6valley-testing.6amdev.xyz`.
- TaxModule internal keys/views such as `Modules/TaxModule/.../6valley` and `6ammart`.
- Flutter package names/imports, Android/iOS bundle IDs, Firebase/Google service identifiers and package namespaces.
- Composer package names, route names, database table names, cache keys, notification topics and framework/vendor dependencies.

These are architecture, protocol, activation or package identifiers rather than ordinary visible customer branding.

## 7. Admin-configurable vs source-code branding

### Safe/admin-configurable later

- Company name, email, phone and copyright: Admin business/website information settings.
- Website primary, secondary, panel-sidebar and primary-light colors; app-primary/app-secondary fields are supported by the controller: Admin Website Setup.
- Web header logo, mobile logo, footer logo, favicon and loader: Admin Website Setup upload slots.
- Invoice PNG variant: generated automatically from the web-logo upload by `WebsiteSetupController`.
- Seller’s own shop logo/name: seller shop data; this is not the platform logo.
- Page/category/product/blog SEO title, description and images: corresponding admin SEO forms.

### Source/static changes required later

- Flutter bundled logos, square splash marks, notification icons, Android density outputs and iOS AppIcon sets.
- Flutter Android/iOS app labels and `app_constants.dart` names.
- Static installer SVG logo/favicon.
- Static fallback logos under `public/assets/back-end`, `public/assets/new/back-end`, theme auction/blog folders and any confirmed storage fallback files.
- A dedicated PWA manifest and 192×192/512×512 web icons, because no standard manifest is currently wired.
- A dedicated OG/social image integration if a separate 1200×600 image is desired instead of reusing the web logo.
- Technical bundle/package IDs only if the app’s store identity is intentionally changed; this also requires Firebase, signing, deep-link and store configuration work.

## 8. Duplicate, demo and unused assets

| Group | Finding | Assessment |
|---|---|---|
| `public/assets/back-end` vs `public/assets/new/back-end` | Main logo, invoice logo and vertical logo are byte-for-byte duplicates; many other admin assets are duplicated | Keep until active references are verified; do not delete |
| DB company uploads | DB filenames point to files that are absent from `storage/app/public/company` | Inactive/missing; current UI falls back or emits empty URLs |
| Static admin logos | `logo.png`, `vertical_logo.png`, invoice logo and loader are legacy fallback/demo assets | Some are active only when DB uploads are missing; others are fallback-only |
| `newlogo.jpg` | 474×155 Google wordmark | Unrelated third-party/demo asset; do not use for Fast X Horizon; do not delete yet |
| `storage/app/logo` and `storage/app/icon` | Repeated logo/icon files with different names/dimensions | Fallback/utility candidates; usage needs confirmation before replacement/deletion |
| Installer SVG logo/favicon | 6Valley installation branding | Active only during install/update screens; not normal storefront runtime |
| Theme Aster logo/auction/blog assets | Separate theme/feature copies | May be active if that theme/feature is selected; retain until theme inventory is finalized |
| Flutter `.png` logo files | Several files have WebP content despite `.png` names | Existing Flutter build accepts them; replacement should use consistent real PNG/WebP encoding and preserve references |
| Demo URLs/text | `dev.6amtech.com`, demo social links, local/demo SEO/copyright data | Customer-facing demo data; review later, but do not modify during asset audit |

## 9. Exact asset pack to provide

Provide masters first; density derivatives and generated upload copies can be prepared during the approved branding implementation.

1. **Primary horizontal logo** — 1000×308, transparent PNG or SVG source; dark/navy artwork for light backgrounds.
2. **White horizontal logo** — same 1000×308 ratio, transparent PNG/SVG; for dark footer/email/alternate surfaces.
3. **Square brand mark** — 1024×1024 transparent PNG; for favicon, collapsed panels and app splash/in-app use.
4. **App-icon master** — 1024×1024 PNG with an opaque/brand-color background or safe padding; Android/iOS app icons should not rely on transparent edges.
5. **Notification mark** — 512×512 transparent PNG, simple monochrome/white mark, no wordmark; for both Android apps.
6. **Loader** — optional 512×512 animated GIF/WebP or static transparent PNG/WebP; only needed if replacing the loader.
7. **Social/OG card** — 1200×600, JPG/PNG, light/white background preferred; needed if a dedicated branded share image is desired.
8. **Optional static installer vector** — SVG horizontal logo and square favicon if the install/update screens should also show Fast X Horizon.
9. **Optional marketing images** — only if the marketplace content is also being rebranded: 3:1 main banner, 2:1 footer/OG banner, 1:1 popup, and Theme Aster-specific sizes listed above.

The web admin upload does not require separate 325×100 files if the 1000×308 master is supplied; however, a 325×100 derivative is useful for reviewing the exact Website Setup preview. Do not stretch a small existing logo.

## 10. Final exact-requirement table

| # | Asset | Recommended size | Ratio | Format | Transparent? | Used where |
|---:|---|---:|---:|---|---|---|
| 1 | Main horizontal logo | **1000×308** | 3.247:1 | PNG/WebP; SVG source preferred | Yes | Customer header, admin, seller, auth, emails, API/checkout |
| 2 | Mobile web header logo | **325×100 minimum** | 3.25:1 | PNG/WebP | Yes | Mobile website header/app download banner |
| 3 | Footer logo | **325×100** | 3.25:1 | PNG/WebP | Yes | Website footer; white version required |
| 4 | Invoice/report logo | **1000×308 source**; generated PNG | 3.247:1 | PNG | Yes | PDF invoices and transaction reports |
| 5 | Square brand mark | **512×512 minimum; 1024×1024 master** | 1:1 | PNG | Yes | Favicon, compact admin/seller mark, app in-app/splash |
| 6 | Browser favicon | **32×32 and 48×48 derivatives** from 512/1024 | 1:1 | PNG/ICO | Yes | Browser tab |
| 7 | Apple touch icon | **180×180 derivative** | 1:1 | PNG | Optional | iOS home-screen web icon |
| 8 | Web page loader | **512×512** | 1:1 | Animated GIF/WebP or PNG/WebP | Preferred | Website/theme preloader |
| 9 | OpenGraph/social card | **1200×600** | 2:1 | JPG/PNG | No | Social sharing/SEO; dedicated slot is not currently wired |
| 10 | Customer app icon master | **1024×1024** | 1:1 | PNG | No/opaque background preferred | Android/iOS launcher |
| 11 | Customer app in-app logo | **512×512 or 1024×1024** | 1:1 | PNG/WebP | Yes | Customer app splash/in-app |
| 12 | Customer app wordmark | **780×242 minimum** | 3.223:1 | PNG/WebP/SVG | Yes | Customer app header/auth |
| 13 | Customer app white wordmark | **780×242 minimum** | 3.223:1 | PNG/WebP/SVG | Yes | Dark auth/alternate surface |
| 14 | Customer Android notification | **512×512** | 1:1 | PNG | Yes; monochrome | Push notification icon |
| 15 | Seller app icon master | **1024×1024** | 1:1 | PNG | No/opaque background preferred | Android/iOS launcher |
| 16 | Seller app in-app logo | **512×512 or 1024×1024** | 1:1 | PNG/WebP | Yes | Seller app UI |
| 17 | Seller app white splash logo | **512×512 or 1024×1024** | 1:1 | PNG/WebP | Yes; white mark | Seller splash |
| 18 | Seller app wordmark | **780×242 minimum** | 3.223:1 | PNG/WebP/SVG | Yes | Seller app header/auth |
| 19 | Seller Android notification | **512×512** | 1:1 | PNG | Yes; monochrome | Push notification icon |
| 20 | Android density outputs | **48/72/96/144/192** | 1:1 | PNG | App-icon background preferred | Both app launcher folders; generated from master |
| 21 | iOS AppIcon set | **1024 master plus generated set** | 1:1 | PNG | Opaque background preferred | Both `AppIcon.appiconset` folders |
| 22 | Installer logo | **1000×308 source** | 3.247:1 | SVG/PNG | Yes | Installer/update UI, optional |
| 23 | Installer favicon | **512×512 source** | 1:1 | SVG/PNG | Yes | Installer/update browser icon, optional |

## 11. Risks and update-safety notes

- Do not rename database setting keys, route names, package IDs, Firebase identifiers, notification topics or module namespaces as part of ordinary branding.
- Use Admin Website Setup for web logo/color/company settings wherever possible. This preserves the 6Valley upgrade path.
- The controller converts the web/footer/mobile uploads to WebP and the main web upload also creates `company_web_logo_png`; test email/PDF output after upload.
- The current database points to missing upload files. A future upload should be done through the official settings flow rather than manually changing database filenames.
- The live app currently emits empty favicon and OG image URLs. This is a configuration/media completeness issue, not a reason to change Laravel architecture.
- Static fallback files and duplicated `public/assets/new` files should not be deleted until all active theme, installer, update and legacy panel references are tested.
- Changing Flutter bundle IDs is materially different from changing visible labels. It can break Firebase, signing, deep links, existing app upgrades and store listings.
- No PWA manifest is present. Do not promise PWA icon support until a manifest and its service-worker/install flow are intentionally added.

## Final report

1. **6Valley version:** 16.5
2. **Total logical branding asset slots found:** **24 web/admin/seller/installer/SEO/utility slots**, plus **customer and seller mobile app slot families** (launcher, splash/in-app, wordmark, notification, iOS set and labels). Duplicate copies are grouped rather than double-counted.
3. **Exact logo variants required:** horizontal dark logo, horizontal white logo, square brand mark, opaque app-icon master, monochrome notification mark; optional loader, OG card and installer SVG/favicon.
4. **Exact/recommended sizes:** main 1000×308; mobile/footer 325×100 minimum; square 1024×1024 master; favicon derivatives 32/48 and Apple 180; OG 1200×600; app notification 512×512; wordmarks 780×242 minimum.
5. **Favicons/icons required:** square 512/1024 master, browser 32/48, Apple 180, customer/seller Android launcher density set, customer/seller iOS AppIcon sets, two 512 notification icons.
6. **Mobile app assets required:** customer and seller app icon masters, square in-app/splash marks, wide/white wordmarks, notification marks; no separate delivery app source found.
7. **Light/dark variants required:** dark-on-light horizontal logo and white-on-dark horizontal/footer logo; square mark; white/monochrome notification mark. App icons should have a safe opaque background.
8. **Brand colors currently used:** web primary `#1b7fed`, secondary `black`, primary-light `#CFDFFB`; Flutter primary `#1455AC`, secondary `#F58300`; admin v2 fallbacks `#1F4FD6` and `#2BB0EE`.
9. **Branding editable from admin:** company info, copyright, web colors, web header/mobile/footer logos, favicon and loader; SEO media through relevant admin forms.
10. **Branding requiring source changes:** Flutter assets/labels, static installer/fallback assets, dedicated PWA manifest/icons, dedicated OG integration, and any intentional package/bundle identity change.
11. **Audit file:** `FASTXHORIZON_BRANDING_AUDIT.md` at the current project root.
12. **READY FOR FAST X HORIZON ASSETS: YES** — the audit is complete and the exact asset pack is listed above. No Fast X Horizon assets or colors have been applied.
