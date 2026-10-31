import { useEffect, useState } from 'react'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/Button'
import { ErrorState } from '@/components/ui/Feedback'
import { apiClient } from '@/services/api/client'
import { formatCurrency } from '@/lib/format'

type ManualMethod = { key: string; name: string; enabled: boolean; instructions: string; recipientDetails: string; order: number; version: number; requiredAnyOf?: string[][]; fields: Array<{ id: string; label: string; type: string; required: boolean; placeholder: string; helpText: string; order: number; enabled: boolean; options: string[]; acceptedMimeTypes: string[]; maxFileSizeBytes: number }> }

const commonFields = [
  { id: 'transactionId', label: 'Transaction ID / TID', type: 'text', required: false, placeholder: 'Letters and numbers accepted', helpText: 'Alphanumeric bank/wallet references are accepted, or upload a payment screenshot.', order: 0, enabled: true, options: [], acceptedMimeTypes: [], maxFileSizeBytes: 2097152 },
  { id: 'paymentScreenshot', label: 'Payment screenshot', type: 'file', required: false, placeholder: '', helpText: 'Upload screenshot if transaction ID is not available.', order: 1, enabled: true, options: [], acceptedMimeTypes: ['image/png', 'image/jpeg', 'image/webp', 'application/pdf'], maxFileSizeBytes: 5242880 },
  { id: 'senderName', label: 'Sender name', type: 'text', required: true, placeholder: 'Name used for payment', helpText: '', order: 2, enabled: true, options: [], acceptedMimeTypes: [], maxFileSizeBytes: 2097152 },
  { id: 'senderBankName', label: 'Sender bank / wallet name', type: 'text', required: true, placeholder: 'Bank or wallet used to send payment', helpText: '', order: 3, enabled: true, options: [], acceptedMimeTypes: [], maxFileSizeBytes: 2097152 },
]

const example: ManualMethod[] = [{ key: 'manual-method', name: 'Manual payment', enabled: true, instructions: 'Enter your payment details and submit proof.', recipientDetails: '', order: 0, version: 1, requiredAnyOf: [['transactionId', 'paymentScreenshot']], fields: commonFields }]

function preset(key: 'easypaisa' | 'bank-account'): ManualMethod {
  const isEasypaisa = key === 'easypaisa'
  return { key, name: isEasypaisa ? 'Easypaisa' : 'Bank Account', enabled: true, instructions: 'Send the exact order amount, then provide your sender details and either the transaction ID or a payment screenshot.', recipientDetails: '', order: isEasypaisa ? 0 : 1, version: 1, requiredAnyOf: [['transactionId', 'paymentScreenshot']], fields: commonFields.map(field => ({ ...field })) }
}

export function PaymentsPage() {
  const [config, setConfig] = useState({ automaticEnabled: false, manualEnabled: true, codEnabled: false, manualMethods: example })
  const [json, setJson] = useState(JSON.stringify(example, null, 2))
  const [message, setMessage] = useState('')
  const [reviews, setReviews] = useState<Array<{ _id: string; orderNumber: string; email: string; methodKey: string; amount: number | string; state: string; createdAt: string }>>([])
  const [error, setError] = useState<unknown>(null)
  const loadReviews = () => apiClient.get<{ data: Array<{ _id: string; orderNumber: string; email: string; methodKey: string; amount: number; state: string; createdAt: string }> }>('/admin/payments/reviews?state=PENDING_VERIFICATION').then(({ data }) => setReviews(data.map((item) => ({ ...item, amount: formatCurrency(item.amount, { precise: true }) })))).catch(setError)
  useEffect(() => { apiClient.get<{ data: typeof config }>('/admin/payments/config').then(({ data }) => { setConfig(data); setJson(JSON.stringify(data.manualMethods || [], null, 2)); loadReviews() }).catch(setError) }, [])
  const save = async () => { try { const manualMethods = JSON.parse(json); if (!Array.isArray(manualMethods)) throw new Error('Manual methods must be a JSON array.'); const { data } = await apiClient.put<{ data: typeof config }>('/admin/payments/config', { ...config, manualMethods }); setConfig(data); setJson(JSON.stringify(data.manualMethods, null, 2)); setMessage('Payment settings saved.'); setError(null) } catch (saveError) { setError(saveError); setMessage('') } }
  const addPreset = (key: 'easypaisa' | 'bank-account') => { try { const current = JSON.parse(json) as ManualMethod[]; if (current.some(method => method.key === key)) { setMessage(`${key === 'easypaisa' ? 'Easypaisa' : 'Bank Account'} already exists. Edit its recipient details below.`); return } setJson(JSON.stringify([...current, preset(key)], null, 2)); setMessage(`${key === 'easypaisa' ? 'Easypaisa' : 'Bank Account'} form added. Add your account details in recipientDetails, then save settings.`); setError(null) } catch { setError(new Error('Current manual payment JSON is invalid. Fix it before adding a preset.')) } }
  if (error && !message) return <ErrorState message={error instanceof Error ? error.message : 'Unable to load payment settings.'} />
  return <>
    <PageHeader title="Payment Settings" description="Control available payment types and manual payment forms." actions={<Button variant="primary" onClick={() => void save()}>Save settings</Button>} />
    {message && <p className="alert alert--success">{message}</p>}
    <div className="card" style={{ padding: 24, marginBottom: 24 }}>
      <h3>Payment types</h3>
      {(['automaticEnabled', 'manualEnabled', 'codEnabled'] as const).map((key) => <label key={key} style={{ display: 'block', margin: '14px 0' }}><input type="checkbox" checked={config[key]} onChange={(event) => setConfig({ ...config, [key]: event.target.checked })} /> {key === 'automaticEnabled' ? 'Automatic Payment' : key === 'manualEnabled' ? 'Manual Payment' : 'Cash on Delivery'} <small> ({config[key] ? 'Enabled' : 'Disabled'})</small></label>)}
    </div>
    <div className="card" style={{ padding: 24 }}><h3>Manual methods and dynamic fields</h3><p className="text-sm text-muted">Add a ready payment form, then replace its <code>recipientDetails</code> with your Easypaisa number or bank account details. Sender name, sender bank/wallet name and transaction ID or screenshot are handled by the checkout form.</p><div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', marginBottom: 16 }}><Button variant="secondary" onClick={() => addPreset('easypaisa')}>+ Add Easypaisa</Button><Button variant="secondary" onClick={() => addPreset('bank-account')}>+ Add Bank Account</Button></div><textarea aria-label="Manual payment methods configuration" value={json} onChange={(event) => setJson(event.target.value)} rows={28} style={{ width: '100%', fontFamily: 'monospace' }} /></div>
    <div className="card" style={{ padding: 24, marginTop: 24 }}><h3>Pending manual payment reviews</h3>{reviews.length === 0 ? <p className="text-muted">No payment evidence is awaiting review.</p> : reviews.map(review => <div key={review._id} style={{ display: 'flex', gap: 16, alignItems: 'center', borderTop: '1px solid var(--color-border)', padding: '14px 0' }}><span><strong>{review.orderNumber}</strong><br />{review.email} · {review.methodKey} · {review.amount}</span><span style={{ marginLeft: 'auto' }}><Button variant="primary" size="sm" onClick={async () => { await apiClient.post(`/admin/payments/reviews/${review._id}/decision`, { state: 'VERIFIED' }); loadReviews() }}>Approve</Button>{' '}<Button variant="danger-ghost" size="sm" onClick={async () => { await apiClient.post(`/admin/payments/reviews/${review._id}/decision`, { state: 'REJECTED', reason: 'Evidence rejected by administrator.' }); loadReviews() }}>Reject</Button></span></div>)}</div>
  </>
}
