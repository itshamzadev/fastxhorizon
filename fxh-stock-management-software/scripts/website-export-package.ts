import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { openDatabase, addProduct } from '../electron/database';
import { createWebsiteExport } from '../electron/websiteExport';

async function run() {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'fxh-website-export-'));
  const mediaRoot = path.join(root, 'app-data');
  const mediaPath = path.join(mediaRoot, 'media', 'products');
  fs.mkdirSync(mediaPath, { recursive: true });
  fs.writeFileSync(path.join(mediaPath, 'attached.webp'), Buffer.from('real-test-product-image'));
  const db = openDatabase(root);
  const now = new Date().toISOString();
  db.prepare('INSERT INTO brands(id,name,active,created_at,updated_at) VALUES(?,?,?,?,?)').run('brand-1', 'Test Brand', 1, now, now);
  addProduct(db, { sku: 'PRIVATE-MODEL-77', modelName: 'PRIVATE-MODEL-77', name: 'Test Product', category: 'Clothing', brandName: 'Test Brand', fabricMaterial: 'Cotton', colors: [{ sku: 'PRIVATE-MODEL-77-BLUE', color: 'Blue', quantity: 2, unitCost: 1000, sellingPrice: 1500, originalPrice: 2000, images: ['media/products/attached.webp'] }] });
  const zip = path.join(root, 'fastxhorizon-website-export-test.zip');
  await createWebsiteExport(zip, db, mediaRoot);
  const extracted = path.join(root, 'extracted');
  execFileSync('powershell.exe', ['-NoProfile', '-NonInteractive', '-Command', `Expand-Archive -LiteralPath '${zip.replace(/'/g, "''")}' -DestinationPath '${extracted.replace(/'/g, "''")}' -Force`]);
  const catalog = JSON.parse(fs.readFileSync(path.join(extracted, 'products.json'), 'utf8')) as any;
  const product = catalog.products[0];
  assert.equal(fs.existsSync(path.join(extracted, 'images', 'attached.webp')), true);
  assert.equal(product.images[0], 'images/attached.webp');
  assert.equal(fs.readFileSync(path.join(extracted, 'images', 'attached.webp'), 'utf8'), 'real-test-product-image');
  assert.equal(JSON.stringify(catalog).includes('PRIVATE-MODEL'), false);
  assert.equal(product.brandName, 'Test Brand');
  assert.equal(product.fabricMaterial, 'Cotton');
  assert.equal(product.originalPrice, 2000);
  assert.equal(product.sellingPrice, 1500);
  assert.equal(product.discountAmount, 500);
  assert.equal(product.discountPercentage, 25);
  assert.equal(JSON.parse(fs.readFileSync(path.join(extracted, 'manifest.json'), 'utf8')).mediaFiles, 1);
  db.close();
  fs.rmSync(root, { recursive: true, force: true });
  console.log('Website export package test passed: real image, Windows extraction, privacy and public pricing verified.');
}

void run();
