import * as React from 'react';
import { Alert, Button, Container, Paper, Stack, TextField, Typography } from '@mui/material';
import { useSearchParams, useParams } from 'react-router-dom';
import { apiClient } from '../services/apiClient';
import { formatPrice } from '../utils/currency';

export default function ArrangementStatus() {
  const { requestNumber } = useParams();
  const [params] = useSearchParams();
  const [state, setState] = React.useState({ loading: true, error: '', request: null });
  const [payment, setPayment] = React.useState({ reference: '', file: null, submitting: false, message: '' });
  React.useEffect(() => {
    let active = true;
    const token = params.get('token');
    if (!token) { setState({ loading: false, error: 'This private tracking link is incomplete.', request: null }); return undefined; }
    apiClient.get(`arrangements/${encodeURIComponent(requestNumber)}?token=${encodeURIComponent(token)}`)
      .then(({ data }) => active && setState({ loading: false, error: '', request: data.data }))
      .catch(error => active && setState({ loading: false, error: error.response?.data?.message || 'Unable to load request status.', request: null }));
    return () => { active = false; };
  }, [params, requestNumber]);
  const submitEvidence = async event => {
    event.preventDefault();
    if (!payment.reference.trim() || !payment.file) { setPayment(value => ({ ...value, message: 'Payment reference and evidence file are required.' })); return; }
    const form = new FormData(); form.append('trackingToken', params.get('token') || ''); form.append('reference', payment.reference.trim()); form.append('evidence', payment.file);
    setPayment(value => ({ ...value, submitting: true, message: '' }));
    try { await apiClient.post(`arrangements/${encodeURIComponent(requestNumber)}/advance/evidence`, form, { headers: { 'Content-Type': 'multipart/form-data' } }); setPayment({ reference: '', file: null, submitting: false, message: 'Evidence submitted and is pending administrator verification.' }); }
    catch (error) { setPayment(value => ({ ...value, submitting: false, message: error.response?.data?.message || 'Unable to submit evidence.' })); }
  };
  if (state.loading) return <Container sx={{ py: 8 }}><Typography>Loading request status...</Typography></Container>;
  if (state.error) return <Container maxWidth="sm" sx={{ py: 8 }}><Alert severity="error">{state.error}</Alert></Container>;
  const request = state.request;
  return <Container maxWidth="sm" sx={{ py: { xs: 4, md: 8 } }}><Paper sx={{ p: { xs: 2.5, md: 5 } }}><Typography variant="overline">ARRANGEMENT REQUEST</Typography><Typography variant="h3" sx={{ mt: 1 }}>Request {request.requestNumber}</Typography><Typography sx={{ mt: 2 }}>Status: <strong>{request.status.replaceAll('_', ' ')}</strong></Typography><Typography color="text.secondary" sx={{ mt: 1 }}>{request.product?.name} · Quantity {request.quantity}</Typography>{request.advanceAmount != null && <Stack spacing={0.5} sx={{ mt: 3 }}><Typography>Agreed subtotal: {formatPrice(request.subtotal)}</Typography><Typography>60% advance: <strong>{formatPrice(request.advanceAmount)}</strong></Typography><Typography>Remaining: {formatPrice(request.remainingAmount)}</Typography><Typography color="text.secondary">Payment status: {request.paymentState?.replaceAll('_', ' ')}</Typography></Stack>}{request.status === 'ADVANCE_PENDING' && <Stack component="form" onSubmit={submitEvidence} spacing={2} sx={{ mt: 3 }}><TextField label="Payment reference" value={payment.reference} onChange={event => setPayment(value => ({ ...value, reference: event.target.value }))} required /><Button component="label" variant="outlined">Choose PNG, JPEG, WebP or PDF<input hidden type="file" accept="image/png,image/jpeg,image/webp,application/pdf" onChange={event => setPayment(value => ({ ...value, file: event.target.files?.[0] || null }))} /></Button>{payment.file && <Typography variant="caption">Selected: {payment.file.name}</Typography>}<Button type="submit" variant="contained" disabled={payment.submitting}>{payment.submitting ? 'Submitting...' : 'Submit payment evidence'}</Button>{payment.message && <Alert severity="info">{payment.message}</Alert>}</Stack>}{request.estimatedWindow && <Typography sx={{ mt: 2 }}>Estimated window: {request.estimatedWindow}</Typography>}<Typography color="text.secondary" sx={{ mt: 3 }}>Keep this private link to check updates. Evidence is private and reviewed only by authorized staff.</Typography></Paper></Container>;
}
