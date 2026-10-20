# Fast X Horizon — PHP / Composer Compatibility Audit

Audit scope: read-only compatibility diagnosis for the installed Fast X Horizon / 6Valley v16.5 application.

No Composer update, install, downgrade, lockfile edit, PHP switch, branding change, database change, route change, or performance-code change was made.

## Executive result

- Runtime PHP: **8.2.12**, CLI binary `C:\xampp\php\php.exe`.
- Laravel: **12.66.0**.
- `composer.json` declares PHP `^8.2`.
- `composer.json` also sets Composer's emulated platform to PHP `8.3.9`.
- The installed lock/vendor set is **not Composer-platform-valid on PHP 8.2.12**.
- `composer check-platform-reqs` exits with code 1.
- Required PHP extensions currently missing: `ext-intl`, `ext-sodium`, and `ext-zip`.
- `vendor/composer/platform_check.php` is currently absent because the optimized autoload was previously regenerated with `--ignore-platform-reqs --no-scripts`. This keeps the local app bootable but is not a valid production compatibility solution.

## 1. Project declaration versus installed runtime

| Item | Observed value | Location / command |
|---|---|---|
| Project PHP constraint | `^8.2` | `composer.json` `require.php` |
| Composer emulated platform | `8.3.9` | `composer.json` `config.platform.php` |
| Actual CLI PHP | `8.2.12` | `php -v`, `PHP_VERSION` |
| PHP executable | `C:\xampp\php\php.exe` | `PHP_BINARY` |
| Laravel | `12.66.0` | `php artisan --version` |
| Composer | `2.6.6` | `php artisan about` |

The root declaration is compatible with PHP 8.2, but the lock contains packages resolved for newer PHP versions. The lock does not record why it was generated. The evidence is consistent with a lock generated under a different PHP platform or with platform requirements bypassed; it is not a valid PHP 8.2 resolution today.

## 2. Exact conflicting packages

The following are the packages reported by `composer why-not php 8.2.12 --locked` / `composer prohibits php 8.2.12 --locked`.

| Package | Installed | PHP requirement | Classification | Dependency chain |
|---|---:|---|---|---|
| `beste/in-memory-cache` | `1.5.0` | `~8.3.0 \|\| ~8.4.0 \|\| ~8.5.0` | Runtime transitive | `kreait/firebase-php 7.24.1` → root `kreait/firebase-php` |
| `kreait/firebase-tokens` | `5.5.0` | `~8.3.0 \|\| ~8.4.0 \|\| ~8.5.0` | Runtime transitive | `kreait/firebase-php 7.24.1` → root `kreait/firebase-php` |
| `laravel/ai` | `v0.7.2` | `^8.3` | Runtime direct | Root `laravel/ai ^0.7.2`; used by `Modules/AI` |
| `lcobucci/clock` | `3.6.0` | `~8.4.0 \|\| ~8.5.0` | Runtime transitive | `league/oauth2-server 8.5.5` → `laravel/passport v12.4.3` |
| `openspout/openspout` | `v4.32.0` | `~8.3.0 \|\| ~8.4.0 \|\| ~8.5.0` | Runtime transitive | `rap2hpoutre/fast-excel v5.14.0` → root `rap2hpoutre/fast-excel` |
| `symfony/clock` | `v8.1.0` | `>=8.4.1` | Runtime transitive | `nesbot/carbon 3.13.2` → `laravel/framework v12.66.0` |
| `symfony/css-selector` | `v8.1.0` | `>=8.4.1` | Runtime transitive | `tijsverkoyen/css-to-inline-styles v2.4.0` → `laravel/framework` |
| `symfony/dom-crawler` | `v8.1.1` | `>=8.4.1` | Runtime transitive | `spatie/crawler 8.5.0` → `spatie/laravel-sitemap 7.4.0` |
| `symfony/event-dispatcher` | `v8.1.2` | `>=8.4.1` | Runtime transitive | `symfony/http-kernel` / `symfony/mailer` → Laravel framework |
| `symfony/filesystem` | `v8.1.2` | `>=8.4.1` | Runtime transitive | `aws/aws-sdk-php 3.392.1` → root AWS / Laravel AI |
| `symfony/string` | `v8.1.2` | `>=8.4.1` | Runtime transitive | `symfony/console v7.4.16` → Laravel framework and console packages |
| `symfony/yaml` | `v8.1.2` | `>=8.4.1` | Development-only transitive | `laravel/sail v1.66.0` → root `require-dev` |
| `laravel/pint` | `v1.30.5` | `^8.3.0` | Development-only direct | Root `require-dev` `laravel/pint ^1.0` |

Additional platform blocker from the same lock:

| Package | Installed | Requirement | Classification | Chain |
|---|---:|---|---|---|
| `maennchen/zipstream-php` | `3.2.2` | `php-64bit ^8.3` | Runtime transitive | `phpoffice/phpspreadsheet 1.30.6` / `maatwebsite/excel 3.1.69` |

## 3. Platform and extension results

`composer check-platform-reqs` exited **1**.

Successful core requirements included `ext-curl`, `ext-dom`, `ext-fileinfo`, `ext-gd`, `ext-json`, `ext-mysqli`, `ext-openssl`, XML-related extensions, and zlib.

Missing requirements:

| Extension | Required by | Current state |
|---|---|---|
| `ext-intl` | Root project / Laravel application | Missing |
| `ext-sodium` | `lcobucci/jwt` | Missing |
| `ext-zip` | `madnest/madzipper`, PhpSpreadsheet/OpenSpout paths | Missing |

The local PHP installation has OPcache enabled and available, but OPcache is unrelated to this dependency failure.

## 4. Safe PHP 8.2 compatibility path

No changes were applied. Read-only package metadata checks identified these PHP 8.2-compatible candidates/lines:

| Current package | PHP 8.2-compatible candidate or line checked | Notes |
|---|---|---|
| `beste/in-memory-cache 1.5.0` | `1.4.0` | Same package; parent Firebase constraint `^1.3.1` can accept it. |
| `kreait/firebase-tokens 5.5.0` | `5.3.0` | Same major; Firebase parent constraint `^5.2` can accept it. Re-test Firebase auth/token behavior. |
| `laravel/ai v0.7.2` | **No compatible version verified** | Checked v0.4.5, v0.5.1, and v0.6.8; each requires PHP `^8.3`. Current AI code directly uses Laravel AI contracts/classes, so removal or replacement is an application/API decision. |
| `lcobucci/clock 3.6.0` | `3.3.0` | `3.3.0` accepts `~8.2.0`; Passport's OAuth2 parent allows the 3.x line. |
| `openspout 4.32.0` | `4.28.0` | `4.28.0` accepts PHP 8.2; FastExcel's `^4.24` parent range can accept it. |
| `maennchen/zipstream-php 3.2.2` | `3.0.2` | `3.0.2` accepts `php-64bit ^8.1`; PhpSpreadsheet allows ZipStream `^2.1 \|\| ^3.0`. |
| Symfony 8.1 packages | Symfony 7.4.x line | Symfony Clock 7.4.0 was checked and accepts PHP `>=8.2`; the lock already uses Symfony 7.4 packages elsewhere. |
| `laravel/pint v1.30.5` | `v1.29.3` | Development-only; checked candidate accepts PHP `^8.2.0`. |

These are resolution candidates, not an approval to edit `composer.json` or `composer.lock`. A future adjustment must be performed in a controlled branch/copy, followed by Composer validation, application tests, Firebase/Passport tests, export tests, and the existing regression QA.

### API-breaking risk

- `laravel/ai` is the highest-risk blocker: the application contains `Modules/AI` routes, tools, `AnonymousAgent`, message classes, and Laravel AI contracts. Downgrading or replacing it cannot be treated as a package-only change.
- Firebase is used by OTP, messaging, notification, and API authentication flows. Even same-major transitive downgrades require those flows to be tested.
- Passport is used by API guards, customer models, and social/API authentication. The `lcobucci/clock` change is transitive and same-major, but token issuance and validation must be tested.
- OpenSpout, ZipStream, PhpSpreadsheet, and Laravel Excel are used by admin/vendor report imports and exports. Export formats and large-file behavior must be checked after any re-resolution.
- Symfony 7.4 versus Symfony 8.1 is a framework-component resolution change. It should be allowed by Composer constraints, not forced by manually editing vendor files.

## 5. PHP 8.4 option and risk

PHP 8.4 would satisfy the listed PHP-version constraints, including the Symfony 8.1 packages and current `lcobucci/clock`, OpenSpout, Firebase transitive packages, and Laravel AI. However, it would still require the missing `ext-intl`, `ext-sodium`, and `ext-zip` extensions.

The source audit found direct usage of:

- Laravel AI in `Modules/AI`.
- Firebase in service providers, OTP, push notification, customer/vendor authentication, and API routes.
- Passport in API authentication and model traits.
- Laravel Excel, PhpSpreadsheet, FastExcel, and OpenSpout in report/export paths.
- Multiple payment SDKs and image/PDF libraries already declared in `composer.json`.

No PHP 8.4 runtime switch was made. No dependency or payment API compatibility suite was run against PHP 8.4, so an upgrade cannot be recommended solely from the current lock. Flutter/mobile API identifiers are unaffected by this audit and were not changed.

## 6. Health commands and exact results

| Command | Result |
|---|---|
| `php artisan --version` | `Laravel Framework 12.66.0` |
| `php artisan about` | Boots successfully; PHP 8.2.12; debug off; config/routes/views cached; MySQL/file session/file cache; local environment |
| `composer validate` | Valid with warnings only: several unbound `*` constraints and one exact Mercado Pago constraint |
| `composer check-platform-reqs` | **Failed**, exit 1; PHP/64-bit conflicts and three missing extensions |
| `composer why-not php 8.2.12 --locked` | **Failed as expected**, listing the 13 PHP-version conflicts above |
| `composer prohibits php 8.2.12 --locked` | Same 13-package conflict list |
| `php --ini` | Loaded `C:\xampp\php\php.ini`; no additional ini files |
| `php -v` | PHP 8.2.12 CLI, Zend OPcache 8.2.12 |
| `php -m` | Confirms `intl`, `sodium`, and `zip` are not loaded |

## 7. Deployment decision

### Recommended strategy: keep PHP 8.2 and resolve the dependency set

This matches the root project declaration and preserves the current stable runtime. The future dependency pass should:

1. Enable/verify the required production extensions (`intl`, `sodium`, `zip`).
2. Re-resolve the lock against the real PHP 8.2 platform, not `--ignore-platform-reqs`.
3. Preserve Laravel 12 and 6Valley architecture.
4. Decide explicitly whether the AI module remains required. A PHP 8.2-compatible Laravel AI version was not verified; the current AI package requires PHP 8.3.
5. Run the complete existing web, auth, Firebase, Passport, payment, export, test, and performance regression checks.

PHP 8.4 is a possible alternative, not the recommended change in this audit, because it would be a runtime migration and still needs extension installation and application-level validation.

## 8. Current deployment safety

**Not currently safe as a Composer-valid PHP 8.2 deployment.** The application boots locally, but the lock fails platform checks, required extensions are absent, and the generated vendor set currently has no Composer platform guard because it was rebuilt with ignored platform requirements.

The current local runtime should be treated as a diagnostic/development state until the dependency decision is approved and the lock/vendor tree is regenerated normally.

## Final verdict

**SAFE AFTER DEPENDENCY ADJUSTMENT**

