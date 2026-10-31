import React from 'react';
import { Link } from 'react-router-dom';
import { List, ListItem, ListItemText, ListItemAvatar, Avatar, Typography, Paper, CircularProgress, Button, Box, Alert } from '@mui/material';
import { formatPrice } from '../utils/currency';
import { resolveMediaUrl } from '../utils/media';

function SearchResults({ results, onResultClick, setSearchResults, variant = 'popover', query = '', loading = false, error = '', onRetry, onViewAll }) {
  const handleItemClick = () => {
    onResultClick();
    setSearchResults([]);
  };

  const isModal = variant === 'modal';
  const paperSx = isModal
    ? {
        width: '100%',
        maxWidth: '100%',
        maxHeight: '60vh',
        overflowY: 'auto',
        padding: 0,
      }
    : {
        width: '100%',
        maxWidth: '50vw',
        maxHeight: '50vh',
        overflowY: 'auto',
        padding: '1rem',
      };

  const listItemSx = {
    borderBottom: '1px solid #eee',
    width: '100%',
    '&:last-child': { borderBottom: 'none' },
    '&:hover': { backgroundColor: '#f5f5f5' },
    px: isModal ? 2 : 1,
  };
  const limit = 6;
  const highlight = text => {
    const value = String(text || '');
    if (!query.trim()) return value;
    const parts = value.split(new RegExp(`(${query.trim().replace(/[.*+?^${}()|[\\]\\]/g, '\\$&')})`, 'ig'));
    return parts.map((part, index) => part.toLowerCase() === query.trim().toLowerCase() ? <Box component="mark" key={`${part}-${index}`} sx={{ bgcolor: '#C5A47E', color: '#292D32', px: 0.15 }}>{part}</Box> : part);
  };

  return (
    <Paper elevation={3} sx={paperSx}>
      <List>
        {loading ? (
          <ListItem sx={{ justifyContent: 'center', py: 3 }}><CircularProgress size={22} aria-label="Loading search results" /></ListItem>
        ) : error ? (
          <ListItem><Alert severity="error" action={<Button color="inherit" size="small" onClick={onRetry}>Retry</Button>}>{error}</Alert></ListItem>
        ) : results.length > 0 ? (
          results.slice(0, limit).map(product => {
            const pid = product._id || product.id;
            return (
              <ListItem key={pid} alignItems="flex-start" component={Link} to={`/product/${pid}`} onClick={handleItemClick} sx={listItemSx}>
                <ListItemAvatar>
                  <Avatar alt={product.name} src={resolveMediaUrl(product.image)} sx={{ borderRadius: '0' }} />
                </ListItemAvatar>
                <ListItemText
                  primary={highlight(product.name)}
                  secondary={
                    <React.Fragment>
                      <Typography sx={{ display: 'inline' }} component="span" variant="body2" color="text.primary">
                        {formatPrice(product.price)}
                      </Typography>
                      {product.shortDescription || product.description ? ` - ${String(product.shortDescription || product.description).slice(0, 50)}...` : ''}
                    </React.Fragment>
                  }
                />
              </ListItem>
            );
          })
        ) : (
          <ListItem><ListItemText primary={query.trim() ? 'No matching products found.' : 'Start typing to search products.'} /></ListItem>
        )}
        {!loading && results.length > 0 && onViewAll && <ListItem sx={{ justifyContent: 'center', borderTop: '1px solid #E2E8F0' }}><Button size="small" onClick={onViewAll}>View all results</Button></ListItem>}
      </List>
    </Paper>
  );
}

export default SearchResults;
