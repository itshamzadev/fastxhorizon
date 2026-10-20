# CLAUDE.md

## Project Rules
@.claude/rules/translate.md
@.claude/rules/response-style.md
@.claude/rules/coding-standards.md
@.claude/rules/auction-ui.md

## Commands

### Artisan
- `php artisan serve` — dev server
- `php artisan migrate` — apply pending migrations (see Database note below)
- `php artisan optimize:clear` — clear all caches
- `php artisan generate:entity {name}` — scaffold model + repo interface + impl
- `php artisan file:permission` — fix storage permissions after deployment

### Frontend (Vite)
- `npm run dev` — Vite dev server (writes `public/hot`)
- `npm run build` — production build to `public/build`
- Entry: `resources/js/app.jsx` (Inertia+React) + `resources/css/app.css`; `@` → `Modules/Builder/resources/js`

## Database
**Critical:** Never run `php artisan migrate` on an empty database. Import `installation/backup/database.sql` first, then migrate.

## Architecture

**Flow:** Controller → Service → Repository → Model. Controllers are thin; logic lives in `app/Services/`. Repos in `app/Contracts/Repositories/`. Helpers in `app/Utils/`.

**Routes** (non-standard, loaded by `app/Providers/RouteServiceProvider.php`):
- Admin: `routes/admin/routes.php`
- Vendor: `routes/vendor/routes.php`
- Web: `routes/web/routes.php`
- API v1/v2: `routes/rest_api/v{1,2}/api.php`
- API v3 seller: `routes/rest_api/v3/seller.php`
- Each `Modules/` dir has its own RouteServiceProvider.

**Modules** (`nwidart/laravel-modules`): `Modules/AI`, `Modules/Auction`, `Modules/Blog`, `Modules/TaxModule` — each is a self-contained mini-app.

**Bootstrap:** `AppServiceProvider` loads themes, settings, payment configs, and add-on states from DB at boot. Many behaviors are DB-driven, not code-driven.

**AI Module:** `Modules/AI/AIProviders/AIProviderManager.php` selects active provider (OpenAI/Claude) from DB config.

**Frontend:** Legacy admin/vendor/web views are server-rendered Blade + Bootstrap 4 with pre-built static assets under `public/assets/` (no bundler). The `Modules/Builder` storefront is Inertia + React, bundled by the root Vite config.

