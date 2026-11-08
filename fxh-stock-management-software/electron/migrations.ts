import Database from 'better-sqlite3';

type Migration = { version: number; name: string; up: (db: Database.Database) => void };

const migrations: Migration[] = [
  {
    version: 1,
    name: 'sync-foundation',
    up: db => {
      db.exec(`
        CREATE TABLE IF NOT EXISTS sync_devices (
          id TEXT PRIMARY KEY, device_name TEXT NOT NULL, device_id TEXT NOT NULL UNIQUE,
          status TEXT NOT NULL DEFAULT 'active', last_sync_at TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS sync_identity_mappings (
          id TEXT PRIMARY KEY, local_entity_type TEXT NOT NULL, local_entity_id TEXT NOT NULL,
          remote_entity_type TEXT NOT NULL, remote_entity_id TEXT NOT NULL, remote_version INTEGER,
          sync_state TEXT NOT NULL DEFAULT 'pending', created_at TEXT NOT NULL, updated_at TEXT NOT NULL,
          UNIQUE(local_entity_type, local_entity_id), UNIQUE(remote_entity_type, remote_entity_id)
        );
        CREATE TABLE IF NOT EXISTS sync_events (
          id TEXT PRIMARY KEY, event_id TEXT NOT NULL UNIQUE, event_type TEXT NOT NULL, entity_type TEXT NOT NULL,
          entity_id TEXT NOT NULL, payload TEXT NOT NULL, idempotency_key TEXT NOT NULL UNIQUE,
          status TEXT NOT NULL DEFAULT 'pending', attempt_count INTEGER NOT NULL DEFAULT 0, last_error TEXT,
          created_at TEXT NOT NULL, updated_at TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_sync_events_status ON sync_events(status, created_at);
        CREATE INDEX IF NOT EXISTS idx_sync_mappings_local ON sync_identity_mappings(local_entity_type, local_entity_id);
      `);
    },
  },
  {
    version: 2,
    name: 'sync-conflicts',
    up: db => {
      db.exec(`
        CREATE TABLE IF NOT EXISTS sync_conflicts (
          id TEXT PRIMARY KEY, event_id TEXT NOT NULL UNIQUE, entity_type TEXT NOT NULL,
          entity_id TEXT NOT NULL, reason TEXT NOT NULL, payload TEXT NOT NULL,
          status TEXT NOT NULL DEFAULT 'open', resolution TEXT, created_at TEXT NOT NULL, resolved_at TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_sync_conflicts_status ON sync_conflicts(status, created_at);
      `);
    },
  },
  {
    version: 3,
    name: 'sync-retry-backoff',
    up: db => {
      try { db.exec('ALTER TABLE sync_events ADD COLUMN next_attempt_at TEXT'); } catch (error) { if (!(error instanceof Error && error.message.includes('duplicate column name'))) throw error; }
      db.exec('CREATE INDEX IF NOT EXISTS idx_sync_events_retry ON sync_events(status, next_attempt_at, created_at)');
    },
  },
  {
    version: 4,
    name: 'local-first-catalog-publishing',
    up: db => {
      for (const statement of [
        "ALTER TABLE products ADD COLUMN local_revision INTEGER NOT NULL DEFAULT 1",
        "ALTER TABLE products ADD COLUMN published_revision INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE products ADD COLUMN publish_state TEXT NOT NULL DEFAULT 'pending'",
        "ALTER TABLE products ADD COLUMN last_published_at TEXT",
        "ALTER TABLE products ADD COLUMN publish_error TEXT",
        "ALTER TABLE products ADD COLUMN remote_product_id TEXT",
        "ALTER TABLE products ADD COLUMN remote_version INTEGER",
      ]) { try { db.exec(statement); } catch (error) { if (!(error instanceof Error && error.message.includes('duplicate column name'))) throw error; } }
      db.exec("UPDATE products SET published_revision=local_revision, publish_state='published' WHERE published_revision=0 AND local_revision=1");
      db.exec(`
        CREATE TABLE IF NOT EXISTS catalog_publication_history (
          id TEXT PRIMARY KEY, product_id TEXT NOT NULL, local_revision INTEGER NOT NULL,
          action TEXT NOT NULL, status TEXT NOT NULL, remote_product_id TEXT,
          error TEXT, created_at TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_catalog_publish_state ON products(publish_state, local_revision, published_revision);
        CREATE INDEX IF NOT EXISTS idx_catalog_publish_history_product ON catalog_publication_history(product_id, created_at);
      `);
    },
  },
];

export function runMigrations(db: Database.Database) {
  db.exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY, name TEXT NOT NULL, applied_at TEXT NOT NULL)');
  const applied = new Set((db.prepare('SELECT version FROM schema_migrations').all() as Array<{ version: number }>).map(row => row.version));
  const insert = db.prepare('INSERT INTO schema_migrations(version,name,applied_at) VALUES(?,?,?)');
  for (const migration of migrations) {
    if (applied.has(migration.version)) continue;
    db.transaction(() => { migration.up(db); insert.run(migration.version, migration.name, new Date().toISOString()); })();
  }
}
