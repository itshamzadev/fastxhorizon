import Database from 'better-sqlite3';
import fs from 'node:fs';
import path from 'node:path';
import { randomUUID } from 'node:crypto';
import { runMigrations } from './migrations';

export type Dashboard = { products: number; variants: number; onHand: number; available: number; reserved: number; sales: number; revenue: number; stockValue: number };

const schema = `
PRAGMA foreign_keys = ON;
CREATE TABLE IF NOT EXISTS application_settings (key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS brands (id TEXT PRIMARY KEY, name TEXT NOT NULL UNIQUE, country TEXT, description TEXT, active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS categories (id TEXT PRIMARY KEY, parent_id TEXT REFERENCES categories(id), name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS fabric_types (id TEXT PRIMARY KEY, name TEXT NOT NULL UNIQUE, description TEXT, active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS suppliers (id TEXT PRIMARY KEY, name TEXT NOT NULL, company_name TEXT, phone TEXT, email TEXT, address TEXT, notes TEXT, active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS products (id TEXT PRIMARY KEY, sku TEXT NOT NULL UNIQUE, name TEXT NOT NULL, barcode TEXT, category_id TEXT REFERENCES categories(id), brand_id TEXT REFERENCES brands(id), supplier_id TEXT REFERENCES suppliers(id), description TEXT, clothing_type TEXT, fabric_material TEXT, gender TEXT, season TEXT, status TEXT NOT NULL DEFAULT 'active', created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS product_variants (id TEXT PRIMARY KEY, product_id TEXT NOT NULL REFERENCES products(id) ON DELETE CASCADE, sku TEXT NOT NULL UNIQUE, barcode TEXT, size TEXT, color TEXT, quantity_on_hand INTEGER NOT NULL DEFAULT 0 CHECK(quantity_on_hand >= 0), reserved_quantity INTEGER NOT NULL DEFAULT 0 CHECK(reserved_quantity >= 0), damaged_quantity INTEGER NOT NULL DEFAULT 0 CHECK(damaged_quantity >= 0), unit_cost INTEGER NOT NULL DEFAULT 0 CHECK(unit_cost >= 0), selling_price INTEGER NOT NULL DEFAULT 0 CHECK(selling_price >= 0), created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS purchases (id TEXT PRIMARY KEY, supplier_id TEXT REFERENCES suppliers(id), invoice_number TEXT, purchase_date TEXT NOT NULL, shipping_cost INTEGER NOT NULL DEFAULT 0, landed_cost INTEGER NOT NULL DEFAULT 0, payment_status TEXT NOT NULL DEFAULT 'pending', created_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS purchase_items (id TEXT PRIMARY KEY, purchase_id TEXT NOT NULL REFERENCES purchases(id) ON DELETE CASCADE, variant_id TEXT NOT NULL REFERENCES product_variants(id), quantity INTEGER NOT NULL CHECK(quantity > 0), unit_cost INTEGER NOT NULL CHECK(unit_cost >= 0));
CREATE TABLE IF NOT EXISTS sales (id TEXT PRIMARY KEY, invoice_number TEXT NOT NULL UNIQUE, sale_date TEXT NOT NULL, customer_name TEXT, customer_phone TEXT, channel TEXT NOT NULL DEFAULT 'Manual Sale', payment_method TEXT NOT NULL DEFAULT 'Cash', payment_status TEXT NOT NULL DEFAULT 'pending', sale_status TEXT NOT NULL DEFAULT 'placed', delivery_cost INTEGER NOT NULL DEFAULT 0, customer_delivery_fee INTEGER NOT NULL DEFAULT 0, packaging_cost INTEGER NOT NULL DEFAULT 0, payment_fee INTEGER NOT NULL DEFAULT 0, discount INTEGER NOT NULL DEFAULT 0, total INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS sale_items (id TEXT PRIMARY KEY, sale_id TEXT NOT NULL REFERENCES sales(id) ON DELETE CASCADE, variant_id TEXT NOT NULL REFERENCES product_variants(id), quantity INTEGER NOT NULL CHECK(quantity > 0), unit_price INTEGER NOT NULL CHECK(unit_price >= 0), unit_cost INTEGER NOT NULL CHECK(unit_cost >= 0));
CREATE TABLE IF NOT EXISTS deliveries (id TEXT PRIMARY KEY, sale_id TEXT NOT NULL UNIQUE REFERENCES sales(id), courier TEXT, tracking_number TEXT, address TEXT, dispatch_date TEXT, expected_date TEXT, delivered_date TEXT, courier_cost INTEGER NOT NULL DEFAULT 0, customer_fee INTEGER NOT NULL DEFAULT 0, cod_amount INTEGER NOT NULL DEFAULT 0, cod_remitted INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'Not Dispatched');
CREATE TABLE IF NOT EXISTS stock_movements (id TEXT PRIMARY KEY, variant_id TEXT NOT NULL REFERENCES product_variants(id), quantity INTEGER NOT NULL, movement_type TEXT NOT NULL, reference_id TEXT, reason TEXT, operator TEXT, device_id TEXT, created_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS expenses (id TEXT PRIMARY KEY, expense_date TEXT NOT NULL, category TEXT NOT NULL, amount INTEGER NOT NULL CHECK(amount >= 0), description TEXT, reference_id TEXT, created_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS audit_logs (id TEXT PRIMARY KEY, action TEXT NOT NULL, entity_type TEXT NOT NULL, entity_id TEXT, details TEXT, created_at TEXT NOT NULL);
CREATE INDEX IF NOT EXISTS idx_variants_product ON product_variants(product_id);
CREATE INDEX IF NOT EXISTS idx_movements_variant ON stock_movements(variant_id, created_at);
CREATE INDEX IF NOT EXISTS idx_sales_date ON sales(sale_date);
`;

const id = () => randomUUID();
const now = () => new Date().toISOString();

export function openDatabase(dataDir: string) {
  fs.mkdirSync(dataDir, { recursive: true });
  const db = new Database(path.join(dataDir, 'fxh-stock-manager.sqlite'));
  db.pragma('journal_mode = WAL'); db.pragma('foreign_keys = ON'); db.exec(schema);
  for (const statement of [
    'ALTER TABLE products ADD COLUMN short_description TEXT',
    'ALTER TABLE products ADD COLUMN brand_name TEXT',
    'ALTER TABLE products ADD COLUMN model_name TEXT',
    'ALTER TABLE products ADD COLUMN catalog_number INTEGER',
    'ALTER TABLE product_variants ADD COLUMN original_price INTEGER NOT NULL DEFAULT 0',
    'ALTER TABLE product_variants ADD COLUMN image_paths TEXT',
  ]) { try { db.exec(statement); } catch (error) { if (!(error instanceof Error && error.message.includes('duplicate column name'))) throw error; } }
  const unnumbered = db.prepare("SELECT id FROM products WHERE catalog_number IS NULL ORDER BY created_at ASC,id ASC").all() as Array<{ id: string }>;
  if (unnumbered.length) { const next = Number((db.prepare('SELECT coalesce(max(catalog_number),0) value FROM products').get() as { value: number }).value || 0); const assign = db.prepare('UPDATE products SET catalog_number=? WHERE id=?'); const assignNumbers = db.transaction(() => unnumbered.forEach((product, index) => assign.run(next + index + 1, product.id))); assignNumbers(); }
  db.exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_products_catalog_number ON products(catalog_number)');
  db.exec("INSERT OR IGNORE INTO brands(id,name,created_at,updated_at) SELECT lower(hex(randomblob(16))),brand_name,datetime('now'),datetime('now') FROM products WHERE brand_name IS NOT NULL AND trim(brand_name)<>''");
  db.exec("INSERT OR IGNORE INTO fabric_types(id,name,created_at,updated_at) SELECT lower(hex(randomblob(16))),fabric_material,datetime('now'),datetime('now') FROM products WHERE fabric_material IS NOT NULL AND trim(fabric_material)<>''");
  runMigrations(db);
  return db;
}

export function queueSyncEvent(db: Database.Database, input: { eventId?: string; eventType: string; entityType: string; entityId: string; payload: unknown; idempotencyKey?: string }) {
  // Catalog mutations are local-first. They are published only through the
  // Publishing Center, never by the operational inventory queue.
  if (['PRODUCT_CREATE', 'PRODUCT_UPDATE', 'PRODUCT_ARCHIVED'].includes(input.eventType)) return input.eventId || randomUUID();
  const eventId = input.eventId || randomUUID();
  const key = input.idempotencyKey || eventId;
  const timestamp = now();
  db.prepare(`INSERT OR IGNORE INTO sync_events(id,event_id,event_type,entity_type,entity_id,payload,idempotency_key,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,'pending',?,?)`).run(randomUUID(), eventId, input.eventType, input.entityType, input.entityId, JSON.stringify(input.payload), key, timestamp, timestamp);
  return eventId;
}

export function listPendingSyncEvents(db: Database.Database, limit = 100) {
  return db.prepare("SELECT * FROM sync_events WHERE status IN ('pending','failed') AND (next_attempt_at IS NULL OR next_attempt_at <= ?) ORDER BY created_at LIMIT ?").all(now(), Math.min(Math.max(1, limit), 500));
}

export function listSyncConflicts(db: Database.Database, limit = 100) {
  return db.prepare("SELECT * FROM sync_conflicts WHERE status='open' ORDER BY created_at DESC LIMIT ?").all(Math.min(Math.max(1, limit), 500));
}

export function resolveSyncConflict(db: Database.Database, conflictId: string, resolution: 'resolved' | 'retry') {
  const conflict = db.prepare("SELECT * FROM sync_conflicts WHERE id=? AND status='open'").get(conflictId) as { event_id: string; entity_type: string; entity_id: string; payload: string } | undefined;
  if (!conflict) throw new Error('Open synchronization conflict not found.');
  if (resolution === 'retry') {
    const stored = JSON.parse(conflict.payload || '{}');
    const event = { eventId: conflict.event_id, eventType: stored.eventType || '', entityType: conflict.entity_type, entityId: conflict.entity_id, payload: stored.payload || {} };
    const result = applyRemoteSyncEvent(db, event as any);
    if (result.status === 'conflict') throw new Error(result.reason);
  }
  db.prepare("UPDATE sync_conflicts SET status='resolved',resolution=?,resolved_at=? WHERE id=?").run(resolution, now(), conflictId);
  return true;
}

export function markSyncEvents(db: Database.Database, input: { eventIds: string[]; status: 'sent' | 'failed'; error?: string }) {
  const update = db.prepare('UPDATE sync_events SET status=?,attempt_count=attempt_count+1,last_error=?,next_attempt_at=?,updated_at=? WHERE event_id=?');
  db.transaction(() => input.eventIds.forEach(eventId => { const row = db.prepare('SELECT attempt_count FROM sync_events WHERE event_id=?').get(eventId) as { attempt_count: number } | undefined; const delay = input.status === 'failed' ? Math.min(30 * 60 * 1000, 1000 * (2 ** Math.min(Number(row?.attempt_count || 0), 10))) : 0; update.run(input.status, input.error || null, delay ? new Date(Date.now() + delay).toISOString() : null, now(), eventId); }))();
  return true;
}

export function applyRemoteSyncEvent(db: Database.Database, event: { eventId: string; eventType: string; entityType: string; entityId: string; payload: any }) {
  const existing = db.prepare('SELECT status FROM sync_events WHERE event_id=?').get(event.eventId) as { status: string } | undefined;
  if (existing?.status === 'remote_applied') return { status: 'already_applied' };
  const timestamp = now();
  try {
    const result = db.transaction(() => {
      const payload = event.payload || {};
      if (['ONLINE_SALE', 'ORDER_CANCELLED', 'RETURN', 'STOCK_RECEIPT', 'STOCK_ADJUSTMENT'].includes(event.eventType)) {
        let localVariantId = String(payload.localVariantId || payload.variantId || '');
        if (!localVariantId && payload.remoteVariantId) localVariantId = String((db.prepare("SELECT local_entity_id FROM sync_identity_mappings WHERE remote_entity_type='variant' AND remote_entity_id=?").get(String(payload.remoteVariantId)) as { local_entity_id: string } | undefined)?.local_entity_id || '');
        if (!localVariantId && payload.remoteProductId) { const mappedProduct = (db.prepare("SELECT local_entity_id FROM sync_identity_mappings WHERE remote_entity_type='product' AND remote_entity_id=?").get(String(payload.remoteProductId)) as { local_entity_id: string } | undefined)?.local_entity_id; if (mappedProduct) { const variants = db.prepare('SELECT id FROM product_variants WHERE product_id=?').all(mappedProduct) as Array<{ id: string }>; if (variants.length === 1) localVariantId = variants[0].id; } }
        if (!localVariantId) throw new Error('Remote event has no local variant mapping');
        const quantity = Number(payload.quantity);
        if (!Number.isInteger(quantity) || quantity === 0) throw new Error('Remote event quantity is invalid');
        const delta = ['ONLINE_SALE'].includes(event.eventType) ? -Math.abs(quantity) : event.eventType === 'RETURN' || event.eventType === 'ORDER_CANCELLED' || event.eventType === 'STOCK_RECEIPT' ? Math.abs(quantity) : quantity;
        const variant = db.prepare('SELECT quantity_on_hand,reserved_quantity,unit_cost FROM product_variants WHERE id=?').get(localVariantId) as { quantity_on_hand: number; reserved_quantity: number; unit_cost: number } | undefined;
        if (!variant) throw new Error('Local variant mapping was not found');
        const next = variant.quantity_on_hand + delta;
        if (next < 0) throw new Error('Remote event would create negative local stock');
        db.prepare('UPDATE product_variants SET quantity_on_hand=?,updated_at=? WHERE id=?').run(next, timestamp, localVariantId);
        db.prepare('INSERT INTO stock_movements(id,variant_id,quantity,movement_type,reference_id,reason,device_id,created_at) VALUES(?,?,?,?,?,?,?,?)').run(id(), localVariantId, delta, event.eventType, event.entityId, 'Remote marketplace synchronization', 'remote', timestamp);
      } else if (event.eventType === 'PRODUCT_ARCHIVED') {
        const localProductId = String(payload.localProductId || payload.productId || '');
        if (!localProductId) throw new Error('Remote archive has no local product mapping');
        const changed = db.prepare("UPDATE products SET status='archived',updated_at=? WHERE id=?").run(timestamp, localProductId);
        if (!changed.changes) throw new Error('Local product mapping was not found');
      } else {
        throw new Error(`Unsupported remote event type: ${event.eventType}`);
      }
      db.prepare('INSERT OR REPLACE INTO sync_events(id,event_id,event_type,entity_type,entity_id,payload,idempotency_key,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,\'remote_applied\',?,?)').run(id(), event.eventId, event.eventType, event.entityType, event.entityId, JSON.stringify(event.payload || {}), `remote:${event.eventId}`, timestamp, timestamp);
      return { status: 'applied' };
    })();
    return result;
  } catch (error) {
    const reason = error instanceof Error ? error.message : 'Remote event could not be applied';
    db.prepare('INSERT OR REPLACE INTO sync_conflicts(id,event_id,entity_type,entity_id,reason,payload,status,created_at) VALUES(?,?,?,?,?,?,\'open\',?)').run(id(), event.eventId, event.entityType, event.entityId, reason, JSON.stringify({ eventType: event.eventType, payload: event.payload || {} }), timestamp);
    return { status: 'conflict', reason };
  }
}

export function dashboard(db: Database.Database): Dashboard {
  const row = db.prepare(`SELECT (SELECT count(*) FROM products) products, (SELECT count(*) FROM product_variants) variants, (SELECT coalesce(sum(quantity_on_hand),0) FROM product_variants) onHand, (SELECT coalesce(sum(quantity_on_hand-reserved_quantity),0) FROM product_variants) available, (SELECT coalesce(sum(reserved_quantity),0) FROM product_variants) reserved, (SELECT count(*) FROM sales) sales, (SELECT coalesce(sum(total),0) FROM sales WHERE sale_status NOT IN ('cancelled','returned')) revenue, (SELECT coalesce(sum(quantity_on_hand*unit_cost),0) FROM product_variants) stockValue`).get() as Dashboard;
  return row;
}

export function listProducts(db: Database.Database) { return db.prepare(`SELECT p.*, coalesce(sum(v.quantity_on_hand),0) quantity, coalesce(sum(v.quantity_on_hand-v.reserved_quantity),0) available, coalesce(sum(v.quantity_on_hand*v.unit_cost),0) stock_value, (SELECT unit_cost FROM product_variants WHERE product_id=p.id ORDER BY created_at LIMIT 1) unit_cost, (SELECT original_price FROM product_variants WHERE product_id=p.id ORDER BY created_at LIMIT 1) original_price, (SELECT selling_price FROM product_variants WHERE product_id=p.id ORDER BY created_at LIMIT 1) selling_price, (SELECT image_paths FROM product_variants WHERE product_id=p.id AND image_paths IS NOT NULL AND image_paths<>'' AND image_paths<>'[]' ORDER BY created_at LIMIT 1) image_paths FROM products p LEFT JOIN product_variants v ON v.product_id=p.id GROUP BY p.id ORDER BY p.updated_at DESC`).all(); }

export function catalogProductPayload(db: Database.Database, productId: string) {
  const product = db.prepare('SELECT p.*, c.name category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=?').get(productId) as any;
  if (!product) throw new Error('Product not found');
  const variants = db.prepare('SELECT id,sku,color,size,quantity_on_hand,reserved_quantity,damaged_quantity,selling_price,original_price,image_paths FROM product_variants WHERE product_id=? ORDER BY created_at,id').all(productId) as any[];
  return { localProductId: product.id, localRevision: Number(product.local_revision || 1), remoteProductId: product.remote_product_id || null, remoteVersion: product.remote_version || null, sku: product.sku, name: product.name, description: product.description || '', category: product.category_name || '', brandName: product.brand_name || '', fabricMaterial: product.fabric_material || '', clothingType: product.clothing_type || '', status: product.status, images: variants.flatMap(item => parseImagePaths(item.image_paths)), variants: variants.map(item => ({ id: item.id, sku: item.sku, color: item.color || '', size: item.size || '', quantity: Number(item.quantity_on_hand || 0), reservedQuantity: Number(item.reserved_quantity || 0), damagedQuantity: Number(item.damaged_quantity || 0), sellingPrice: Number(item.selling_price || 0), compareAtPrice: Number(item.original_price || 0) || null, images: parseImagePaths(item.image_paths) })) };
}

export function listPendingCatalogProducts(db: Database.Database, limit = 500) {
  const rows = db.prepare("SELECT id,sku,name,status,local_revision,published_revision,publish_state,last_published_at,publish_error,remote_product_id FROM products WHERE publish_state='pending' OR local_revision>published_revision ORDER BY updated_at,id LIMIT ?").all(Math.min(Math.max(1, limit), 2000)) as any[];
  return rows.map(row => ({ ...row, payload: catalogProductPayload(db, row.id) }));
}

export function markCatalogPublication(db: Database.Database, input: { productId: string; localRevision: number; status: 'published' | 'failed'; remoteProductId?: string; remoteVersion?: number; error?: string; action?: string }) {
  const t = now();
  const action = input.action || 'UPSERT';
  const tx = db.transaction(() => {
    const product = db.prepare('SELECT local_revision FROM products WHERE id=?').get(input.productId) as { local_revision: number } | undefined;
    if (!product) throw new Error('Product not found');
    if (input.status === 'published' && Number(product.local_revision) === Number(input.localRevision)) db.prepare("UPDATE products SET published_revision=?,publish_state='published',last_published_at=?,publish_error=NULL,remote_product_id=COALESCE(?,remote_product_id),remote_version=COALESCE(?,remote_version),updated_at=? WHERE id=?").run(input.localRevision,t,input.remoteProductId || null,input.remoteVersion || null,t,input.productId);
    else if (input.status === 'failed') db.prepare("UPDATE products SET publish_state='pending',publish_error=?,updated_at=? WHERE id=?").run(input.error || 'Publication failed',t,input.productId);
    db.prepare('INSERT INTO catalog_publication_history(id,product_id,local_revision,action,status,remote_product_id,error,created_at) VALUES(?,?,?,?,?,?,?,?)').run(id(),input.productId,input.localRevision,action,input.status,input.remoteProductId || null,input.error || null,t);
  });
  tx();
  return true;
}

export function listCatalogPublicationHistory(db: Database.Database, limit = 200) { return db.prepare('SELECT * FROM catalog_publication_history ORDER BY created_at DESC LIMIT ?').all(Math.min(Math.max(1, limit), 1000)); }

export function addProduct(db: Database.Database, input: { sku?: string; modelName?: string; name: string; category?: string; brandName?: string; shortDescription?: string; description?: string; fabricMaterial?: string; clothingType?: string; colors?: Array<{ sku?: string; size?: string; color?: string; quantity?: number; unitCost?: number; sellingPrice?: number; originalPrice?: number; discount?: number; imagePaths?: string[]; images?: string[] }>; size?: string; color?: string; quantity?: number; unitCost?: number; sellingPrice?: number; originalPrice?: number; imagePaths?: string[] }) {
  const t = now(); const category = input.category || 'Uncategorized'; let cat = db.prepare('SELECT id FROM categories WHERE name=?').get(category) as { id: string } | undefined;
  const variants = input.colors?.length ? input.colors : [{ sku: input.sku, size: input.size, color: input.color, quantity: input.quantity, unitCost: input.unitCost, sellingPrice: input.sellingPrice, originalPrice: input.originalPrice, imagePaths: input.imagePaths }];
  const transaction = db.transaction(() => { if (!cat) { const categoryId = id(); db.prepare('INSERT INTO categories(id,name,slug,created_at,updated_at) VALUES(?,?,?,?,?)').run(categoryId, category, category.toLowerCase().replace(/[^a-z0-9]+/g, '-'), t, t); cat = { id: categoryId }; } const productId = id(); const productSku = input.sku || `FXH-${Date.now()}`; const productName = input.name || [input.brandName, input.fabricMaterial].filter(Boolean).join(' ') || input.modelName || productSku; const catalogNumber = Number((db.prepare('SELECT coalesce(max(catalog_number),0)+1 value FROM products').get() as { value: number }).value); db.prepare('INSERT INTO products(id,sku,catalog_number,name,category_id,brand_name,model_name,short_description,description,fabric_material,clothing_type,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)').run(productId,productSku,catalogNumber,productName,cat.id,input.brandName || null,input.modelName || null,input.shortDescription || null,input.description || null,input.fabricMaterial || null,input.clothingType || null,t,t); for (const item of variants) { const variantId=id(); const sku = item.sku || `${productSku}-${String(item.color || 'variant').toUpperCase().replace(/[^A-Z0-9]+/g,'-')}`; const original = item.originalPrice || item.sellingPrice || 0; const selling = Math.max(0, Number(item.sellingPrice || 0) || (original - Number(item.discount || 0))); db.prepare('INSERT INTO product_variants(id,product_id,sku,size,color,quantity_on_hand,unit_cost,selling_price,original_price,image_paths,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)').run(variantId,productId,sku,item.size||null,item.color||null,item.quantity||0,item.unitCost||0,selling,original,JSON.stringify(item.imagePaths || item.images || []),t,t); if (item.quantity) db.prepare('INSERT INTO stock_movements(id,variant_id,quantity,movement_type,reason,created_at) VALUES(?,?,?,?,?,?)').run(id(),variantId,item.quantity,'Purchase Received','Initial product entry',t); } return productId; }); const productId = transaction(); const product = db.prepare('SELECT p.*, c.name category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=?').get(productId) as any; const syncVariants = db.prepare('SELECT id,sku,color,size,quantity_on_hand,reserved_quantity,damaged_quantity,unit_cost,selling_price,original_price,image_paths FROM product_variants WHERE product_id=?').all(productId); queueSyncEvent(db, { eventType: 'PRODUCT_CREATE', entityType: 'product', entityId: productId, payload: { localProductId: productId, localSku: product.sku, name: product.name, modelName: product.model_name || '', category: product.category_name || category, brandName: product.brand_name || '', fabricMaterial: product.fabric_material || '', clothingType: product.clothing_type || '', description: product.description || '', status: product.status, variants: syncVariants } }); return productId;
}

export function updateProduct(db: Database.Database, input: { id: string; brandName?: string; fabricMaterial?: string; description?: string; originalPrice?: number; sellingPrice?: number; unitCost?: number }) {
  const t = now();
  const tx = db.transaction(() => {
    const product = db.prepare('SELECT id FROM products WHERE id=?').get(input.id);
    if (!product) throw new Error('Product not found');
    const name = [input.brandName, input.fabricMaterial].filter(Boolean).join(' ') || undefined;
    db.prepare("UPDATE products SET name=COALESCE(?,name),brand_name=?,fabric_material=?,description=?,local_revision=local_revision+1,publish_state='pending',publish_error=NULL,updated_at=? WHERE id=?").run(name || null, input.brandName || null, input.fabricMaterial || null, input.description || null, t, input.id);
    const variant = db.prepare('SELECT id FROM product_variants WHERE product_id=? ORDER BY created_at LIMIT 1').get(input.id) as { id: string } | undefined;
    if (variant) db.prepare('UPDATE product_variants SET unit_cost=?,original_price=?,selling_price=?,updated_at=? WHERE id=?').run(Math.max(0, Number(input.unitCost || 0)), Math.max(0, Number(input.originalPrice || input.sellingPrice || 0)), Math.max(0, Number(input.sellingPrice || 0)), t, variant.id);
  });
  tx();
  const product = db.prepare('SELECT p.*, c.name category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=?').get(input.id) as any;
  const variants = db.prepare('SELECT id,sku,color,size,quantity_on_hand,reserved_quantity,damaged_quantity,selling_price,original_price,image_paths FROM product_variants WHERE product_id=?').all(input.id);
  queueSyncEvent(db, { eventType: 'PRODUCT_UPDATE', entityType: 'product', entityId: input.id, payload: { localProductId: input.id, localSku: product.sku, name: product.name, modelName: product.model_name || '', category: product.category_name || '', brandName: product.brand_name || '', fabricMaterial: product.fabric_material || '', clothingType: product.clothing_type || '', description: product.description || '', status: product.status, variants } });
  return true;
}

export function archiveProduct(db: Database.Database, productId: string) {
  const result = db.prepare("UPDATE products SET status='archived',local_revision=local_revision+1,publish_state='pending',publish_error=NULL,updated_at=? WHERE id=?").run(now(), productId);
  if (!result.changes) throw new Error('Product not found');
  db.prepare('INSERT INTO audit_logs(id,action,entity_type,entity_id,details,created_at) VALUES(?,?,?,?,?,?)').run(id(), 'ARCHIVE', 'product', productId, JSON.stringify({ reason: 'User requested product removal; history preserved' }), now());
  queueSyncEvent(db, { eventType: 'PRODUCT_ARCHIVED', entityType: 'product', entityId: productId, payload: { localProductId: productId } });
  return true;
}

export function deleteProduct(db: Database.Database, productId: string, mediaRoot?: string) {
  // Compatibility entry point: business history must never be physically deleted.
  return archiveProduct(db, productId);
  /* legacy physical-delete implementation intentionally unreachable
  const imageReferences = db.prepare('SELECT image_paths FROM product_variants WHERE product_id=?').all(productId) as Array<{ image_paths?: string | null }>;
  const variantIds = (db.prepare('SELECT id FROM product_variants WHERE product_id=?').all(productId) as Array<{ id: string }>).map(row => row.id);
  if (!variantIds.length) throw new Error('Product not found');
  const tx = db.transaction(() => {
    const placeholders = variantIds.map(() => '?').join(',');
    const saleIds = (db.prepare(`SELECT DISTINCT sale_id FROM sale_items WHERE variant_id IN (${placeholders})`).all(...variantIds) as Array<{ sale_id: string }>).map(row => row.sale_id);
    const purchaseIds = (db.prepare(`SELECT DISTINCT purchase_id FROM purchase_items WHERE variant_id IN (${placeholders})`).all(...variantIds) as Array<{ purchase_id: string }>).map(row => row.purchase_id);
    db.prepare(`DELETE FROM audit_logs WHERE entity_id=? OR entity_id IN (${placeholders})`).run(productId, ...variantIds);
    db.prepare(`DELETE FROM stock_movements WHERE variant_id IN (${placeholders})`).run(...variantIds);
    // A full delete removes the complete local transaction records linked to this product.
    // This avoids leaving an order/purchase header whose totals or history still identify it.
    for (const saleId of saleIds) db.prepare('DELETE FROM deliveries WHERE sale_id=?').run(saleId);
    for (const saleId of saleIds) db.prepare('DELETE FROM sale_items WHERE sale_id=?').run(saleId);
    for (const saleId of saleIds) db.prepare('DELETE FROM sales WHERE id=?').run(saleId);
    for (const purchaseId of purchaseIds) db.prepare('DELETE FROM purchase_items WHERE purchase_id=?').run(purchaseId);
    for (const purchaseId of purchaseIds) db.prepare('DELETE FROM purchases WHERE id=?').run(purchaseId);
    db.prepare(`DELETE FROM product_variants WHERE id IN (${placeholders})`).run(...variantIds);
    db.prepare('DELETE FROM products WHERE id=?').run(productId);
  });
  tx();
  if (mediaRoot) {
    const root = path.resolve(mediaRoot);
    for (const reference of imageReferences.flatMap(item => parseImagePaths(item.image_paths))) {
      const candidate = path.resolve(root, String(reference).replace(/^[/\\]+/, ''));
      if (candidate.startsWith(`${root}${path.sep}`) && fs.existsSync(candidate)) {
        const stillUsed = db.prepare('SELECT count(*) count FROM product_variants WHERE image_paths LIKE ?').get(`%${String(reference)}%`) as { count: number };
        if (!stillUsed.count) fs.rmSync(candidate, { force: true });
      }
    }
  }
  return true;
  */
}

export function listCategories(db: Database.Database) { return db.prepare('SELECT id,parent_id,name,slug,active FROM categories ORDER BY parent_id IS NOT NULL,name').all(); }
export function listCategoryManagement(db: Database.Database) { return db.prepare('SELECT id,parent_id,name,slug,active FROM categories ORDER BY parent_id IS NOT NULL,name').all(); }
export function listBrands(db: Database.Database) { return db.prepare('SELECT id,name,description,active FROM brands ORDER BY name').all(); }
export function listFabricTypes(db: Database.Database) { return db.prepare('SELECT id,name,description,active FROM fabric_types ORDER BY name').all(); }
export function createManagedEntry(db: Database.Database, input: { type: 'category'|'brand'|'fabric'; name: string; parentId?: string; description?: string }) { const name=input.name.trim(); if (!name) throw new Error('Name is required'); const t=now(); if(input.type==='category'){const slug=name.toLowerCase().replace(/[^a-z0-9]+/g,'-');return db.prepare('INSERT INTO categories(id,parent_id,name,slug,active,created_at,updated_at) VALUES(?,?,?,?,1,?,?)').run(id(),input.parentId||null,name,slug,t,t).changes;} if(input.type==='brand') return db.prepare('INSERT INTO brands(id,name,description,active,created_at,updated_at) VALUES(?,?,?,1,?,?)').run(id(),name,input.description||null,t,t).changes; return db.prepare('INSERT INTO fabric_types(id,name,description,active,created_at,updated_at) VALUES(?,?,?,1,?,?)').run(id(),name,input.description||null,t,t).changes; }
export function updateManagedEntry(db: Database.Database, input: { type: 'category'|'brand'|'fabric'; id: string; name: string; parentId?: string; description?: string }) { const name=input.name.trim(); if(!name) throw new Error('Name is required'); const t=now(); if(input.type==='category'){const slug=name.toLowerCase().replace(/[^a-z0-9]+/g,'-'); db.prepare('UPDATE categories SET name=?,slug=?,parent_id=?,updated_at=? WHERE id=?').run(name,slug,input.parentId||null,t,input.id);} else {const table=input.type==='brand'?'brands':'fabric_types'; db.prepare(`UPDATE ${table} SET name=?,description=?,updated_at=? WHERE id=?`).run(name,input.description||null,t,input.id);} return true; }
export function archiveManagedEntry(db: Database.Database, type: 'category'|'brand'|'fabric', entryId: string) { const table=type==='category'?'categories':type==='brand'?'brands':'fabric_types'; const used=type==='category'?db.prepare('SELECT count(*) count FROM products WHERE category_id=?').get(entryId) as {count:number}:type==='brand'?db.prepare('SELECT count(*) count FROM products WHERE brand_id=?').get(entryId) as {count:number}:{count:0}; if(used.count>0) throw new Error('This entry is used by products; deactivate it instead of deleting.'); db.prepare(`UPDATE ${table} SET active=0,updated_at=? WHERE id=?`).run(now(),entryId); return true; }
export function listSuppliers(db: Database.Database) { return db.prepare('SELECT id,name,company_name,phone,email,active FROM suppliers ORDER BY name').all(); }
export function listVariants(db: Database.Database) { return db.prepare(`SELECT v.id,v.product_id,v.sku,v.size,v.color,v.quantity_on_hand,v.reserved_quantity,v.unit_cost,v.selling_price,v.original_price,v.image_paths,p.name product_name FROM product_variants v JOIN products p ON p.id=v.product_id ORDER BY p.name,v.size,v.color`).all(); }
export function listSales(db: Database.Database) { return db.prepare(`SELECT s.*, count(si.id) item_lines FROM sales s LEFT JOIN sale_items si ON si.sale_id=s.id GROUP BY s.id ORDER BY s.created_at DESC LIMIT 200`).all(); }

export function receiveStock(db: Database.Database, input: { variantId: string; quantity: number; unitCost: number; reason?: string }) {
  if (!Number.isInteger(input.quantity) || input.quantity <= 0) throw new Error('Quantity must be a positive whole number');
  if (!Number.isFinite(input.unitCost) || input.unitCost < 0) throw new Error('Unit cost is invalid');
  const t = now(); const tx = db.transaction(() => {
    const variant = db.prepare('SELECT id,product_id FROM product_variants WHERE id=?').get(input.variantId) as { id: string; product_id: string } | undefined;
    if (!variant) throw new Error('Variant not found');
    db.prepare('UPDATE product_variants SET quantity_on_hand=quantity_on_hand+?,unit_cost=?,updated_at=? WHERE id=?').run(input.quantity,input.unitCost,t,input.variantId);
    db.prepare('INSERT INTO stock_movements(id,variant_id,quantity,movement_type,reason,operator,created_at) VALUES(?,?,?,?,?,?,?)').run(id(),input.variantId,input.quantity,'Purchase Received',input.reason || 'Stock received','Local operator',t);
  }); tx();
  queueSyncEvent(db, { eventType: 'STOCK_RECEIPT', entityType: 'variant', entityId: input.variantId, payload: { localVariantId: input.variantId, localProductId: (db.prepare('SELECT product_id FROM product_variants WHERE id=?').get(input.variantId) as { product_id: string }).product_id, quantity: input.quantity, unitCost: input.unitCost, reason: input.reason || '' } });
  return true;
}

export function createSale(db: Database.Database, input: { variantId: string; quantity: number; customerName?: string; customerPhone?: string; paymentMethod?: string; unitPrice?: number; deliveryCost?: number; customerDeliveryFee?: number; discount?: number }) {
  if (!Number.isInteger(input.quantity) || input.quantity <= 0) throw new Error('Quantity must be a positive whole number');
  const t = now(); const invoice = `FXH-SALE-${Date.now().toString().slice(-8)}`;
  const tx = db.transaction(() => {
    const variant = db.prepare('SELECT quantity_on_hand,reserved_quantity,unit_cost,selling_price FROM product_variants WHERE id=?').get(input.variantId) as { quantity_on_hand:number; reserved_quantity:number; unit_cost:number; selling_price:number } | undefined;
    if (!variant) throw new Error('Variant not found');
    const available = variant.quantity_on_hand - variant.reserved_quantity;
    if (available < input.quantity) throw new Error(`Only ${available} units are available`);
    const price = Number.isFinite(input.unitPrice) && (input.unitPrice as number) >= 0 ? input.unitPrice as number : variant.selling_price;
    const delivery = input.deliveryCost || 0; const customerFee = input.customerDeliveryFee || 0; const discount = input.discount || 0;
    const total = Math.max(0, price * input.quantity + customerFee - discount);
    const saleId = id();
    db.prepare('INSERT INTO sales(id,invoice_number,sale_date,customer_name,customer_phone,payment_method,payment_status,sale_status,delivery_cost,customer_delivery_fee,discount,total,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)').run(saleId,invoice,t,input.customerName || null,input.customerPhone || null,input.paymentMethod || 'Cash','paid','completed',delivery,customerFee,discount,total,t);
    db.prepare('INSERT INTO sale_items(id,sale_id,variant_id,quantity,unit_price,unit_cost) VALUES(?,?,?,?,?,?)').run(id(),saleId,input.variantId,input.quantity,price,variant.unit_cost);
    db.prepare('UPDATE product_variants SET quantity_on_hand=quantity_on_hand-?,updated_at=? WHERE id=?').run(input.quantity,t,input.variantId);
    db.prepare('INSERT INTO stock_movements(id,variant_id,quantity,movement_type,reference_id,reason,created_at) VALUES(?,?,?,?,?,?,?)').run(id(),input.variantId,-input.quantity,saleId,'Sale',invoice,t);
    return invoice;
  });
  const invoiceNumber = tx();
  const localProductId = (db.prepare('SELECT product_id FROM product_variants WHERE id=?').get(input.variantId) as { product_id: string }).product_id;
  queueSyncEvent(db, { eventId: `offline-sale:${invoiceNumber}`, idempotencyKey: `offline-sale:${invoiceNumber}`, eventType: 'OFFLINE_SALE', entityType: 'sale', entityId: invoiceNumber, payload: { localVariantId: input.variantId, localProductId, quantity: input.quantity, reason: 'Offline sale' } });
  return invoiceNumber;
}

export function addSupplier(db: Database.Database, input: { name: string; companyName?: string; phone?: string; email?: string; address?: string }) {
  const t = now(); const supplierId = id(); db.prepare('INSERT INTO suppliers(id,name,company_name,phone,email,address,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)').run(supplierId,input.name,input.companyName || null,input.phone || null,input.email || null,input.address || null,t,t); return supplierId;
}

export function financialReport(db: Database.Database) {
  return db.prepare(`SELECT
    (SELECT coalesce(sum(total),0) FROM sales WHERE sale_status NOT IN ('cancelled','returned')) revenue,
    (SELECT coalesce(sum(si.quantity*si.unit_cost),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.sale_status NOT IN ('cancelled','returned')) cogs,
    (SELECT coalesce(sum(amount),0) FROM expenses) expenses,
    (SELECT coalesce(sum(quantity_on_hand*unit_cost),0) FROM product_variants) inventory_value,
    (SELECT count(*) FROM stock_movements WHERE movement_type='Sale') stock_out_events,
    (SELECT coalesce(sum(quantity),0) FROM stock_movements WHERE movement_type='Purchase Received') received_units,
    (SELECT coalesce(sum(-quantity),0) FROM stock_movements WHERE movement_type='Sale') sold_units`).get() as Record<string, number>;
}

export function exportCatalog(db: Database.Database, range?: { from?: number; to?: number }) {
  const categories = listCategories(db) as any[];
  const brands = listBrands(db) as any[];
  const fabricTypes = listFabricTypes(db) as any[];
  const brandByName = new Map(brands.map(brand => [String(brand.name).trim().toLowerCase(), brand]));
  const categoryById = new Map(categories.map(category => [category.id, category]));
  const usedSlugs = new Set<string>();
  const allVariants = listVariants(db) as any[];
  const products = (listProducts(db) as any[]).filter(product => (range?.from === undefined || Number(product.catalog_number) >= range.from) && (range?.to === undefined || Number(product.catalog_number) <= range.to)).map(({ stock_value, unit_cost, ...product }) => {
    const brand = product.brand_id ? brands.find(item => item.id === product.brand_id) : brandByName.get(String(product.brand_name || '').trim().toLowerCase());
    const productVariants = allVariants.filter(variant => variant.product_id === product.id);
    const images = productVariants.flatMap(variant => parseImagePaths(variant.image_paths));
    const publicSku = websiteIdentifier('P', product.id);
    const category = product.category_id ? categoryById.get(product.category_id) : undefined;
    const baseSlug = slugify(product.name) || `product-${shortIdentifier(product.id)}`;
    let slug = baseSlug;
    if (usedSlugs.has(slug)) slug = `${baseSlug}-${shortIdentifier(product.id)}`;
    let suffix = 2;
    while (usedSlugs.has(slug)) slug = `${baseSlug}-${shortIdentifier(product.id)}-${suffix++}`;
    usedSlugs.add(slug);
    const originalPrice = Number(product.original_price || product.selling_price || 0);
    const sellingPrice = Number(product.selling_price || 0);
    const discountAmount = Math.max(0, originalPrice - sellingPrice);
    return {
      id: publicSku,
      importId: publicSku,
      sourceProductId: product.id,
      catalogNumber: Number(product.catalog_number),
      supplierKey: `FXH_STOCK_MANAGER:${product.id}`,
      sku: publicSku,
      name: product.name,
      slug,
      description: product.description || '',
      fabricMaterial: product.fabric_material || '',
      specifications: product.fabric_material ? [{ key: 'fabric', label: 'Fabric', value: product.fabric_material, type: 'text', public: true, order: 0 }] : [],
      brandId: brand?.id || null,
      brandName: brand?.name || product.brand_name || '',
      brand: brand?.name || product.brand_name || '',
      categoryId: product.category_id || null,
      categorySlug: category?.slug || null,
      categoryName: category?.name || null,
      currency: 'PKR',
      sellingPrice,
      originalPrice,
      discountAmount,
      discountPercentage: originalPrice > 0 ? Math.round((discountAmount / originalPrice) * 10000) / 100 : 0,
      quantity: productVariants.reduce((sum, variant) => sum + Math.max(0, Number(variant.quantity_on_hand || 0) - Number(variant.reserved_quantity || 0)), 0),
      inventoryMode: 'NUMERIC',
      availabilityStatus: productVariants.some(variant => Number(variant.quantity_on_hand || 0) - Number(variant.reserved_quantity || 0) > 0) ? 'in_stock' : 'out_of_stock',
      status: 'draft',
      images,
    };
  });
  const selectedProductIds = new Set(products.map(product => product.sourceProductId));
  const variants = allVariants.filter(variant => selectedProductIds.has(variant.product_id)).map(({ unit_cost, quantity_on_hand, reserved_quantity, image_paths, sku: _internalSku, ...variant }) => ({
    id: websiteIdentifier('V', variant.id),
    importId: websiteIdentifier('V', variant.id),
    sourceVariantId: variant.id,
    product_id: websiteIdentifier('P', variant.product_id),
    productImportId: websiteIdentifier('P', variant.product_id),
    sku: websiteIdentifier('V', variant.id),
    size: variant.size,
    color: variant.color,
    price: Number(variant.selling_price || 0),
    compareAtPrice: Number(variant.original_price || variant.selling_price || 0),
    discountAmount: Math.max(0, Number(variant.original_price || variant.selling_price || 0) - Number(variant.selling_price || 0)),
    discountPercentage: Number(variant.original_price || variant.selling_price || 0) > 0 ? Math.round((Math.max(0, Number(variant.original_price || variant.selling_price || 0) - Number(variant.selling_price || 0)) / Number(variant.original_price || variant.selling_price || 0)) * 10000) / 100 : 0,
    available_quantity: Math.max(0, Number(quantity_on_hand || 0) - Number(reserved_quantity || 0)),
    images: parseImagePaths(image_paths),
  }));
  return { exportedAt: now(), formatVersion: 3, source: 'FXH_STOCK_MANAGER', currency: 'PKR', categories, brands, fabricTypes, products, variants };
}

function slugify(value: string) { return value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); }
function shortIdentifier(value: string) { return String(value).replace(/[^a-z0-9]/gi, '').toLowerCase() || 'item'; }
function websiteIdentifier(prefix: string, value: string) { return `FXH-WEB-${prefix}-${shortIdentifier(value).toUpperCase()}`; }
export function parseImagePaths(value: unknown): string[] { if (Array.isArray(value)) return value.filter(item => typeof item === 'string' && item.trim()); if (typeof value !== 'string' || !value.trim()) return []; try { const parsed = JSON.parse(value); return Array.isArray(parsed) ? parsed.filter(item => typeof item === 'string' && item.trim()) : []; } catch { return []; } }
