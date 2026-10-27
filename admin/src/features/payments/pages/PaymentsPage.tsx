import { useEffect, useState } from 'react'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/Button'
import { ErrorState } from '@/components/ui/Feedback'
import { apiClient } from '@/services/api/client'
import { formatCurrency } from '@/lib/format'

const example = [{ key: 'manual-method', name: 'Manual payment', enabled: true, instructions: 'Enter your payment details and submit proof.', recipientDetails: '', order: 0, version: 1, fields: [{ id: 'transactionId', label: 'Transaction ID', type: 'text', required: true, placeholder: '', helpText: '', order: 0, enabled: true, options: [], acceptedMimeTypes: [], maxFileSizeBytes: 2097152 }] }]

export function PaymentsPage() {
  const [config, setConfig] = useState({ automaticEnabled: false, manualEnabled: true, codEnabled: false, manualMethods: example })
  const [json, setJson] = useState(JSON.stringify(example, null, 2))
  const [message, setMessage] = useState('')
  const [reviews, setReviews] = useState<Array<{ _id: string; orderNumber: string; email: string; methodKey: string; amount: number | string; state: string; createdAt: string }>>([])
  const [error, setError] = useState<unknown>(null)
  const loadReviews = () => apiClient.get<{ data: Array<{ _id: string; orderNumber: string; email: string; methodKey: string; amount: number; state: string; createdAt: string }> }>('/admin/payments/reviews?state=PENDING_VERIFICATION').then(({ data }) => setReviews(data.map((item) => ({ ...item, amount: formatCurrency(item.amount, { precise: true }) })))).catch(setError)
  useEffect(() => { apiClient.get<{ data: typeof config }>('/admin/payments/config').then(({ data }) => { setConfig(data); setJson(JSON.stringify(data.manualMethods || [], null, 2)); loadReviews() }).catch(setError) }, [])
  const save = async () => { try { const manualMethods = JSON.parse(json); if (!Array.isArray(manualMethods)) throw new Error('Manual methods must be a JSON array.'); const { data } = await apiClient.put<{ data: typeof config }>('/admin/payments/config', { ...config, manualMethods }); setConfig(data); setJson(JSON.stringify(data.manualMethods, null, 2)); setMessage('Payment settings saved.'); setError(null) } catch (saveError) { setError(saveError); setMessage('') } }
  if (error && !message) return <ErrorState message={error instanceof Error ? error.message : 'Unable to load payment settings.'} />
  return <>
    <PageHeader title="Payment Settings" description="Control available payment types and manual payment forms." actions={<Button variant="primary" onClick={() => void save()}>Save settings</Button>} />
    {message && <p className="alert alert--success">{message}</p>}
    <div className="card" style={{ padding: 24, marginBottom: 24 }}>
      <h3>Payment types</h3>
      {(['automaticEnabled', 'manualEnabled', 'codEnabled'] as const).map((key) => <label key={key} style={{ display: 'block', margin: '14px 0' }}><input type="checkbox" checked={config[key]} onChange={(event) => setConfig({ ...config, [key]: event.target.checked })} /> {key === 'automaticEnabled' ? 'Automatic Payment' : key === 'manualEnabled' ? 'Manual Payment' : 'Cash on Delivery'} <small> ({config[key] ? 'Enabled' : 'Disabled'})</small></label>)}
    </div>
    <div className="card" style={{ padding: 24 }}><h3>Manual methods and dynamic fields</h3><p className="text-sm text-muted">Edit the JSON configuration to create methods and fields. Only supported field types are accepted by the server: text, textarea, number, select, radio, checkbox and file.</p><textarea aria-label="Manual payment methods configuration" value={json} onChange={(event) => setJson(event.target.value)} rows={24} style={{ width: '100%', fontFamily: 'monospace' }} /></div>
    <div className="card" style={{ padding: 24, marginTop: 24 }}><h3>Pending manual payment reviews</h3>{reviews.length === 0 ? <p className="text-muted">No payment evidence is awaiting review.</p> : reviews.map(review => <div key={review._id} style={{ display: 'flex', gap: 16, alignItems: 'center', borderTop: '1px solid var(--color-border)', padding: '14px 0' }}><span><strong>{review.orderNumber}</strong><br />{review.email} · {review.methodKey} · {review.amount}</span><span style={{ marginLeft: 'auto' }}><Button variant="primary" size="sm" onClick={async () => { await apiClient.post(`/admin/payments/reviews/${review._id}/decision`, { state: 'VERIFIED' }); loadReviews() }}>Approve</Button>{' '}<Button variant="danger-ghost" size="sm" onClick={async () => { await apiClient.post(`/admin/payments/reviews/${review._id}/decision`, { state: 'REJECTED', reason: 'Evidence rejected by administrator.' }); loadReviews() }}>Reject</Button></span></div>)}</div>
  </>
}
