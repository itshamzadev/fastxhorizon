import React, { useEffect, useState } from 'react';
import { Alert, Box, Button, CircularProgress, Divider, FormControl, FormControlLabel, Grid, Paper, Radio, RadioGroup, TextField, Typography } from '@mui/material';
import PersonOutlineIcon from '@mui/icons-material/PersonOutline';
import PaymentsOutlinedIcon from '@mui/icons-material/PaymentsOutlined';
import CreditCardOutlinedIcon from '@mui/icons-material/CreditCardOutlined';
import AccountBalanceWalletOutlinedIcon from '@mui/icons-material/AccountBalanceWalletOutlined';
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined';
import CheckCircleOutlinedIcon from '@mui/icons-material/CheckCircleOutlined';
import UploadFileOutlinedIcon from '@mui/icons-material/UploadFileOutlined';
import { apiClient } from '../services/apiClient';
import { useNotifier } from '../context/NotificationProvider';
import { trackCartEvent } from '../analytics/tiktokEvents';
import { trackMetaCart } from '../analytics/metaEvents';

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
// Keep the encoded checkout request below common reverse-proxy body limits.
const MAX_PAYMENT_IMAGE_BYTES = 400 * 1024;

function CheckoutForm({ onSubmit, submitting = false, cartItems = [], onStepChange }) {
  const [form, setForm] = useState({ name: '', email: '', phone: '', shippingAddress: '', paymentMethod: 'manual', methodKey: '', paymentValues: {}, paymentEvidence: [] });
  const [config, setConfig] = useState(null);
  const [step, setStep] = useState(1);
  const [error, setError] = useState('');
  const [validationError, setValidationError] = useState('');
  const { notify } = useNotifier();
  useEffect(() => { apiClient.get('payments/config').then(({ data: response }) => { const data = response?.data || response; setConfig(data); const first = data?.manual?.methods?.[0]; if (first) setForm(current => ({ ...current, methodKey: first.key })) }).catch(() => setError('Payment options are currently unavailable.')) }, []);
  const methods = config?.manual?.methods || [];
  const selected = methods.find(method => method.key === form.methodKey);
  const setField = (name, value) => setForm(current => ({ ...current, [name]: value }));
  const setPaymentValue = (id, value) => setForm(current => ({ ...current, paymentValues: { ...current.paymentValues, [id]: value } }));
  const fileToData = file => new Promise((resolve, reject) => {
    if (!file.type.startsWith('image/')) {
      if (file.size > MAX_PAYMENT_IMAGE_BYTES) return reject(new Error('Please upload a screenshot smaller than 400 KB.'));
      const reader = new FileReader(); reader.onload = () => resolve({ mimeType: file.type, data: reader.result }); reader.onerror = () => reject(new Error('Unable to read payment evidence.')); reader.readAsDataURL(file); return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      const image = new Image();
      image.onload = () => {
        const scale = Math.min(1, 1200 / Math.max(image.width, image.height));
        const canvas = document.createElement('canvas'); canvas.width = Math.max(1, Math.round(image.width * scale)); canvas.height = Math.max(1, Math.round(image.height * scale));
        canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
        const encode = quality => canvas.toBlob(blob => { if (!blob) return reject(new Error('Unable to process payment screenshot.')); if (blob.size <= MAX_PAYMENT_IMAGE_BYTES || quality <= .35) { const output = new FileReader(); output.onload = () => resolve({ mimeType: 'image/jpeg', data: output.result, size: blob.size }); output.readAsDataURL(blob); } else encode(quality - .1); }, 'image/jpeg', quality);
        encode(.78);
      };
      image.onerror = () => reject(new Error('Invalid payment screenshot.')); image.src = reader.result;
    };
    reader.onerror = () => reject(new Error('Unable to read payment screenshot.')); reader.readAsDataURL(file);
  });
  useEffect(() => { onStepChange?.(step); }, [onStepChange, step]);
  const submit = async event => {
    event.preventDefault();
    setValidationError('');
    const contactInvalid = !form.name.trim() || !emailPattern.test(form.email) || !form.phone.trim() || !form.shippingAddress.trim();
    if (step === 1) {
      if (contactInvalid) { const message = 'Please enter your name, email, phone number and complete delivery address.'; setValidationError(message); notify({ severity: 'error', message }); return; }
      setStep(2); return;
    }
    if (step === 2) {
      if (!form.paymentMethod) { const message = 'Please choose a payment type.'; setValidationError(message); notify({ severity: 'error', message }); return; }
      setStep(3); return;
    }
    if (step === 3) {
      if (form.paymentMethod === 'manual' && !form.methodKey) { const message = 'Select a manual payment method.'; setValidationError(message); notify({ severity: 'error', message }); return; }
      setStep(4); return;
    }
    const paymentNeedsProof = form.paymentMethod === 'manual';
    const required = paymentNeedsProof ? (selected?.fields?.filter(field => field.required) || []) : [];
    const missing = required.find(field => field.type !== 'file' && !String(form.paymentValues[field.id] || '').trim());
    const anyMissing = paymentNeedsProof && (selected?.requiredAnyOf || []).some(group => !group.some(id => String(form.paymentValues[id] || '').trim() || form.paymentEvidence.some(file => file.fieldId === id)));
    if (contactInvalid || (paymentNeedsProof && (!form.methodKey || missing || anyMissing))) { const message = missing ? `${missing.label} is required.` : anyMissing ? 'Enter a Transaction ID or upload a payment screenshot.' : 'Please complete the required checkout fields.'; setValidationError(message); notify({ severity: 'error', message }); return; }
    await onSubmit(form);
  };
  useEffect(() => {
    if (step >= 3) {
      const eventId = `payment-info:${form.paymentMethod}:${cartItems.map(item => item._id || item.id).sort().join(',')}`;
      trackCartEvent('AddPaymentInfo', cartItems, eventId);
      trackMetaCart('AddPaymentInfo', cartItems, eventId, { payment_method: form.paymentMethod });
    }
  }, [cartItems, form.paymentMethod, step]);
  if (error) return <Alert severity="error">{error}</Alert>;
  if (!config) return <Box className="luxury-route-loading"><CircularProgress size={24} /><Typography variant="body2">Loading secure checkout…</Typography></Box>;
  if (!config.ordersEnabled) return <Alert severity="info" className="luxury-checkout-lock">Online ordering is temporarily unavailable. Please return to your cart when checkout is reopened.</Alert>;
  return <form onSubmit={submit}>
    {validationError && <Alert severity="warning" sx={{ mb: 2 }}>{validationError}</Alert>}
    {step === 1 && <>
    <SectionTitle icon={<PersonOutlineIcon />} title="Step 1: Contact & Delivery" caption="We will use these details for order updates." />
    <Grid container spacing={2.25}><Grid item xs={12} sm={4}><TextField required fullWidth label="Full name" value={form.name} onChange={e => setField('name', e.target.value)} /></Grid><Grid item xs={12} sm={4}><TextField required fullWidth type="email" label="Email address" value={form.email} onChange={e => setField('email', e.target.value)} /></Grid><Grid item xs={12} sm={4}><TextField required fullWidth type="tel" label="Phone number" placeholder="03XX XXXXXXX" value={form.phone} onChange={e => setField('phone', e.target.value)} helperText="Required for delivery calls and order updates" /></Grid></Grid>
    <SectionTitle icon={<LocalShippingOutlinedIcon />} title="Delivery address" caption="Enter the address where you want your order delivered." />
    <TextField required fullWidth multiline minRows={3} label="Complete shipping address" value={form.shippingAddress} onChange={e => setField('shippingAddress', e.target.value)} />
    </>}
    {step === 2 && <>
    <SectionTitle icon={<PaymentsOutlinedIcon />} title="Step 2: Choose Payment Type" caption="How would you like to pay?" />
    <FormControl fullWidth>
      <RadioGroup value={form.paymentMethod} onChange={e => setField('paymentMethod', e.target.value)}>
        <Grid container spacing={1.5}>
          {[
            ['automatic', 'Card', 'Automatic / online payment', config?.automatic?.enabled, <CreditCardOutlinedIcon />],
            ['manual', 'Manual', 'Easypaisa, JazzCash or bank transfer', config?.manual?.enabled && methods.length > 0, <AccountBalanceWalletOutlinedIcon />],
            ['cod', 'Cash on Delivery (COD)', 'Place order directly', config?.cod?.enabled, <LocalShippingOutlinedIcon />],
          ].map(([value, label, caption, enabled, icon]) => <Grid item xs={12} md={4} key={value}>
            <Paper variant="outlined" sx={{ height: '100%', borderRadius: 2.5, borderWidth: form.paymentMethod === value ? 2 : 1, borderColor: form.paymentMethod === value ? 'primary.main' : 'divider', bgcolor: form.paymentMethod === value ? 'action.selected' : 'background.paper', opacity: enabled ? 1 : .55, transition: 'all .2s ease' }}>
              <FormControlLabel sx={{ m: 0, p: 1.5, width: '100%', height: '100%', alignItems: 'flex-start' }} value={value} disabled={!enabled} control={<Radio sx={{ display: 'none' }} />} label={<Box sx={{ display: 'flex', gap: 1.25, alignItems: 'flex-start' }}><Box sx={{ color: 'primary.main', mt: .25 }}>{icon}</Box><Box><Typography fontWeight={800}>{label}</Typography><Typography variant="caption" color="text.secondary">{enabled ? caption : 'Currently unavailable'}</Typography></Box>{form.paymentMethod === value && <CheckCircleOutlinedIcon color="primary" sx={{ ml: 'auto', fontSize: 19 }} />}</Box>} />
            </Paper>
          </Grid>)}
        </Grid>
      </RadioGroup>
    </FormControl>
    </>}
    {step === 3 && <>
    {form.paymentMethod === 'automatic' && <Box sx={{ mt: 2, p: 2, borderRadius: 2.5, bgcolor: 'action.hover' }}><Typography fontWeight={700}>Automatic / online payment</Typography><Typography variant="body2" color="text.secondary" sx={{ mt: .5 }}>Your card payment will be processed securely online.</Typography></Box>}
    {form.paymentMethod === 'cod' && <Box sx={{ mt: 2, p: 2, borderRadius: 2.5, bgcolor: 'action.hover' }}><Typography fontWeight={700}>Place order directly</Typography><Typography variant="body2" color="text.secondary" sx={{ mt: .5 }}>Pay when your order is delivered to your address.</Typography></Box>}
    {form.paymentMethod === 'manual' && <Box sx={{ mt: 2.5, p: { xs: 2, md: 2.5 }, borderRadius: 3, bgcolor: 'action.hover' }}>
      <SectionTitle icon={<AccountBalanceWalletOutlinedIcon />} title="Step 3: Choose Payment Method" caption="Select a manual payment account, then submit your payment proof." />
      <Grid container spacing={1.25} sx={{ mb: 2 }}>
        {methods.map(method => <Grid item xs={12} sm={6} md={4} key={method.key}><Paper onClick={() => setField('methodKey', method.key)} variant="outlined" sx={{ cursor: 'pointer', p: 1.5, height: '100%', borderRadius: 2, borderColor: form.methodKey === method.key ? 'primary.main' : 'divider', bgcolor: form.methodKey === method.key ? 'background.paper' : 'transparent' }}><Typography fontWeight={700}>{method.name}</Typography><Typography variant="caption" color="text.secondary">Manual transfer</Typography></Paper></Grid>)}<Grid item xs={12} sm={6} md={4}><Paper variant="outlined" sx={{ p: 1.5, height: '100%', borderRadius: 2, opacity: .65 }}><Typography fontWeight={700}>More methods coming soon</Typography><Typography variant="caption" color="text.secondary">Additional payment options</Typography></Paper></Grid>
      </Grid>
      {selected && <><Box sx={{ mt: 2, p: 1.5, borderRadius: 2, bgcolor: 'background.paper' }}><Typography variant="caption" color="text.secondary">Send payment to</Typography><Typography sx={{ whiteSpace: 'pre-wrap', fontWeight: 600 }}>{selected.recipientDetails || 'Payment account details will be shown here.'}</Typography><Typography variant="body2" color="text.secondary" sx={{ mt: 1, whiteSpace: 'pre-wrap' }}>{selected.instructions}</Typography></Box><PaymentFields fields={selected.fields} form={form} setField={setField} setPaymentValue={setPaymentValue} fileToData={fileToData} /></>}
    </Box>}
    </>}
    {step === 4 && <>
      <SectionTitle icon={<CheckCircleOutlinedIcon />} title="Step 4: Confirm Order" caption="Review your details, then place your order securely." />
      <Paper variant="outlined" sx={{ p: 2, borderRadius: 2.5, bgcolor: 'action.hover' }}><Typography variant="body2"><strong>{form.name}</strong> · {form.email} · {form.phone}</Typography><Typography variant="body2" color="text.secondary" sx={{ mt: .5, whiteSpace: 'pre-wrap' }}>{form.shippingAddress}</Typography><Typography variant="body2" sx={{ mt: 1 }}>Payment: <strong>{form.paymentMethod === 'manual' ? selected?.name : form.paymentMethod === 'automatic' ? 'Card / online payment' : 'Cash on Delivery (COD)'}</strong></Typography></Paper>
    </>}
    <Divider sx={{ my: 3 }} />
    <Box sx={{ display: 'flex', gap: 1.25 }}>
      {step > 1 && <Button type="button" variant="outlined" fullWidth onClick={() => setStep(current => current - 1)} disabled={submitting}>Back</Button>}
      <Button type="submit" fullWidth variant="contained" size="large" disabled={submitting || !config || !config.ordersEnabled} sx={{ py: 1.5, borderRadius: 2 }}>{submitting ? <CircularProgress size={20} /> : step === 4 ? 'Place Order' : 'Continue'}</Button>
    </Box>
  </form>;
}

function SectionTitle({ icon, title, caption }) { return <Box className="checkout-section-title" sx={{ mt: 1, mb: 2 }}><Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}><Box sx={{ color: 'primary.main', display: 'flex' }}>{icon}</Box><Typography variant="h6" fontWeight={800}>{title}</Typography></Box><Typography variant="body2" color="text.secondary" sx={{ ml: 4 }}>{caption}</Typography></Box> }

function PaymentFields({ fields, form, setField, setPaymentValue, fileToData }) {
  const transaction = fields.find(field => field.id === 'transactionId');
  const screenshot = fields.find(field => field.id === 'paymentScreenshot' || field.type === 'file');
  const others = transaction && screenshot ? fields.filter(field => field !== transaction && field !== screenshot) : [];
  return <Box sx={{ mt: 2 }}><Typography variant="subtitle2" fontWeight={700} sx={{ mb: 1 }}>Payment proof <Typography component="span" variant="caption" color="text.secondary">(Transaction ID or screenshot)</Typography></Typography>{transaction && screenshot && <Grid container spacing={1.5} alignItems="center"><Grid item xs={12} md={5}><PaymentField field={transaction} form={form} setField={setField} setPaymentValue={setPaymentValue} /></Grid><Grid item xs={12} md={2} sx={{ textAlign: 'center' }}><Typography variant="body2" fontWeight={800} color="text.secondary">OR</Typography></Grid><Grid item xs={12} md={5}><PaymentField field={screenshot} form={form} setField={setField} setPaymentValue={setPaymentValue} fileToData={fileToData} /></Grid></Grid>}{(!transaction || !screenshot) && <Grid container spacing={2}>{fields.map(field => <Grid item xs={12} sm={6} key={field.id}><PaymentField field={field} form={form} setField={setField} setPaymentValue={setPaymentValue} fileToData={fileToData} /></Grid>)}</Grid>}<Grid container spacing={2} sx={{ mt: transaction && screenshot ? .5 : 0 }}>{others.map(field => <Grid item xs={12} sm={6} key={field.id}><PaymentField field={field} form={form} setField={setField} setPaymentValue={setPaymentValue} fileToData={fileToData} /></Grid>)}</Grid></Box>;
}

function PaymentField({ field, form, setField, setPaymentValue, fileToData }) {
  if (field.type === 'file') return <><Button component="label" fullWidth variant="outlined" startIcon={<UploadFileOutlinedIcon />} sx={{ py: 1.4, justifyContent: 'flex-start' }}>{field.label}{field.required ? ' *' : ''}<input hidden type="file" accept={(field.acceptedMimeTypes || []).join(',')} onChange={async event => { const file = event.target.files?.[0]; if (!file) return; try { const encoded = await fileToData(file); setField('paymentEvidence', [{ ...encoded, fieldId: field.id, name: file.name }]); } catch (error) { setField('paymentEvidence', []); window.alert(error.message); } }} /></Button>{form.paymentEvidence.some(file => file.fieldId === field.id) && <Typography variant="caption" color="success.main">Screenshot attached (compressed)</Typography>}</>;
  const isReference = /transaction|reference|tid/i.test(`${field.id} ${field.label}`);
  return <TextField fullWidth multiline={field.type === 'textarea'} type={isReference ? 'text' : field.type === 'number' ? 'number' : 'text'} label={`${field.label}${field.required ? ' *' : ''}`} placeholder={field.placeholder} helperText={isReference ? 'Enter the reference exactly as received. Any letters or numbers are accepted.' : field.helpText} value={form.paymentValues[field.id] || ''} onChange={event => setPaymentValue(field.id, event.target.value)} />;
}

export default CheckoutForm;
