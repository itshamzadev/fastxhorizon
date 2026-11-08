import React, { useEffect, useState } from 'react';

export function SyncCenter() {
  const [pending, setPending] = useState<any[]>([]);
  const [conflicts, setConflicts] = useState<any[]>([]);
  const [status, setStatus] = useState('');
  const [busy, setBusy] = useState(false);
  const load = async () => { setPending(await window.fxhSync.pending(200)); setConflicts(await window.fxhSync.conflicts(200)); };
  useEffect(() => { void load(); }, []);
  const resolve = async (id: string, resolution: 'retry' | 'resolved') => { setBusy(true); try { await window.fxhSync.resolveConflict({ id, resolution }); setStatus(resolution === 'retry' ? 'Conflict retried successfully.' : 'Conflict marked resolved.'); await load(); } catch (error) { setStatus(error instanceof Error ? error.message : 'Conflict resolution failed.'); } finally { setBusy(false); } };
  return <section><div className="panel-heading"><div><span className="eyebrow">SYNC CENTER</span><h2>Conflicts & pending operations</h2></div></div>{status && <div className="success">{status}</div>}<div className="table-card compact"><table><thead><tr><th>EVENT</th><th>ENTITY</th><th>REASON</th><th>ACTIONS</th></tr></thead><tbody>{conflicts.map(item => <tr key={item.id}><td className="mono">{item.event_id}</td><td>{item.entity_type} / {item.entity_id}</td><td className="danger">{item.reason}</td><td><button className="table-edit" disabled={busy} onClick={() => void resolve(item.id, 'retry')}>Retry</button><button className="secondary" disabled={busy} onClick={() => void resolve(item.id, 'resolved')}>Resolve</button></td></tr>)}</tbody></table>{!conflicts.length && <div className="empty">No open synchronization conflicts.</div>}</div><div className="panel"><strong>{pending.length}</strong> pending local events <button className="primary" disabled={busy} onClick={() => void load()}>Refresh</button></div></section>;
}
