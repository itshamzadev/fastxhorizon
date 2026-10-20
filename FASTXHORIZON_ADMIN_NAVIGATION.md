# Fast X Horizon Admin Navigation

## Scope

The admin navigation keeps the existing 6Valley V2 two-column shell: a narrow
icon rail and a contextual submenu panel. This pass changes navigation
grouping and labels only. Backend routes, controllers, permissions, database
tables and business logic were not renamed or changed.

## Final granular modules

| Rail module | Permission gate | Main groups |
| --- | --- | --- |
| Dashboard | always available | Overview: Dashboard |
| POS | `pos` | Point of Sale: POS |
| Orders | `orders` | Orders: all statuses; After Sales: refund statuses |
| Products | `catalog` | Product Management, Inventory |
| Catalog | `catalog` | Taxonomy, Homepage Taxonomy |
| Deals | `marketing` | Fast X Horizon deal management |
| Promotions | `marketing` | Featured products, coupons and promotional products |
| Sellers | `people` | Seller Management, Seller Operations |
| Customers | `people` | Customers, Customer Activity |
| Marketing | `marketing` | Banners, announcements and campaign tools |
| Notifications | `marketing` | Customer and push notifications |
| Newsletter | `people` | Subscribers |
| Finance | `reports` | Transactions and financial operations |
| Payouts | `people` | Seller withdrawals and earnings |
| Reports | `reports` | Sales, product, seller and finance reports |
| Content | `marketing` | Website pages and blog |
| Support | `people` | Tickets, inbox and contact messages |
| Reviews | `people` | Product reviews |
| Integrations | `3rd_party_setup` | Communication, Authentication, Maps & Business, AI, Payment Integrations |
| Administration | `system_settings` + master admin | Team, Access Control |
| Website Settings | business/theme/marketing permissions | Public website, homepage and SEO |
| Commerce Settings | `business_settings` | Product, order, seller, customer, shipping and tax |
| System Settings | system/business permissions | Business identity and core system |

The final rail order is:

Dashboard → POS → Orders → Products → Catalog → Deals & Promotions → Sellers
→ Customers → Marketing → Newsletter → Finance → Reports & Analytics → Content
→ Support & Reviews → Integrations → Administration → Settings.

The key ownership rules are:

- Products contains product operations and inventory only.
- Catalog is the canonical home for categories, subcategories, brands,
  attributes, product gallery and homepage taxonomy.
- Deals contains deal management only. Promotions owns Featured Products,
  Coupons and other product-promotion pages; Homepage Categories remains in
  Catalog.
- Marketing owns banners and campaign tools. Notifications owns push/customer/
  seller notification sending; Newsletter remains its own module.
- Support owns tickets, contact messages and inbox. Reviews owns product
  reviews.
- Integrations owns third-party communication, authentication, maps, ERP,
  courier, AI and payment integration pages.
- Administration owns employees and roles; customers never appear there.
- Website Settings owns public-site appearance, homepage sections, social links
  and SEO. Commerce Settings owns marketplace operations, shipping, tax and
  currency. System Settings owns business identity and core system tools.

## Route mapping highlights

### Dashboard

- `admin.dashboard.index` → Dashboard
- `admin.pos.index` → Dashboard → Overview → POS

### Orders

- `admin.orders.list` → Orders → all, pending, confirmed, processing, out for delivery, delivered, returned, failed and cancelled
- `admin.refund-section.refund.list` → Orders → After Sales → refund states

### Products

- `admin.products.list`, `admin.products.add`, `admin.products.updated-product-list`
  and `admin.products.bulk-import` → Product Management
- `admin.category.view`, `admin.sub-category.view`,
  `admin.sub-sub-category.view`, `admin.brand.list`, `admin.attribute.view` and
  `admin.products.product-gallery` → Catalog
- `admin.reviews.list`, `admin.products.stock-limit-list` and
  `admin.products.request-restock-list` → Product Content & Stock

### Deals & Promotions

- `admin.deal.flash` and `admin.deal.flash-add` → Fast X Horizon Deals
- `admin.business-settings.priority-setup.index` → Homepage Commerce settings
- `admin.products.list` → Featured Product Selection (the existing product
  list/toggle remains the source of truth)
- `admin.category.view` → Homepage Categories (same existing taxonomy page;
  no duplicate category system)
- `admin.coupon.add`, `admin.banner.list`, `admin.deal.day`,
  `admin.deal.feature` and `admin.deal.clearance-sale.index` → Other Promotions
- Auction addon routes are appended under Deals & Promotions only when the
  Auction addon is published and its existing route configuration is present.

### Sellers and customers

- `admin.vendors.vendor-list`, `admin.vendors.add` and
  `admin.vendors.withdraw_list` → Sellers
- `admin.delivery-man.list`, `admin.delivery-man.add`, delivery withdrawals and
  emergency contacts → Sellers → Seller Operations
- `admin.customer.list`, `admin.reviews.list`, wallet and loyalty routes →
  Customers
- `admin.customer.subscriber-list` → the dedicated Newsletter module

### Marketing, finance, reports and content

- Notification, push-notification, announcement and analytics routes →
  Marketing
- Order, expense, refund and wallet-bonus transaction routes → Finance
- Order, earnings, in-house sales, product, stock, wishlist and seller report
  routes → Reports & Analytics
- Pages/media, social links, SEO and blog routes → Content

### Settings

Existing settings routes are grouped without renaming them:

- business/web/in-house-shop/currency/system setup
- product, order, seller, customer, shipping and delivery settings
- homepage section and theme settings under Website
- VAT/tax setup when the Tax add-on is published
- maintenance, storage, email templates, file manager and add-on routes under
  System

Third-party tools are surfaced in the dedicated Integrations module. Admin
employees and roles are surfaced in the dedicated Administration module.

Administration entries are additionally restricted to the existing master-admin
role (`admin_role_id = 1`), matching the previous team-menu behavior.

## Permission and feature behavior

- Every rail item is gated with the existing `Helpers::module_permission_check`
  module permission.
- Settings entries for third-party, system and theme/add-on areas retain their
  narrower existing permission gates; published add-ons are checked before
  their navigation links are rendered.
- Route links are rendered only when `Route::has()` confirms that the current
  installation provides the page. Optional modules therefore do not produce
  dead links.
- An unavailable group is omitted. A module with no permitted/available menu
  entries is omitted from the rail.
- Existing order/refund counters are reused; no new menu-count queries were
  introduced.
- Existing pinning, accordion expansion, active route highlighting, command
  palette and setup-guide behavior remain on the V2 shell.

## Active route behavior

Each entry carries path patterns for the actual existing URL structure. The
existing V2 JavaScript still derives the selected rail module from the links
rendered in that module, so a nested route keeps its department active without
renaming route names.

## Responsive behavior

The existing V2 CSS/JS behavior remains in use:

- desktop: icon rail + contextual panel + content
- collapsed mode: rail only, with the existing flyout behavior
- smaller viewports: existing collapse/off-canvas behavior
- rail and contextual panel scroll independently
- setup guide remains pinned below the contextual menu when applicable

## Validation

- Blade view cache compiles successfully after the navigation changes.
- Server-side V2 sidebar render produces 23 module sections and includes the
  Deals, Promotions, Notifications, Payouts, Support, Reviews, Website
  Settings, Commerce Settings and System Settings entries available in this
  installation.
- Existing V2 JavaScript/CSS and the original sidebar partials were preserved;
  no routes, controllers, permissions, database tables or business logic were
  changed.
- Authenticated browser-dashboard screenshot validation remains dependent on
  the local admin route/session environment; the partial render and public
  login/home smoke checks pass.

The rail was widened from 60px to 72px and the contextual panel from 232px to
280px to match the requested enterprise navigation density. No content-column
width or backend layout calculation was changed.

## Files

- `resources/views/layouts/admin/partials/v2/_module-rail.blade.php`
- `resources/views/layouts/admin/partials/v2/_module-side-bar.blade.php`
- `resources/views/layouts/admin/partials/v2/_body.blade.php`
- `public/assets/back-end/css/admin-v2.css`

The previous `_rail.blade.php` and `_side-bar.blade.php` remain untouched as a
rollback/reference copy; the active body now includes the module-specific
partials.

## Granular refinement supersedes earlier grouping

The current active implementation supersedes the original 12-module grouping
and the intermediate 17-module grouping:

- POS is no longer inside Dashboard.
- Catalog is no longer inside Products.
- Homepage Categories is no longer inside Deals; it is under Catalog.
- Banners are no longer inside Deals; they are under Marketing.
- Reviews and support are no longer inside Customers; they are under Support &
  Reviews.
- Third-party tools are no longer inside Settings; they are under Integrations.
- Employees and roles are no longer inside Settings; they are under
  Administration.

## Final 23-module refinement

The active rail order is now:

Dashboard, POS, Orders, Products, Catalog, Deals, Promotions, Sellers,
Customers, Marketing, Notifications, Newsletter, Finance, Payouts, Reports,
Content, Support, Reviews, Integrations, Administration, Website Settings,
Commerce Settings, System Settings.

The additional splits are:

- Deals contains only the Fast X Horizon deal management routes. Coupons,
  featured products and clearance/promotional routes are under Promotions.
- Marketing contains campaign/banner tools. Sending notifications and push
  notifications are under Notifications.
- Finance contains transaction pages. Seller withdrawals and seller earnings
  are under Payouts.
- Support contains inbox, tickets and contact messages. Product reviews are
  under Reviews.
- The former Settings module is replaced by Website Settings, Commerce
  Settings and System Settings.

Canonical examples:

| Page | Canonical module |
| --- | --- |
| Featured Products | Promotions |
| Coupons | Promotions |
| Banners | Marketing |
| Push Notifications | Notifications |
| Withdrawal Requests | Payouts |
| Product Reviews | Reviews |
| Support Tickets | Support |
| SMTP / Social Login / Courier / AI | Integrations |
| Website Setup / Homepage Sections / SEO | Website Settings |
| Shipping / Tax / Product Settings | Commerce Settings |
| Maintenance / Storage / Core system | System Settings |
| Employees / Roles | Administration |

Social Media Links and SEO Settings are canonical Website Settings entries;
they are not repeated under Content. The historical route notes above are
superseded by this final 23-module mapping.
