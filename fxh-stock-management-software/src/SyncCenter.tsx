import React, { useEffect, useMemo, useState } from 'react';

const DEFAULT_API = 'https://api.fastxhorizon.com/api';
type ConnectionState = 'idle' | 'checking' | 'registered' | 'api_reachable_auth_missing' | 'auth_rejected' | 'server_unavailable';

function configValue(config: Array<{ key: string; value: string }>, key: string, fallback = '') {
  return config.find(item => item.key === key)?.value || fallback;
}

function newDeviceId() {
  return `desktop-${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;
}

export function SyncCenter() {
  const [pending, setPending] = useState<any[]>([]);
  const [conflicts, setConflicts] = useState<any[]>([]);
  const [apiBaseUrl, setApiBaseUrl] = useState(DEFAULT_API);
  const [deviceId, setDeviceId] = useState(newDeviceId());
  const [deviceName, setDeviceName] = useState('FASTXHORIZON Stock Manager');
  const [registrationSecret, setRegistrationSecret] = useState('');
  const [lastSuccessAt, setLastSuccessAt] = useState('');
  const [connection, setConnection] = useState<ConnectionState>('idle');
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);

  const load = async () => {
    const [nextPending, nextConflicts, config] = await Promise.all([window.fxhSync.pending(200), window.fxhSync.conflicts(200), window.fxhSync.getConfig()]);
    setPending(nextPending); setConflicts(nextConflicts);
    setApiBaseUrl(configValue(config, 'sync.apiBaseUrl', DEFAULT_API));
    setDeviceId(configValue(config, 'sync.deviceId', deviceId)); setDeviceName(configValue(config, 'sync.deviceName', deviceName));
    setLastSuccessAt(configValue(config, 'sync.lastSuccessAt'));
  };

  useEffect(() => { void load().catch(error => setMessage(error instanceof Error ? error.message : 'Could not load sync settings.')); }, []);
  const failedCount = useMemo(() => pending.filter(item => item.status === 'failed').length, [pending]);
  const statusLabel: Record<ConnectionState, string> = { idle: 'Not tested', checking: 'Checking…', registered: 'Connected & registered', api_reachable_auth_missing: 'API reachable · registration required', auth_rejected: 'Authentication rejected', server_unavailable: 'Server unavailable' };

  const saveSettings = async () => { setBusy(true); try { await window.fxhSync.setConfig({ apiBaseUrl: apiBaseUrl.trim(), deviceId: deviceId.trim(), deviceName: deviceName.trim() }); setMessage('Connection settings saved locally.'); } catch (error) { setMessage(error instanceof Error ? error.message : 'Could not save connection settings.'); } finally { setBusy(false); } };
  const testConnection = async () => { setBusy(true); setConnection('checking'); setMessage(''); try { const result = await window.fxhSync.testConnection({ apiBaseUrl: apiBaseUrl.trim() }); setConnection(result.status); setMessage(result.message); if (result.status === 'registered') await load(); } catch (error) { setConnection('server_unavailable'); setMessage(error instanceof Error ? error.message : 'Connection test failed.'); } finally { setBusy(false); } };
  const registerDevice = async () => { setBusy(true); setMessage(''); try { await window.fxhSyncActions.register({ apiBaseUrl: apiBaseUrl.trim(), deviceId: deviceId.trim() || newDeviceId(), deviceName: deviceName.trim(), registrationSecret }); setRegistrationSecret(''); setConnection('registered'); setMessage('Device registered securely. The token was stored with Windows secure storage.'); await load(); } catch (error) { setConnection('auth_rejected'); setMessage(error instanceof Error ? error.message : 'Device registration failed.'); } finally { setBusy(false); } };
  const syncNow = async () => { setBusy(true); setMessage('Synchronizing local events and remote inventory…'); try { const result = await window.fxhSyncRunner.run(); setMessage(`Sync complete: ${result.pushed?.length || 0} pushed, ${result.pulled || 0} pulled, ${result.applied || 0} applied.`); await load(); setConnection('registered'); } catch (error) { setMessage(error instanceof Error ? error.message : 'Synchronization failed.'); } finally { setBusy(false); } };
  const retryFailed = async () => { setBusy(true); try { const count = await window.fxhSync.retryFailed(); setMessage(`${count} failed event${count === 1 ? '' : 's'} queued for retry.`); await load(); } catch (error) { setMessage(error instanceof Error ? error.message : 'Could not retry failed events.'); } finally { setBusy(false); } };
  const resolve = async (id: string, resolution: 'retry' | 'resolved') => { setBusy(true); try { await window.fxhSync.resolveConflict({ id, resolution }); setMessage(resolution === 'retry' ? 'Conflict retried successfully.' : 'Conflict marked resolved.'); await load(); } catch (error) { setMessage(error instanceof Error ? error.message : 'Conflict resolution failed.'); } finally { setBusy(false); } };

  return <section className="sync-center">
    <div className="panel-heading"><div><span className="eyebrow">SECURE OPERATIONS SYNC</span><h2>Sync Center</h2><p className="sync-subtitle">Connect this local Stock Manager to the FASTXHORIZON marketplace without exposing database credentials.</p></div><span className={`sync-state sync-state-${connection}`}><span className="status-dot"/>{statusLabel[connection]}</span></div>
    {message && <div className={connection === 'registered' || message.includes('complete') || message.includes('saved') ? 'success' : 'error'}>{message}</div>}
    <div className="sync-connection-grid">
      <div className="panel sync-connection-panel"><div className="panel-heading"><div><span className="eyebrow">CONNECTION</span><h3>Backend connection</h3></div><span className="mono">HTTPS</span></div><label>Backend API URL<input value={apiBaseUrl} onChange={event => setApiBaseUrl(event.target.value)} placeholder={DEFAULT_API}/></label><div className="form-grid"><label>Device name<input value={deviceName} onChange={event => setDeviceName(event.target.value)}/></label><label>Device ID<input value={deviceId} onChange={event => setDeviceId(event.target.value)}/></label></div><div className="sync-actions"><button className="secondary" disabled={busy} onClick={() => void saveSettings()}>Save settings</button><button className="secondary" disabled={busy} onClick={() => void testConnection()}>{connection === 'checking' ? 'Checking…' : 'Test connection'}</button></div><p className="help-text">API reachability, registration status, rejected credentials and unavailable server are shown separately.</p></div>
      <div className="panel sync-connection-panel"><div className="panel-heading"><div><span className="eyebrow">DEVICE ACCESS</span><h3>Register this device</h3></div></div><p className="help-text">Ask an authorized administrator for the one-time registration secret. It is sent directly to the backend and never saved in SQLite.</p><label>Registration secret<input type="password" value={registrationSecret} onChange={event => setRegistrationSecret(event.target.value)} placeholder="Enter server-provided secret" autoComplete="off"/></label><button className="primary" disabled={busy || registrationSecret.length < 16 || !deviceId.trim() || !deviceName.trim()} onClick={() => void registerDevice()}>Register device securely</button></div>
    </div>
    <div className="sync-summary-grid"><div className="metric"><span>Connection</span><strong>{statusLabel[connection]}</strong><small>{apiBaseUrl}</small></div><div className="metric"><span>Pending events</span><strong>{pending.length}</strong><small>{failedCount} failed and awaiting retry</small></div><div className="metric"><span>Last successful sync</span><strong>{lastSuccessAt ? new Date(lastSuccessAt).toLocaleString() : 'Not yet'}</strong><small>Stored locally</small></div><div className="metric sync-summary-actions"><button className="primary" disabled={busy || connection !== 'registered'} onClick={() => void syncNow()}>Sync now</button><button className="secondary" disabled={busy || failedCount === 0} onClick={() => void retryFailed()}>Retry failed</button></div></div>
    <div className="panel-heading sync-list-heading"><div><span className="eyebrow">RECONCILIATION</span><h3>Conflicts & pending operations</h3></div><button className="secondary" disabled={busy} onClick={() => void load()}>Refresh</button></div>
    <div className="table-card compact"><table><thead><tr><th>EVENT</th><th>ENTITY</th><th>REASON</th><th>ACTIONS</th></tr></thead><tbody>{conflicts.map(item => <tr key={item.id}><td className="mono">{item.event_id}</td><td>{item.entity_type} / {item.entity_id}</td><td className="danger">{item.reason}</td><td><button className="table-edit" disabled={busy} onClick={() => void resolve(item.id, 'retry')}>Retry</button><button className="secondary" disabled={busy} onClick={() => void resolve(item.id, 'resolved')}>Resolve</button></td></tr>)}</tbody></table>{!conflicts.length && <div className="empty">No open synchronization conflicts.</div>}</div>
  </section>;
}
