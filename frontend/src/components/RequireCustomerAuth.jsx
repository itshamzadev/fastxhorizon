import React from 'react';
import { Alert, Box, CircularProgress, Typography } from '@mui/material';
import { Navigate, useLocation } from 'react-router-dom';
import { apiClient } from '../services/apiClient';
import { getCustomerToken } from '../services/wishlist';

export default function RequireCustomerAuth({ children }) {
  const location = useLocation();
  const [state, setState] = React.useState({ loading: true, authenticated: false, error: '' });

  React.useEffect(() => {
    let active = true;
    if (!getCustomerToken()) {
      setState({ loading: false, authenticated: false, error: '' });
      return () => { active = false; };
    }
    apiClient.get('auth/me')
      .then(() => active && setState({ loading: false, authenticated: true, error: '' }))
      .catch(error => active && setState({ loading: false, authenticated: false, error: error?.response?.status === 401 ? '' : 'We could not verify your session. Please sign in again.' }));
    return () => { active = false; };
  }, []);

  if (state.loading) {
    return <Box className="luxury-route-loading"><CircularProgress size={28} /><Typography variant="body2">Verifying your customer session…</Typography></Box>;
  }
  if (state.error) return <Alert severity="error" className="luxury-route-error">{state.error}</Alert>;
  if (!state.authenticated) return <Navigate to={`/login?returnTo=${encodeURIComponent(location.pathname + location.search)}`} replace state={{ from: location }} />;
  return children;
}
