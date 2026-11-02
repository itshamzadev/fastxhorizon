import * as React from 'react';
import { Alert, Box, Button, CircularProgress, Container, Grid, Paper, Stack, Typography } from '@mui/material';
import ArrowForwardRoundedIcon from '@mui/icons-material/ArrowForwardRounded';
import NorthEastRoundedIcon from '@mui/icons-material/NorthEastRounded';
import ProductCard from '../components/ProductCard';
import { Link as RouterLink } from 'react-router-dom';
import '../App.css';
import { resolveMediaUrl } from '../utils/media';

const MARKETPLACE_EDITORIAL = '/editorial/marketplace-tech.jpg';
const CATEGORY_FALLBACKS = [MARKETPLACE_EDITORIAL];

function imageSource(product) {
  const value = String(product?.image || '').trim();
  if (!value) return MARKETPLACE_EDITORIAL;
  return resolveMediaUrl(value);
}

function categoryLabel(value) { return String(value || 'Collection').replace(/&/g, ' & ').replace(/\s+/g, ' ').trim(); }

function buildCategories(products) {
  const grouped = new Map();
  products.forEach(product => {
    const name = categoryLabel(product?.category);
    if (!name || name === 'Collection') return;
    const current = grouped.get(name) || { name, count: 0, image: imageSource(product) };
    current.count += 1;
    grouped.set(name, current);
  });
  return Array.from(grouped.values()).slice(0, 6).map((item, index) => ({ ...item, image: item.image || CATEGORY_FALLBACKS[index % CATEGORY_FALLBACKS.length] }));
}

function SectionIntro({ eyebrow, title, description, action, dark = false }) {
  return <Stack className={dark ? 'luxury-intro luxury-intro--dark' : 'luxury-intro'} direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ xs: 'flex-start', sm: 'flex-end' }} spacing={2}><Box><Typography className="luxury-eyebrow">{eyebrow}</Typography><Typography className="luxury-section-title">{title}</Typography>{description && <Typography className="luxury-section-copy">{description}</Typography>}</Box>{action}</Stack>;
}

export default function HomePremium({ products = [], randomProducts = [], addToCart, error, loading, randomLoading, randomError }) {
  const catalog = React.useMemo(() => (randomProducts.length ? randomProducts : products), [randomProducts, products]);
  // Keep category totals based on the complete public catalog; only the product
  // cards use the bounded random discovery selection.
  const categories = React.useMemo(() => buildCategories(products.length ? products : catalog), [products, catalog]);
  const selectedProducts = catalog.slice(0, 8);
  const featureProduct = selectedProducts[1] || selectedProducts[0];
  if (loading && !products.length && !randomProducts.length) return <Box className="luxury-loading"><CircularProgress /></Box>;

  return <Box className="luxury-home luxury-home--v2">
    <section className="luxury-campaign-hero"><Box className="luxury-campaign-hero__image" component="img" src={MARKETPLACE_EDITORIAL} alt="A considered assortment of everyday technology and lifestyle objects" loading="eager" /><Box className="luxury-campaign-hero__veil" /><Container maxWidth="xl" className="luxury-campaign-hero__inner"><Box className="luxury-campaign-hero__index">01 <span /> THE MARKETPLACE EDIT</Box><Box className="luxury-campaign-hero__copy"><Typography className="luxury-eyebrow luxury-eyebrow--light">A WIDER WAY TO SHOP</Typography><Typography component="h1">Everything,<br /><em>considered.</em></Typography><Typography className="luxury-campaign-hero__description">A premium destination for the things that make life move, feel and function beautifully.</Typography><Button component={RouterLink} to="/shop" className="luxury-button luxury-button--light" endIcon={<ArrowForwardRoundedIcon />}>Explore the marketplace</Button></Box><Box className="luxury-campaign-hero__footer"><span>FASTXHORIZON / 2026</span><span>Discover across categories</span></Box></Container></section>
    <Container maxWidth="xl" className="luxury-home__content">
      {error && <Alert severity="error" sx={{ mb: 3 }}>{error.message || 'Unable to load the current catalog.'}</Alert>}
      <section className="luxury-introduction"><Typography className="luxury-eyebrow">The marketplace edit</Typography><Typography component="h2">More to discover,<br /><em>beautifully considered.</em></Typography><Typography>Explore useful, expressive and beautifully presented finds across the categories currently represented in our catalog.</Typography><Button component={RouterLink} to="/about" className="luxury-text-link" endIcon={<NorthEastRoundedIcon />}>Our point of view</Button></section>
      <section className="luxury-category-editorial"><SectionIntro eyebrow="Explore" title="A world in considered detail." description="Move through the collections currently available at FastXHorizon." action={<Button component={RouterLink} to="/shop" className="luxury-text-link" endIcon={<ArrowForwardRoundedIcon />}>View all products</Button>} />{!categories.length && <Paper className="luxury-empty">Collections will appear here as products are published.</Paper>}<Grid container spacing={{ xs: 1, md: 1.5 }} className="luxury-category-grid">{categories.map((category, index) => <Grid item xs={6} md={index === 0 ? 5 : 3.5} key={category.name}><RouterLink to={`/shop?category=${encodeURIComponent(category.name)}`} className={`luxury-category-card luxury-category-card--${index % 3}`}><Box component="img" src={category.image} alt={`${category.name} collection`} loading="lazy" /><Box className="luxury-category-card__overlay"><Typography>{category.name}</Typography><small>{String(category.count).padStart(2, '0')} available</small></Box></RouterLink></Grid>)}</Grid></section>
      <section className="luxury-collection-band"><Box component="img" src={MARKETPLACE_EDITORIAL} alt="Curated technology and lifestyle objects arranged for everyday discovery" className="luxury-collection-band__image" loading="lazy" /><Box className="luxury-collection-band__label">FASTXHORIZON<br />/ 02</Box><Box className="luxury-collection-band__words"><span>DISCOVER</span><span>CHOOSE</span><span>LIVE</span></Box><Typography component="h2">For every part<br /><em>of your day.</em></Typography><Button component={RouterLink} to="/shop" className="luxury-button luxury-button--outline-light" endIcon={<ArrowForwardRoundedIcon />}>Explore all categories</Button></section>
      <section className="luxury-product-edit"><SectionIntro eyebrow="Selected for you" title="The considered edit." description="Real products from the current public catalog, presented without the noise." dark action={<Button component={RouterLink} to="/shop" className="luxury-text-link luxury-text-link--light" endIcon={<ArrowForwardRoundedIcon />}>Shop the edit</Button>} />{randomError && <Alert severity="info" sx={{ mb: 2 }}>The curated selection is temporarily unavailable. Showing the current catalog instead.</Alert>}{randomLoading && <Box className="luxury-inline-loading"><CircularProgress size={24} /></Box>}{!randomLoading && selectedProducts.length > 0 && <Grid container className="product-grid" spacing={0}>{selectedProducts.map(product => <Grid item key={product.id || product._id} xs={6} sm={6} md={4} lg={3} xl={2}><ProductCard product={product} addToCart={addToCart} /></Grid>)}</Grid>}{!randomLoading && !selectedProducts.length && <Paper className="luxury-empty">No published products are available yet.</Paper>}</section>
      <section className="luxury-story-grid"><Box className="luxury-story-grid__portrait"><Box component="img" src={MARKETPLACE_EDITORIAL} alt="A curated assortment of everyday technology and lifestyle objects" loading="lazy" /><span>03 / EDITORIAL ASSORTMENT</span></Box><Box className="luxury-story-grid__copy"><Typography className="luxury-eyebrow">The FastXHorizon point of view</Typography><Typography component="h2">A marketplace made to be explored.</Typography><Typography>From useful technology and home essentials to accessories and personal finds, we make room for products that earn their place.</Typography><Button component={RouterLink} to="/about" className="luxury-button luxury-button--dark" endIcon={<ArrowForwardRoundedIcon />}>Our story</Button></Box><Box className="luxury-story-grid__detail">{featureProduct && <Box component="img" src={imageSource(featureProduct)} alt={featureProduct.name || 'Current catalog product'} loading="lazy" />}<Typography>04 / FROM THE CURRENT CATALOG</Typography></Box></section>
      <section className="luxury-more-edit"><SectionIntro eyebrow="Keep exploring" title="Find what feels like you." description="A living selection that changes as our marketplace grows." /><Grid container className="product-grid" spacing={0}>{selectedProducts.slice(3, 8).map(product => <Grid item key={`more-${product.id || product._id}`} xs={6} sm={6} md={4} lg={3} xl={2}><ProductCard product={product} addToCart={addToCart} /></Grid>)}</Grid></section>
      <section className="luxury-trust luxury-trust--v2"><Box><Typography className="luxury-eyebrow">Browse with clarity</Typography><Typography component="h2">Beautifully presented.<br />Truthfully described.</Typography></Box><Box><Typography>FastXHorizon is currently focused on product discovery. Online ordering and payments remain paused while the marketplace continues to grow.</Typography><Button component={RouterLink} to="/support" className="luxury-text-link" endIcon={<ArrowForwardRoundedIcon />}>Visit support</Button></Box></section>
    </Container>
  </Box>;
}
