import * as React from 'react';
import { Alert, Card, CardContent, Container, Stack, Typography } from '@mui/material';
import { apiClient } from '../services/apiClient';
import { formatPrice } from '../utils/currency';

export default function MyProductRequests() {
  const [state, setState] = React.useState({ loading: true, error: '', items: [] });
  React.useEffect(() => { apiClient.get('arrangements/mine').then(({ data }) => setState({ loading: false, error: '', items: data.data || [] })).catch(error => setState({ loading: false, error: error.response?.data?.message || 'Please sign in to view your product requests.', items: [] })); }, []);
  return <Container maxWidth="md" sx={{ py: { xs: 4, md: 8 } }}><Typography variant="overline">ACCOUNT</Typography><Typography variant="h3" sx={{ mb: 3 }}>My Product Requests</Typography>{state.loading && <Typography>Loading requests...</Typography>}{state.error && <Alert severity="info">{state.error}</Alert>}<Stack spacing={2}>{state.items.map(item => <Card key={item.requestNumber}><CardContent><Typography variant="h6">{item.product?.name || 'Product'}</Typography><Typography color="text.secondary">{item.requestNumber} · Quantity {item.quantity}</Typography><Typography sx={{ mt: 1 }}>Status: <strong>{item.status.replaceAll('_', ' ')}</strong></Typography>{item.advanceAmount != null && <Typography color="text.secondary">Total {formatPrice(item.subtotal)} · Advance {formatPrice(item.advanceAmount)} · Remaining {formatPrice(item.remainingAmount)}</Typography>}</CardContent></Card>)}</Stack>{!state.loading && !state.error && state.items.length === 0 && <Typography color="text.secondary">You have no product requests yet.</Typography>}</Container>;
}
