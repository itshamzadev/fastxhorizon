import * as React from 'react';
import { Box, Container, Grid, Typography, Link as MuiLink, Stack, TextField, IconButton, Divider, Chip, Tooltip } from '@mui/material';
import { Link as RouterLink } from 'react-router-dom';
import SendIcon from '@mui/icons-material/Send';
import GitHubIcon from '@mui/icons-material/GitHub';
import LinkedInIcon from '@mui/icons-material/LinkedIn';
import EmailIcon from '@mui/icons-material/Email';
import LanguageIcon from '@mui/icons-material/Language';
import PrivacyTipOutlinedIcon from '@mui/icons-material/PrivacyTipOutlined';
import GavelIcon from '@mui/icons-material/Gavel';
import LocalShippingIcon from '@mui/icons-material/LocalShipping';
import SupportAgentIcon from '@mui/icons-material/SupportAgent';
import { useNotifier } from '../context/NotificationProvider';
import { apiClient } from '../services/apiClient';

const quickLinks = [
  { label: 'Home', to: '/' },
  { label: 'Shop', to: '/shop' },
  { label: 'About', to: '/about' },
  { label: 'Support', to: '/support' },
  { label: 'Cart', to: '/cart' },
];

const helpLinks = [
  { label: 'Shipping & Returns', to: '/shipping-returns' },
  { label: 'Terms & Conditions', to: '/terms' },
  { label: 'Privacy Policy', to: '/privacy' },
  { label: 'FAQ', to: '/support#faq' },
  { label: 'Contact Us', to: '/support#contact' },
];

const socialLinks = [
  { icon: <GitHubIcon />, label: 'GitHub', href: process.env.REACT_APP_SOCIAL_GITHUB },
  { icon: <LinkedInIcon />, label: 'LinkedIn', href: process.env.REACT_APP_SOCIAL_LINKEDIN },
  { icon: <LanguageIcon />, label: 'Website', href: process.env.REACT_APP_SOCIAL_WEBSITE },
  { icon: <EmailIcon />, label: 'Email', href: process.env.REACT_APP_CONTACT_EMAIL ? `mailto:${process.env.REACT_APP_CONTACT_EMAIL}` : '' },
].filter(link => link.href);

const policyLinks = [
  { label: 'Privacy', to: '/privacy', icon: <PrivacyTipOutlinedIcon fontSize="small" /> },
  { label: 'Terms', to: '/terms', icon: <GavelIcon fontSize="small" /> },
  { label: 'Shipping & Returns', to: '/shipping-returns', icon: <LocalShippingIcon fontSize="small" /> },
  { label: 'Contact', to: '/support#contact', icon: <SupportAgentIcon fontSize="small" /> },
];

function Footer() {
  const [email, setEmail] = React.useState('');
  const { notify } = useNotifier();
  const [submitting, setSubmitting] = React.useState(false);

  const handleSubmit = event => {
    event.preventDefault();
    const trimmed = email.trim();
    if (!trimmed || !/[^@\s]+@[^@\s]+\.[^@\s]+/.test(trimmed)) {
      notify({ severity: 'warning', message: 'Enter a valid email address.' });
      return;
    }
    setSubmitting(true);
    apiClient.post('/newsletter/subscribe', { email: trimmed, source: 'footer' })
      .then(response => {
        notify({ severity: 'info', message: response?.data?.message || 'Subscription saved. Confirmation is pending.' });
        setEmail('');
      })
      .catch(error => {
        notify({ severity: 'error', message: error?.response?.data?.message || 'Unable to subscribe right now. Please try again.' });
      })
      .finally(() => setSubmitting(false));
  };

  const linkSx = { color: '#D8D1C6', '&:hover': { color: '#C5A47E' } };

  return (
    <Box component="footer" sx={{ background: '#292D32', color: '#F4F0E9', mt: 8, borderTop: '4px solid #C5A47E' }}>
      <Container maxWidth="xl" sx={{ py: { xs: 6, md: 8 } }}>
        <Grid container spacing={5}>
          <Grid item xs={12} sm={6} md={4}>
            <MuiLink component={RouterLink} to="/" aria-label="FastXHorizon home" sx={{ display: 'inline-flex', lineHeight: 0, mb: 2 }}>
              <Box component="img" src="/fastxhorizon.png" alt="FastXHorizon" sx={{ display: 'block', width: { xs: 190, sm: 220 }, maxWidth: '70vw', height: 'auto' }} />
            </MuiLink>
            <Typography variant="body2" sx={{ color: '#D8D1C6', maxWidth: 360 }}>
              A multi-category marketplace for thoughtfully presented tech, jewellery, accessories and everyday essentials.
            </Typography>
            {socialLinks.length > 0 && (
              <Stack direction="row" spacing={1.5} sx={{ mt: 3 }}>
                {socialLinks.map(link => (
                  <IconButton key={link.label} component="a" href={link.href} target="_blank" rel="noopener noreferrer" color="primary" size="small" aria-label={link.label} sx={{ color: '#292D32', border: '1px solid #C5A47E', bgcolor: '#F4F0E9', '&:hover': { bgcolor: '#E6F0EA', color: '#256B58' } }}>
                    {link.icon}
                  </IconButton>
                ))}
              </Stack>
            )}
          </Grid>

          <Grid item xs={12} sm={6} md={2.5}>
            <Typography variant="subtitle1" sx={{ color: '#C5A47E', fontWeight: 700, mb: 2 }}>Explore</Typography>
            <Stack spacing={1.2}>
              {quickLinks.map(link => <MuiLink key={link.to} component={RouterLink} to={link.to} color="inherit" underline="none" sx={linkSx}>{link.label}</MuiLink>)}
            </Stack>
          </Grid>

          <Grid item xs={12} sm={6} md={2.5}>
            <Typography variant="subtitle1" sx={{ color: '#C5A47E', fontWeight: 700, mb: 2 }}>Customer Care</Typography>
            <Stack spacing={1.2}>
              {helpLinks.map(link => <MuiLink key={link.to} component={RouterLink} to={link.to} color="inherit" underline="none" sx={linkSx}>{link.label}</MuiLink>)}
            </Stack>
          </Grid>

          <Grid item xs={12} sm={6} md={3}>
            <Typography variant="subtitle1" sx={{ color: '#C5A47E', fontWeight: 700, mb: 2 }}>Stay Connected</Typography>
              <Typography variant="body2" sx={{ color: '#D8D1C6', mb: 2 }}>
              Receive product updates and occasional marketplace news from FastXHorizon.
            </Typography>
            <Box component="form" onSubmit={handleSubmit} sx={{ display: 'flex', gap: 1, alignItems: 'stretch' }}>
              <TextField type="email" value={email} onChange={event => setEmail(event.target.value)} placeholder="your@email.com" size="small" variant="outlined" disabled={submitting} inputProps={{ 'aria-label': 'Email for newsletter' }} sx={{ flexGrow: 1, minWidth: 0, bgcolor: '#FFFFFF', borderRadius: 1 }} />
              <IconButton type="submit" color="primary" disabled={submitting} aria-label="Subscribe to newsletter" sx={{ flexShrink: 0, borderRadius: 1.5, bgcolor: '#FFFFFF', border: '1px solid #E2E8F0', '&:hover': { bgcolor: '#EFF6FF' } }}><SendIcon /></IconButton>
            </Box>
            <Chip label="Confirmation pending · We respect your inbox" size="small" sx={{ mt: 2, bgcolor: '#F1F5F9', color: '#64748B' }} />
          </Grid>
        </Grid>

        <Divider sx={{ my: 5, borderColor: '#E2E8F0' }} />
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} justifyContent="space-between" alignItems={{ xs: 'flex-start', md: 'center' }}>
          <Typography variant="body2" sx={{ color: '#64748B' }}>© {new Date().getFullYear()} FASTXHORIZON (PRIVATE) LIMITED. All rights reserved.</Typography>
          <Stack direction="row" spacing={2} sx={{ color: '#64748B', display: { xs: 'none', sm: 'flex' } }}>
            {policyLinks.map(link => <MuiLink key={link.to} component={RouterLink} to={link.to} color="inherit" underline="none" sx={{ '&:hover': { color: '#C5A47E' } }}>{link.label}</MuiLink>)}
          </Stack>
          <Stack direction="row" spacing={1} sx={{ display: { xs: 'flex', sm: 'none' } }}>
            {policyLinks.map(link => <Tooltip key={link.to} title={link.label} enterTouchDelay={0} leaveTouchDelay={2500}><IconButton component={RouterLink} to={link.to} size="small" aria-label={link.label} sx={{ color: '#292D32', bgcolor: '#F4F0E9', border: '1px solid #C5A47E', '&:hover': { bgcolor: '#E6F0EA', color: '#256B58' } }}>{link.icon}</IconButton></Tooltip>)}
          </Stack>
        </Stack>
      </Container>
    </Box>
  );
}

export default Footer;
