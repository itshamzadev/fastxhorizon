import fs from 'node:fs';
import path from 'node:path';
import { randomUUID } from 'node:crypto';
import archiverModule = require('archiver');
import type Database from 'better-sqlite3';
import { exportCatalog } from './database';

const archiver = (_format: string, options?: Record<string, unknown>) => new archiverModule.ZipArchive(options);

export async function createWebsiteExport(filePath: string, db: Database.Database, mediaRootInput: string, range?: { from?: number; to?: number }) {
  const catalog = exportCatalog(db, range) as any;
  const categoryIds = new Set(catalog.categories.map((item: any) => item.id));
  const brandIds = new Set(catalog.brands.map((item: any) => item.id));
  const productIds = new Set(catalog.products.map((item: any) => item.id));
  const errors: string[] = [];
  for (const product of catalog.products) {
    if (!product.sku || !product.name || !product.slug || !product.importId || !product.supplierKey) errors.push(`Product ${product.id} is missing a stable import identifier, name or slug`);
    if (product.categoryId && !categoryIds.has(product.categoryId)) errors.push(`Product ${product.sku} has an invalid category_id`);
    if (product.brandName && !product.brandId) errors.push(`Product ${product.sku} has brand_name but no brand_id`);
    if (product.brandId && !brandIds.has(product.brandId)) errors.push(`Product ${product.sku} has an invalid brand_id`);
    if (!Number.isFinite(product.sellingPrice) || product.sellingPrice < 0 || !Number.isFinite(product.originalPrice) || product.originalPrice < 0) errors.push(`Product ${product.sku} has invalid public pricing`);
  }
  for (const variant of catalog.variants) {
    if (!productIds.has(variant.product_id)) errors.push(`Variant ${variant.sku} references a missing product`);
    if (!variant.sku || !Number.isFinite(variant.price) || variant.price < 0) errors.push(`Variant ${variant.sku || '(missing SKU)'} has invalid public pricing`);
  }
  const mediaRoot = path.resolve(mediaRootInput);
  const media = new Map<string, string>();
  for (const product of catalog.products) for (const reference of product.images || []) {
    const relative = String(reference).replace(/^[/\\]+/, '');
    const source = path.resolve(mediaRoot, relative);
    if (!source.startsWith(`${mediaRoot}${path.sep}`) || !fs.existsSync(source) || !fs.statSync(source).isFile()) errors.push(`Missing product image: ${reference}`);
    else media.set(relative, `images/${path.basename(relative)}`);
  }
  for (const variant of catalog.variants) variant.images = (variant.images || []).map((reference: string) => media.get(String(reference).replace(/^[/\\]+/, '')) || reference);
  for (const product of catalog.products) product.images = (product.images || []).map((reference: string) => media.get(String(reference).replace(/^[/\\]+/, '')) || reference);
  if (errors.length) throw new Error(`Website export validation failed:\n${errors.join('\n')}`);
  const csv = ['import_id,supplier_key,sku,name,slug,brand_id,brand_name,fabric_material,category_id,category_slug,original_price,selling_price,discount_amount,discount_percentage,currency,quantity,status', ...catalog.products.map((p: any) => [p.importId,p.supplierKey,p.sku,p.name,p.slug,p.brandId || '',p.brandName || '',p.fabricMaterial || '',p.categoryId || '',p.categorySlug || '',p.originalPrice,p.sellingPrice,p.discountAmount,p.discountPercentage,p.currency,p.quantity,p.status].map(csvCell).join(','))].join('\n');
  const temporaryPath = `${filePath}.${randomUUID()}.partial`;
  const output = fs.createWriteStream(temporaryPath, { flags: 'wx' });
  const archive = archiver('zip', { zlib: { level: 6 } });
  archive.pipe(output);
  archive.append(JSON.stringify(catalog, null, 2), { name: 'products.json' });
  archive.append(csv, { name: 'products.csv' });
  archive.append(JSON.stringify(catalog.categories, null, 2), { name: 'categories.json' });
  archive.append(JSON.stringify(catalog.brands, null, 2), { name: 'brands.json' });
  archive.append(JSON.stringify(catalog.fabricTypes, null, 2), { name: 'fabric-types.json' });
  for (const [relative, target] of media) archive.file(path.resolve(mediaRoot, relative), { name: target });
  archive.append(JSON.stringify({ formatVersion: 3, source: 'FXH_STOCK_MANAGER', exportedAt: catalog.exportedAt, catalogNumberRange: { from: catalog.products.length ? Math.min(...catalog.products.map((product: any) => product.catalogNumber)) : null, to: catalog.products.length ? Math.max(...catalog.products.map((product: any) => product.catalogNumber)) : null }, products: catalog.products.length, variants: catalog.variants.length, mediaFiles: media.size, includesInternalCosts: false, publicIdentifiers: 'generated-from-stable-local-record-ids' }, null, 2), { name: 'manifest.json' });
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
