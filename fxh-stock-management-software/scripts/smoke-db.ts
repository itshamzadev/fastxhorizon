import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { openDatabase, dashboard, addProduct, listProducts } from '../electron/database';

const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'fxh-stock-'));
const db = openDatabase(dir);
addProduct(db, { sku: 'SMOKE-001', name: 'Synthetic Cotton Shirt', category: 'Clothing', size: 'M', color: 'Black', quantity: 3, unitCost: 1200, sellingPrice: 1800 });
const stats = dashboard(db); const rows = listProducts(db);
if (stats.products !== 1 || stats.onHand !== 3 || stats.available !== 3 || rows.length !== 1) throw new Error(`Smoke test failed: ${JSON.stringify({ stats, rows })}`);
db.close(); fs.rmSync(dir, { recursive: true, force: true });
console.log('SQLite smoke test passed: one product, one variant, three pieces, one stock movement.');
