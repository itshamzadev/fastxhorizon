import * as React from 'react';
import { Alert, Box, Button, Grid, Stack, Typography } from '@mui/material';
import FavoriteBorderRoundedIcon from '@mui/icons-material/FavoriteBorderRounded';
import ArrowForwardRoundedIcon from '@mui/icons-material/ArrowForwardRounded';
import { Link as RouterLink } from 'react-router-dom';
import ProductCard from '../components/ProductCard';
import { useWishlist } from '../context/WishlistContext';

export default function Wishlist({ addToCart }) {
  const { wishlist, wishlistLoading, wishlistError } = useWishlist();

  return (
    <Box sx={{ py: { xs: 2, md: 5 } }}>
      <Stack direction={{ xs: 'column', sm: 'row' }} alignItems={{ xs: 'flex-start', sm: 'flex-end' }} justifyContent="space-between" spacing={2} sx={{ mb: 4 }}>
        <Box>
          <Typography component="span" sx={{ color: 'primary.main', fontSize: 12, fontWeight: 700, letterSpacing: '0.18em', textTransform: 'uppercase' }}>Saved for later</Typography>
          <Typography variant="h3" sx={{ mt: 0.5, fontWeight: 800 }}>Your Wishlist</Typography>
          <Typography color="text.secondary" sx={{ mt: 0.5 }}>Keep the products you love close.</Typography>
        </Box>
        <Button component={RouterLink} to="/shop" variant="outlined" endIcon={<ArrowForwardRoundedIcon />}>Continue shopping</Button>
      </Stack>

      {wishlistError && <Alert severity="error" sx={{ mb: 2, borderRadius: 3 }}>Unable to load your wishlist. Refresh and try again.</Alert>}
      {wishlistLoading ? (
        <Alert severity="info" sx={{ borderRadius: 3, py: 2 }}>Loading your saved products…</Alert>
      ) : wishlist.length === 0 ? (
        <Alert severity="info" icon={<FavoriteBorderRoundedIcon />} sx={{ borderRadius: 3, py: 2 }}>
          Your wishlist is empty. Tap the heart on a product to save it here.
        </Alert>
      ) : (
        <Grid container spacing={{ xs: 1.25, sm: 1.5, md: 2 }} sx={{ '& > .MuiGrid-item': { minWidth: 0, width: '100%', '@media (min-width:1920px)': { flexBasis: '12.5%', maxWidth: '12.5%' } } }}>
          {wishlist.map(product => (
            <Grid item key={product._id || product.id} xs={6} sm={6} md={4} lg={3} xl={2}>
              <ProductCard product={product} addToCart={addToCart} />
            </Grid>
          ))}
        </Grid>
      )}
    </Box>
  );
}
