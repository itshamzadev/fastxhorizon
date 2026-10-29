import React from 'react';
import { Alert, Avatar, Box, Button, Container, Divider, IconButton, List, ListItem, ListItemAvatar, ListItemText, Stack, Typography } from '@mui/material';
import DeleteOutlineRoundedIcon from '@mui/icons-material/DeleteOutlineRounded';
import ArrowBackRoundedIcon from '@mui/icons-material/ArrowBackRounded';
import LockOutlinedIcon from '@mui/icons-material/LockOutlined';
import { useNavigate } from 'react-router-dom';
import { useNotifier } from '../context/NotificationProvider';
import { apiClient } from '../services/apiClient';
import { formatPrice } from '../utils/currency';
import { getCustomerToken } from '../services/wishlist';

function Cart({ cart, setCart }) {
  const navigate = useNavigate();
  const { notify } = useNotifier();
  const [ordersEnabled, setOrdersEnabled] = React.useState(false);
  const [configLoading, setConfigLoading] = React.useState(true);

  React.useEffect(() => {
    apiClient.get('payments/config').then(({ data: response }) => setOrdersEnabled((response?.data || response)?.ordersEnabled === true)).catch(() => setOrdersEnabled(false)).finally(() => setConfigLoading(false));
  }, []);
  const removeFromCart = productId => { setCart(cart.filter(item => item.id !== productId && item._id !== productId)); notify({ severity: 'info', message: 'Removed from cart.' }); };
  const total = cart.reduce((sum, item) => sum + (Number(item.price) || 0) * (Number(item.quantity) || 1), 0);
  const itemCount = cart.reduce((sum, item) => sum + (Number(item.quantity) || 1), 0);
  const goToCheckout = () => navigate(getCustomerToken() ? '/checkout' : '/login?returnTo=%2Fcheckout');

  return <Container maxWidth="lg" className="luxury-page luxury-cart luxury-cart-v10" sx={{ pb: 8 }}>
    <Box className="luxury-cart-v10__intro"><Typography className="luxury-eyebrow">Your selection</Typography><Typography component="h1">Shopping cart</Typography><Typography>Keep your considered finds together before you decide what comes next.</Typography></Box>
    {cart.length === 0 ? <Box className="luxury-cart-v10__empty"><Typography className="luxury-eyebrow">Nothing here yet</Typography><Typography component="h2">Your cart is waiting to be discovered.</Typography><Typography>Explore our collections and find something worth keeping.</Typography><Button variant="contained" onClick={() => navigate('/shop')} endIcon={<ArrowBackRoundedIcon sx={{ transform: 'rotate(180deg)' }} />}>Explore Products</Button></Box> : <Box className="luxury-cart-v10__layout">
      <Box className="luxury-cart-v10__items"><Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 1.5 }}><Typography component="h2">Selected products</Typography><Typography variant="caption">{itemCount} item{itemCount === 1 ? '' : 's'}</Typography></Stack><List disablePadding>{cart.map(item => { const id = item.id || item._id; const quantity = Number(item.quantity) || 1; return <React.Fragment key={id}><ListItem className="luxury-cart-v10__item" disableGutters secondaryAction={<IconButton aria-label={`Remove ${item.name}`} onClick={() => removeFromCart(id)}><DeleteOutlineRoundedIcon /></IconButton>}><ListItemAvatar><Avatar src={item.image || item.images?.[0]} alt={item.name} variant="rounded" /></ListItemAvatar><ListItemText primary={item.name} secondary={<>{item.storeName || 'FASTXHORIZON'}{item.selectedVariant ? ` · ${Object.values(item.selectedVariant.options || {}).join(' / ')}` : ''} · {quantity} × {formatPrice(item.price || 0)}</>} /></ListItem><Divider /></React.Fragment>; })}</List><Button variant="text" startIcon={<ArrowBackRoundedIcon />} onClick={() => navigate('/shop')} sx={{ mt: 2 }}>Continue shopping</Button></Box>
      <Box className="luxury-cart-v10__summary"><Typography className="luxury-eyebrow">Order summary</Typography><Typography component="h2">A considered total</Typography><Stack direction="row" justifyContent="space-between" sx={{ mt: 3 }}><Typography>Items</Typography><Typography>{itemCount}</Typography></Stack><Divider sx={{ my: 2 }} /><Stack direction="row" justifyContent="space-between" alignItems="baseline"><Typography>Subtotal</Typography><Typography className="luxury-cart-v10__total">{formatPrice(total)}</Typography></Stack><Typography variant="caption" sx={{ display: 'block', mt: 2 }}>Shipping and any applicable order details are communicated when ordering is available.</Typography><Alert severity="info" icon={<LockOutlinedIcon />} sx={{ mt: 3 }}>Online ordering is currently unavailable.</Alert>{configLoading ? <Button disabled fullWidth sx={{ mt: 3 }}>Checking checkout…</Button> : ordersEnabled ? <Button variant="contained" fullWidth sx={{ mt: 3 }} onClick={goToCheckout}>{getCustomerToken() ? 'Continue to Checkout' : 'Sign In to Checkout'}</Button> : <Button disabled fullWidth sx={{ mt: 3 }}>Checkout unavailable</Button>}</Box>
    </Box>}
  </Container>;
}

export default Cart;
