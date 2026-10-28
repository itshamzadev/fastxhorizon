import React, { useEffect, useState } from 'react';
import { Alert, Box, Button, CircularProgress, FormControl, FormControlLabel, FormLabel, Grid, Radio, RadioGroup, TextField, Typography } from '@mui/material';
import { apiClient } from '../services/apiClient';
import { useNotifier } from '../context/NotificationProvider';

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function CheckoutForm({ onSubmit, submitting = false }) {
  const [form, setForm] = useState({ name: '', email: '', shippingAddress: '', paymentMethod: 'manual', methodKey: '', paymentValues: {}, paymentEvidence: [] });
  const [config, setConfig] = useState(null);
  const [error, setError] = useState('');
  const { notify } = useNotifier();
  useEffect(() => { apiClient.get('payments/config').then(({ data }) => { setConfig(data); const first = data?.manual?.methods?.[0]; if (first) setForm(current => ({ ...current, methodKey: first.key })) }).catch(() => setError('Payment options are currently unavailable.')) }, []);
  const methods = config?.manual?.methods || [];
  const selected = methods.find(method => method.key === form.methodKey);
  const setField = (name, value) => setForm(current => ({ ...current, [name]: value }));
  const setPaymentValue = (id, value) => setForm(current => ({ ...current, paymentValues: { ...current.paymentValues, [id]: value } }));
  const fileToData = (file, fieldId) => new Promise(resolve => { const reader = new FileReader(); reader.onload = () => resolve({ fieldId, name: file.name, mimeType: file.type, data: reader.result }); reader.readAsDataURL(file) });
  const submit = async event => { event.preventDefault(); const required = selected?.fields?.filter(field => field.required) || []; const missing = required.find(field => field.type !== 'file' && (form.paymentValues[field.id] === undefined || form.paymentValues[field.id] === '')); if (!form.name.trim() || !emailPattern.test(form.email) || !form.shippingAddress.trim() || !form.methodKey || missing) { notify({ severity: 'error', message: missing ? `${missing.label} is required.` : 'Please complete the required checkout fields.' }); return; } await onSubmit(form); };
  if (error) return <Typography color="error">{error}</Typography>;
  return <form onSubmit={submit}>
    <Typography variant="h4" gutterBottom>Billing Information</Typography>
    <Grid container spacing={3}><Grid item xs={12}><TextField required fullWidth label="Full Name" value={form.name} onChange={e => setField('name', e.target.value)} /></Grid><Grid item xs={12}><TextField required fullWidth type="email" label="Email Address" value={form.email} onChange={e => setField('email', e.target.value)} /></Grid></Grid>
    <Typography variant="h4" gutterBottom sx={{ mt: 4 }}>Shipping Information</Typography><TextField required fullWidth label="Shipping Address" value={form.shippingAddress} onChange={e => setField('shippingAddress', e.target.value)} />
    <Typography variant="h4" gutterBottom sx={{ mt: 4 }}>Payment</Typography>
    {!config?.ordersEnabled && <Alert severity="info" sx={{ mb: 2 }}>Online ordering is temporarily unavailable.</Alert>}
    <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>Availability is subject to final confirmation. If an item is unavailable after payment, you will receive a full refund.</Typography>
    <FormControl><FormLabel>Payment type</FormLabel><RadioGroup value={form.paymentMethod} onChange={e => setField('paymentMethod', e.target.value)}>{[['automatic', 'Automatic Payment', config?.automatic?.enabled], ['manual', 'Manual Payment', config?.manual?.enabled && methods.length > 0], ['cod', 'Cash on Delivery', config?.cod?.enabled]].map(([value, label, enabled]) => <FormControlLabel key={value} value={value} disabled={!enabled} control={<Radio />} label={`${label}${enabled ? '' : ' (Unavailable)'}`} />)}</RadioGroup></FormControl>
    {form.paymentMethod === 'manual' && <Box sx={{ mt: 2 }}><TextField select SelectProps={{ native: true }} fullWidth label="Manual payment method" value={form.methodKey} onChange={e => setField('methodKey', e.target.value)}>{methods.map(method => <option key={method.key} value={method.key}>{method.name}</option>)}</TextField>{selected && <><Typography variant="body2" sx={{ mt: 2, whiteSpace: 'pre-wrap' }}>{selected.recipientDetails}</Typography><Typography variant="body2" color="text.secondary" sx={{ mt: 1, whiteSpace: 'pre-wrap' }}>{selected.instructions}</Typography>{selected.fields.map(field => field.type === 'file' ? <Button key={field.id} component="label" variant="outlined" sx={{ mt: 2, display: 'block' }}>{field.label}{field.required ? ' *' : ''}<input hidden type="file" accept={(field.acceptedMimeTypes || []).join(',')} onChange={async e => { const file = e.target.files?.[0]; if (file) setField('paymentEvidence', [await fileToData(file, field.id)]) }} /></Button> : field.type === 'checkbox' ? <FormControlLabel key={field.id} control={<input type="checkbox" checked={Boolean(form.paymentValues[field.id])} onChange={e => setPaymentValue(field.id, e.target.checked)} />} label={`${field.label}${field.required ? ' *' : ''}`} /> : <TextField key={field.id} fullWidth sx={{ mt: 2 }} multiline={field.type === 'textarea'} type={field.type === 'number' ? 'number' : 'text'} label={`${field.label}${field.required ? ' *' : ''}`} placeholder={field.placeholder} helperText={field.helpText} value={form.paymentValues[field.id] || ''} onChange={e => setPaymentValue(field.id, e.target.value)} />)}</>}</Box>}
    <Button type="submit" variant="contained" disabled={submitting || !config || !config.ordersEnabled} sx={{ mt: 4 }}>{submitting ? <CircularProgress size={20} /> : 'Submit order and payment evidence'}</Button>
  </form>;
}
export default CheckoutForm;
