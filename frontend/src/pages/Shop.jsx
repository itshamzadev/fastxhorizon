import * as React from 'react';
import {
  Grid,
  Typography,
  Container,
  Box,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  CircularProgress,
  Alert,
  Chip,
  Stack,
  ToggleButton,
  ToggleButtonGroup,
  Paper,
  Divider,
} from '@mui/material';
import TuneIcon from '@mui/icons-material/Tune';
import SortIcon from '@mui/icons-material/Sort';
import FilterAltIcon from '@mui/icons-material/FilterAlt';
import ProductCard from '../components/ProductCard';
import '../App.css';
import { useNotifier } from '../context/NotificationProvider';
import { apiClient } from '../services/apiClient';
import { useLocation } from 'react-router-dom';
import { trackTikTokEvent } from '../analytics/tiktokPixel';
import { trackMetaEvent } from '../analytics/metaPixel';
import { TIKTOK_CURRENCY } from '../analytics/tiktokEvents';

const itemsPerPage = 24;

function Shop({ products, addToCart, loading, error }) {
  const [categoryFilter, setCategoryFilter] = React.useState('all');
  const [viewFilter, setViewFilter] = React.useState('all');
  const [page, setPage] = React.useState(1);
  const [animatedCards, setAnimatedCards] = React.useState([]);
  const [sortOption, setSortOption] = React.useState('featured');
  const [searchQuery, setSearchQuery] = React.useState('');
  const [feedProducts, setFeedProducts] = React.useState([]);
  const [feedTotal, setFeedTotal] = React.useState(0);
  const [feedHasMore, setFeedHasMore] = React.useState(false);
  const [feedLoading, setFeedLoading] = React.useState(false);
  const [feedError, setFeedError] = React.useState(null);
  const feedRequestRef = React.useRef(false);
  const randomSeed = React.useRef(`${Date.now()}-${Math.random().toString(36).slice(2)}`);
  const { notify } = useNotifier();
  const location = useLocation();

  const capitalizeCategory = React.useCallback(category => (category ? category.charAt(0).toUpperCase() + category.slice(1) : ''), []);

  const uniqueCategories = React.useMemo(() => {
    const bucket = new Set();
    products.forEach(product => {
      if (product?.category) {
        bucket.add(capitalizeCategory(product.category));
        if (Array.isArray(product.categoryPath) && product.categoryPath[1]) bucket.add(String(product.categoryPath[1]));
      }
    });
    return Array.from(bucket).sort();
  }, [products, capitalizeCategory]);

  React.useEffect(() => {
    const params = new URLSearchParams(location.search);
    const categoryParam = params.get('category');
    const queryParam = (params.get('search') || '').trim();
    setSearchQuery(queryParam);
    if (categoryParam) {
      const legacyCategory = /^(women|women\/jewellery)$/i.test(categoryParam) ? 'Jewellery' : categoryParam;
      const formatted = capitalizeCategory(legacyCategory);
      if (formatted && (formatted === 'All' || uniqueCategories.includes(formatted))) {
        setCategoryFilter(formatted === 'All' ? 'all' : formatted);
        setPage(1);
      }
    }
  }, [location.search, uniqueCategories, capitalizeCategory]);

  React.useEffect(() => {
    setPage(1);
    setFeedProducts([]);
    setFeedTotal(0);
    setFeedHasMore(false);
  }, [searchQuery, sortOption, categoryFilter, viewFilter]);

  React.useEffect(() => {
    const query = searchQuery.trim();
    if (!query) return;
    const eventId = `search:${query.toLowerCase()}`;
    trackTikTokEvent('Search', { contents: [], value: 0, currency: TIKTOK_CURRENCY, search_string: query }, eventId);
    trackMetaEvent('Search', { content_type: 'product', search_string: query, value: 0, currency: TIKTOK_CURRENCY }, eventId);
  }, [searchQuery]);

  React.useEffect(() => {
    let active = true;
    const controller = new AbortController();
    feedRequestRef.current = true;
    setFeedLoading(true);
    setFeedError(null);
    const params = { limit: itemsPerPage, page };
    if (categoryFilter !== 'all') params.category = categoryFilter;
    if (viewFilter === 'featured') params.featured = 'true';
    if (viewFilter === 'inStock') params.availability = 'available';
    if (sortOption !== 'featured') params.sort = sortOption;
    if (searchQuery) params.q = searchQuery;
    if (sortOption === 'random') params.seed = `${randomSeed.current}:${categoryFilter}:${searchQuery}:${viewFilter}`;
    const endpoint = searchQuery ? 'search' : (sortOption === 'random' ? 'products/random' : 'products');
    apiClient.get(endpoint, { params, signal: controller.signal })
      .then(({ data }) => {
        if (!active) return;
        const payload = Array.isArray(data) ? { products: data, total: data.length, hasMore: false } : data;
        const incoming = Array.isArray(payload?.products) ? payload.products : [];
        setFeedProducts(previous => {
          if (page === 1) return incoming;
          const seen = new Set(previous.map(item => String(item.id || item._id)));
          return [...previous, ...incoming.filter(item => !seen.has(String(item.id || item._id)))];
        });
        setFeedTotal(Number(payload?.total) || 0);
        setFeedHasMore(Boolean(payload?.hasMore));
      })
      .catch(requestError => {
        if (active && requestError?.code !== 'ERR_CANCELED') {
          setFeedError(null);
          if (page === 1) {
            setFeedProducts(products);
            setFeedTotal(products.length);
            setFeedHasMore(false);
          } else {
            setFeedError(requestError);
          }
        }
      })
      .finally(() => { if (active) { feedRequestRef.current = false; setFeedLoading(false); } });
    return () => { active = false; feedRequestRef.current = false; controller.abort(); };
  }, [page, searchQuery, sortOption, categoryFilter, viewFilter, products]);

  const productsToShow = feedProducts;

  const handleCategoryChange = event => {
    randomSeed.current = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    setCategoryFilter(event.target.value);
    setPage(1);
    setAnimatedCards([]);
  };

  const handleSortChange = event => {
    randomSeed.current = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    setSortOption(event.target.value);
    setPage(1);
  };

  const handleViewChange = (_event, nextView) => {
    if (nextView !== null) {
      randomSeed.current = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
      setViewFilter(nextView);
      setPage(1);
    }
  };

  const handleQuickSelect = category => {
    randomSeed.current = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    setCategoryFilter(category);
    setPage(1);
    notify({ severity: 'info', message: `Showing ${category === 'all' ? 'all' : category} products` });
  };

  React.useEffect(() => {
    const timer = setTimeout(() => {
      setAnimatedCards(productsToShow.map((_, index) => index));
    }, 100);

    return () => clearTimeout(timer);
  }, [productsToShow]);

  if (loading || (feedLoading && !feedProducts.length)) {
    return (
      <Box
        sx={{
          position: 'fixed',
          top: 0,
          left: 0,
          width: '100%',
          height: '100%',
          backgroundColor: 'rgba(255, 255, 255, 0.8)',
          display: 'flex',
          justifyContent: 'center',
          alignItems: 'center',
          zIndex: 1000,
        }}
      >
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Container maxWidth="xl" className="luxury-page luxury-shop marketplace-shop" sx={{ pb: 8 }}>
      <Paper className="luxury-page__hero"
        elevation={0}
        sx={{ p: { xs: 3, md: 5 }, borderRadius: 4, mb: 4, background: '#E8E1D8', border: '1px solid #C5A47E' }}
      >
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} justifyContent="space-between" alignItems={{ xs: 'flex-start', md: 'center' }}>
          <Box>
            <Typography variant="h4" sx={{ fontWeight: 700 }}>
              Shop
            </Typography>
            <Typography variant="body1" sx={{ mt: 1, color: 'text.secondary', maxWidth: 520 }}>
              Browse jewellery, clothing, watches and more. Filter by category, availability and price to find what fits you.
            </Typography>
          </Box>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
            <ToggleButtonGroup value={viewFilter} exclusive onChange={handleViewChange} size="small">
              <ToggleButton value="all">
                <FilterAltIcon fontSize="small" sx={{ mr: 0.5 }} /> All
              </ToggleButton>
              <ToggleButton value="inStock">In stock</ToggleButton>
              <ToggleButton value="featured">Featured</ToggleButton>
            </ToggleButtonGroup>
            <FormControl size="small" sx={{ minWidth: 160 }}>
              <InputLabel id="sort-label">
                <SortIcon />
              </InputLabel>
              <Select labelId="sort-label" id="sort-select" value={sortOption} label="Sort" onChange={handleSortChange}>
                <MenuItem value="featured">Featured</MenuItem>
                <MenuItem value="priceLow">Price: Low to High</MenuItem>
                <MenuItem value="priceHigh">Price: High to Low</MenuItem>
                <MenuItem value="rating">Top Rated</MenuItem>
                <MenuItem value="newest">Newest</MenuItem>
                <MenuItem value="random">Random / Discover</MenuItem>
              </Select>
            </FormControl>
          </Stack>
        </Stack>
      </Paper>

      {searchQuery && <Typography variant="h5" sx={{ mb: 2 }}>Search results for “{searchQuery}” <Typography component="span" variant="body1" color="text.secondary">({feedTotal} products)</Typography></Typography>}
      {(error || feedError) && (
        <Alert severity="error" sx={{ mb: 3 }}>
          {(feedError || error)?.message || 'Unable to load products right now. Please try again shortly.'}
        </Alert>
      )}

      <Paper className="luxury-page__filters" elevation={0} sx={{ p: { xs: 2, md: 3 }, borderRadius: 4, mb: 4 }}>
        <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 2 }}>
          <TuneIcon color="primary" />
          <Typography variant="subtitle1" fontWeight={700}>
            Quick filters
          </Typography>
        </Stack>
        <Box className="luxury-shop__category-scroll" aria-label="Shop categories">
          <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
            <Chip label="All" color={categoryFilter === 'all' ? 'primary' : 'default'} onClick={() => handleQuickSelect('all')} />
            {uniqueCategories.map(category => (
              <Chip key={category} label={category} color={categoryFilter === category ? 'primary' : 'default'} onClick={() => handleQuickSelect(category)} />
            ))}
          </Stack>
        </Box>
        <Divider sx={{ my: 2 }} />
        <FormControl fullWidth size="small">
          <InputLabel id="category-filter-label">Filter by Category</InputLabel>
          <Select labelId="category-filter-label" id="category-filter" value={categoryFilter} label="Filter by Category" onChange={handleCategoryChange}>
            <MenuItem value="all">All Categories</MenuItem>
            {uniqueCategories.map(category => (
              <MenuItem key={category} value={category}>
                {category}
              </MenuItem>
            ))}
          </Select>
        </FormControl>
      </Paper>

      <Grid container className="product-grid" spacing={0}>
        {productsToShow.map((product, index) => (
          <Grid item key={product.id} xs={6} sm={6} md={4} lg={3} xl={2} className={animatedCards.includes(index) ? 'product-card-animated' : ''}>
            <ProductCard product={product} priority={page === 1 && index < 4} addToCart={addToCart} />
          </Grid>
        ))}
      </Grid>

      <Typography variant="body2" color="text.secondary" sx={{ mt: 3, textAlign: 'center' }}>
        {feedTotal ? `Loaded ${productsToShow.length} of ${feedTotal} products` : 'Showing 0 products'}
      </Typography>

      {!productsToShow.length && !loading && !feedLoading && (
        <Alert severity="info" sx={{ mt: 4 }}>
          No products matched your filters. Try adjusting categories or sorting options.
        </Alert>
      )}

      <Box sx={{ minHeight: 56, display: 'flex', justifyContent: 'center', alignItems: 'center', mt: 3 }}>
        {feedLoading && <CircularProgress size={28} />}
        {!feedLoading && feedHasMore && <Typography variant="body2" color="text.secondary">Loading more products…</Typography>}
        {!feedLoading && !feedHasMore && feedProducts.length > 0 && <Typography variant="body2" color="text.secondary">You’ve reached the end of this catalog.</Typography>}
      </Box>
      {!feedLoading && feedHasMore && <Box className="marketplace-load-more"><button type="button" className="marketplace-outline-button marketplace-load-more__native" disabled={feedLoading || feedRequestRef.current} onClick={() => { if (!feedRequestRef.current) setPage(current => current + 1); }}>Load More Products</button></Box>}
    </Container>
  );
}

export default Shop;
