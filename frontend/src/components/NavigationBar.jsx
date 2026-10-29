import * as React from 'react';
import { AppBar, Toolbar, IconButton, Badge, InputBase, Box, CircularProgress, Tooltip, Drawer, List, ListItemButton, ListItemText, Divider, Typography } from '@mui/material';
import SearchIcon from '@mui/icons-material/Search';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import LogoutIcon from '@mui/icons-material/Logout';
import LoginIcon from '@mui/icons-material/Login';
import PersonAddAltIcon from '@mui/icons-material/PersonAddAlt';
import CloseIcon from '@mui/icons-material/Close';
import AccountCircleIcon from '@mui/icons-material/AccountCircle';
import MenuRoundedIcon from '@mui/icons-material/MenuRounded';
import FavoriteBorderRoundedIcon from '@mui/icons-material/FavoriteBorderRounded';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { debounce } from 'lodash';
import SearchResults from './SearchResults';
import { apiClient } from '../services/apiClient';
import { useNotifier } from '../context/NotificationProvider';
import AnnouncementBar from './AnnouncementBar';
import { useWishlist } from '../context/WishlistContext';

const controlSx = {
  color: '#292D32',
  width: { xs: 36, sm: 40 },
  height: { xs: 36, sm: 40 },
  flex: '0 0 auto',
  '&:hover': { bgcolor: '#E6F0EA', color: '#256B58' },
  '&:focus-visible': { outline: '2px solid #C5A47E', outlineOffset: 2 },
};

function NavigationBar({ cartItemCount }) {
  const [searchOpen, setSearchOpen] = React.useState(false);
  const [searchQuery, setSearchQuery] = React.useState('');
  const [searchResults, setSearchResults] = React.useState([]);
  const [loading, setLoading] = React.useState(false);
  const [searchError, setSearchError] = React.useState('');
  const [suggestionIndex, setSuggestionIndex] = React.useState(-1);
  const [isLoggedIn, setIsLoggedIn] = React.useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = React.useState(false);
  const searchBarRef = React.useRef(null);
  const searchInputRef = React.useRef(null);
  const searchResultsRef = React.useRef(null);
  const searchRequestRef = React.useRef(0);
  const location = useLocation();
  const navigate = useNavigate();
  const { notify } = useNotifier();
  const { wishlist } = useWishlist();

  React.useEffect(() => {
    const checkToken = () => setIsLoggedIn(Boolean(sessionStorage.getItem('MERNEcommerceToken')));
    checkToken();
    const interval = setInterval(checkToken, 2000);
    return () => clearInterval(interval);
  }, []);

  const debouncedSearch = React.useMemo(
    () => debounce(async query => {
      if (query.trim() === '') {
        setSearchResults([]);
        setLoading(false);
        setSearchError('');
        return;
      }
      const requestId = ++searchRequestRef.current;
      setLoading(true);
      setSearchError('');
      try {
        const response = await apiClient.get('search', { params: { q: query, limit: 6 } });
        if (requestId !== searchRequestRef.current) return;
        const products = Array.isArray(response.data) ? response.data : (response.data?.products || []);
        setSearchResults(products);
        setSuggestionIndex(-1);
      } catch (error) {
        if (requestId !== searchRequestRef.current) return;
        console.error('Error fetching search results:', error);
        setSearchResults([]);
        setSearchError('Search is unavailable. Check your connection and try again.');
        notify({ severity: 'error', message: 'Search is unavailable right now.' });
      } finally {
        if (requestId === searchRequestRef.current) setLoading(false);
      }
    }, 320),
    [notify]
  );

  React.useEffect(() => () => debouncedSearch.cancel(), [debouncedSearch]);

  const closeSearch = React.useCallback(() => {
    setSearchOpen(false);
    debouncedSearch.cancel();
    setSearchResults([]);
    setLoading(false);
    setSearchError('');
    setSuggestionIndex(-1);
  }, [debouncedSearch]);

  const openSearch = () => {
    setSearchOpen(true);
    window.setTimeout(() => searchInputRef.current?.focus(), 50);
    if (searchQuery.trim() && !searchResults.length) debouncedSearch(searchQuery);
  };

  React.useEffect(() => {
    const handleOutsideClick = event => {
      if (searchOpen && searchBarRef.current && !searchBarRef.current.contains(event.target) && (!searchResultsRef.current || !searchResultsRef.current.contains(event.target))) closeSearch();
    };
    const handleEscape = event => {
      if (event.key === 'Escape' && searchOpen) {
        closeSearch();
        searchInputRef.current?.blur();
      }
    };
    document.addEventListener('mousedown', handleOutsideClick);
    document.addEventListener('keydown', handleEscape);
    return () => {
      document.removeEventListener('mousedown', handleOutsideClick);
      document.removeEventListener('keydown', handleEscape);
    };
  }, [closeSearch, searchOpen]);

  React.useEffect(() => { setSearchResults([]); setSearchError(''); setSuggestionIndex(-1); }, [location.pathname]);

  const handleSearchChange = event => {
    const value = event.target.value;
    setSearchQuery(value);
    setSuggestionIndex(-1);
    debouncedSearch(value);
  };

  const handleSearchKeyDown = event => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      if (!searchResults.length) return;
      setSuggestionIndex(current => event.key === 'ArrowDown'
        ? Math.min(current + 1, searchResults.length - 1)
        : Math.max(current - 1, -1));
    }
    if (event.key === 'Enter' && suggestionIndex >= 0 && searchResults[suggestionIndex]) {
      event.preventDefault();
      const id = searchResults[suggestionIndex]?._id || searchResults[suggestionIndex]?.id;
      if (id) { navigate(`/product/${id}`); closeSearch(); }
    }
  };

  const handleSearchSubmit = event => {
    event.preventDefault();
    const query = searchQuery.trim();
    if (!query) return searchInputRef.current?.focus();
    if (searchResults.length === 1) {
      const product = searchResults[0];
      const id = product?._id || product?.id;
      if (id) navigate(`/product/${id}`);
    } else {
      navigate(`/shop?search=${encodeURIComponent(query)}`);
    }
    closeSearch();
  };

  const handleLogout = () => {
    sessionStorage.removeItem('MERNEcommerceToken');
    setIsLoggedIn(false);
    notify({ severity: 'success', message: 'Signed out successfully.' });
    navigate('/');
  };

  return (
    <Box component="div" sx={{ position: 'sticky', top: 0, zIndex: theme => theme.zIndex.appBar, width: '100%', bgcolor: '#F4F0E9' }}>
      <AppBar position="static" elevation={0} sx={{ backgroundColor: '#FBF9F5', color: '#292D32', borderRadius: 0, borderBottom: '1px solid rgba(41,45,50,.12)', boxShadow: '0 8px 24px rgba(41,45,50,.06)', '& .logo-link': { textDecoration: 'none', display: 'inline-flex', alignItems: 'center', lineHeight: 0 }, '& .logo-image': { display: 'block', width: { xs: 145, sm: 175, md: 205 }, maxWidth: '38vw', height: 'auto' } }}>
        <Toolbar sx={{ py: { xs: 1, md: 1.5 }, gap: { xs: 0.25, sm: 0.75, md: 1 } }}>
          <Box sx={{ flexGrow: 1, minWidth: 0, display: 'flex', alignItems: 'center', gap: 3 }}>
            <Link to="/" className="logo-link" aria-label="FastXHorizon home"><Box component="img" className="logo-image" src="/fastxhorizon.png" alt="FastXHorizon" /></Link>
            <Box className="luxury-desktop-nav" component="nav" aria-label="Primary navigation"><Link to="/" className="luxury-nav-link">Discover</Link><Link to="/shop" className="luxury-nav-link">Collections</Link><Link to="/about" className="luxury-nav-link">About</Link></Box>
          </Box>

          <Box sx={{ display: 'flex', alignItems: 'center', gap: { xs: 0, sm: 0.25 }, minWidth: 0 }}>
            <IconButton className="luxury-mobile-menu" aria-label="Open navigation menu" onClick={() => setMobileMenuOpen(true)} sx={controlSx}><MenuRoundedIcon fontSize="small" /></IconButton>
            <Box ref={searchBarRef} component="form" onSubmit={handleSearchSubmit} sx={{ position: 'relative', display: 'flex', alignItems: 'center', justifyContent: 'flex-end', width: searchOpen ? { xs: 'min(210px, calc(100vw - 156px))', sm: 280, md: 330 } : { xs: 36, sm: 40 }, height: { xs: 36, sm: 40 }, overflow: 'visible', borderRadius: 999, bgcolor: searchOpen ? '#F1F5FF' : 'transparent', transition: 'width 300ms ease, background-color 250ms ease, box-shadow 250ms ease', transformOrigin: 'right center', '&:focus-within': { bgcolor: '#EFF6FF', boxShadow: '0 0 0 2px rgba(37,99,235,0.18)' }, '@media (prefers-reduced-motion: reduce)': { transition: 'none' } }}>
              {searchOpen && <InputBase inputRef={searchInputRef} onKeyDown={handleSearchKeyDown} placeholder="Search products..." inputProps={{ 'aria-label': 'Search products', role: 'combobox', 'aria-expanded': Boolean(searchQuery.trim()), 'aria-autocomplete': 'list' }} value={searchQuery} onChange={handleSearchChange} sx={{ flex: 1, minWidth: 0, ml: 1.25, color: '#0F172A', '& input::placeholder': { color: '#64748B', opacity: 1 } }} />}
              {loading && <CircularProgress size={17} sx={{ color: '#256B58', mr: 0.5 }} />}
              <Tooltip title={searchOpen ? 'Close search' : 'Search'} arrow placement="bottom"><IconButton aria-label={searchOpen ? 'Close search' : 'Search products'} onClick={searchOpen ? closeSearch : openSearch} sx={controlSx}>{searchOpen ? <CloseIcon fontSize="small" /> : <SearchIcon fontSize="small" />}</IconButton></Tooltip>
              {searchOpen && searchQuery.trim() && <Box ref={searchResultsRef} sx={{ position: 'absolute', top: 48, right: 0, width: { xs: 'min(320px, calc(100vw - 24px))', sm: 360 }, zIndex: theme => theme.zIndex.modal - 1, backgroundColor: 'white', borderRadius: 2, boxShadow: '0 22px 48px rgba(15,23,42,0.18)', border: '1px solid rgba(148,163,184,0.18)' }}><SearchResults results={searchResults} loading={loading} error={searchError} query={searchQuery} onRetry={() => debouncedSearch(searchQuery)} onResultClick={() => { closeSearch(); }} onViewAll={() => { navigate(`/shop?search=${encodeURIComponent(searchQuery.trim())}`); closeSearch(); }} setSearchResults={setSearchResults} /></Box>}
            </Box>

            {!isLoggedIn ? <>
              <Tooltip title="Sign up" arrow placement="bottom"><IconButton component={Link} to="/register" aria-label="Sign up" sx={controlSx}><PersonAddAltIcon fontSize="small" /></IconButton></Tooltip>
              <Tooltip title="Login" arrow placement="bottom"><IconButton component={Link} to="/login" aria-label="Login" sx={controlSx}><LoginIcon fontSize="small" /></IconButton></Tooltip>
            </> : <>
              <Tooltip title="Account" arrow placement="bottom"><IconButton aria-label="Account" onClick={() => navigate('/')} sx={controlSx}><AccountCircleIcon fontSize="small" /></IconButton></Tooltip>
              <Tooltip title="Logout" arrow placement="bottom"><IconButton aria-label="Logout" onClick={handleLogout} sx={controlSx}><LogoutIcon fontSize="small" /></IconButton></Tooltip>
            </>}

            <Tooltip title="Wishlist" arrow placement="bottom"><IconButton component={Link} to="/wishlist" aria-label="Wishlist" sx={controlSx}><Badge badgeContent={wishlist.length} color="secondary" overlap="circular" showZero={false}><FavoriteBorderRoundedIcon fontSize="small" /></Badge></IconButton></Tooltip>
            <Tooltip title="Shopping cart" arrow placement="bottom"><IconButton component={Link} to="/cart" aria-label="Shopping cart" sx={controlSx}><Badge badgeContent={cartItemCount} overlap="circular" showZero sx={{ '& .MuiBadge-badge': { backgroundColor: '#DC2626', color: '#FFFFFF', fontWeight: 700 } }}><ShoppingCartIcon fontSize="small" /></Badge></IconButton></Tooltip>
          </Box>
        </Toolbar>
      </AppBar>
      <AnnouncementBar />
      <Drawer anchor="right" open={mobileMenuOpen} onClose={() => setMobileMenuOpen(false)} PaperProps={{ className: 'luxury-mobile-drawer' }}>
        <Box sx={{ width: 280, p: 2 }} role="presentation"><Typography variant="overline" sx={{ letterSpacing: '.16em', color: '#717171' }}>FASTXHORIZON</Typography><Divider sx={{ my: 2 }} /><List onClick={() => setMobileMenuOpen(false)}><ListItemButton component={Link} to="/"><ListItemText primary="Discover" /></ListItemButton><ListItemButton component={Link} to="/shop"><ListItemText primary="Collections" /></ListItemButton><ListItemButton component={Link} to="/about"><ListItemText primary="About" /></ListItemButton><ListItemButton component={Link} to="/support"><ListItemText primary="Support" /></ListItemButton></List></Box>
      </Drawer>
    </Box>
  );
}

export default NavigationBar;
