# FASTXHORIZON Stock Manager

Offline-first Windows desktop inventory and physical sales management for FASTXHORIZON (PRIVATE) LIMITED. SQLite is authoritative; the application never connects directly to MongoDB or production.

## Current foundation

- Electron with `contextIsolation: true`, `nodeIntegration: false` and a narrow preload API.
- SQLite WAL database in the Windows application-data directory.
- Products, variants, categories, brands, suppliers, purchases, sales, deliveries, stock movements, expenses and audit-log tables with foreign keys and indexes.
- Transactional product creation with stock movement recording.
- Premium operations dashboard with stock valuation, available/reserved quantities and revenue cards.
- Product list and first-entry workflow.
- Backup database export through a controlled IPC handler.

## Development

```powershell
npm install
npm run typecheck
npm run dev
```

Money is stored as integer PKR amounts. No example inventory is seeded; the 145 clothing pieces must be entered manually or through a future validated import wizard. Website exports will intentionally exclude supplier costs, margins and private customer data.

## Windows package

```powershell
npm run build
npm run dist
```

Installer output is written to `release/`. Application data is kept separately by Electron and is not silently deleted during uninstall. Before financial use, configure the remaining import/export, returns, weighted-average COGS and sync modules described in the master specification.

## Safety

Never put production credentials in this project. Backups must be stored securely. All future financial mutations must use SQLite transactions and append immutable stock movements.
