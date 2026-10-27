# Admin Security Notes

- Administrator passwords are bcrypt hashes and are never returned by APIs.
- Sessions are server-side records with hashed tokens, expiry and revocation. Browser storage is not used for administrator session tokens.
- Production requires password plus standards-based TOTP. Recovery codes are bcrypt hashes and are consumed once.
- Local MFA bypass is accepted only when `NODE_ENV=development`, `ADMIN_DEV_SKIP_MFA=true`, the server and client are loopback peers, and `MONGO_URI` is a loopback MongoDB URI.
- Admin routes use server-side role/permission checks; hiding a frontend control is not authorization.
- Product deletion is blocked when an order references the product.
- Query page sizes are bounded and product/customer/order search fields are allowlisted.
- No production database, VPS, DNS, Cloudflare or deployment resource was accessed.

Before production deployment, provision `ADMIN_TOTP_ENCRYPTION_KEY`, disable the development bypass, use HTTPS cookies, review CORS origins and complete CSRF protection and payment audit workflows.
