import * as React from 'react';
import { Box, Button, CircularProgress, Container, Grid, Paper, Stack, Typography, Alert } from '@mui/material';
import ArrowForwardRoundedIcon from '@mui/icons-material/ArrowForwardRounded';
import NorthEastRoundedIcon from '@mui/icons-material/NorthEastRounded';
import ProductCard from '../components/ProductCard';
import { Link as RouterLink } from 'react-router-dom';
import '../App.css';

const FALLBACK_EDITORIAL = '/editorial/luxury-jewellery.jpg';
const TEXTILE_EDITORIAL = '/editorial/luxury-textile.jpg';
const CATEGORY_FALLBACKS = ['/editorial/luxury-jewellery.jpg', '/editorial/luxury-textile.jpg'];

function imageSource(product) {
  const value = String(product?.image || '').trim();
  if (!value) return FALLBACK_EDITORIAL;
  if (/^(https?:|data:|blob:|\/)/i.test(value)) return value;
  return `/${value.replace(/^\/+/, '')}`;
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

function SectionIntro({ eyebrow, title, description, action }) {
  return <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ xs: 'flex-start', sm: 'flex-end' }} spacing={2} sx={{ mb: { xs: 3, md: 4 } }}><Box><Typography className="luxury-eyebrow">{eyebrow}</Typography><Typography className="luxury-section-title">{title}</Typography>{description && <Typography className="luxury-section-copy">{description}</Typography>}</Box>{action}</Stack>;
}

export default function HomePremium({ products = [], randomProducts = [], addToCart, error, loading, randomLoading, randomError }) {
  const catalog = React.useMemo(() => (randomProducts.length ? randomProducts : products), [randomProducts, products]);
  const categories = React.useMemo(() => buildCategories(catalog), [catalog]);
  const selectedProducts = catalog.slice(0, 8);
  const heroProduct = selectedProducts[0];

  if (loading && !products.length && !randomProducts.length) return <Box className="luxury-loading"><CircularProgress /></Box>;

  return <Box className="luxury-home">
    <section className="luxury-hero"><Container maxWidth="xl" className="luxury-hero__inner"><Grid container spacing={{ xs: 3, md: 7 }} alignItems="center"><Grid item xs={12} md={6} lg={5}><Typography className="luxury-eyebrow luxury-eyebrow--light">FASTXHORIZON / CURATED DISCOVERY</Typography><Typography component="h1" className="luxury-hero__title">Discover more.<br /><em>Experience better.</em></Typography><Typography className="luxury-hero__copy">Thoughtfully selected products for the way you live, work and express your style.</Typography><Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ mt: 3 }}><Button component={RouterLink} to="/shop" variant="contained" endIcon={<ArrowForwardRoundedIcon />} className="luxury-button luxury-button--light">Explore collections</Button><Button component={RouterLink} to="/about" variant="text" endIcon={<NorthEastRoundedIcon />} className="luxury-button luxury-button--quiet">About FastXHorizon</Button></Stack></Grid><Grid item xs={12} md={6} lg={7}><Box className="luxury-hero__visual"><Box component="img" src={imageSource(heroProduct)} alt={heroProduct ? heroProduct.name : 'Editorial jewellery detail'} loading="eager" /><Box className="luxury-hero__caption"><span>01</span><span>{heroProduct ? 'From the current collection' : 'An evolving marketplace'}</span></Box></Box></Grid></Grid></Container></section>
    <Container maxWidth="xl" className="luxury-home__content">{error && <Alert severity="error" sx={{ mb: 3 }}>{error.message || 'Unable to load the current catalog.'}</Alert>}
      <section className="luxury-section luxury-section--categories"><SectionIntro eyebrow="Explore" title="Find your next favourite." description="Browse the categories currently represented in our public catalog." action={<Button component={RouterLink} to="/shop" className="luxury-text-link" endIcon={<ArrowForwardRoundedIcon />}>View all products</Button>} /><Grid container spacing={{ xs: 1.5, md: 2.5 }}>{categories.map((category, index) => <Grid item xs={6} sm={4} md={index === 0 ? 6 : 3} key={category.name}><RouterLink to={`/shop?category=${encodeURIComponent(category.name)}`} className="luxury-category-card"><Box component="img" src={category.image} alt={`${category.name} collection`} loading="lazy" /><Box className="luxury-category-card__overlay"><Typography>{category.name}</Typography><small>{category.count} available</small></Box></RouterLink></Grid>)}</Grid>{!categories.length && <Paper className="luxury-empty">Categories will appear here as products are published.</Paper>}</section>
      <section className="luxury-section"><SectionIntro eyebrow="Selected for you" title="A considered edit." description="Real products from the current FastXHorizon catalog, presented for effortless discovery." action={<Button component={RouterLink} to="/shop" className="luxury-text-link" endIcon={<ArrowForwardRoundedIcon />}>Shop the edit</Button>} />{randomError && <Alert severity="info" sx={{ mb: 2 }}>The curated selection is temporarily unavailable. Showing the current catalog instead.</Alert>}{randomLoading && <Box className="luxury-inline-loading"><CircularProgress size={24} /></Box>}{!randomLoading && selectedProducts.length > 0 && <Grid container className="product-grid" spacing={0}>{selectedProducts.map(product => <Grid item key={product.id || product._id} xs={6} sm={6} md={4} lg={3} xl={2}><ProductCard product={product} addToCart={addToCart} /></Grid>)}</Grid>}{!randomLoading && !selectedProducts.length && <Paper className="luxury-empty">No published products are available yet.</Paper>}</section>
      <section className="luxury-editorial"><Grid container spacing={0} alignItems="stretch"><Grid item xs={12} md={6}><Box component="img" src={TEXTILE_EDITORIAL} alt="Close-up of a refined textile texture" loading="lazy" className="luxury-editorial__image" /></Grid><Grid item xs={12} md={6}><Box className="luxury-editorial__copy"><Typography className="luxury-eyebrow">The FastXHorizon point of view</Typography><Typography component="h2">A more considered way to discover.</Typography><Typography>We bring together useful, expressive and thoughtfully presented products in one evolving marketplace. Explore the real catalog, compare what is available and choose at your own pace.</Typography><Button component={RouterLink} to="/about" className="luxury-button luxury-button--dark" endIcon={<ArrowForwardRoundedIcon />}>Our story</Button></Box></Grid></Grid></section>
      <section className="luxury-section luxury-section--more"><SectionIntro eyebrow="Keep exploring" title="Details that feel like you." description="From statement jewellery to practical everyday finds, discover what is currently available." /><Grid container className="product-grid" spacing={0}>{selectedProducts.slice(3, 8).map(product => <Grid item key={`more-${product.id || product._id}`} xs={6} sm={6} md={4} lg={3} xl={2}><ProductCard product={product} addToCart={addToCart} /></Grid>)}</Grid></section>
      <section className="luxury-trust"><Typography className="luxury-eyebrow">Browse with clarity</Typography><Typography component="h2">Real catalog. Clear information. No unnecessary promises.</Typography><Typography>FastXHorizon is currently focused on product discovery. Online ordering and payments remain paused while the marketplace continues to grow.</Typography><Button component={RouterLink} to="/support" className="luxury-text-link" endIcon={<ArrowForwardRoundedIcon />}>Visit support</Button></section>
    </Container>
  </Box>;
}
