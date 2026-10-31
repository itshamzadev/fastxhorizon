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

function resolveStoreName(product) {
  const sellerName = product?.seller?.storeName || product?.seller?.name || product?.sellerStoreName || product?.storeName;
  return String(sellerName || 'FASTXHORIZON').trim().slice(0, 28).toUpperCase();
}

function resolveImageSource(value) {
  return resolveMediaUrl(value);
}

export default function ProductCard({ product, addToCart }) {
  const navigate = useNavigate();
  const canonicalId = product?._id || product?.id;
  const stock = Number.isFinite(Number(product?.stock)) ? Number(product.stock) : 0;
  const reviewCount = Number.isFinite(Number(product?.numReviews)) ? Math.max(0, Number(product.numReviews)) : 0;
  const ratingValue = reviewCount > 0 && Number.isFinite(Number(product?.rating)) ? Math.min(5, Math.max(0, Number(product.rating))) : 0;
  const supplierAvailable = product?.inventoryMode === 'SUPPLIER_BASED' && product?.availabilityStatus === 'AVAILABLE_TO_ORDER';
  const inStock = supplierAvailable || (['AVAILABILITY_BASED', 'AVAILABILITY'].includes(product?.inventoryMode) ? product?.availabilityStatus === 'IN_STOCK' : stock > 0);
  const imageSource = resolveImageSource(product?.image);
  const [imageFailed, setImageFailed] = React.useState(false);
  const { wishlist, toggleWishlist, wishlistPending } = useWishlist();
  const wishlisted = wishlist.some(item => String(item?._id || item?.id) === String(canonicalId));
  const wishlistIsPending = wishlistPending.has(String(canonicalId));

  const handleViewDetails = () => {
    if (canonicalId) navigate(`/product/${canonicalId}`);
  };

  const handleCardClick = event => {
    if (!canonicalId || event.target.closest('button')) return;
    handleViewDetails();
  };

  const handleAddToCart = event => {
    event.stopPropagation();
    if (inStock && typeof addToCart === 'function') addToCart(product);
  };

  const handleWishlistToggle = event => {
    event.stopPropagation();
    event.preventDefault();
    if (canonicalId && !wishlistIsPending) void toggleWishlist(product);
  };

  return (
    <Card
      className="product-card-premium"
      onClick={handleCardClick}
      sx={{
        width: '100%', height: 'auto', aspectRatio: '4 / 5', minHeight: 0, minWidth: 0, cursor: canonicalId ? 'pointer' : 'default', position: 'relative', display: 'flex', flexDirection: 'column', overflow: 'hidden',
        bgcolor: '#292D32', color: '#fff', border: '1px solid rgba(197,164,126,0.35)', borderRadius: '8px', boxShadow: '0 12px 28px rgba(41,45,50,0.18)',
        transition: 'transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease',
        '&:hover': { transform: 'translateY(-4px)', boxShadow: '0 18px 34px rgba(15, 23, 42, 0.24)', borderColor: 'rgba(96,165,250,0.4)' },
        '&:focus-within': { borderColor: '#60A5FA', boxShadow: '0 0 0 3px rgba(96,165,250,0.28)' },
        '&:hover .product-media': { transform: 'scale(1.04)' },
      }}
    >
      <Box sx={{ position: 'absolute', inset: 0, overflow: 'hidden', bgcolor: '#202126', borderRadius: '6px' }}>
        {imageSource && !imageFailed ? (
          <CardMedia component="img" className="product-media" alt={product?.name || 'Product image'} src={imageSource} loading="lazy" onError={() => setImageFailed(true)} sx={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover', transition: 'transform 0.35s ease' }} />
        ) : (
          <Box className="product-media-fallback" sx={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#D8D1C6', background: '#292D32' }}>
            <ImageNotSupportedRoundedIcon sx={{ fontSize: { xs: 28, sm: 36 }, opacity: 0.65 }} aria-hidden="true" />
          </Box>
        )}
        <Box aria-hidden="true" sx={{ position: 'absolute', inset: 0, pointerEvents: 'none', background: 'rgba(197,164,126,0.04)' }} />
        <Tooltip title={wishlisted ? 'Remove from wishlist' : 'Add to wishlist'} arrow placement="top" disableInteractive PopperProps={{ modifiers: [{ name: 'preventOverflow', options: { boundary: 'viewport', padding: 8 } }, { name: 'flip', options: { fallbackPlacements: ['bottom'] } }] }}>
          <span>
          <IconButton aria-label={wishlisted ? `Remove ${product?.name || 'product'} from wishlist` : `Add ${product?.name || 'product'} to wishlist`} aria-pressed={wishlisted} disabled={!canonicalId || wishlistIsPending} onClick={handleWishlistToggle} sx={{ position: 'absolute', top: 10, left: 10, width: 40, height: 40, bgcolor: '#F4F0E9', color: wishlisted ? '#B65C43' : '#292D32', border: '1px solid #C5A47E', boxShadow: '0 6px 16px rgba(41,45,50,.24)', '&:hover': { bgcolor: '#FFFFFF', color: wishlisted ? '#B65C43' : '#256B58', transform: 'scale(1.06)' }, '&:focus-visible': { outline: '2px solid #C5A47E', outlineOffset: 2 }, '&.Mui-disabled': { color: '#8B9196', bgcolor: '#E8E1D8' } }}>
              {wishlisted ? <FavoriteRoundedIcon sx={{ fontSize: 18 }} /> : <FavoriteBorderRoundedIcon sx={{ fontSize: 18 }} />}
            </IconButton>
          </span>
        </Tooltip>
        <Tooltip title={product?.inventoryMode === 'SUPPLIER_BASED' && supplierAvailable ? 'Available to order' : (inStock ? 'Add to cart' : 'Out of stock')} arrow placement="top" disableInteractive PopperProps={{ modifiers: [{ name: 'preventOverflow', options: { boundary: 'viewport', padding: 8 } }, { name: 'flip', options: { fallbackPlacements: ['bottom'] } }] }}>
          <span>
          <IconButton aria-label={inStock ? `Add ${product?.name || 'product'} to cart` : `${product?.name || 'Product'} is out of stock`} disabled={!inStock || typeof addToCart !== 'function'} onClick={event => { event.preventDefault(); handleAddToCart(event); }} sx={{ position: 'absolute', top: 10, right: 10, width: 40, height: 40, bgcolor: '#F4F0E9', color: '#292D32', border: '1px solid #C5A47E', boxShadow: '0 6px 16px rgba(41,45,50,.24)', '&:hover': { bgcolor: '#FFFFFF', color: '#256B58', transform: 'scale(1.06)' }, '&:focus-visible': { outline: '2px solid #C5A47E', outlineOffset: 2 }, '&.Mui-disabled': { color: '#8B9196', bgcolor: '#E8E1D8' } }}>
              <ShoppingCartRoundedIcon sx={{ fontSize: 18 }} />
            </IconButton>
          </span>
        </Tooltip>
      </Box>

      <CardContent className="product-card-premium__overlay" sx={{ position: 'absolute', zIndex: 2, left: { xs: 6, sm: 8 }, right: { xs: 6, sm: 8 }, bottom: { xs: 6, sm: 8 }, minHeight: { xs: 126, sm: 136 }, display: 'flex', flexDirection: 'column', p: { xs: 1.35, sm: 1.6 }, '&:last-child': { pb: { xs: 1.35, sm: 1.6 } }, background: 'rgba(25,28,33,0.58)', border: '1px solid rgba(197,164,126,0.45)', borderRadius: '6px', boxShadow: '0 10px 28px rgba(41,45,50,0.22)', backdropFilter: 'blur(2px)', WebkitBackdropFilter: 'blur(2px)' }}>
        <Typography component="span" sx={{ color: '#E2E8F0', fontSize: '0.62rem', fontWeight: 700, letterSpacing: '0.12em', textTransform: 'uppercase', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', textShadow: '0 1px 4px rgba(15,23,42,0.65)' }}>{resolveStoreName(product)}</Typography>
        <Typography component="h3" sx={{ mt: 0.5, color: '#FFFFFF', fontSize: { xs: '0.86rem', sm: '0.95rem' }, fontWeight: 700, lineHeight: 1.3, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden', minHeight: { xs: '2.23rem', sm: '2.47rem' }, textShadow: '0 1px 5px rgba(15,23,42,0.72)' }}>{product?.name || 'Unnamed product'}</Typography>
        <Stack direction="row" spacing={0.45} alignItems="center" sx={{ mt: 0.8, minHeight: 20 }}>
          {reviewCount > 0 ? <><Rating name={`rating-${canonicalId}`} value={ratingValue} precision={0.1} readOnly size="small" sx={{ color: '#FBBF24', fontSize: '0.92rem' }} /><Typography component="span" sx={{ color: '#E2E8F0', fontSize: '0.68rem', whiteSpace: 'nowrap', textShadow: '0 1px 4px rgba(15,23,42,0.65)' }}>{ratingValue.toFixed(1)} ({reviewCount})</Typography></> : <Typography component="span" sx={{ color: '#CBD5E1', fontSize: '0.68rem', whiteSpace: 'nowrap', textShadow: '0 1px 4px rgba(15,23,42,0.65)' }}>No reviews yet</Typography>}
        </Stack>
        <Stack direction="row" alignItems="center" justifyContent="space-between" spacing={1} sx={{ mt: 'auto', pt: 1.25 }}>
          <Typography sx={{ color: '#FFFFFF', fontSize: { xs: '0.82rem', sm: '0.92rem' }, fontWeight: 800, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', textShadow: '0 1px 5px rgba(15,23,42,0.72)' }}>{formatPrice(product?.price)}</Typography>
          <Typography component="span" sx={{ color: inStock ? '#86EFAC' : '#FCA5A5', fontSize: '0.65rem', fontWeight: 700, whiteSpace: 'nowrap', textShadow: '0 1px 4px rgba(15,23,42,0.65)' }}><Box component="span" sx={{ display: 'inline-block', width: 6, height: 6, borderRadius: '50%', bgcolor: 'currentColor', mr: 0.5, verticalAlign: 'middle' }} />{product?.inventoryMode === 'SUPPLIER_BASED' && supplierAvailable ? 'Available to order' : (inStock ? 'In stock' : 'Out of stock')}</Typography>
        </Stack>
      </CardContent>
    </Card>
  );
}
