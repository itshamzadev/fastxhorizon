import * as React from 'react';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CardMedia from '@mui/material/CardMedia';
import IconButton from '@mui/material/IconButton';
import Typography from '@mui/material/Typography';
import Stack from '@mui/material/Stack';
import Rating from '@mui/material/Rating';
import Box from '@mui/material/Box';
import Tooltip from '@mui/material/Tooltip';
import ShoppingCartRoundedIcon from '@mui/icons-material/ShoppingCartRounded';
import ImageNotSupportedRoundedIcon from '@mui/icons-material/ImageNotSupportedRounded';
import FavoriteRoundedIcon from '@mui/icons-material/FavoriteRounded';
import FavoriteBorderRoundedIcon from '@mui/icons-material/FavoriteBorderRounded';
import { useNavigate } from 'react-router-dom';
import { useWishlist } from '../context/WishlistContext';
import { formatPrice } from '../utils/currency';
import { resolveMediaUrl } from '../utils/media';
import { getValidDiscount } from '../utils/pricing';

export default function ProductCard({ product, addToCart, priority = false }) {
  const navigate = useNavigate();
  const canonicalId = product?._id || product?.id;
  const defaultVariant = Array.isArray(product?.variants) ? product.variants.find(variant => variant.enabled !== false) : null;
  const price = Number(product?.price ?? defaultVariant?.price);
  const { sellingPrice: safePrice, originalPrice, discountPercent } = getValidDiscount(Number.isFinite(price) ? price : 0, product?.compareAtPrice ?? defaultVariant?.compareAtPrice);
  const stock = Number.isFinite(Number(product?.stock)) ? Number(product.stock) : 0;
  const reviewCount = Number.isFinite(Number(product?.numReviews)) ? Math.max(0, Number(product.numReviews)) : 0;
  const ratingValue = reviewCount > 0 && Number.isFinite(Number(product?.rating)) ? Math.min(5, Math.max(0, Number(product.rating))) : 0;
  const supplierAvailable = product?.inventoryMode === 'SUPPLIER_BASED' && product?.availabilityStatus === 'AVAILABLE_TO_ORDER';
  const inStock = supplierAvailable || (['AVAILABILITY_BASED', 'AVAILABILITY'].includes(product?.inventoryMode) ? product?.availabilityStatus === 'IN_STOCK' : stock > 0);
  const imageSource = resolveMediaUrl(product?.image);
  const [imageFailed, setImageFailed] = React.useState(false);
  const { wishlist, toggleWishlist, wishlistPending } = useWishlist();
  const wishlisted = wishlist.some(item => String(item?._id || item?.id) === String(canonicalId));
  const wishlistIsPending = wishlistPending.has(String(canonicalId));

  const handleViewDetails = () => { if (canonicalId) navigate(`/product/${canonicalId}`); };
  const handleCardClick = event => { if (!canonicalId || event.target.closest('button')) return; handleViewDetails(); };
  const canArrange = product?.allowArrangeOnRequest === true && !inStock && product?.inventoryMode === 'NUMERIC';
  const handleAddToCart = event => { event.stopPropagation(); if (inStock && typeof addToCart === 'function') addToCart(product); };
  const handleArrange = event => { event.stopPropagation(); if (canonicalId && canArrange) navigate(`/arrange/${canonicalId}`); };
  const handleWishlistToggle = event => { event.stopPropagation(); event.preventDefault(); if (canonicalId && !wishlistIsPending) void toggleWishlist(product); };
  const actionButtonSx = { width: 38, height: 38, bgcolor: '#FFFFFF', color: '#292D32', border: '1px solid #C5A47E', boxShadow: '0 4px 12px rgba(41,45,50,.18)', '&:hover': { bgcolor: '#FFF9EF', color: '#2456A6', transform: 'scale(1.05)' }, '&:focus-visible': { outline: '2px solid #C5A47E', outlineOffset: 2 }, '&.Mui-disabled': { color: '#8B9196', bgcolor: '#E8E1D8' } };

  return (
    <Card className="product-card-premium" onClick={handleCardClick} sx={{ width: '100%', height: '100%', minHeight: 0, minWidth: 0, cursor: canonicalId ? 'pointer' : 'default', display: 'flex', flexDirection: 'column', overflow: 'hidden', bgcolor: '#FFFFFF', color: '#292D32', border: '1px solid rgba(197,164,126,0.32)', borderRadius: '5px', boxShadow: '0 3px 12px rgba(41,45,50,0.08)', transition: 'transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease', '&:hover': { transform: 'translateY(-2px)', boxShadow: '0 8px 18px rgba(15,23,42,0.13)', borderColor: '#C5A47E' }, '&:focus-within': { borderColor: '#2456A6', boxShadow: '0 0 0 3px rgba(36,86,166,0.20)' }, '&:hover .product-media': { transform: 'scale(1.025)' } }}>
      <Box sx={{ position: 'relative', flex: '0 0 auto', height: { xs: 150, sm: 175, md: 195 }, overflow: 'hidden', bgcolor: '#F7F3EC' }}>
        {imageSource && !imageFailed ? <CardMedia component="img" className="product-media" alt={product?.name || 'Product image'} src={imageSource} loading={priority ? 'eager' : 'lazy'} fetchPriority={priority ? 'high' : 'auto'} decoding="async" onError={() => setImageFailed(true)} sx={{ width: '100%', height: '100%', objectFit: 'contain', objectPosition: 'center', transition: 'transform 0.35s ease' }} /> : <Box className="product-media-fallback" sx={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#8B9196', bgcolor: '#F7F3EC' }}><ImageNotSupportedRoundedIcon sx={{ fontSize: 36, opacity: 0.65 }} aria-hidden="true" /></Box>}
        <Tooltip title={wishlisted ? 'Remove from wishlist' : 'Add to wishlist'} arrow><span><IconButton aria-label={wishlisted ? `Remove ${product?.name || 'product'} from wishlist` : `Add ${product?.name || 'product'} to wishlist`} aria-pressed={wishlisted} disabled={!canonicalId || wishlistIsPending} onClick={handleWishlistToggle} sx={{ ...actionButtonSx, position: 'absolute', top: 10, right: 10, color: wishlisted ? '#B65C43' : '#292D32' }}>{wishlisted ? <FavoriteRoundedIcon sx={{ fontSize: 18 }} /> : <FavoriteBorderRoundedIcon sx={{ fontSize: 18 }} />}</IconButton></span></Tooltip>
      </Box>
      <CardContent sx={{ flex: 1, display: 'flex', flexDirection: 'column', p: { xs: 1, sm: 1.2 }, '&:last-child': { pb: { xs: 1, sm: 1.2 } } }}>
        <Typography component="h3" sx={{ color: '#1F2937', fontSize: { xs: '0.78rem', sm: '0.86rem' }, fontWeight: 600, lineHeight: 1.28, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden', minHeight: { xs: '2rem', sm: '2.2rem' } }}>{product?.name || 'Unnamed product'}</Typography>
        {reviewCount > 0 && <Stack direction="row" spacing={0.35} alignItems="center" sx={{ mt: 0.45, minHeight: 16 }}><Rating name={`rating-${canonicalId}`} value={ratingValue} precision={0.1} readOnly size="small" sx={{ color: '#D49B2A', fontSize: '0.76rem' }} /><Typography component="span" sx={{ color: '#6B7280', fontSize: '0.62rem' }}>{ratingValue.toFixed(1)} ({reviewCount})</Typography></Stack>}
        <Stack direction="row" spacing={0.6} alignItems="baseline" sx={{ mt: 0.45, flexWrap: 'wrap' }}><Typography sx={{ color: '#B65C43', fontSize: { xs: '0.92rem', sm: '1rem' }, fontWeight: 900, whiteSpace: 'nowrap' }}>{formatPrice(safePrice)}</Typography>{originalPrice && <Typography component="span" sx={{ color: '#9CA3AF', fontSize: '0.68rem', textDecoration: 'line-through', whiteSpace: 'nowrap' }}>{formatPrice(originalPrice)}</Typography>}{discountPercent > 0 && <Typography component="span" sx={{ color: '#B65C43', fontSize: '0.64rem', fontWeight: 800 }}>-{discountPercent}%</Typography>}</Stack>
        <Stack direction="row" alignItems="center" justifyContent="space-between" spacing={1} sx={{ mt: 0.35 }}>{canArrange ? <Typography component="button" onClick={handleArrange} sx={{ border: 0, p: 0, bgcolor: 'transparent', color: '#2456A6', fontSize: '0.62rem', fontWeight: 800, cursor: 'pointer' }}>Arrange on request</Typography> : <Typography component="span" sx={{ color: inStock ? '#256B58' : '#B65C43', fontSize: '0.62rem', fontWeight: 700 }}>{inStock ? (supplierAvailable ? 'Available' : 'In stock') : 'Sold out'}</Typography>}<Tooltip title={inStock ? 'Add to cart' : canArrange ? 'Arrange on request' : 'Sold out'} arrow><span><IconButton aria-label={inStock ? `Add ${product?.name || 'product'} to cart` : canArrange ? `Arrange ${product?.name || 'product'} on request` : `${product?.name || 'Product'} is sold out`} disabled={!inStock || typeof addToCart !== 'function'} onClick={event => { event.preventDefault(); handleAddToCart(event); }} sx={{ ...actionButtonSx, width: 32, height: 32 }}><ShoppingCartRoundedIcon sx={{ fontSize: 16 }} /></IconButton></span></Tooltip></Stack>
      </CardContent>
    </Card>
  );
}
