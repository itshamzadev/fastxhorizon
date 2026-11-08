import fs from 'node:fs';
import path from 'node:path';
import { safeStorage } from 'electron';
import { listPendingCatalogProducts, markCatalogPublication } from './database';

function mimeFor(reference: string) { const ext = path.extname(reference).toLowerCase(); return ext === '.png' ? 'image/png' : ext === '.webp' ? 'image/webp' : 'image/jpeg'; }

export async function publishCatalogWithMedia(db: any, appDataPath: string, selectedIds?: string[]) {
  if (!safeStorage.isEncryptionAvailable()) throw new Error('Windows secure credential storage is unavailable.');
  const settings = Object.fromEntries((db.prepare("SELECT key,value FROM application_settings WHERE key LIKE 'sync.%'").all() as Array<{ key: string; value: string }>).map(row => [row.key, row.value]));
  if (!settings['sync.apiBaseUrl'] || !settings['sync.deviceId'] || !settings['sync.token']) throw new Error('Register this device before publishing catalog changes.');
  const endpoint = settings['sync.apiBaseUrl'].replace(/\/$/, ''); const origin = endpoint.replace(/\/api\/?$/, ''); const token = safeStorage.decryptString(Buffer.from(settings['sync.token'], 'base64')); const headers = { authorization: `Bearer ${token}`, 'x-fxh-device-id': settings['sync.deviceId'] };
  const pending = listPendingCatalogProducts(db, 2000).filter(item => !selectedIds?.length || selectedIds.includes(item.id)); const results: any[] = [];
  for (const item of pending) {
    try {
      const payload: any = JSON.parse(JSON.stringify(item.payload)); const references = [...(payload.images || []), ...payload.variants.flatMap((variant: any) => variant.images || [])].filter((value: string) => !/^https?:\/\//i.test(String(value))); const mapping = new Map<string, string>();
      if (references.length) { const form = new FormData(); for (const reference of [...new Set(references)]) { const root = path.resolve(appDataPath); const source = path.resolve(root, String(reference).replace(/^[/\\]+/, '')); if (!source.startsWith(`${root}${path.sep}`) || !fs.existsSync(source) || !fs.statSync(source).isFile()) throw new Error(`Missing local product image: ${reference}`); const bytes = fs.readFileSync(source); form.append('files', new Blob([bytes], { type: mimeFor(source) }), path.basename(source)); } const mediaResponse = await fetch(`${endpoint}/stock-manager/catalog/media`, { method: 'POST', headers, body: form }); const mediaBody = await mediaResponse.json() as any; if (!mediaResponse.ok) throw new Error(mediaBody.message || 'Image upload failed.'); for (let index = 0; index < [...new Set(references)].length; index += 1) mapping.set([...new Set(references)][index], `${origin}${mediaBody.data.files[index].url}`); }
      payload.images = (payload.images || []).map((value: string) => mapping.get(value) || value); payload.variants = payload.variants.map((variant: any) => ({ ...variant, imageUrls: (variant.images || []).map((value: string) => mapping.get(value) || value) }));
      const response = await fetch(`${endpoint}/stock-manager/catalog/upsert`, { method: 'POST', headers: { ...headers, 'content-type': 'application/json', 'x-idempotency-key': `catalog:${item.id}:${item.local_revision}` }, body: JSON.stringify({ product: payload }) }); const body = await response.json() as any; if (!response.ok) throw new Error(body.message || 'Catalog publication failed.'); const data = body.data; markCatalogPublication(db, { productId: item.id, localRevision: item.local_revision, status: 'published', remoteProductId: data.remoteProductId, remoteVersion: data.remoteVersion }); results.push({ id: item.id, status: 'published', remoteProductId: data.remoteProductId });
    } catch (error) { const message = error instanceof Error ? error.message : 'Catalog publication failed.'; markCatalogPublication(db, { productId: item.id, localRevision: item.local_revision, status: 'failed', error: message }); results.push({ id: item.id, status: 'failed', error: message }); }
  }
  return results;
}
