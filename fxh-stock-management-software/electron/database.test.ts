import { afterEach, describe, expect, it } from 'vitest';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { addProduct, archiveProduct, applyRemoteSyncEvent, createSale, listPendingSyncEvents, openDatabase, queueSyncEvent, receiveStock } from './database';

const temporaryDirectories: string[] = [];

afterEach(() => {
  for (const directory of temporaryDirectories.splice(0)) fs.rmSync(directory, { recursive: true, force: true });
});

describe('Stock Manager safety foundation', () => {
  it('runs versioned migrations and preserves product history when removing a product', () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'fxh-stock-manager-'));
    temporaryDirectories.push(directory);
    const db = openDatabase(directory);
    const productId = addProduct(db, { name: 'Migration test product', brandName: 'Test Brand', fabricMaterial: 'Cotton', quantity: 1, unitCost: 100, sellingPrice: 200, originalPrice: 250 });
    const movementCountBefore = (db.prepare('SELECT count(*) count FROM stock_movements').get() as { count: number }).count;
    expect((db.prepare('SELECT count(*) count FROM schema_migrations').get() as { count: number }).count).toBeGreaterThan(0);
    expect(archiveProduct(db, productId)).toBe(true);
    expect((db.prepare('SELECT status FROM products WHERE id=?').get(productId) as { status: string }).status).toBe('archived');
    expect((db.prepare('SELECT count(*) count FROM stock_movements').get() as { count: number }).count).toBe(movementCountBefore);
    db.close();
  });

  it('queues a synchronization event idempotently for safe retries', () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'fxh-stock-manager-'));
    temporaryDirectories.push(directory);
    const db = openDatabase(directory);
    const first = queueSyncEvent(db, { eventId: 'event-test-001', eventType: 'STOCK_RECEIPT', entityType: 'variant', entityId: 'variant-001', idempotencyKey: 'idem-test-001', payload: { quantity: 1 } });
    const second = queueSyncEvent(db, { eventId: 'event-test-001', eventType: 'STOCK_RECEIPT', entityType: 'variant', entityId: 'variant-001', idempotencyKey: 'idem-test-001', payload: { quantity: 1 } });
    expect(first).toBe(second);
    expect(listPendingSyncEvents(db)).toHaveLength(1);
    db.close();
  });

  it('records stock receiving and sales as durable outbound events', () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'fxh-stock-manager-'));
    temporaryDirectories.push(directory);
    const db = openDatabase(directory);
    const productId = addProduct(db, { name: 'Stock flow test product', quantity: 1, unitCost: 100, sellingPrice: 200 });
    const variantId = (db.prepare('SELECT id FROM product_variants WHERE product_id=?').get(productId) as { id: string }).id;
    receiveStock(db, { variantId, quantity: 2, unitCost: 120, reason: 'Restock test' });
    expect(createSale(db, { variantId, quantity: 1, unitPrice: 200 })).toMatch(/^FXH-SALE-/);
    expect((db.prepare('SELECT quantity_on_hand FROM product_variants WHERE id=?').get(variantId) as { quantity_on_hand: number }).quantity_on_hand).toBe(2);
    expect((db.prepare("SELECT count(*) count FROM sync_events WHERE status='pending'").get() as { count: number }).count).toBe(2);
    expect((db.prepare("SELECT count(*) count FROM products WHERE publish_state='pending'").get() as { count: number }).count).toBe(1);
    db.close();
  });

  it('applies remote stock events once and records a conflict for a missing mapping', () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'fxh-stock-manager-'));
    temporaryDirectories.push(directory);
    const db = openDatabase(directory);
    const productId = addProduct(db, { name: 'Remote flow test product', quantity: 2, unitCost: 100, sellingPrice: 200 });
    const variantId = (db.prepare('SELECT id FROM product_variants WHERE product_id=?').get(productId) as { id: string }).id;
    const event = { eventId: 'remote-online-sale-001', eventType: 'ONLINE_SALE', entityType: 'order', entityId: 'order-001', payload: { localVariantId: variantId, quantity: 1 } };
    expect(applyRemoteSyncEvent(db, event).status).toBe('applied');
    expect(applyRemoteSyncEvent(db, event).status).toBe('already_applied');
    expect((db.prepare('SELECT quantity_on_hand FROM product_variants WHERE id=?').get(variantId) as { quantity_on_hand: number }).quantity_on_hand).toBe(1);
    expect(applyRemoteSyncEvent(db, { ...event, eventId: 'remote-missing-001', payload: { localVariantId: 'missing', quantity: 1 } }).status).toBe('conflict');
    expect((db.prepare("SELECT count(*) count FROM sync_conflicts WHERE status='open'").get() as { count: number }).count).toBe(1);
    db.close();
  });
});
