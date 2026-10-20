# Fast X Horizon — 6Valley v16.5 Performance Audit

Date: 2026-10-11  
Project: current installed Fast X Horizon / 6Valley v16.5 project  
Local URL: `http://127.0.0.1:8000`

## Executive result

The main confirmed storefront issue was repeated cart-group lookup work in shared
views, plus duplicate homepage CSS/JavaScript references. Two narrowly scoped
changes were applied:

1. Request-scoped, GET-only memoization for `CartManager::get_cart_group_ids()`.
2. Removal of confirmed duplicate `home.css`, Owl Carousel CSS, and Owl Carousel
   JavaScript references from the homepage view. The global layout still loads
   each required asset once.

No framework, 6Valley architecture, route, technical ID, branding asset, master
logo, vendor dependency, or database index was changed for this audit.

The measured local PHP/XAMPP median improved in the comparison run, but this is
not a production benchmark. The application still has significant PHP bootstrap
and local-server overhead, and PHP OPcache is not enabled in the current CLI
runtime.

## 1. Runtime and project scope

| Item | Result |
|---|---|
| 6Valley version | 16.5 (`app/Library/Constant.php`, `SOFTWARE_VERSION`) |
| Laravel | 12.66.0 |
| PHP | 8.2.12 CLI/XAMPP |
| Environment | `APP_ENV=local`, `APP_DEBUG=false` |
| Database | MySQL-compatible MariaDB 10.4.32 on local XAMPP |
| Cache/session | File cache and file session |
| Queue | Database queue |
| Web UI | Laravel Blade storefront, default theme active; Theme Aster remains available |
| Flutter SDK | Not found on PATH or checked common locations |
| Browser automation | Chrome exists, but headless runtime exits with code 13 |

The database contains no active products or categories after the restored install
seed (`products=0`, `categories=0`). Therefore, valid product-detail and
category-detail performance measurements were not possible. Synthetic missing
slugs redirect/fallback and must not be treated as representative catalogue
benchmarks.

## 2. Before/after HTTP timings

Each value is the median of three local `curl.exe` requests. The baseline was
captured before the safe source optimizations. The comparison run was captured
after request memoization, duplicate asset removal, and Laravel cache warmup.

| Route | Baseline median | Comparison median | Change | Comparison bytes |
|---|---:|---:|---:|---:|
| `/` | 1.986 s | 1.595 s | 19.7% lower | 75,749 |
| `/customer/auth/login` | 1.751 s | 1.532 s | 12.5% lower | 71,033 |
| `/vendor/auth/login` | 0.807 s | 0.642 s | 20.4% lower | 9,318 |
| `/login/admin` | 0.906 s | 0.714 s | 21.2% lower | 8,863 |

These percentages combine the source changes, valid Laravel caches, warmup, and
normal local-server variance; they are not isolated microbenchmarks of one line
of code.

### Final post-restore smoke timing

After the test-database incident was recovered from the bundled install dump and
Fast X Horizon settings were restored, the final smoke medians were:

| Route | Final median | Bytes | Status |
|---|---:|---:|---:|
| `/` | 1.477 s | 81,120 | 200 |
| `/customer/auth/login` | 1.438 s | 76,415 | 200 |
| `/vendor/auth/login` | 0.861 s | 9,318 | 200 |
| `/login/admin` | 0.798 s | 8,863 | 200 |

The different homepage/login byte counts reflect the restored seeded database
and session state, so these final smoke values should be compared cautiously with
the empty-catalogue baseline.

## 3. Query profiling

Temporary request-level profiling was used during measurement and fully removed
afterward. No profiler marker or profiling file remains in `public/index.php` or
`storage/logs`.

| Surface | Before queries | After queries | Before DB time | After DB time |
|---|---:|---:|---:|---:|
| Homepage `/` | 22 | 20 | 26.69 ms | 26.32 ms |
| Customer login | 12 | 10 | 11.61 ms | 5.16 ms |
| Vendor login | 0 | 0 | 0 ms | 0 ms |
| Admin login | 1 | 1 | 1.44 ms | 0.62 ms |

The homepage database execution time was only about 26 ms while end-to-end local
response time was roughly 1.1–2.0 seconds. The dominant current bottleneck is
PHP/Laravel bootstrap, shared configuration/view work, and local XAMPP request
overhead—not slow SQL execution.

### Main repeated query

The same cart-group-ID query was executed three times during a homepage request:

```sql
select cart_group_id from carts
where exists (... active products ...)
...
group by cart_group_id
```

`app/Utils/CartManager.php` now memoizes this result only on the current GET
request and includes the customer/guest identity and requested type in the key.
POST and checkout/write requests never use the memoized value, preserving cart
correctness and user isolation.

Other observed work included `ORDER BY RAND()` for a random discounted product,
business-settings existence checks, product-count checks, flash-deal/stock-
clearance/deal-of-the-day checks, and cart queries. No index was added because
the catalogue is empty and the measured database time does not justify a blind
schema change.

## 4. N+1 and catalogue coverage

No product-card or category N+1 query was confirmed because the current database
has no active products/categories. The product and category routes were checked,
but no valid seeded slug exists. A production follow-up should profile a realistic
catalogue with Debugbar/Telescope or a production-safe query listener before
adding indexes or eager-loading changes.

## 5. Asset and payload audit

Final homepage static inspection found approximately:

| Item | Count/result |
|---|---:|
| Script references | 30 |
| Stylesheet/font references | 23 |
| Image references | 24 |
| Unique extracted refs in final smoke | 74 |
| Local absolute asset HEAD checks | 74/74 HTTP 200 |
| Missing/failed local assets | 0 |
| Lazy/data image matches | 1 |
| External rendered asset refs | 0 in the tested homepage HTML |

Confirmed duplicate references removed from
`resources/themes/default/web-views/home.blade.php`:

- duplicate `home.css`
- duplicate `owl.carousel.min.css`
- duplicate `owl.carousel.min.js`

Final duplicate counts are one reference each for `home.css`, Owl Carousel CSS,
Owl Carousel JS, and `home.js`. The remaining global backend-named assets (icons,
validation, telephone input, toastr, and conditional reCAPTCHA assets) are used
by shared storefront forms or icons and were not removed speculatively.

The current approved Fast X Horizon web/mobile/footer/favicon/invoice/loader
assets returned HTTP 200 in the earlier branding QA and remain in their existing
storage locations. No image recompression, resizing, logo regeneration, or
branding asset change was performed in this performance task.

## 6. Cache and runtime audit

The following commands all passed at the end of the audit:

```text
php artisan optimize:clear   PASS
php artisan config:cache     PASS
php artisan route:cache      PASS
php artisan view:cache       PASS
```

Current local cache/session drivers are file-based and the queue is database-
backed. Redis was not configured, so no Redis migration was attempted.

CLI inspection showed OPcache is not loaded or enabled in the current PHP
runtime. For production, use a supported Apache/PHP-FPM deployment with OPcache
configured and `APP_ENV=production`, `APP_DEBUG=false`, and cached config/routes/
views. Redis is an optional next step for shared cache/session/queue workloads,
not a required local change.

## 7. Route smoke validation

Final post-restore smoke checks:

| Route | HTTP result | Notes |
|---|---:|---|
| `/` | 200 | Fast X Horizon title/name present |
| `/products` | 200 | Empty catalogue state; no product records |
| `/customer/auth/login` | 200 | Public login page |
| `/vendor/auth/login` | 200 | Public seller login page |
| `/login/admin` | 200 | Public admin login page |
| `/category/nonexistent-performance-slug` | 302 | Synthetic missing slug; not a valid category benchmark |
| `/product/nonexistent-performance-slug` | 302 | Synthetic missing slug; not a valid product benchmark |
| `/shop-cart` | 302 | Empty/unauthenticated cart flow |

Authenticated admin/seller dashboard performance was not measured because no
legitimate authenticated session was available. Authentication was not bypassed.

## 8. Flutter/mobile validation

The following were checked:

```text
where.exe flutter       NOT FOUND
where.exe dart          NOT FOUND
C:\src\flutter         not found
C:\flutter              not found
C:\Users\hamza\flutter not found
C:\Users\hamza\development\flutter not found
```

Consequently these commands were not run:

```text
flutter --version
flutter pub get
flutter analyze
flutter build apk --debug
```

No Flutter dependency or mobile source file was modified. The earlier branding
QA statically verified the customer and vendor app assets/labels; actual build
performance and APK validation remain environment-blocked.

## 9. Browser visual/network validation

Chrome exists at `C:\Program Files\Google\Chrome\Application\chrome.exe`.
Dedicated temporary user-data directories and headless flag variants were tried
previously. Chrome exited with code 13 and reported:

```text
Multiple targets are not supported in headless mode.
```

No fake screenshots were created, and browser-level request counts/Lighthouse
metrics are not claimed. Static HTML asset extraction and HTTP HEAD checks were
used instead.

## 10. Test result and environment warning

Command run:

```text
php artisan test
```

Current result:

```text
15 failed, 15 passed (46 assertions)
```

Classification:

- **Branding/performance regressions:** none identified.
- **Pre-existing AI issues:** eight `AgentLoopService::isFollowUp()` reflection
  failures and one AI query-guard digits assertion failure.
- **Test database/environment issues:** five chat authorization tests failed
  because the test run attempted to alter missing `flash_deals` in the local
  MySQL database; the feature example also received HTTP 500 from the resulting
  incomplete schema.

Important environment finding: the project has no `.env.testing`, and the
existing cached production configuration caused `php artisan test` to use the
local MySQL database despite `phpunit.xml` declaring SQLite `:memory:`. The test
runner reset that local database to an incomplete migration state. The exact
database state was verified (only six OAuth/migration tables), then restored
from the bundled `installation/backup/database.sql` dump (157 tables). The
Fast X Horizon business settings and platform shop name were re-established
from the approved branding state; no source migration was rewritten.

Future test runs must clear the config cache and/or use an isolated test
database before running `php artisan test`. Do not run a test suite against a
production/local business database.

## 11. Files changed by this performance task

| File | Change |
|---|---|
| `app/Utils/CartManager.php` | GET-only request-scoped cart-group memoization |
| `resources/themes/default/web-views/home.blade.php` | Removed confirmed duplicate homepage CSS/JS references |
| `FASTXHORIZON_PERFORMANCE_AUDIT.md` | This evidence report |

Generated Laravel cache files were rebuilt by Artisan. No migration, index,
framework dependency, `vendor/` file, logo master, color source, route, or
technical identifier was changed.

## 12. Recommended next steps

1. Run production-like benchmarks with PHP OPcache enabled and a real catalogue.
2. Add query profiling for category/product pages after representative products,
   sellers, carts, and promotions exist.
3. Evaluate replacing `ORDER BY RAND()` with a deterministic/randomized indexed
   strategy if the random discounted-product query becomes material at scale.
4. Split shared frontend assets by page only after browser regression testing;
   the current global form/icon dependencies are intentionally retained.
5. Install/use Flutter on a validation machine and run customer/vendor analyze
   and debug APK builds.
6. Repair or replace the Chrome automation runtime before claiming browser-level
   visual, network, or Lighthouse completion.

## Final verdict

**COMPLETE EXCEPT ENVIRONMENT VALIDATION**

Safe web-side performance improvements were implemented and measured. Full
production-grade validation remains incomplete because Flutter SDK validation,
browser visual/network automation, authenticated dashboards, and a realistic
catalogue benchmark are unavailable in this environment.

## PERFORMANCE PASS 2 — SERVER-SIDE BOTTLENECK ISOLATION

### 1. Test-database safety was fixed before further testing

The earlier test incident was caused by Laravel's cached production/local
configuration taking precedence over the SQLite settings in `phpunit.xml`.
There was no `.env.testing`, so the test process connected to the normal local
MySQL `6valley` database and reset it to an incomplete migration state.

The test bootstrap is now isolated in `tests/bootstrap.php` and is loaded by
`phpunit.xml`. Before Composer/Laravel boot it forces:

```text
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

It also removes a cached production `bootstrap/cache/config.php` before PHPUnit
boots. This is test-only isolation; production `.env` and production database
configuration were not rewritten.

Safety proof from the isolated run:

| Check | Before test | After test |
|---|---:|---:|
| Local MySQL `6valley` tables | 157 | 157 |
| `business_settings.company_name` | Fast X Horizon | Fast X Horizon |
| `shops.id=1` name | Fast X Horizon | Fast X Horizon |
| Test driver/database | SQLite `:memory:` | SQLite `:memory:` |

The test result with isolation was `16 failed, 14 passed (44 assertions)`.
There were no branding or performance regressions. The failures remain
pre-existing/test-schema issues: eight `AgentLoopService::isFollowUp()`
failures, one AI query-guard assertion, one missing SQLite
`business_settings` setup, five missing SQLite `flash_deals` setup failures,
and the related example failure. The local MySQL database was not mutated by
this isolated run.

### 2. Final clean baseline and benchmark

The earlier measured values remain the comparison points:

```text
Before Pass 1: 1.986000s median
After Pass 1:  1.595000s median
```

After temporary profiling was removed, Laravel caches were rebuilt, and the
first warm-up request was discarded, the final clean benchmark used 10 warm
requests:

| Metric | Final Pass 2 result |
|---|---:|
| Warm requests measured | 10 (after 1 warm-up request) |
| HTTP status | 10/10 HTTP 200 |
| Total median | **1.655596s** |
| Total minimum | 1.477795s |
| Total maximum | 1.915501s |
| Total p95 | 1.915501s |
| TTFB median | 1.650151s |
| TTFB minimum | 1.475008s |
| TTFB maximum | 1.911673s |
| TTFB p95 | 1.911673s |
| Homepage query count | 20 |

The Pass 2 clean result is approximately **16.64% faster than the original
1.986s baseline**, but is statistically in the same local range as the
1.595s Pass 1 comparison. No second production optimization is claimed as a
measurable speed win.

Cold/warm cache sampling before the final cleanup also showed the expected
cache effect:

| State | Result |
|---|---:|
| Cold request after `optimize:clear` | 2.046624s total / 2.043028s TTFB |
| Warm 10-request median | 1.568176s total / 1.565183s TTFB |

Local request variance was substantial; one warm sample reached 2.087232s.

### 3. Temporary request profiling findings

The homepage was temporarily profiled only through the private
`__fxh_perf=1` query path. The profiler was removed after diagnosis, and its
JSONL output was deleted. No profiling data is exposed by the final request.

Representative clean-catalogue profile ranges were:

| Layer/section | Observed time |
|---|---:|
| AppServiceProvider global boot/shared web config | 145–210ms |
| Homepage controller/data work | 48–78ms in representative samples |
| Blade/layout rendering | 759–862ms |
| Database execution total | approximately 20–30ms |
| Remaining framework/PHP/local-server overhead | inferred remainder, approximately 500–800ms |

The controller section timings were:

| Homepage section | Observed range |
|---|---:|
| Clearance-sale products | 28–35ms |
| Featured products | 7–17ms |
| New arrivals | 7–10ms |
| Recommended product | 10–13ms |
| Top vendors | 3–4ms |
| Categories | 4–6ms |
| Flash deals | 2–3ms |
| Deal of the day | 1–2ms |
| Brands/home categories/banners | generally below 2ms each |

The three largest measured application contributors were clearance-sale
product preparation, featured/new-arrival product builders, and recommended
product preparation. They are still small compared with Blade plus global
bootstrap time. The current local database contains zero products and zero
categories, so product-card rating/discount/translation loops and realistic
category trees could not be validated safely.

### 4. Top database queries by measured execution time

The homepage remained at 20 queries and database time was not the bottleneck.
The following identities are the slowest observed query classes across the
temporary profiles; timings vary by request and are rounded ranges:

| Rank | Query identity | Observed time |
|---:|---|---:|
| 1 | Guest-user insert from first-time guest middleware | 7.3–7.8ms |
| 2 | Random discounted product with `ORDER BY RAND()` | 2.4–4.8ms |
| 3 | Cart-group lookup grouped by `cart_group_id` | 0.8–1.9ms |
| 4 | Cart/product existence or cart item select | 0.6–1.6ms |
| 5 | Stock-clearance product ID query | 0.8–1.5ms |
| 6 | Active discounted-product count aggregate | 0.7–1.3ms |
| 7 | Flash-deal query with related-product count/subquery | 0.4–1.0ms |
| 8 | Default robots-meta lookup | 0.4–1.5ms |
| 9 | Deal-of-the-day/product join | 0.4–0.8ms |
| 10 | `information_schema` table-existence checks | 0.3–0.7ms |

The guest insert appears on fresh curl requests because no session cookie is
reused; a normal browser session does not create a new guest row on every
request. Query rows returned were not independently available from Laravel's
query event, but the database currently has no catalogue rows, so these
product queries return empty results. No blind index or schema change was
made.

### 5. Settings, helpers, and request-scope checks

`business_settings` is loaded through the existing cache architecture. The
homepage calls `getWebConfig()` repeatedly, but the underlying settings table
was not repeatedly fetched from MySQL in the profile. A temporary GET-only
request memoization experiment for `getWebConfig()`/`getLoginConfig()` did not
produce a repeatable end-to-end improvement in this local run, so it was
reverted rather than retained as speculative optimization.

The existing first-pass `CartManager` request-scope memoization remains. The
profile showed no new cross-user cache behavior, and the cart-group lookup is
still a small part of the request. Guest and authenticated cart behavior was
not fully browser-session tested because no authenticated session was supplied.

No product rating value or discount value was changed. With an empty
catalogue, no product-card N+1, rating-row hydration, translation loop, image
filesystem loop, or large seller collection was observed.

### 6. Middleware, composers, and external-call findings

`AppServiceProvider` globally shares the storefront `$web_config`, and this is
the main application-level cost identified in the profile. The only registered
view composers found are scoped to courier/admin views and the admin layout;
they are not homepage composers. The customer homepage does run normal web
middleware including session/guest, localization, maintenance, and browsing
history handling. The guest middleware's first-time insert is visible in the
query table above, but is not large enough to explain the request latency.

Static tracing found HTTP/cURL integrations for optional reCAPTCHA, push
notifications, SMS, and file helpers elsewhere in the application. No
synchronous AI, courier, payment, update/licensing, or 6amTech network call
was evidenced during the homepage profile. The conditional reCAPTCHA/analytics
assets were not active in the tested homepage response.

### 7. PHP and MySQL runtime checks

```text
PHP 8.2.12
Zend OPcache: not loaded/enabled in the current CLI/runtime inspection
realpath_cache_size: 4096K
realpath_cache_ttl: 120
Relevant extensions: PDO, pdo_mysql, pdo_sqlite, curl, fileinfo, gd,
                     mbstring, openssl
DB host: 127.0.0.1:3306
SELECT 1: 0.864ms in Laravel tinker
SELECT COUNT(*) FROM products: 0.773ms in Laravel tinker
```

These simple query timings do not indicate a MySQL latency bottleneck. A
fresh-PDO handshake was not separately measured from the already booted
Laravel process; the working DB host was not changed. OPcache should be
enabled in the production PHP SAPI, but the local `php.ini` was not modified.

### 8. Regression and asset smoke checks

The following final smoke checks passed or returned their expected empty-state
redirect:

| Route | Result |
|---|---:|
| `/` | 200 |
| `/products` | 200 |
| `/customer/auth/login` | 200 |
| `/vendor/auth/login` | 200 |
| `/login/admin` | 200 |
| `/category/nonexistent-performance-slug` | 302 expected synthetic-missing-slug redirect |
| `/product/nonexistent-performance-slug` | 302 expected synthetic-missing-slug redirect |
| `/shop-cart` | 302 expected empty/guest cart redirect |

Active branding assets were checked against their actual storage URLs:

```text
fastxhorizon-web.webp       200
fastxhorizon-mobile.webp   200
fastxhorizon-footer.webp   200
fastxhorizon-favicon.png   200
fastxhorizon-invoice.png   200
/favicon.ico               200
```

The final HTML still contains Fast X Horizon branding, no `Local Store`, and
no public `Copy@6amtech.com`. The public `6amTech` occurrences previously
found are technical/documentation/demo links, not visible marketplace
branding. No logo, color, UI, route, technical identifier, or master asset was
changed during Pass 2.

### 9. Pass 2 changes and remaining bottleneck

Pass 2 production performance changes: none were retained after profiling
because the measured application work did not identify a safe, repeatable
second optimization. The first-pass CartManager and duplicate asset changes
remain intact. The durable Pass 2 change is test-environment isolation in
`phpunit.xml` and `tests/bootstrap.php`; temporary profiling code was removed.

The largest remaining bottleneck is not the 20-query database workload. It is
the combination of global storefront configuration/bootstrap, Blade/layout
rendering, missing OPcache, and local PHP/XAMPP request overhead. Reaching
approximately one second safely requires a representative catalogue and a
production-like PHP SAPI/OPcache benchmark before changing global storefront
architecture. No random micro-optimization was applied.

### 10. Pass 2 final verdict

**COMPLETE EXCEPT ENVIRONMENT VALIDATION**

The server-side bottleneck was isolated, the test database is now safely
separated, and the final clean homepage benchmark is documented. Full
validation is still environment-limited because Flutter is unavailable,
browser screenshot automation remains unavailable, authenticated dashboard
sessions were not provided, and the database has no representative catalogue
for product/category/rating performance validation.

## PERFORMANCE PASS 3 — PHP RUNTIME + BLADE BOOTSTRAP

### 1. PHP runtime identity

```text
CLI executable: C:\xampp\php\php.exe
PHP version: 8.2.12
Active CLI php.ini: C:\xampp\php\php.ini
php --ini: no additional .ini scan directory
```

The local web server uses the same `C:\xampp\php\php.exe` installation. The
active process was restarted safely as the bound port-8000 process using:

```text
php.exe -S 127.0.0.1:8000 -t public "<project>\server.php"
```

The `-t public` document root matters: without it, Laravel routes worked but
`/storage/...` static assets returned 404. After the corrected restart, active
Fast X Horizon assets returned HTTP 200 again.

Relevant modules include `PDO`, `pdo_mysql`, `pdo_sqlite`, `curl`, `fileinfo`,
`gd`, `mbstring`, and `openssl`. No separate web PHP runtime was found; CLI
and web use the same PHP path/version.

### 2. OPcache availability and local settings

The OPcache DLL was already present at:

```text
C:\xampp\php\ext\php_opcache.dll
```

It was initially commented out in `C:\xampp\php\php.ini`. The local
development-safe changes applied were:

```ini
zend_extension=opcache
opcache.enable=1
opcache.enable_cli=1
opcache.validate_timestamps=1
opcache.revalidate_freq=0
```

Current verified values:

```text
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.validate_timestamps=1
opcache.revalidate_freq=0
```

The PHP server was restarted after the php.ini change. Development timestamp
validation remains enabled, so source edits are detected without a
production-style stale-code policy.

Recommended production profile, not applied locally:

```ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=192       ; or 256 for a larger deployment
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
```

Production `validate_timestamps=0` must only be used with controlled deploys
and a PHP-FPM/Apache restart.

### 3. Blade, config, and route cache

All cache commands passed after final cleanup:

```text
php artisan view:clear    PASS
php artisan view:cache    PASS
php artisan config:cache  PASS
php artisan route:cache   PASS
```

`storage/framework/views` contains 872 compiled Blade files after caching.
Temporary Pass 3 profiling code and its JSONL log were removed; no
`__fxh_pass3_profile`, `fxh_p3`, or `performance-pass3` marker remains in
active source.

### 4. Composer autoloader

The initial plain `composer dump-autoload -o` exposed a pre-existing
lock/runtime mismatch: the lock-generated platform guard required PHP
`>=8.4.1`, while this installed 6Valley project declares PHP `^8.2` and the
active runtime is 8.2.12. No PHP upgrade or dependency installation was
performed.

For local performance validation only, the autoloader was regenerated with:

```text
composer dump-autoload -o --ignore-platform-reqs --no-scripts
```

This generated optimized classmaps containing 18,992 classes. Composer emitted
existing PSR-4 warnings for test/migration classes under Courier and TaxModule;
no application class was changed. `composer install --no-dev` was not run.

Because the lock/runtime platform metadata is inconsistent, the generated
platform guard is not present after this local validation command. This is a
deployment-safety warning: resolve the project lock/PHP version mismatch before
using Composer in production, and do not use `--ignore-platform-reqs` as a
production deployment policy.

### 5. Blade/layout profile

The active default theme was temporarily instrumented and then restored. With
OPcache enabled, representative profile ranges were:

| Component | Observed time |
|---|---:|
| Laravel kernel/provider bootstrap aggregate | 216–304ms |
| Active front-end layout | 145–214ms |
| Header/navigation partial | 87–116ms |
| Footer partial | 21–34ms |
| Homepage child/body view | 14–51ms |
| Database execution | 17–20ms |

The header/navigation is the slowest individual Blade partial. Overall layout
and provider bootstrap are still larger than the homepage body. The profile did
not justify flattening includes or changing marketplace sections.

Static active-template counts were:

| Helper/source item | Count |
|---|---:|
| `getWebConfig()` references | 38 |
| `translate()` references | 135 |
| `getStorageImages()` references | 12 |
| `theme_asset()` references | 49 |
| `dynamicAsset()` references | 17 |
| Homepage include/component directives | 15 |
| Layout include/component directives | 10 |

These are source-level counts, not a claim that each call hits MySQL. The
existing settings/cache architecture kept database time low, and no
request-scope helper rewrite was retained because it did not produce a proven
additional gain in Pass 2. Translation support, cart, wishlist, and
customer-specific data were not globally cached.

### 6. 15-warm-request benchmark

Method: 16 requests per route, first request discarded, 15 warm samples,
`curl` total time and time-to-first-byte, with config/routes/views cached and
OPcache enabled.

| Route | Median total | Min | Max | p95 | Median TTFB | HTTP |
|---|---:|---:|---:|---:|---:|---:|
| Homepage `/` | **0.580711s** | 0.495648s | 0.655444s | 0.655444s | 0.579066s | 15/15 200 |
| Customer login | 0.459601s | 0.401606s | 0.523167s | 0.523167s | 0.457937s | 15/15 200 |
| Admin login | 0.340830s | 0.307216s | 0.432205s | 0.432205s | 0.339484s | 15/15 200 |
| Seller login | 0.295657s | 0.221493s | 0.346303s | 0.346303s | 0.292412s | 15/15 200 |

Homepage comparison:

```text
Original: 1.986s
Pass 1:   1.595s
Pass 2:   1.656s
Pass 3:   0.581s median
```

Pass 3 is a real improvement: approximately **64.92% faster than Pass 2** and
**70.76% faster than the original 1.986s baseline**. The improvement is
attributable to enabling the existing OPcache for the matching CLI/web PHP
runtime, with optimized Composer classmaps contributing to the
production-like bootstrap path. No application-level feature or database
optimization was added in this pass.

### 7. Regression checks

Final route and asset smoke checks passed:

```text
/                         200
/customer/auth/login      200
/login/admin              200
/vendor/auth/login        200
/shop-cart                302 expected empty/guest-cart redirect
Fast X Horizon assets     200 (web/mobile/footer/favicon/invoice/favicon.ico)
```

Fast X Horizon branding remains present. No logo, color, marketplace feature,
technical ID, route, or database behavior was changed.

The isolated test suite remains `16 failed, 14 passed (44 assertions)` with
the same pre-existing AI and incomplete SQLite-schema failures documented in
Pass 2. No new runtime/performance failure was introduced. After the test run,
config/route/view caches were rebuilt successfully.

### 8. Pass 3 conclusion and remaining bottleneck

The dominant local bottleneck identified in Pass 2 was PHP/Laravel bootstrap
and Blade rendering. Enabling the existing OPcache removed most of that local
interpreter/bootstrap cost. Remaining server-side work is primarily the global
AppServiceProvider/storefront bootstrap and header/layout rendering; database
time remains insignificant at approximately 17–20ms.

No random Blade flattening, helper rewrite, UI change, or feature removal was
made. Production should first reproduce this result under PHP-FPM/Apache with
OPcache and a representative catalogue before considering further application
changes.

**PASS 3 RESULT: COMPLETE EXCEPT ENVIRONMENT VALIDATION**
