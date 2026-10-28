import * as React from 'react';
import { Grid } from '@mui/material';
import ProductCard from './ProductCard';

export default function ProductListing({ products, addToCart }) {
  return (
    <Grid container className="product-grid" spacing={0}>
      {products.map(product => (
        <Grid item key={product.id} xs={6} sm={6} md={4} lg={3} xl={2}>
          <ProductCard product={product} addToCart={addToCart} />
        </Grid>
      ))}
    </Grid>
  );
}
