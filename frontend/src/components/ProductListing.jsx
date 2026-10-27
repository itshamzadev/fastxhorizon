import * as React from 'react';
import { Grid } from '@mui/material';
import ProductCard from './ProductCard';

export default function ProductListing({ products, addToCart }) {
  return (
    <Grid container spacing={{ xs: 1.25, sm: 1.5, md: 2 }} sx={{ '& > .MuiGrid-item': { minWidth: 0, width: '100%', '@media (min-width:1920px)': { flexBasis: '12.5%', maxWidth: '12.5%' } } }}>
      {products.map(product => (
        <Grid item key={product.id} xs={6} sm={6} md={4} lg={3} xl={2}>
          <ProductCard product={product} addToCart={addToCart} />
        </Grid>
      ))}
    </Grid>
  );
}
