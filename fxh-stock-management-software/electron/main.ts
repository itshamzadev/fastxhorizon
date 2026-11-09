import { app, BrowserWindow, ipcMain, dialog, shell, safeStorage } from 'electron';
import path from 'node:path';
import fs from 'node:fs';
import archiverModule = require('archiver');
const archiver = (_format: string, options?: Record<string, unknown>) => new archiverModule.ZipArchive(options);
import { randomUUID } from 'node:crypto';
import { openDatabase, dashboard, listProducts, addProduct, updateProduct, archiveProduct, listVariants, listCategories, listCategoryManagement, listBrands, listFabricTypes, createManagedEntry, updateManagedEntry, archiveManagedEntry, listSuppliers, listSales, financialReport, receiveStock, createSale, addSupplier, exportCatalog, parseImagePaths, queueSyncEvent, listPendingSyncEvents, listPendingCatalogProducts, listCatalogPublicationHistory, markCatalogPublication, listSyncConflicts, resolveSyncConflict, markSyncEvents, retryFailedSyncEvents, importExistingCatalogProducts } from './database';
import { createWebsiteExport } from './websiteExport';
import { parseIpc, saleSchema, stockReceiveSchema, productIdSchema, syncRegistrationSchema, syncConfigSchema, syncTestSchema, syncEventSchema } from './validation';
import { runSynchronization } from './syncService';
import { publishCatalogWithMedia } from './catalogPublisher';

let win: BrowserWindow; let db: ReturnType<typeof openDatabase>;
async function publishCatalog(dbHandle: ReturnType<typeof openDatabase>, selectedIds?: string[]) {
  if (!safeStorage.isEncryptionAvailable()) throw new Error('Windows secure credential storage is unavailable.');
  const settings = Object.fromEntries((dbHandle.prepare("SELECT key,value FROM application_settings WHERE key LIKE 'sync.%'").all() as Array<{ key: string; value: string }>).map(row => [row.key, row.value]));
  if (!settings['sync.apiBaseUrl'] || !settings['sync.deviceId'] || !settings['sync.token']) throw new Error('Register this device before publishing catalog changes.');
  const endpoint = settings['sync.apiBaseUrl'].replace(/\/$/, ''); const token = safeStorage.decryptString(Buffer.from(settings['sync.token'], 'base64')); const headers = { authorization: `Bearer ${token}`, 'x-fxh-device-id': settings['sync.deviceId'] };
  const pending = listPendingCatalogProducts(dbHandle, 2000).filter(item => !selectedIds?.length || selectedIds.includes(item.id)); const results: any[] = [];
  for (const item of pending) {
    try {
      const response = await fetch(`${endpoint}/stock-manager/catalog/upsert`, { method: 'POST', headers: { ...headers, 'content-type': 'application/json', 'x-idempotency-key': `catalog:${item.id}:${item.local_revision}` }, body: JSON.stringify({ product: item.payload }) });
      const body = await response.json() as any;
      if (!response.ok) throw new Error(body.message || 'Catalog publication failed.');
      const data = body.data; markCatalogPublication(dbHandle, { productId: item.id, localRevision: item.local_revision, status: 'published', remoteProductId: data.remoteProductId, remoteVersion: data.remoteVersion }); results.push({ id: item.id, status: 'published', remoteProductId: data.remoteProductId });
    } catch (error) { const message = error instanceof Error ? error.message : 'Catalog publication failed.'; markCatalogPublication(dbHandle, { productId: item.id, localRevision: item.local_revision, status: 'failed', error: message }); results.push({ id: item.id, status: 'failed', error: message }); }
  }
  return results;
}
function createWindow() { win = new BrowserWindow({ width: 1440, height: 900, minWidth: 1100, minHeight: 700, backgroundColor: '#101114', webPreferences: { preload: path.join(__dirname, 'preload.js'), contextIsolation: true, nodeIntegration: false, sandbox: true } }); if (process.env.VITE_DEV_SERVER_URL) win.loadURL(process.env.VITE_DEV_SERVER_URL); else win.loadFile(path.join(app.getAppPath(), 'dist/index.html')); }
function registerIpc() {
  ipcMain.handle('dashboard:get', () => dashboard(db));
  ipcMain.handle('products:list', () => listProducts(db));
  ipcMain.handle('products:create', (_event, input) => addProduct(db, input));
  ipcMain.handle('products:update', (_event, input) => updateProduct(db, input));
  ipcMain.handle('products:archive', (_event, productId) => archiveProduct(db, parseIpc(productIdSchema, productId)));
  ipcMain.handle('products:delete', (_event, productId) => archiveProduct(db, parseIpc(productIdSchema, productId)));
  ipcMain.handle('categories:management', () => listCategoryManagement(db));
  ipcMain.handle('brands:list', () => listBrands(db));
  ipcMain.handle('fabric-types:list', () => listFabricTypes(db));
  ipcMain.handle('catalog-entry:create', (_event, input) => createManagedEntry(db, input));
  ipcMain.handle('catalog-entry:update', (_event, input) => updateManagedEntry(db, input));
  ipcMain.handle('catalog-entry:archive', (_event, input) => archiveManagedEntry(db, input.type, input.id));
  ipcMain.handle('variants:list', () => listVariants(db));
  ipcMain.handle('categories:list', () => listCategories(db));
  ipcMain.handle('suppliers:list', () => listSuppliers(db));
  ipcMain.handle('sales:list', () => listSales(db));
  ipcMain.handle('report:get', () => financialReport(db));
  ipcMain.handle('stock:receive', (_event, input) => receiveStock(db, parseIpc(stockReceiveSchema, input)));
  ipcMain.handle('sales:create', (_event, input) => createSale(db, parseIpc(saleSchema, input)));
  ipcMain.handle('sync:queue', (_event, input) => queueSyncEvent(db, parseIpc(syncEventSchema, input)));
  ipcMain.handle('sync:pending', (_event, limit?: number) => listPendingSyncEvents(db, limit));
  ipcMain.handle('sync:retry-failed', () => retryFailedSyncEvents(db));
  ipcMain.handle('sync:conflicts', (_event, limit?: number) => listSyncConflicts(db, limit));
  ipcMain.handle('sync:conflict:resolve', (_event, input: { id: string; resolution: 'resolved' | 'retry' }) => resolveSyncConflict(db, input.id, input.resolution));
  ipcMain.handle('catalog:pending', (_event, limit?: number) => listPendingCatalogProducts(db, limit));
  ipcMain.handle('catalog:history', (_event, limit?: number) => listCatalogPublicationHistory(db, limit));
  ipcMain.handle('catalog:publish-with-media', (_event, selectedIds?: string[]) => publishCatalogWithMedia(db, path.resolve(app.getPath('userData')), selectedIds));
  ipcMain.handle('catalog:publish', (_event, selectedIds?: string[]) => publishCatalog(db, selectedIds));
  ipcMain.handle('catalog:fetch-existing', async (_event, input: { page?: number; limit?: number }) => {
    if (!safeStorage.isEncryptionAvailable()) throw new Error('Windows secure credential storage is unavailable.');
    const settings = Object.fromEntries((db.prepare("SELECT key,value FROM application_settings WHERE key LIKE 'sync.%'").all() as Array<{ key: string; value: string }>).map(row => [row.key, row.value]));
    if (!settings['sync.apiBaseUrl'] || !settings['sync.deviceId'] || !settings['sync.token']) throw new Error('Register this device before importing website products.');
    const page = Math.max(1, Math.floor(Number(input?.page || 1))); const limit = Math.min(100, Math.max(1, Math.floor(Number(input?.limit || 50))));
    const token = safeStorage.decryptString(Buffer.from(settings['sync.token'], 'base64'));
    const response = await fetch(`${settings['sync.apiBaseUrl'].replace(/\/$/, '')}/stock-manager/catalog/products?page=${page}&limit=${limit}`, { headers: { authorization: `Bearer ${token}`, 'x-fxh-device-id': settings['sync.deviceId'] } });
    const body = await response.json() as any;
    if (!response.ok) throw new Error(body.message || `Catalog import request failed (HTTP ${response.status}).`);
    const data = body.data || { products: [], total: 0, page, limit, totalPages: 0, hasMore: false };
    const origin = settings['sync.apiBaseUrl'].replace(/\/api\/?$/, '');
    const absolute = (value: string) => /^https?:\/\//i.test(value) ? value : value.startsWith('/') ? `${origin}${value}` : value;
    data.products = (data.products || []).map((item: any) => ({ ...item, images: (item.images || []).map(absolute), variants: (item.variants || []).map((variant: any) => ({ ...variant, images: (variant.images || []).map(absolute) })) }));
    return data;
  });
  ipcMain.handle('catalog:import-existing', (_event, input: unknown) => { if (!Array.isArray(input)) throw new Error('Import payload must be an array.'); return importExistingCatalogProducts(db, input as any[]); });
  ipcMain.handle('sync:mark', (_event, input) => markSyncEvents(db, input));
  ipcMain.handle('sync:config:get', () => { const saved = db.prepare("SELECT key,value FROM application_settings WHERE key IN ('sync.apiBaseUrl','sync.deviceId','sync.deviceName','sync.lastSuccessAt') ORDER BY key").all() as Array<{ key: string; value: string }>; if (!saved.some(item => item.key === 'sync.apiBaseUrl')) saved.push({ key: 'sync.apiBaseUrl', value: process.env.FXH_SYNC_API_URL || 'https://api.fastxhorizon.com/api' }); return saved; });
  ipcMain.handle('sync:config:set', (_event, input) => { const config = parseIpc(syncConfigSchema, input); const timestamp = new Date().toISOString(); for (const [key, value] of Object.entries({ 'sync.apiBaseUrl': config.apiBaseUrl, 'sync.deviceId': config.deviceId, 'sync.deviceName': config.deviceName })) db.prepare('INSERT INTO application_settings(key,value,updated_at) VALUES(?,?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_at=excluded.updated_at').run(key, value, timestamp); return true; });
  ipcMain.handle('sync:test', async (_event, input) => {
    const config = parseIpc(syncTestSchema, input);
    const endpoint = config.apiBaseUrl.replace(/\/$/, '');
    const origin = endpoint.replace(/\/api\/?$/, '');
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 8000);
    try {
      const health = await fetch(`${origin}/health`, { signal: controller.signal });
      if (!health.ok) return { status: 'server_unavailable', message: `API health check returned HTTP ${health.status}.` };
      const saved = Object.fromEntries((db.prepare("SELECT key,value FROM application_settings WHERE key IN ('sync.apiBaseUrl','sync.deviceId','sync.token')").all() as Array<{ key: string; value: string }>).map(row => [row.key, row.value]));
      if (!saved['sync.token'] || saved['sync.apiBaseUrl'] !== config.apiBaseUrl) return { status: 'api_reachable_auth_missing', message: 'API is reachable, but this device is not registered for this API URL.' };
      if (!safeStorage.isEncryptionAvailable()) return { status: 'auth_rejected', message: 'Windows secure credential storage is unavailable.' };
      const token = safeStorage.decryptString(Buffer.from(saved['sync.token'], 'base64'));
      const response = await fetch(`${endpoint}/stock-manager/sync/pull`, { signal: controller.signal, headers: { authorization: `Bearer ${token}`, 'x-fxh-device-id': saved['sync.deviceId'] } });
      if (response.ok) return { status: 'registered', message: 'API reachable and device authentication accepted.' };
      if (response.status === 401 || response.status === 403) return { status: 'auth_rejected', message: 'API is reachable, but the saved device credentials were rejected or revoked.' };
      return { status: 'server_unavailable', message: `Sync endpoint returned HTTP ${response.status}.` };
    } catch (error) {
      return { status: 'server_unavailable', message: error instanceof Error && error.name === 'AbortError' ? 'The API did not respond within 8 seconds.' : 'The API could not be reached.' };
    } finally { clearTimeout(timeout); }
  });
  ipcMain.handle('sync:register', async (_event, input) => { const config = parseIpc(syncRegistrationSchema, input); if (!safeStorage.isEncryptionAvailable()) throw new Error('Windows secure credential storage is unavailable. Device registration was not saved.'); const response = await fetch(`${config.apiBaseUrl.replace(/\/$/, '')}/stock-manager/devices/register`, { method: 'POST', headers: { 'content-type': 'application/json', 'x-fxh-registration-secret': config.registrationSecret }, body: JSON.stringify({ deviceId: config.deviceId, deviceName: config.deviceName }) }); const body = await response.json() as { data?: { token?: string }; message?: string }; if (!response.ok || !body.data?.token) throw new Error(body.message || 'Device registration failed.'); const timestamp = new Date().toISOString(); for (const [key, value] of Object.entries({ 'sync.apiBaseUrl': config.apiBaseUrl, 'sync.deviceId': config.deviceId, 'sync.deviceName': config.deviceName })) db.prepare('INSERT INTO application_settings(key,value,updated_at) VALUES(?,?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_at=excluded.updated_at').run(key, value, timestamp); db.prepare('INSERT INTO application_settings(key,value,updated_at) VALUES(?,?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_at=excluded.updated_at').run('sync.token', safeStorage.encryptString(body.data.token).toString('base64'), timestamp); return { deviceId: config.deviceId, deviceName: config.deviceName }; });
  ipcMain.handle('sync:push', async () => { if (!safeStorage.isEncryptionAvailable()) throw new Error('Windows secure credential storage is unavailable.'); const settings = Object.fromEntries((db.prepare("SELECT key,value FROM application_settings WHERE key LIKE 'sync.%'").all() as Array<{ key: string; value: string }>).map(row => [row.key, row.value])); if (!settings['sync.apiBaseUrl'] || !settings['sync.deviceId'] || !settings['sync.token']) throw new Error('Register this device before synchronizing.'); const events = listPendingSyncEvents(db) as Array<any>; if (!events.length) return { results: [] }; const payload = { events: events.map(event => ({ eventId: event.event_id, eventType: event.event_type, entityType: event.entity_type, entityId: event.entity_id, idempotencyKey: event.idempotency_key, payload: JSON.parse(event.payload) })) }; const token = safeStorage.decryptString(Buffer.from(settings['sync.token'], 'base64')); const response = await fetch(`${settings['sync.apiBaseUrl'].replace(/\/$/, '')}/stock-manager/sync/push`, { method: 'POST', headers: { 'content-type': 'application/json', authorization: `Bearer ${token}`, 'x-fxh-device-id': settings['sync.deviceId'] }, body: JSON.stringify(payload) }); const body = await response.json() as { data?: { results?: Array<{ eventId: string; status: string; error?: string }> }; message?: string }; if (!response.ok) throw new Error(body.message || 'Synchronization push failed.'); const results = body.data?.results || []; markSyncEvents(db, { eventIds: results.filter(item => item.status === 'APPLIED').map(item => item.eventId), status: 'sent' }); const failed = results.filter(item => item.status === 'FAILED'); if (failed.length) markSyncEvents(db, { eventIds: failed.map(item => item.eventId), status: 'failed', error: failed[0].error }); return { results }; });
  ipcMain.handle('sync:pull', async () => { if (!safeStorage.isEncryptionAvailable()) throw new Error('Windows secure credential storage is unavailable.'); const settings = Object.fromEntries((db.prepare("SELECT key,value FROM application_settings WHERE key LIKE 'sync.%'").all() as Array<{ key: string; value: string }>).map(row => [row.key, row.value])); if (!settings['sync.apiBaseUrl'] || !settings['sync.deviceId'] || !settings['sync.token']) throw new Error('Register this device before synchronizing.'); const token = safeStorage.decryptString(Buffer.from(settings['sync.token'], 'base64')); const response = await fetch(`${settings['sync.apiBaseUrl'].replace(/\/$/, '')}/stock-manager/sync/pull`, { headers: { authorization: `Bearer ${token}`, 'x-fxh-device-id': settings['sync.deviceId'] } }); const body = await response.json() as { data?: { events?: unknown[]; hasMore?: boolean }; message?: string }; if (!response.ok) throw new Error(body.message || 'Synchronization pull failed.'); return body.data || { events: [], hasMore: false }; });
  ipcMain.handle('sync:run', () => runSynchronization(db));
  ipcMain.handle('suppliers:create', (_event, input) => addSupplier(db, input));
  ipcMain.handle('catalog:export', async (_event, range?: { from?: number; to?: number }) => { const result = await dialog.showSaveDialog(win, { defaultPath: `fastxhorizon-website-export-${new Date().toISOString().slice(0,10)}.zip`, filters: [{ name: 'Website catalog ZIP', extensions: ['zip'] }] }); if (result.canceled || !result.filePath) return null; return await createWebsiteExport(result.filePath, db, path.resolve(app.getPath('userData')), range); });
  ipcMain.handle('media:choose-images', async () => { const result = await dialog.showOpenDialog(win, { title: 'Select product images', properties: ['openFile', 'multiSelections'], filters: [{ name: 'Images', extensions: ['jpg','jpeg','png','webp'] }] }); if (result.canceled) return []; const mediaDir = path.join(app.getPath('userData'), 'media', 'products'); fs.mkdirSync(mediaDir, { recursive: true }); return result.filePaths.map(source => { const ext = path.extname(source).toLowerCase(); const filename = `${randomUUID()}${ext}`; fs.copyFileSync(source, path.join(mediaDir, filename)); return path.join('media', 'products', filename); }); });
  ipcMain.handle('media:read-image', (_event, reference: string) => { const root = path.resolve(process.env.FXH_DATA_DIR || app.getPath('userData')); const relative = String(reference || '').replace(/^[/\\]+/, ''); const source = path.resolve(root, relative); if (!source.startsWith(`${root}${path.sep}`) || !fs.existsSync(source) || !fs.statSync(source).isFile()) return null; const ext = path.extname(source).toLowerCase(); const mime = ext === '.png' ? 'image/png' : ext === '.webp' ? 'image/webp' : 'image/jpeg'; return `data:${mime};base64,${fs.readFileSync(source).toString('base64')}`; });
  ipcMain.handle('app:backup', async () => { const result = await dialog.showSaveDialog(win, { defaultPath: `FXH-STOCK-BACKUP-${new Date().toISOString().slice(0,10)}.db` }); if (result.canceled || !result.filePath) return null; db.pragma('wal_checkpoint(TRUNCATE)'); fs.copyFileSync(path.join(app.getPath('userData'), 'fxh-stock-manager.sqlite'), result.filePath); return result.filePath; });
  ipcMain.handle('app:open-url', (_event, url: string) => { if (/^https:\/\//.test(url)) return shell.openExternal(url); return false; });
}
async function legacyWebsiteExport(filePath: string) {
  const catalog = exportCatalog(db) as any;
  const categoryIds = new Set(catalog.categories.map((item: any) => item.id));
  const brandIds = new Set(catalog.brands.map((item: any) => item.id));
  const productIds = new Set(catalog.products.map((item: any) => item.id));
  const errors: string[] = [];
  for (const product of catalog.products) {
    if (!product.sku || !product.name || !product.slug || !product.importId || !product.supplierKey) errors.push(`Product ${product.id} is missing a stable import identifier, name or slug`);
    if (product.categoryId && !categoryIds.has(product.categoryId)) errors.push(`Product ${product.sku} has an invalid category_id`);
    if (product.brandName && !product.brandId) errors.push(`Product ${product.sku} has brand_name but no brand_id`);
    if (product.brandId && !brandIds.has(product.brandId)) errors.push(`Product ${product.sku} has an invalid brand_id`);
    if (!Number.isFinite(product.sellingPrice) || product.sellingPrice < 0) errors.push(`Product ${product.sku} has invalid selling price`);
  }
  for (const variant of catalog.variants) {
    if (!productIds.has(variant.product_id)) errors.push(`Variant ${variant.sku} references a missing product`);
    if (!variant.sku || !Number.isFinite(variant.price) || variant.price < 0) errors.push(`Variant ${variant.sku || '(missing SKU)'} has invalid public pricing`);
  }
  const mediaRoot = path.resolve(app.getPath('userData'));
  const media = new Map<string, string>();
  for (const product of catalog.products) for (const reference of product.images || []) { const relative = String(reference).replace(/^[/\\]+/, ''); const source = path.resolve(mediaRoot, relative); if (!source.startsWith(`${mediaRoot}${path.sep}`) || !fs.existsSync(source) || !fs.statSync(source).isFile()) errors.push(`Missing product image: ${reference}`); else media.set(relative, `images/${path.basename(relative)}`); }
  for (const variant of catalog.variants) variant.images = (variant.images || []).map((reference: string) => media.get(String(reference).replace(/^[/\\]+/, '')) || reference);
  for (const product of catalog.products) product.images = (product.images || []).map((reference: string) => media.get(String(reference).replace(/^[/\\]+/, '')) || reference);
  if (errors.length) throw new Error(`Website export validation failed:\n${errors.join('\n')}`);
  const csv = ['import_id,supplier_key,sku,name,slug,brand_id,brand_name,fabric_material,category_id,category_slug,original_price,selling_price,discount_amount,discount_percentage,currency,quantity,status', ...catalog.products.map((p: any) => [p.importId,p.supplierKey,p.sku,p.name,p.slug,p.brandId || '',p.brandName || '',p.fabricMaterial || '',p.categoryId || '',p.categorySlug || '',p.originalPrice,p.sellingPrice,p.discountAmount,p.discountPercentage,p.currency,p.quantity,p.status].map(csvCell).join(','))].join('\n');
  const temporaryPath = `${filePath}.${randomUUID()}.partial`;
  const output = fs.createWriteStream(temporaryPath, { flags: 'wx' }); const archive = archiver('zip', { zlib: { level: 6 } }); archive.pipe(output);
  archive.append(JSON.stringify({ ...catalog, products: catalog.products, variants: catalog.variants }, null, 2), { name: 'products.json' }); archive.append(csv, { name: 'products.csv' }); archive.append(JSON.stringify(catalog.categories, null, 2), { name: 'categories.json' }); archive.append(JSON.stringify(catalog.brands, null, 2), { name: 'brands.json' }); archive.append(JSON.stringify(catalog.fabricTypes, null, 2), { name: 'fabric-types.json' });
  for (const [relative, target] of media) archive.file(path.resolve(mediaRoot, relative), { name: target });
  archive.append(JSON.stringify({ formatVersion: 3, source: 'FXH_STOCK_MANAGER', exportedAt: catalog.exportedAt, products: catalog.products.length, variants: catalog.variants.length, mediaFiles: media.size, includesInternalCosts: false, publicIdentifiers: 'generated-from-stable-local-record-ids' }, null, 2), { name: 'manifest.json' });
  try {
    await new Promise<void>((resolve, reject) => {
      let settled = false;
      const fail = (error: unknown) => { if (!settled) { settled = true; reject(error); } };
      output.once('close', () => { if (!settled) { settled = true; resolve(); } });
      output.once('error', fail); archive.once('error', fail);
      void archive.finalize().catch(fail);
    });
    const stat = fs.statSync(temporaryPath);
    if (!stat.isFile() || stat.size < 22) throw new Error('ZIP validation failed: archive is empty or incomplete.');
    if (fs.existsSync(filePath)) fs.rmSync(filePath, { force: true });
    fs.renameSync(temporaryPath, filePath);
    return filePath;
  } catch (error) {
    if (fs.existsSync(temporaryPath)) fs.rmSync(temporaryPath, { force: true });
    throw error;
  }
}
function csvCell(value: unknown) { const text = String(value ?? ''); return /[",\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text; }
app.whenReady().then(() => { db = openDatabase(process.env.FXH_DATA_DIR || app.getPath('userData')); registerIpc(); createWindow(); const syncTimer = setInterval(() => { const registered = db.prepare("SELECT 1 FROM application_settings WHERE key='sync.token'").get(); if (registered) void runSynchronization(db).catch(error => console.warn('Background synchronization deferred:', error.message)); }, 60_000); syncTimer.unref(); app.on('activate', () => { if (BrowserWindow.getAllWindows().length === 0) createWindow(); }); });
app.on('window-all-closed', () => { if (process.platform !== 'darwin') app.quit(); });
