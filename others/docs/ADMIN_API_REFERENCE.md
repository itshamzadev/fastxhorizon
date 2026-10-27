# FastXHorizon Admin API Reference

All endpoints below are mounted at `/api` and require the `fxh_admin_session` HttpOnly session cookie unless stated otherwise. The Admin Vite development proxy forwards `/api` to the local Express server.

## Authentication

- `POST /admin/auth/login` — password verification; returns a local authenticated session only when the explicitly gated development bypass is valid, otherwise creates a pre-MFA challenge.
- `GET /admin/auth/mfa/enroll` — creates an encrypted pending TOTP secret and recovery codes for a pre-MFA session.
- `POST /admin/auth/mfa/verify` — completes TOTP or one-time recovery-code verification.
- `GET /admin/auth/me` — returns the current administrator.
- `POST /admin/auth/logout` — revokes the current server-side session.

## Live Admin resources

- `GET /admin/dashboard/overview` — MongoDB customer, product, order and revenue aggregates.
- `GET /admin/products` — bounded, paginated product search/filter/sort.
- `GET /admin/products/:id` — product detail.
- `POST /admin/products` — creates a product after server validation.
- `PATCH /admin/products/:id` — updates product data, inventory, visibility status or metadata.
- `DELETE /admin/products/:id` — deletes only products not referenced by an order.
- `GET /admin/customers` and `GET /admin/customers/:id` — customer list/detail without password fields.
- `PATCH /admin/customers/:id` — updates safe customer fields and suspension status.
- `GET /admin/orders` and `GET /admin/orders/:id` — order list/detail.
- `PATCH /admin/orders/:id` — validates supported order state transitions.

All mutating routes write an administrator audit event. `super_admin` bypasses named permissions; regular administrators must receive explicit permissions in a future administrator-management workflow.
