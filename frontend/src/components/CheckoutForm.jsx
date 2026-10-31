import React, { useEffect, useState } from 'react';
import { Alert, Box, Button, CircularProgress, Divider, FormControl, FormControlLabel, FormLabel, Grid, Paper, Radio, RadioGroup, TextField, Typography } from '@mui/material';
import PersonOutlineIcon from '@mui/icons-material/PersonOutline';
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined';
import PaymentsOutlinedIcon from '@mui/icons-material/PaymentsOutlined';
import UploadFileOutlinedIcon from '@mui/icons-material/UploadFileOutlined';
import { apiClient } from '../services/apiClient';
import { useNotifier } from '../context/NotificationProvider';

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function CheckoutForm({ onSubmit, submitting = false }) {
  const [form, setForm] = useState({ name: '', email: '', shippingAddress: '', paymentMethod: 'manual', methodKey: '', paymentValues: {}, paymentEvidence: [] });
  const [config, setConfig] = useState(null);
  const [error, setError] = useState('');
  const { notify } = useNotifier();
  useEffect(() => { apiClient.get('payments/config').then(({ data: response }) => { const data = response?.data || response; setConfig(data); const first = data?.manual?.methods?.[0]; if (first) setForm(current => ({ ...current, methodKey: first.key })) }).catch(() => setError('Payment options are currently unavailable.')) }, []);
  const methods = config?.manual?.methods || [];
  const selected = methods.find(method => method.key === form.methodKey);
  const setField = (name, value) => setForm(current => ({ ...current, [name]: value }));
  const setPaymentValue = (id, value) => setForm(current => ({ ...current, paymentValues: { ...current.paymentValues, [id]: value } }));
  const fileToData = (file, fieldId) => new Promise(resolve => { const reader = new FileReader(); reader.onload = () => resolve({ fieldId, name: file.name, mimeType: file.type, data: reader.result }); reader.readAsDataURL(file) });
  const submit = async event => {
    event.preventDefault();
    const required = selected?.fields?.filter(field => field.required) || [];
    const missing = required.find(field => field.type !== 'file' && !String(form.paymentValues[field.id] || '').trim());
    const anyMissing = (selected?.requiredAnyOf || []).some(group => !group.some(id => String(form.paymentValues[id] || '').trim() || form.paymentEvidence.some(file => file.fieldId === id)));
    if (!form.name.trim() || !emailPattern.test(form.email) || !form.shippingAddress.trim() || !form.methodKey || missing || anyMissing) { notify({ severity: 'error', message: missing ? `${missing.label} is required.` : anyMissing ? 'Enter a Transaction ID or upload a payment screenshot.' : 'Please complete the required checkout fields.' }); return; }
    await onSubmit(form);
  };
  if (error) return <Alert severity="error">{error}</Alert>;
  if (!config) return <Box className="luxury-route-loading"><CircularProgress size={24} /><Typography variant="body2">Loading secure checkout…</Typography></Box>;
  if (!config.ordersEnabled) return <Alert severity="info" className="luxury-checkout-lock">Online ordering is temporarily unavailable. Please return to your cart when checkout is reopened.</Alert>;
  return <form onSubmit={submit}>
    <SectionTitle icon={<PersonOutlineIcon />} title="Contact information" caption="We will use these details for order updates." />
    <Grid container spacing={2.25}><Grid item xs={12} sm={6}><TextField required fullWidth label="Full name" value={form.name} onChange={e => setField('name', e.target.value)} /></Grid><Grid item xs={12} sm={6}><TextField required fullWidth type="email" label="Email address" value={form.email} onChange={e => setField('email', e.target.value)} /></Grid></Grid>
    <SectionTitle icon={<LocalShippingOutlinedIcon />} title="Delivery address" caption="Enter the address where you want your order delivered." />
    <TextField required fullWidth multiline minRows={3} label="Complete shipping address" value={form.shippingAddress} onChange={e => setField('shippingAddress', e.target.value)} />
    <SectionTitle icon={<PaymentsOutlinedIcon />} title="Payment method" caption="Choose an available method and follow its instructions." />
    <FormControl fullWidth><FormLabel sx={{ mb: 1 }}>Available payment methods</FormLabel><RadioGroup value={form.paymentMethod} onChange={e => setField('paymentMethod', e.target.value)}>{[['automatic', 'Automatic payment', config?.automatic?.enabled], ['manual', 'Manual payment', config?.manual?.enabled && methods.length > 0], ['cod', 'Cash on delivery', config?.cod?.enabled]].map(([value, label, enabled]) => <Paper key={value} variant="outlined" sx={{ mb: 1, borderColor: form.paymentMethod === value ? 'primary.main' : 'divider', bgcolor: form.paymentMethod === value ? 'action.selected' : 'transparent' }}><FormControlLabel sx={{ m: 0, px: 1.5, py: .5, width: '100%' }} value={value} disabled={!enabled} control={<Radio />} label={<Box><Typography fontWeight={600}>{label}</Typography><Typography variant="caption" color="text.secondary">{enabled ? (value === 'manual' ? 'Pay by transfer and submit proof' : 'Available at checkout') : 'Currently unavailable'}</Typography></Box>} /></Paper>)}</RadioGroup></FormControl>
    {form.paymentMethod === 'manual' && <Box sx={{ mt: 2.5, p: { xs: 2, md: 2.5 }, borderRadius: 3, bgcolor: 'action.hover' }}><TextField select SelectProps={{ native: true }} fullWidth label="Select payment account" value={form.methodKey} onChange={e => setField('methodKey', e.target.value)}>{methods.map(method => <option key={method.key} value={method.key}>{method.name}</option>)}</TextField>{selected && <><Box sx={{ mt: 2, p: 1.5, borderRadius: 2, bgcolor: 'background.paper' }}><Typography variant="caption" color="text.secondary">Send payment to</Typography><Typography sx={{ whiteSpace: 'pre-wrap', fontWeight: 600 }}>{selected.recipientDetails || 'Payment account details will be shown here.'}</Typography><Typography variant="body2" color="text.secondary" sx={{ mt: 1, whiteSpace: 'pre-wrap' }}>{selected.instructions}</Typography></Box><Grid container spacing={2} sx={{ mt: .5 }}>{selected.fields.map(field => field.type === 'file' ? <Grid item xs={12} key={field.id}><Button component="label" fullWidth variant="outlined" startIcon={<UploadFileOutlinedIcon />} sx={{ py: 1.4, justifyContent: 'flex-start' }}>{field.label}{field.required ? ' *' : ''}<input hidden type="file" accept={(field.acceptedMimeTypes || []).join(',')} onChange={async e => { const file = e.target.files?.[0]; if (file) setField('paymentEvidence', [await fileToData(file, field.id)]) }} /></Button>{form.paymentEvidence.some(file => file.fieldId === field.id) && <Typography variant="caption" color="success.main">Screenshot attached</Typography>}</Grid> : <Grid item xs={12} sm={6} key={field.id}><TextField fullWidth multiline={field.type === 'textarea'} type={field.type === 'number' ? 'number' : 'text'} label={`${field.label}${field.required ? ' *' : ''}`} placeholder={field.placeholder} helperText={field.helpText} value={form.paymentValues[field.id] || ''} onChange={e => setPaymentValue(field.id, e.target.value)} /></Grid>)}</Grid></>}</Box>}
    <Divider sx={{ my: 3 }} /><Button type="submit" fullWidth variant="contained" size="large" disabled={submitting || !config || !config.ordersEnabled} sx={{ py: 1.5, borderRadius: 2 }}>{submitting ? <CircularProgress size={20} /> : 'Place order securely'}</Button>
  </form>;
}

function SectionTitle({ icon, title, caption }) { return <Box sx={{ mt: 1, mb: 2 }}><Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}><Box sx={{ color: 'primary.main', display: 'flex' }}>{icon}</Box><Typography variant="h6" fontWeight={800}>{title}</Typography></Box><Typography variant="body2" color="text.secondary" sx={{ ml: 4 }}>{caption}</Typography></Box> }

export default CheckoutForm;
