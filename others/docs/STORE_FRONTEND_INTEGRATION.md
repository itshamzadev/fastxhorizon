# Storefront Integration Status

The Admin and Fusion storefront use the same Express backend and Product collection. Admin-created and updated products therefore persist in the same MongoDB source used by `/api/products`.

The customer storefront was not redesigned or migrated. Its existing React configuration remains unchanged. Public storefront filtering for archived/draft product visibility, CMS content, categories/brands, coupons and payment workflows remain follow-up work and must be implemented with matching backend contracts before being marked complete.
