import * as React from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { Box, Container, CssBaseline } from '@mui/material';
import { createTheme, ThemeProvider } from '@mui/material/styles';
import NavigationBar from './components/NavigationBar';
import Home from './pages/Home';
import Shop from './pages/Shop';
import Cart from './pages/Cart';
import Checkout from './pages/Checkout';
import OrderSuccess from './pages/OrderSuccess';
import ProductDetails from './pages/ProductDetails';
import Login from './pages/Login';
import Register from './pages/Register';
import ForgotPassword from './pages/ForgotPassword';
import ResetPassword from './pages/ResetPassword';
import NotFoundPage from './pages/NotFoundPage';
import Footer from './components/Footer';
import About from './pages/About';
import Support from './pages/Support';
import Terms from './pages/Terms';
import Privacy from './pages/Privacy';
import ShippingReturns from './pages/ShippingReturns';
import ScrollToTop from './components/ScrollToTop';
import { apiClient, withRetry } from './services/apiClient';
import { useNotifier } from './context/NotificationProvider';
import { WishlistProvider } from './context/WishlistContext';
import WishlistPage from './pages/Wishlist';
import { addWishlistItem, getCustomerToken, getGuestWishlistIds, getWishlist, mergeWishlist, removeWishlistItem, saveGuestWishlistIds } from './services/wishlist';

const theme = createTheme({
  palette: {
    primary: {
      main: '#2456A6',
    },
    secondary: {
      main: '#B65C43',
    },
    background: {
      default: '#F4F0E9',
      paper: '#FFFFFF',
    },
    text: {
      primary: '#292D32',
      secondary: '#59636B',
    },
  },
  typography: {
    fontFamily: 'Poppins, sans-serif',
    h3: {
      fontWeight: 700,
    },
    h4: {
      fontWeight: 700,
    },
    button: {
      fontWeight: 600,
      textTransform: 'none',
    },
  },
  shape: {
    borderRadius: 14,
  },
  components: {
    MuiButton: {
      styleOverrides: {
        root: {
          borderRadius: 999,
          textTransform: 'none',
          paddingInline: 20,
        },
      },
    },
    MuiPaper: {
      styleOverrides: {
        root: {
          borderRadius: 18,
          boxShadow: '0 12px 28px rgba(41, 45, 50, 0.10)',
        },
      },
    },
    MuiCard: {
      styleOverrides: {
        root: {
          borderRadius: 18,
          boxShadow: '0 16px 34px rgba(41, 45, 50, 0.10)',
        },
      },
    },
    MuiCssBaseline: {
      styleOverrides: {
        body: {
          backgroundColor: '#F4F0E9',
          color: '#292D32',
        },
      },
    },
  },
});

function App() {
  const [products, setProducts] = React.useState([]);
  const [homeProducts, setHomeProducts] = React.useState([]);
  const [cart, setCart] = React.useState([]);
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState(null);
  const [homeProductsLoading, setHomeProductsLoading] = React.useState(true);
  const [homeProductsError, setHomeProductsError] = React.useState(null);
  const [wishlist, setWishlist] = React.useState([]);
  const [wishlistPending, setWishlistPending] = React.useState(new Set());
  const [wishlistLoading, setWishlistLoading] = React.useState(false);
  const [wishlistError, setWishlistError] = React.useState(null);
  const [authToken, setAuthToken] = React.useState(getCustomerToken());
  const { notify } = useNotifier();

  React.useEffect(() => {
    const interval = setInterval(() => setAuthToken(current => {
      const next = getCustomerToken();
      return current === next ? current : next;
    }), 800);
    return () => clearInterval(interval);
  }, []);

  React.useEffect(() => {
    try {
      const storedCart = JSON.parse(localStorage.getItem('fusionCart'));
      if (Array.isArray(storedCart) && storedCart.length) {
        setCart(storedCart);
      }
    } catch (storageError) {
      console.warn('Unable to restore saved cart', storageError);
    }
  }, []);

  React.useEffect(() => {
    localStorage.setItem('fusionCart', JSON.stringify(cart));
  }, [cart]);

  React.useEffect(() => {
    let active = true;

    const fetchProducts = async () => {
      setError(null);
      try {
        const { data } = await withRetry(() => apiClient.get('products'));
        if (!Array.isArray(data)) {
          throw new Error('Unexpected products response.');
        }
        const normalized = data.map(product => {
          const canonicalId = product?._id || product?.id;
          return {
            ...product,
            _id: canonicalId,
            id: canonicalId,
          };
        });
        if (active) {
          setProducts(normalized);

          let removedCount = 0;
          setCart(prevCart => {
            if (!Array.isArray(prevCart) || !prevCart.length) return prevCart;
            const validIds = new Set(normalized.map(item => String(item.id)));
            const sanitized = prevCart.filter(item => {
              const itemId = item?._id || item?.id;
              return itemId && validIds.has(String(itemId));
            });
            removedCount = prevCart.length - sanitized.length;
            return sanitized;
          });

          if (removedCount > 0) {
            notify({ severity: 'info', message: 'We refreshed your cart to remove items that are no longer available.' });
          }
        }
      } catch (err) {
        if (!active) return;
        console.error('Error fetching products:', err);
        setProducts([]);
        setError(err);
        notify({ severity: 'error', message: 'Unable to load products. Please try again shortly.' });
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    };

    fetchProducts();

    const fetchRandomHomeProducts = async () => {
      setHomeProductsLoading(true);
      setHomeProductsError(null);
      try {
        const { data } = await withRetry(() => apiClient.get('products/random', { params: { limit: 24 } }));
        if (!Array.isArray(data)) throw new Error('Unexpected random products response.');
        const normalized = data.map(product => {
          const canonicalId = product?._id || product?.id;
          return { ...product, _id: canonicalId, id: canonicalId };
        });
        if (active) setHomeProducts(normalized);
      } catch (err) {
        if (!active) return;
        console.error('Error fetching random homepage products:', err);
        setHomeProducts([]);
        setHomeProductsError(err);
      } finally {
        if (active) setHomeProductsLoading(false);
      }
    };

    fetchRandomHomeProducts();

    return () => {
      active = false;
    };
  }, [notify]);

  React.useEffect(() => {
    let active = true;
    const loadWishlist = async () => {
      setWishlistLoading(true);
      setWishlistError(null);
      const guestIds = getGuestWishlistIds();
      if (!authToken) {
        if (active) setWishlist(products.filter(product => guestIds.includes(String(product.id || product._id))));
        setWishlistLoading(false);
        return;
      }
      try {
        const serverItems = guestIds.length ? await mergeWishlist(guestIds) : await getWishlist();
        if (!active) return;
        if (guestIds.length) saveGuestWishlistIds([]);
        setWishlist(serverItems);
      } catch (wishlistError) {
        if (!active) return;
        setWishlist(products.filter(product => guestIds.includes(String(product.id || product._id))));
        setWishlistError(wishlistError);
        if (guestIds.length) notify({ severity: 'error', message: 'Wishlist sync failed. Your guest wishlist is preserved.' });
      } finally {
        if (active) setWishlistLoading(false);
      }
    };
    void loadWishlist();
    return () => { active = false; };
  }, [authToken, products, notify]);

  const toggleWishlist = React.useCallback(async product => {
    const id = product?._id || product?.id;
    if (!id || wishlistPending.has(String(id))) return;
    const key = String(id);
    const exists = wishlist.some(item => String(item?._id || item?.id) === key);
    const previousWishlist = wishlist;
    const nextWishlist = exists ? wishlist.filter(item => String(item?._id || item?.id) !== key) : [...wishlist, product];
    setWishlistPending(current => new Set(current).add(key));
    setWishlist(nextWishlist);
    try {
      if (authToken) {
        if (exists) await removeWishlistItem(key);
        else await addWishlistItem(key);
      } else {
        const ids = getGuestWishlistIds();
        saveGuestWishlistIds(exists ? ids.filter(itemId => itemId !== key) : [...ids, key]);
      }
    } catch (wishlistError) {
      setWishlist(previousWishlist);
      notify({ severity: 'error', message: 'Unable to update wishlist. Please try again.' });
    } finally {
      setWishlistPending(current => { const next = new Set(current); next.delete(key); return next; });
    }
  }, [authToken, notify, wishlist, wishlistPending]);

  const removeWishlist = React.useCallback(product => toggleWishlist(product), [toggleWishlist]);

  const wishlistContext = React.useMemo(() => ({ wishlist, toggleWishlist, removeWishlist, wishlistPending, wishlistLoading, wishlistError }), [wishlist, toggleWishlist, removeWishlist, wishlistPending, wishlistLoading, wishlistError]);

  const addToCart = React.useCallback(
    product => {
      if (!product) return;
      const canonicalId = product._id || product.id;
      if (!canonicalId) {
        notify({ severity: 'error', message: 'Unable to add this product right now.' });
        return;
      }
      setCart(prevCart => {
        const alreadyInCart = prevCart.some(item => item.id === canonicalId || item._id === canonicalId);
        if (alreadyInCart) {
          notify({ severity: 'info', message: 'Item is already in your cart.' });
          return prevCart;
        }
        notify({ severity: 'success', message: 'Added to cart!' });
        const normalized = { ...product, id: canonicalId, _id: canonicalId };
        return [...prevCart, normalized];
      });
    },
    [notify]
  );

  const handleOrderComplete = React.useCallback(() => {
    setCart([]);
    notify({ severity: 'success', message: 'Thank you for your order!' });
  }, [notify]);

  return (
    <ThemeProvider theme={theme}>
      <CssBaseline />
      <BrowserRouter>
        <WishlistProvider value={wishlistContext}>
        <ScrollToTop />
        <NavigationBar cartItemCount={cart.length} />
        <Box component="main" sx={{ minHeight: 'calc(100vh - 200px)' }}>
          <Container maxWidth="xl" sx={{ pb: 8 }}>
            <Routes>
              <Route path="/" element={<Home products={products} randomProducts={homeProducts} loading={loading} error={error} randomLoading={homeProductsLoading} randomError={homeProductsError} addToCart={addToCart} />} />

              <Route path="/shop" element={<Shop products={products} addToCart={addToCart} loading={loading} error={error} />} />

              <Route path="/about" element={<About />} />

              <Route path="/support" element={<Support />} />

              <Route path="/terms" element={<Terms />} />

              <Route path="/privacy" element={<Privacy />} />

              <Route path="/shipping-returns" element={<ShippingReturns />} />


              <Route path="/cart" element={<Cart cart={cart} setCart={setCart} />} />

              <Route path="/wishlist" element={<WishlistPage addToCart={addToCart} />} />

              <Route path="/checkout" element={<Checkout cartItems={cart} onOrderComplete={handleOrderComplete} />} />

              <Route path="/order-success" element={<OrderSuccess />} />

              <Route path="/product/:id" element={<ProductDetails addToCart={addToCart} />} />

              <Route path="/login" element={<Login />} />

              <Route path="/register" element={<Register />} />

              <Route path="/forgot-password" element={<ForgotPassword />} />

              <Route path="/reset-password" element={<ResetPassword />} />

              <Route path="*" element={<NotFoundPage />} />
            </Routes>
          </Container>
        </Box>
        <Footer />
        </WishlistProvider>
      </BrowserRouter>
    </ThemeProvider>
  );
}

export default App;
