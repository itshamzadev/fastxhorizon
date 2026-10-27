# FASTXHORIZON Architecture

Phase 1 preserves the selected source applications while establishing three local application boundaries: customer React application (CRA/CRACO in this phase), central Node/Express/Mongoose backend, and independent React/Vite/TypeScript administration frontend. Future frontends will call the central API through environment-configured URLs.

Production domain mapping is documented in `DOMAIN_MAPPING.md`. Authentication, RBAC, TOTP MFA, CSRF controls, session revocation, audit logging, and financial-operation reauthentication remain future implementation work.
