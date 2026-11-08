import { safeStorage } from 'electron';
import { applyRemoteSyncEvent, listPendingSyncEvents, markSyncEvents } from './database';

type Setting = { key: string; value: string };

function settings(db: any) {
  return Object.fromEntries((db.prepare("SELECT key,value FROM application_settings WHERE key LIKE 'sync.%'").all() as Setting[]).map(row => [row.key, row.value]));
}

export async function runSynchronization(db: any) {
  if (!safeStorage.isEncryptionAvailable()) throw new Error('Windows secure credential storage is unavailable.');
  const config = settings(db);
  if (!config['sync.apiBaseUrl'] || !config['sync.deviceId'] || !config['sync.token']) throw new Error('Register this device before synchronizing.');
  const token = safeStorage.decryptString(Buffer.from(config['sync.token'], 'base64'));
  const headers = { authorization: `Bearer ${token}`, 'x-fxh-device-id': config['sync.deviceId'], 'content-type': 'application/json' };
  const endpoint = config['sync.apiBaseUrl'].replace(/\/$/, '');
  const pending = listPendingSyncEvents(db) as Array<any>;
  let pushed: any[] = [];
  if (pending.length) {
    const response = await fetch(`${endpoint}/stock-manager/sync/push`, { method: 'POST', headers, body: JSON.stringify({ events: pending.map(event => ({ eventId: event.event_id, eventType: event.event_type, entityType: event.entity_type, entityId: event.entity_id, idempotencyKey: event.idempotency_key, payload: JSON.parse(event.payload) })) }) });
    const body = await response.json() as any;
    if (!response.ok) throw new Error(body.message || 'Synchronization push failed.');
    pushed = body.data?.results || [];
    const sent = pushed.filter((item: any) => ['APPLIED', 'already_applied'].includes(item.status)).map((item: any) => item.eventId);
    if (sent.length) markSyncEvents(db, { eventIds: sent, status: 'sent' });
    const failed = pushed.filter((item: any) => item.status === 'FAILED');
    if (failed.length) markSyncEvents(db, { eventIds: failed.map((item: any) => item.eventId), status: 'failed', error: failed[0].error });
  }
  const pullResponse = await fetch(`${endpoint}/stock-manager/sync/pull`, { headers });
  const pullBody = await pullResponse.json() as any;
  if (!pullResponse.ok) throw new Error(pullBody.message || 'Synchronization pull failed.');
  const pulled = pullBody.data?.events || [];
  const acknowledgements: string[] = [];
  const conflicts: any[] = [];
  for (const event of pulled) {
    const applied = applyRemoteSyncEvent(db, { eventId: event.eventId, eventType: event.eventType, entityType: event.entityType, entityId: event.entityId, payload: event.payload });
    if (applied.status === 'applied' || applied.status === 'already_applied') acknowledgements.push(event.eventId);
    if (applied.status === 'conflict') conflicts.push({ eventId: event.eventId, reason: applied.reason });
  }
  if (acknowledgements.length) {
    await fetch(`${endpoint}/stock-manager/sync/ack`, { method: 'POST', headers, body: JSON.stringify({ eventIds: acknowledgements }) });
  }
  db.prepare("INSERT INTO application_settings(key,value,updated_at) VALUES('sync.lastSuccessAt',?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_at=excluded.updated_at").run(new Date().toISOString(), new Date().toISOString());
  return { pushed, pulled: pulled.length, applied: acknowledgements.length, conflicts };
}
