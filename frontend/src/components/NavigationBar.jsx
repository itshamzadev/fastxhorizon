import * as React from 'react';
import { AppBar, Toolbar, IconButton, Badge, InputBase, Box, CircularProgress, Tooltip } from '@mui/material';
import SearchIcon from '@mui/icons-material/Search';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import LogoutIcon from '@mui/icons-material/Logout';
import LoginIcon from '@mui/icons-material/Login';
import PersonAddAltIcon from '@mui/icons-material/PersonAddAlt';
import CloseIcon from '@mui/icons-material/Close';
import AccountCircleIcon from '@mui/icons-material/AccountCircle';
import FavoriteBorderRoundedIcon from '@mui/icons-material/FavoriteBorderRounded';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { debounce } from 'lodash';
import SearchResults from './SearchResults';
import { apiClient } from '../services/apiClient';
import { useNotifier } from '../context/NotificationProvider';
import AnnouncementBar from './AnnouncementBar';
import { useWishlist } from '../context/WishlistContext';

const controlSx = {
  color: '#123B98',
  width: { xs: 36, sm: 40 },
  height: { xs: 36, sm: 40 },
  flex: '0 0 auto',
  '&:hover': { bgcolor: '#EFF6FF', color: '#2563EB' },
  '&:focus-visible': { outline: '2px solid #2563EB', outlineOffset: 2 },
};

function NavigationBar({ cartItemCount }) {
  const [searchOpen, setSearchOpen] = React.useState(false);
  const [searchQuery, setSearchQuery] = React.useState('');
  const [searchResults, setSearchResults] = React.useState([]);
  const [loading, setLoading] = React.useState(false);
  const [isLoggedIn, setIsLoggedIn] = React.useState(false);
  const searchBarRef = React.useRef(null);
  const searchInputRef = React.useRef(null);
  const searchResultsRef = React.useRef(null);
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
        return;
      }
      setLoading(true);
      try {
        const response = await apiClient.get('search', { params: { q: query } });
        setSearchResults(response.data);
        if (Array.isArray(response.data) && response.data.length === 0) notify({ severity: 'info', message: 'No products matched your search yet.' });
      } catch (error) {
        console.error('Error fetching search results:', error);
        setSearchResults([]);
        notify({ severity: 'error', message: 'Search is unavailable right now.' });
      } finally {
        setLoading(false);
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
  }, [debouncedSearch]);

  const openSearch = () => {
    setSearchOpen(true);
    window.setTimeout(() => searchInputRef.current?.focus(), 50);
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

  React.useEffect(() => setSearchResults([]), [location.pathname]);

  const handleSearchChange = event => {
    const value = event.target.value;
    setSearchQuery(value);
    debouncedSearch(value);
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
    <Box component="div" sx={{ position: 'sticky', top: 0, zIndex: theme => theme.zIndex.appBar, width: '100%', bgcolor: '#FFFFFF' }}>
      <AppBar position="static" elevation={0} sx={{ backgroundColor: '#FFFFFF', color: '#123B98', borderRadius: 0, borderBottom: '1px solid #E2E8F0', boxShadow: '0 2px 10px rgba(15, 23, 42, 0.06)', '& .logo-link': { textDecoration: 'none', display: 'inline-flex', alignItems: 'center', lineHeight: 0 }, '& .logo-image': { display: 'block', width: { xs: 145, sm: 175, md: 205 }, maxWidth: '38vw', height: 'auto' } }}>
        <Toolbar sx={{ py: { xs: 1, md: 1.5 }, gap: { xs: 0.25, sm: 0.75, md: 1 } }}>
          <Box sx={{ flexGrow: 1, minWidth: 0 }}>
            <Link to="/" className="logo-link" aria-label="FastXHorizon home"><Box component="img" className="logo-image" src="/fastxhorizon.png" alt="FastXHorizon" /></Link>
          </Box>

          <Box sx={{ display: 'flex', alignItems: 'center', gap: { xs: 0, sm: 0.25 }, minWidth: 0 }}>
            <Box ref={searchBarRef} component="form" onSubmit={handleSearchSubmit} sx={{ position: 'relative', display: 'flex', alignItems: 'center', justifyContent: 'flex-end', width: searchOpen ? { xs: 'min(210px, calc(100vw - 156px))', sm: 280, md: 330 } : { xs: 36, sm: 40 }, height: { xs: 36, sm: 40 }, overflow: 'visible', borderRadius: 999, bgcolor: searchOpen ? '#F1F5FF' : 'transparent', transition: 'width 300ms ease, background-color 250ms ease, box-shadow 250ms ease', transformOrigin: 'right center', '&:focus-within': { bgcolor: '#EFF6FF', boxShadow: '0 0 0 2px rgba(37,99,235,0.18)' }, '@media (prefers-reduced-motion: reduce)': { transition: 'none' } }}>
              {searchOpen && <InputBase inputRef={searchInputRef} placeholder="Search gadgets, accessories..." inputProps={{ 'aria-label': 'Search products' }} value={searchQuery} onChange={handleSearchChange} sx={{ flex: 1, minWidth: 0, ml: 1.25, color: '#0F172A', '& input::placeholder': { color: '#64748B', opacity: 1 } }} />}
              {loading && <CircularProgress size={17} sx={{ color: '#2563EB', mr: 0.5 }} />}
              <Tooltip title={searchOpen ? 'Close search' : 'Search'} arrow placement="bottom"><IconButton aria-label={searchOpen ? 'Close search' : 'Search products'} onClick={searchOpen ? closeSearch : openSearch} sx={controlSx}>{searchOpen ? <CloseIcon fontSize="small" /> : <SearchIcon fontSize="small" />}</IconButton></Tooltip>
              {searchOpen && searchResults.length > 0 && <Box ref={searchResultsRef} sx={{ position: 'absolute', top: 48, right: 0, width: { xs: 'min(320px, calc(100vw - 24px))', sm: 360 }, zIndex: theme => theme.zIndex.modal - 1, backgroundColor: 'white', borderRadius: 3, boxShadow: '0 22px 48px rgba(15,23,42,0.18)', border: '1px solid rgba(148,163,184,0.18)' }}><SearchResults results={searchResults} onResultClick={() => { closeSearch(); }} setSearchResults={setSearchResults} /></Box>}
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
    </Box>
  );
}

export default NavigationBar;
