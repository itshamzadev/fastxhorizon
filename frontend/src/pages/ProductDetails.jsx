import React, { useCallback, useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Alert,
  AlertTitle,
  Box,
  Button,
  Chip,
  CircularProgress,
  Collapse,
  Container,
  Grid,
  Link,
  Paper,
  Rating,
  Stack,
  Typography,
  IconButton,
  Tab,
  Tabs,
  Dialog,
  DialogContent,
} from '@mui/material';
import CloudOffIcon from '@mui/icons-material/CloudOff';
import RefreshIcon from '@mui/icons-material/Refresh';
import OpenInNewIcon from '@mui/icons-material/OpenInNew';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import { formatPrice } from '../utils/currency';
import { apiClient, withRetry } from '../services/apiClient';
import { resolveMediaUrl } from '../utils/media';
import FavoriteBorderRoundedIcon from '@mui/icons-material/FavoriteBorderRounded';
import FavoriteRoundedIcon from '@mui/icons-material/FavoriteRounded';
import ShoppingCartRoundedIcon from '@mui/icons-material/ShoppingCartRounded';
import CloseRoundedIcon from '@mui/icons-material/CloseRounded';
import { useWishlist } from '../context/WishlistContext';
import ProductCard from '../components/ProductCard';

function SimilarProductsError({ onRetry }) {
  const [showDetails, setShowDetails] = React.useState(false);

  return (
    <Alert
      severity="warning"
      variant="outlined"
      icon={<CloudOffIcon fontSize="inherit" />}
      sx={{
        borderRadius: 2,
        borderWidth: 2,
        alignItems: 'center',
        background: theme => `linear-gradient(180deg, ${theme.palette.background.paper} 0%, ${theme.palette.action.hover} 100%)`,
        '& .MuiAlert-message': { width: '100%' },
      }}
      action={
        <Stack direction="row" spacing={1}>
          <Button size="small" startIcon={<RefreshIcon />} onClick={onRetry}>
            Retry
          </Button>
          <Button size="small" component={Link} href="https://weaviate.io" target="_blank" rel="noopener" endIcon={<OpenInNewIcon />}>
            Docs
          </Button>
        </Stack>
      }
    >
      <AlertTitle>Similar products unavailable</AlertTitle>
      We couldn’t load recommendations right now. This often happens when the vector database is unreachable. Please try again shortly.
      <Box sx={{ mt: 1 }}>
        <Button
          size="small"
          endIcon={
            <ExpandMoreIcon
              sx={{
                transform: showDetails ? 'rotate(180deg)' : 'none',
                transition: '0.2s',
              }}
            />
          }
          onClick={() => setShowDetails(v => !v)}
        >
          {showDetails ? 'Hide details' : 'Show details'}
        </Button>
        <Collapse in={showDetails}>
          <Paper
            variant="outlined"
            sx={{
              p: 1.5,
              mt: 1,
              bgcolor: theme => theme.palette.action.hover,
              fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace',
              fontSize: 12,
              whiteSpace: 'pre-wrap',
              wordBreak: 'break-word',
            }}
          >
            Weaviate cluster has been suspended. Please create your own free cluster at https://weaviate.io/developers/weaviate/installation/cloud and update
            the API URL in <code>.env</code> to restore recommendations.
          </Paper>
        </Collapse>
      </Box>
    </Alert>
  );
}

function ProductDetails({ addToCart }) {
  const { id } = useParams();
  const navigate = useNavigate();
  const [product, setProduct] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [recommended, setRecommended] = useState([]);
  const [recLoading, setRecLoading] = useState(true);
  const [similarError, setSimilarError] = useState(false);
  const [galleryIndex, setGalleryIndex] = useState(0);
  const [selectedOptions, setSelectedOptions] = useState({});
  const [ordersEnabled, setOrdersEnabled] = useState(false);
  const [tab, setTab] = useState(0);
  const [zoomOpen, setZoomOpen] = useState(false);
  const { wishlist, toggleWishlist, wishlistPending } = useWishlist();

  const normalizeProduct = useCallback(prod => {
    if (!prod || typeof prod !== 'object') return null;
    const candidate = prod._id ?? prod.id ?? prod.mongoId ?? prod?.metadata?.mongoId;
    const normalizedId = candidate !== undefined && candidate !== null ? `${candidate}` : undefined;

    return normalizedId
      ? {
          ...prod,
          id: normalizedId,
          _id: normalizedId,
        }
      : { ...prod };
  }, []);

  const recordVisit = useCallback(
    prod => {
      try {
        const normalized = normalizeProduct(prod);
        if (!normalized?.id) return;

        const key = 'visitedProducts';
        const raw = localStorage.getItem(key);
        const parsed = raw ? JSON.parse(raw) : [];
        const stored = Array.isArray(parsed) ? parsed : [];
        const filtered = stored.filter(item => item.id !== normalized.id);
        const next = [
          ...filtered,
          {
            id: normalized.id,
            name: normalized.name,
            image: normalized.image,
            price: normalized.price,
            visitedAt: Date.now(),
          },
        ].slice(-12);
        localStorage.setItem(key, JSON.stringify(next));
      } catch (storageError) {
        console.warn('Unable to track visited product', storageError);
      }
    },
    [normalizeProduct]
  );

  const fetchRecommended = useCallback(async () => {
    setRecLoading(true);
    setSimilarError(false);
    try {
      const { data: recs } = await withRetry(() => apiClient.get(`products/${id}/similar`));
      if (!Array.isArray(recs)) {
        setRecommended([]);
        return;
      }
      const normalized = recs
        .map(item => normalizeProduct(item))
        .filter(Boolean)
        .filter((item, index, self) => item.id && self.findIndex(other => other.id === item.id) === index)
        .filter(item => item.id !== `${id}`);
      setRecommended(normalized);
    } catch (err) {
      console.error('Error fetching recommendations:', err);
      setSimilarError(true);
      setRecommended([]);
    } finally {
      setRecLoading(false);
    }
  }, [id, normalizeProduct]);

  const fetchProduct = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await withRetry(() => apiClient.get(`products/${id}`));
      const normalized = normalizeProduct(data);
      if (!normalized?.id) {
        throw new Error('Product not found');
      }
      setProduct(normalized);
      recordVisit(normalized);
      fetchRecommended();
    } catch (err) {
      console.error('Error fetching product details:', err);
      setProduct(null);
      setError(err);
      if (err?.response?.status === 404) {
        try {
          const key = 'visitedProducts';
          const raw = localStorage.getItem(key);
          if (raw) {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
              const filtered = parsed.filter(item => item?.id !== id);
              localStorage.setItem(key, JSON.stringify(filtered));
            }
          }
        } catch (storageError) {
          console.warn('Unable to prune visitedProducts cache', storageError);
        }
      }
    } finally {
      setLoading(false);
    }
  }, [fetchRecommended, id, normalizeProduct, recordVisit]);

  useEffect(() => {
    fetchProduct();
  }, [fetchProduct]);

  useEffect(() => {
    apiClient.get('payments/config').then(({ data }) => setOrdersEnabled(data?.data?.ordersEnabled === true || data?.ordersEnabled === true)).catch(() => setOrdersEnabled(false));
  }, []);

  useEffect(() => { setGalleryIndex(0); setSelectedOptions({}); }, [product?.id]);

  const formatCategory = value => {
    if (typeof value !== 'string' || !value.length) {
      return 'Uncategorized';
    }
    return value.charAt(0).toUpperCase() + value.slice(1);
  };

  const images = (product?.images?.length ? product.images : [product?.image]).filter(Boolean);
  const variants = Array.isArray(product?.variants) ? product.variants : [];
  const variantAttributes = Array.isArray(product?.variantAttributes) ? product.variantAttributes : [];
  const selectionComplete = variantAttributes.length > 0 && variantAttributes.every(attribute => selectedOptions[attribute.name]);
  const selectedVariant = selectionComplete ? (variants.find(variant => Object.entries(selectedOptions).every(([key, value]) => variant.options?.[key] === value)) || null) : null;
  const displayedPrice = selectedVariant?.price ?? product?.price;
  const displayedSku = selectedVariant?.sku || product?.sku;
  const displayedImages = selectedVariant?.images?.length ? selectedVariant.images : images;
  const supplierProduct = product?.inventoryMode === 'SUPPLIER_BASED';
  const supplierVariant = selectedVariant?.inventoryMode === 'SUPPLIER_BASED';
  const productAvailable = supplierProduct ? product.availabilityStatus === 'AVAILABLE_TO_ORDER' : (['AVAILABILITY_BASED', 'AVAILABILITY'].includes(product?.inventoryMode) ? product.availabilityStatus === 'IN_STOCK' : Number(product?.stock) > 0);
  const variantAvailable = selectedVariant ? (selectedVariant.inventoryMode === 'SUPPLIER_BASED' ? selectedVariant.availabilityStatus === 'AVAILABLE_TO_ORDER' : (selectedVariant.inventoryMode === 'AVAILABILITY_BASED' ? selectedVariant.availabilityStatus === 'IN_STOCK' : Number(selectedVariant.stock) > 0)) : productAvailable;
  const isWishlisted = wishlist.some(item => String(item?._id || item?.id) === String(product?.id));
  const handleAddToCart = useCallback(() => { if (product && ordersEnabled && variantAvailable) addToCart({ ...product, price: displayedPrice, sku: displayedSku, image: displayedImages[0] || product.image, selectedVariantId: selectedVariant?.id, selectedVariant }); }, [addToCart, displayedImages, displayedPrice, displayedSku, ordersEnabled, product, selectedVariant, variantAvailable]);

  if (loading) {
    return (
      <Container sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '50vh' }}>
        <CircularProgress />
      </Container>
    );
  }

  if (error || !product) {
    return (
      <Container maxWidth="sm" sx={{ py: 10 }}>
        <Paper elevation={0} sx={{ p: { xs: 3, md: 5 }, textAlign: 'center', borderRadius: 4 }}>
          <Typography variant="h4" gutterBottom>
            We could not load this product.
          </Typography>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
            The product may have been removed or is temporarily unavailable. Please refresh or browse the latest releases.
          </Typography>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} justifyContent="center">
            <Button variant="contained" onClick={fetchProduct} startIcon={<RefreshIcon />}>
              Try again
            </Button>
            <Button variant="outlined" onClick={() => navigate('/shop')}>
              Back to shop
            </Button>
          </Stack>
        </Paper>
      </Container>
    );
  }

  return (
    <Box className="product-details-v7 product-details-v8" sx={{ minHeight: '100vh', color: '#292D32', background: '#F4F0E9', py: { xs: 2, md: 4 } }}>
    <Container maxWidth="xl" sx={{ pb: 8 }}>
      <Typography className="product-details-v8__breadcrumb" variant="body2" sx={{ mb: 2, color: '#667085' }}>
        Home / {formatCategory(product.category)} / Product
      </Typography>
      <Box className="product-details-v7__main product-details-v8__showcase" sx={{ position: 'relative', pb: { xs: 2, md: 4 }, borderBottom: '1px solid #D7DEE8' }}>
        <Grid container spacing={{ xs: 3, md: 6 }}>
          <Grid item xs={12} md={7} className="product-details-v7__gallery-column product-details-v8__gallery-column">
            <Box className="product-details-v7__gallery-layout product-details-v8__gallery-layout" sx={{ display: 'flex', flexDirection: { xs: 'column', md: 'row' }, gap: { xs: 1, md: 1.5 }, alignItems: 'stretch' }}>
            <Box className="product-details-v7__gallery-stage product-details-v8__gallery-stage" sx={{ position: 'relative', flex: 1, bgcolor: '#E8EDF3', border: '1px solid #C5A47E', borderRadius: { xs: 3, md: 5 }, minHeight: { xs: 290, md: 470 }, display: 'flex', alignItems: 'center', justifyContent: 'center', overflow: 'hidden', boxShadow: '0 18px 45px rgba(41,45,50,.10)' }}>
              {displayedImages.length ? <Box component="button" type="button" onClick={() => setZoomOpen(true)} aria-label="Open enlarged product image" sx={{ display: 'block', border: 0, p: { xs: 1, md: 2 }, bgcolor: 'transparent', cursor: 'zoom-in', width: '100%' }}><Box component="img" src={resolveMediaUrl(displayedImages[galleryIndex % displayedImages.length])} alt={product.name} sx={{ display: 'block', mx: 'auto', width: '100%', height: { xs: 285, md: 465 }, objectFit: 'contain', transition: 'transform .25s ease', '&:hover': { transform: 'scale(1.035)' } }} /></Box> : <Typography color="text.secondary">Image unavailable</Typography>}
              {displayedImages.length > 0 && <Box sx={{ position: 'absolute', right: 14, bottom: 14, px: 1, py: .45, borderRadius: 2, bgcolor: 'rgba(7,26,61,.82)', color: '#fff', fontSize: 12, fontWeight: 700 }}>{galleryIndex + 1} / {displayedImages.length}</Box>}
              {displayedImages.length > 1 && <>
                <IconButton aria-label="Previous product image" onClick={() => setGalleryIndex(index => (index - 1 + displayedImages.length) % displayedImages.length)} sx={{ position: 'absolute', left: 14, top: '50%', bgcolor: '#FFFFFF', color: '#0B1735', boxShadow: '0 8px 20px rgba(11,23,53,.14)', '&:hover': { bgcolor: '#155EEF', color: '#fff', transform: 'translateX(-2px)' } }}>‹</IconButton>
                <IconButton aria-label="Next product image" onClick={() => setGalleryIndex(index => (index + 1) % displayedImages.length)} sx={{ position: 'absolute', right: 14, top: '50%', bgcolor: '#FFFFFF', color: '#0B1735', boxShadow: '0 8px 20px rgba(11,23,53,.14)', '&:hover': { bgcolor: '#155EEF', color: '#fff', transform: 'translateX(2px)' } }}>›</IconButton>
              </>}
            </Box>
            <Stack direction={{ xs: 'row', md: 'column' }} spacing={1} sx={{ mt: { xs: 1.25, md: 0 }, overflowX: { xs: 'auto', md: 'visible' }, overflowY: { xs: 'visible', md: 'auto' }, pb: .5, flex: { md: '0 0 68px' }, maxHeight: { md: 470 }, scrollbarWidth: 'thin' }}>
              {displayedImages.map((image, index) => <Box key={`${image}-${index}`} component="button" type="button" onClick={() => setGalleryIndex(index)} aria-label={`View product image ${index + 1}`} sx={{ border: index === galleryIndex ? '2px solid #155EEF' : '1px solid #DCE6F4', borderRadius: 2.5, p: .35, bgcolor: '#fff', flex: '0 0 64px', cursor: 'pointer', transition: 'transform .2s ease, box-shadow .2s ease', '&:hover': { transform: 'translateY(-2px)', boxShadow: '0 6px 14px rgba(21,94,239,.16)' } }}><Box component="img" src={resolveMediaUrl(image)} alt="" sx={{ width: 58, height: 58, objectFit: 'cover', borderRadius: 1.5 }} /></Box>)}
            </Stack>
            </Box>
          </Grid>

          <Grid item xs={12} md={5} className="product-details-v7__info-column product-details-v8__info-column" sx={{ bgcolor: 'rgba(255,255,255,.86)', border: '1px solid #D7DEE8', borderRadius: { xs: 3, md: 4 }, p: { xs: 2.5, md: 3.5 }, boxShadow: '0 16px 38px rgba(32,42,60,.10)' }}>
            <Typography variant="overline" sx={{ color: '#2359CE', letterSpacing: '.14em', fontWeight: 800 }}>{formatCategory(product.category)} · {product.storeName || 'FASTXHORIZON'}</Typography>
            <Typography component="h1" sx={{ color: '#202A3C', fontSize: { xs: '1.45rem', sm: '1.7rem', md: '2rem' }, fontWeight: 750, lineHeight: 1.2, letterSpacing: '-.02em', mb: 1, display: '-webkit-box', WebkitLineClamp: 4, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>{product.name}</Typography>
            {displayedSku && <Typography variant="body2" sx={{ color: '#667085', mb: 1 }}>SKU: {displayedSku}</Typography>}
            <Stack direction="row" alignItems="center" spacing={1} sx={{ mb: 1 }}>{product.numReviews > 0 ? <><Rating value={product.rating || 0} precision={.1} readOnly size="small" /><Typography variant="body2" sx={{ color: '#667085' }}>{Number(product.rating || 0).toFixed(1)} ({product.numReviews})</Typography></> : <Typography variant="body2" sx={{ color: '#667085' }}>No reviews yet</Typography>}</Stack>
            <Box sx={{ mb: 1.5, pb: 1.5, borderBottom: '1px solid #D7DEE8' }}><Typography variant="caption" sx={{ display: 'block', color: '#667085', letterSpacing: '.12em', textTransform: 'uppercase' }}>Price</Typography><Typography variant="h4" sx={{ color: '#2359CE', fontSize: { xs: '1.65rem', md: '2rem' }, fontWeight: 850 }}>{formatPrice(displayedPrice)}</Typography></Box>
            <Typography sx={{ color: '#4B5567', mb: 2, display: '-webkit-box', WebkitLineClamp: 3, WebkitBoxOrient: 'vertical', overflow: 'hidden', lineHeight: 1.55 }}>{product.shortDescription || product.description}</Typography>

            {product.variantsEnabled && variantAttributes.length > 0 && <Stack spacing={2} sx={{ mb: 2 }}>{variantAttributes.filter(attribute => attribute.values?.length).map(attribute => <Box key={attribute.name}><Typography variant="subtitle2" sx={{ color: '#202A3C', mb: .75 }}>{attribute.name}: <b style={{ color: '#2359CE' }}>{selectedOptions[attribute.name] || 'Select'}</b></Typography><Stack direction="row" spacing={1} flexWrap="wrap">{attribute.values.map(value => { const candidate = { ...selectedOptions, [attribute.name]: value }; const available = variants.some(variant => variant.enabled !== false && Object.entries(candidate).every(([key, option]) => variant.options?.[key] === option)); return <Button key={value} size="small" variant={selectedOptions[attribute.name] === value ? 'contained' : 'outlined'} disabled={!available} onClick={() => { setSelectedOptions(current => ({ ...current, [attribute.name]: value })); setGalleryIndex(0); }} sx={{ color: selectedOptions[attribute.name] === value ? '#fff' : '#344054', borderColor: '#B8C5D8', '&:hover': { borderColor: '#2359CE', bgcolor: '#F1F0FA' }, '&.Mui-disabled': { color: '#AAB4C3', borderColor: '#E0E5ED' } }}>{value}</Button> })}</Stack></Box>)}</Stack>}

            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}><Typography variant="body2" sx={{ color: '#667085' }}>Availability:</Typography><Chip label={selectedVariant?.inventoryMode === 'SUPPLIER_BASED' || supplierProduct ? (variantAvailable ? 'Available to Order' : 'Out of Stock') : (variantAvailable ? 'In Stock' : 'Out of Stock')} color={variantAvailable ? 'success' : 'error'} size="small" sx={{ color: '#FFFFFF' }} /></Box>
            <Box sx={{ mb: 2, px: 1.5, py: 1.1, borderLeft: '3px solid #F4B86A', bgcolor: '#F1F0FA', borderRadius: 1.5 }}><Typography variant="body2" sx={{ color: '#202A3C', fontWeight: 750 }}>{supplierProduct || supplierVariant ? 'Available to Order' : (variantAvailable ? 'In Stock' : 'Out of Stock')}</Typography><Typography variant="caption" sx={{ color: '#667085' }}>{supplierProduct || supplierVariant ? 'Subject to supplier confirmation. Ordering is currently paused.' : (!ordersEnabled ? 'Online ordering is temporarily unavailable.' : 'Availability confirmed by FastXHORIZON.')}</Typography></Box>
            <Stack direction="row" spacing={1} sx={{ mb: 1 }}><Button variant="contained" startIcon={<ShoppingCartRoundedIcon />} onClick={handleAddToCart} disabled={!ordersEnabled || !variantAvailable || (product.variantsEnabled && variantAttributes.length > 0 && !selectedVariant)} sx={{ bgcolor: '#2359CE', '&:hover': { bgcolor: '#1745A6' } }}>Add to Cart</Button><IconButton aria-label={isWishlisted ? 'Remove from wishlist' : 'Add to wishlist'} aria-pressed={isWishlisted} disabled={wishlistPending.has(String(product.id))} onClick={() => toggleWishlist(product)} sx={{ color: isWishlisted ? '#D98A28' : '#2359CE', border: '1px solid #B8C5D8', '&:hover': { bgcolor: '#F1F0FA' } }}>{isWishlisted ? <FavoriteRoundedIcon /> : <FavoriteBorderRoundedIcon />}</IconButton></Stack>
          </Grid>
        </Grid>
      </Box>

      <Dialog open={zoomOpen} onClose={() => setZoomOpen(false)} maxWidth="lg" fullWidth aria-labelledby="product-image-dialog-title"><IconButton onClick={() => setZoomOpen(false)} aria-label="Close enlarged image" sx={{ position: 'absolute', right: 8, top: 8, zIndex: 1, bgcolor: 'rgba(255,255,255,.9)' }}><CloseRoundedIcon /></IconButton><DialogContent id="product-image-dialog-title" sx={{ p: { xs: 1, md: 3 }, bgcolor: '#F8FAFC', textAlign: 'center' }}>{displayedImages.length > 0 && <Box component="img" src={resolveMediaUrl(displayedImages[galleryIndex % displayedImages.length])} alt={`${product.name} enlarged`} sx={{ maxWidth: '100%', maxHeight: '80vh', objectFit: 'contain' }} />}</DialogContent></Dialog>

      <Box className="product-details-v7__details product-details-v8__details" sx={{ mt: 4, p: { xs: 2, md: 3 }, borderRadius: 4, bgcolor: 'rgba(255,255,255,.72)', border: '1px solid #D7DEE8', boxShadow: '0 12px 32px rgba(32,42,60,.07)' }}><Tabs value={tab} onChange={(_event, value) => setTab(value)} variant="scrollable" sx={{ '& .MuiTabs-indicator': { backgroundColor: '#2359CE', height: 3, borderRadius: 2 }, '& .MuiTab-root': { color: '#667085', '&.Mui-selected': { color: '#2359CE', fontWeight: 800 } } }}><Tab label="Description" /><Tab label="Specifications" /><Tab label="Reviews" /><Tab label="Shipping & Returns" /></Tabs>
        <Box sx={{ p: { xs: 1, md: 2 }, mt: 1, color: '#344054', borderTop: '1px solid #E0E5ED' }}>
          {tab === 0 && <Typography sx={{ whiteSpace: 'pre-line', lineHeight: 1.8 }}>{product.longDescription || product.description || 'No description available.'}</Typography>}
          {tab === 1 && <Stack spacing={1.25}><Typography><b style={{ color: '#2359CE' }}>SKU:</b> {displayedSku || 'Not specified'}</Typography><Typography><b style={{ color: '#2359CE' }}>Category:</b> {formatCategory(product.category)}</Typography>{product.brand && <Typography><b style={{ color: '#2359CE' }}>Brand:</b> {product.brand}</Typography>}{Array.isArray(product.specifications) && product.specifications.filter(item => item?.value).map(item => <Typography key={item.key}><b style={{ color: '#2359CE' }}>{item.label}:</b> {item.value}{item.unit ? ` ${item.unit}` : ''}</Typography>)}{product.productType === 'DIGITAL' && product.digitalConfiguration && <Box sx={{ mt: 1, pt: 1.5, borderTop: '1px solid #E0E5ED' }}><Typography variant="subtitle2" sx={{ color: '#2359CE', mb: 1 }}>Digital product information</Typography>{product.digitalConfiguration.version && <Typography>Version: {product.digitalConfiguration.version}</Typography>}{product.digitalConfiguration.supportedPlatforms?.length > 0 && <Typography>Platforms: {product.digitalConfiguration.supportedPlatforms.join(', ')}</Typography>}{product.digitalConfiguration.licenseType && <Typography>License: {product.digitalConfiguration.licenseType}</Typography>}{product.digitalConfiguration.downloadLimit && <Typography>Download limit: {product.digitalConfiguration.downloadLimit}</Typography>}{product.digitalConfiguration.accessDurationDays && <Typography>Access duration: {product.digitalConfiguration.accessDurationDays} days</Typography>}</Box>}</Stack>}
          {tab === 2 && <Box><Rating value={product.numReviews ? product.rating || 0 : 0} readOnly /><Typography sx={{ color: '#667085', mt: 1 }}>{product.numReviews ? `${product.numReviews} genuine review(s)` : 'No reviews yet.'}</Typography></Box>}
          {tab === 3 && <Typography sx={{ color: '#667085' }}>Shipping and return details will be communicated after your order is confirmed. No delivery time is promised.</Typography>}
        </Box>
      </Box>

      <Box className="product-details-v7__related product-details-v8__related" sx={{ mt: 5 }}>
        <Typography variant="h5" gutterBottom>
          Discover Something More
        </Typography>

        {recLoading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', mt: 3 }}>
            <CircularProgress size={32} />
          </Box>
        ) : recommended.length === 0 ? (
          similarError ? (
            <SimilarProductsError onRetry={fetchRecommended} />
          ) : (
            <Typography variant="body2" color="text.secondary">
              We are curating recommendations for this product. Check back soon for more discoveries.
            </Typography>
          )
        ) : (
          <Grid container className="product-grid" spacing={0}>
            {recommended.map(rec => <Grid item xs={6} sm={6} md={4} lg={3} xl={2} key={rec.id}><ProductCard product={rec} addToCart={addToCart} /></Grid>)}
          </Grid>
        )}
      </Box>
    </Container></Box>
  );
}

export default ProductDetails;
