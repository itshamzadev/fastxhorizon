# FASTXHORIZON MERN Marketplace

Phase 1 workspace for the FASTXHORIZON customer website, central Express backend, and independently hosted admin frontend.

## Applications

- `frontend/` — Fusion Electronics customer application; original frontend tooling is preserved in Phase 1.
- `backend/` — Fusion Electronics Node/Express backend.
- `admin/` — Vital Admin React/Vite frontend.
- `others/original-repos/` — clean source snapshots and attribution.

Install and run from the workspace root with `npm run install:user`, `npm run install:admin`, `npm run install:backend`, then the corresponding `dev:*` scripts. The customer path is intentionally quoted because it contains a space.

This local Phase 1 workspace is isolated from production. No DNS, live domains, VPS, or production databases are modified.

See [FASTXHORIZON_MERN_PROGRESS.md](FASTXHORIZON_MERN_PROGRESS.md) and `others/docs/` for the audit and roadmap.
