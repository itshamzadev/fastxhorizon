import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import CheckoutForm from '../components/CheckoutForm';
import {
  Typography,
  CircularProgress,
  Container,
  Paper,
  Stack,
  Divider,
  Box,
  IconButton,
  Collapse,
  List,
  ListItem,
  ListItemAvatar,
  Avatar,
  ListItemText,
  Checkbox,
  Tooltip,
} from '@mui/material';
import ShoppingCartCheckoutIcon from '@mui/icons-material/ShoppingCartCheckout';
import LockIcon from '@mui/icons-material/Lock';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import ShoppingBagIcon from '@mui/icons-material/ShoppingBag';
import { useNotifier } from '../context/NotificationProvider';
import { apiClient, withRetry } from '../services/apiClient';
import { formatPrice } from '../utils/currency';
import { resolveMediaUrl } from '../utils/media';

function Checkout({ cartItems = [], onOrderComplete }) {
  const navigate = useNavigate();
  const [orderCreated, setOrderCreated] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const [loading, setLoading] = useState(false);
  const { notify } = useNotifier();

  const [showCartSummary, setShowCartSummary] = useState(false);
  const [selectedItems, setSelectedItems] = useState(() => {
    const defaults = cartItems.map(item => item._id || item.id).filter(Boolean);
    return new Set(defaults);
  });

  const handleSubmit = async formData => {
    if (!selectedItems.size) {
      notify({ severity: 'warning', message: 'Select at least one item before checking out.' });
      return;
    }

    const itemsToPurchase = cartItems.filter(item => {
      const id = item._id || item.id;
      return id && selectedItems.has(id);
    });

    if (!itemsToPurchase.length) {
      notify({ severity: 'warning', message: 'Selected items are unavailable. Please refresh your cart.' });
      return;
    }

    setLoading(true);
    setErrorMessage('');

    try {
      const orderPayload = {
        ...formData,
        items: itemsToPurchase
          .map(item => ({
            productId: item._id || item.id,
            quantity: item.quantity || 1,
          }))
          .filter(item => item.productId),
      };

      if (!orderPayload.items.length) {
        setLoading(false);
        notify({ severity: 'error', message: 'Unable to place order: missing product information.' });
        return;
      }

      const { data } = await withRetry(() => apiClient.post('checkout/create-order', orderPayload));

      const normalizedEmail = formData.email?.trim() || '';

      setLoading(false);
      setOrderCreated(true);
      onOrderComplete?.();

      if (data?.orderNumber) {
        try {
          localStorage.setItem('fusionLastOrder', JSON.stringify({ orderNumber: data.orderNumber, email: normalizedEmail }));
        } catch (storageError) {
          console.warn('Unable to persist last order reference', storageError);
        }
      }

      notify({ severity: 'success', message: 'Order placed successfully! Redirecting…' });

      navigate('/order-success', {
        state: {
          orderNumber: data?.orderNumber,
          email: normalizedEmail,
          items: data?.items,
          total: data?.total,
        },
      });
    } catch (error) {
      console.error('Error creating order:', error);
      setLoading(false);
      const message = error?.response?.data?.error || error?.response?.data?.message || (error?.response?.status === 413 ? 'Payment evidence is too large. Please use a smaller screenshot or enter the TID instead.' : error?.response?.status === 409 ? 'This order could not be confirmed because the selected item is no longer available. Please refresh your cart and try again.' : 'Something went wrong while placing your order.');
      setErrorMessage(message);
      notify({ severity: 'error', message });
    }
  };

  const itemsToShow = cartItems.filter(item => {
    const id = item._id || item.id;
    return id && selectedItems.has(id);
  });
  const total = itemsToShow.reduce((sum, item) => sum + (Number(item.price) || 0) * (Number(item.quantity) || 1), 0);
  const allSelected = cartItems.every(item => selectedItems.has(item._id || item.id));

  const toggleItem = id => {
    if (!id) return;
    setSelectedItems(prev => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const toggleAll = () => {
    setSelectedItems(prev => {
      if (prev.size === cartItems.length) {
        return new Set();
      }
      return new Set(cartItems.map(item => item._id || item.id).filter(Boolean));
    });
  };

  return (
    <Container maxWidth="xl" className="luxury-page luxury-checkout luxury-checkout-v10" sx={{ pb: 10, pt: { xs: 2, md: 5 } }}>
      <Box sx={{ mb: { xs: 3, md: 5 } }}>
        <Stack direction="row" spacing={2} alignItems="center" sx={{ mb: 3 }}>
          <Box sx={{ width: 52, height: 52, display: 'grid', placeItems: 'center', borderRadius: 2.5, bgcolor: 'primary.main', color: 'primary.contrastText' }}><ShoppingCartCheckoutIcon /></Box>
          <Box>
            <Typography variant="overline" color="primary.main" fontWeight={700} letterSpacing={1.5}>FASTXHORIZON SECURE CHECKOUT</Typography>
            <Typography variant="h3" sx={{ fontWeight: 800, lineHeight: 1.1 }}>Complete your order</Typography>
            <Typography color="text.secondary" sx={{ mt: .75 }}>Review your items, provide delivery details and choose a payment method.</Typography>
          </Box>
        </Stack>
        <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', gap: 1 }}>
          {['1  Cart review', '2  Delivery details', '3  Payment confirmation'].map((step, index) => <Box key={step} sx={{ px: 1.5, py: .75, borderRadius: 10, bgcolor: index === 0 ? 'primary.main' : 'action.hover', color: index === 0 ? 'primary.contrastText' : 'text.secondary', fontSize: 13, fontWeight: 600 }}>{step}</Box>)}
        </Stack>
      </Box>

        {cartItems.length > 0 && (
            <Paper elevation={0} sx={{ mb: { xs: 3, md: 0 }, borderRadius: 3, background: 'rgba(40,116,240,0.06)', p: 2 }}>
            <Stack direction="row" spacing={1} alignItems="center">
              <ShoppingBagIcon color="primary" />
              <Typography variant="subtitle1" fontWeight={600}>
                Selected items ({itemsToShow.length}/{cartItems.length})
              </Typography>
              <Tooltip title={allSelected ? 'Deselect all items' : 'Select all items'}>
                <Checkbox
                  color="primary"
                  checked={allSelected}
                  indeterminate={selectedItems.size > 0 && !allSelected}
                  onChange={toggleAll}
                  sx={{ ml: 'auto' }}
                />
              </Tooltip>
              <IconButton size="small" onClick={() => setShowCartSummary(prev => !prev)}>
                <ExpandMoreIcon
                  sx={{
                    transform: showCartSummary ? 'rotate(180deg)' : 'none',
                    transition: 'transform 0.2s ease',
                  }}
                />
              </IconButton>
            </Stack>
            <Collapse in={showCartSummary} timeout="auto" unmountOnExit>
              <List dense>
                {cartItems.map(item => {
                  const id = item._id || item.id;
                  if (!id) return null;
                  const checked = selectedItems.has(id);
                  return (
                    <ListItem key={id} secondaryAction={<Checkbox edge="end" color="primary" checked={checked} onChange={() => toggleItem(id)} />}>
                      <ListItemAvatar>
                        <Avatar src={resolveMediaUrl(item.image)} alt={item.name} variant="rounded" />
                      </ListItemAvatar>
                      <ListItemText primary={item.name} secondary={formatPrice(item.price || 0)} />
                    </ListItem>
                  );
                })}
                {selectedItems.size === 0 && (
                  <ListItem>
                    <ListItemText primary="No items selected. Choose at least one item to proceed." />
                  </ListItem>
                )}
              </List>
            </Collapse>
          </Paper>
        )}

        {orderCreated ? <Paper elevation={0} sx={{ p: 5, textAlign: 'center', borderRadius: 4 }}><ShoppingBagIcon color="primary" sx={{ fontSize: 48, mb: 1 }} /><Typography variant="h5" gutterBottom>Thank you for your order!</Typography><Typography color="text.secondary">Your order has been received. Redirecting you to the confirmation page.</Typography></Paper> : <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: 'minmax(0, 1.55fr) minmax(300px, .75fr)' }, gap: 3, alignItems: 'start' }}>
          <Paper elevation={0} sx={{ p: { xs: 2.5, md: 4 }, borderRadius: 4 }}>
            {loading && <CircularProgress size={22} sx={{ mb: 2 }} />}
            {errorMessage && <Box sx={{ mb: 3, p: 1.5, borderRadius: 2, bgcolor: 'error.main', color: 'error.contrastText' }}><Typography variant="body2">{errorMessage}</Typography></Box>}
            <CheckoutForm onSubmit={handleSubmit} submitting={loading} />
            <Stack direction="row" spacing={1} alignItems="center" sx={{ mt: 3, color: 'text.secondary' }}><LockIcon fontSize="small" /><Typography variant="caption">Your order details are transmitted securely. Payment evidence is reviewed by our team.</Typography></Stack>
          </Paper>
          <Paper elevation={0} sx={{ p: { xs: 2.5, md: 3 }, borderRadius: 4, position: { md: 'sticky' }, top: { md: 24 } }}>
            <Typography variant="h6" fontWeight={800}>Order summary</Typography><Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>{itemsToShow.length} selected item{itemsToShow.length !== 1 ? 's' : ''}</Typography>
            <Stack spacing={1.5}>{itemsToShow.map(item => <Stack key={item._id || item.id} direction="row" spacing={1.5} alignItems="center"><Avatar src={resolveMediaUrl(item.image)} alt="" variant="rounded" sx={{ width: 54, height: 54 }} /><Box sx={{ minWidth: 0, flex: 1 }}><Typography variant="body2" fontWeight={600} noWrap>{item.name}</Typography><Typography variant="caption" color="text.secondary">Qty {item.quantity || 1}</Typography></Box><Typography variant="body2" fontWeight={700}>{formatPrice((Number(item.price) || 0) * (Number(item.quantity) || 1))}</Typography></Stack>)}</Stack>
            <Divider sx={{ my: 2 }} /><Stack direction="row" justifyContent="space-between"><Typography fontWeight={700}>Total due</Typography><Typography variant="h6" color="primary.main" fontWeight={800}>{formatPrice(total)}</Typography></Stack>
          </Paper>
        </Box>}
    </Container>
  );
}

export default Checkout;
