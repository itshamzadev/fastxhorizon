import * as React from 'react';
import { Box, Chip, Container, Divider, Grid, List, ListItem, ListItemIcon, ListItemText, Paper, Stack, Typography } from '@mui/material';
import GavelIcon from '@mui/icons-material/Gavel';
import VerifiedUserIcon from '@mui/icons-material/VerifiedUser';
import LocalShippingIcon from '@mui/icons-material/LocalShipping';
import AutorenewIcon from '@mui/icons-material/Autorenew';
import PaymentsIcon from '@mui/icons-material/Payments';
import SupportAgentIcon from '@mui/icons-material/SupportAgent';

const termsSections = [
  {
    title: 'Using our platform',
    copy: 'FASTXHORIZON (PRIVATE) LIMITED provides a multi-category e-commerce marketplace. By visiting the site you agree to engage respectfully, refrain from malicious activity, and submit accurate personal details. Accounts may be restricted when suspicious behaviour is detected to help keep the community safe.',
    icon: <VerifiedUserIcon color="primary" fontSize="large" />,
  },
  {
    title: 'Orders, billing, and pricing',
    copy: 'Checkout totals are confirmed at the point of order, including any applicable delivery charges. We may cancel or refund an order if a price, product availability or catalog error is identified. Promotions and bundles will show their eligibility conditions clearly.',
    icon: <PaymentsIcon color="primary" fontSize="large" />,
  },
  {
    title: 'Shipping commitments',
    copy: 'Shipping charges, fulfilment details and any applicable requirements are communicated for an order when ordering is available. Please verify your shipping address carefully.',
    icon: <LocalShippingIcon color="primary" fontSize="large" />,
  },
  {
    title: 'Returns and exchanges',
    copy: 'Eligible products may be returned according to the Shipping & Returns Policy. Clothing may be returned if the colour fades, runs, transfers or the fabric is damaged on arrival, provided the item is reported promptly with clear photos and has not been washed, worn or altered. Size, preference or change-of-mind returns require the item to be unused with tags and original packaging. Hygiene, customised and clearly marked final-sale items may be excluded.',
    icon: <AutorenewIcon color="primary" fontSize="large" />,
  },
  {
    title: 'Support and escalation',
    copy: 'Our concierge team loves solving tough problems. If an issue arises, contact us through the Support Centre so we can document the details and provide a resolution timeline. Emergencies impacting device safety will always receive priority routing.',
    icon: <SupportAgentIcon color="primary" fontSize="large" />,
  },
];

function Terms() {
  return (
    <Container maxWidth="md" className="luxury-page luxury-policy" sx={{ py: { xs: 6, md: 10 } }}>
      <Stack spacing={3} alignItems="center" textAlign="center" sx={{ mb: 4 }}>
        <Chip label="Terms of Service" color="primary" variant="outlined" />
        <Typography variant="h3" fontWeight={700}>
          The agreement that powers our partnership
        </Typography>
        <Typography variant="body1" color="text.secondary" sx={{ maxWidth: 680 }}>
          These terms explain how the FastXHorizon marketplace operates and how customer information, products and support interactions are handled.
        </Typography>
        <Typography variant="caption" color="text.secondary">
          Last updated: November 2, 2026
        </Typography>
      </Stack>

      <Paper elevation={0} sx={{ pr: 4, pt: 1, borderRadius: 4 }}>
        <Stack spacing={4}>
          {termsSections.map(section => (
            <Grid container spacing={3} key={section.title} alignItems="flex-start">
              <Grid item xs={12} sm={2} display="flex" justifyContent="center">
                {section.icon}
              </Grid>
              <Grid item xs={12} sm={10}>
                <Typography variant="h5" fontWeight={700} gutterBottom>
                  {section.title}
                </Typography>
                <Typography variant="body1" color="text.secondary">
                  {section.copy}
                </Typography>
              </Grid>
            </Grid>
          ))}

          <Divider />

          <Box>
            <Typography variant="h6" fontWeight={700} gutterBottom sx={{ pl: 4 }}>
              Your responsibilities
            </Typography>
            <List dense sx={{ pl: 4, pb: 4 }}>
              <ListItem disableGutters>
                <ListItemIcon sx={{ minWidth: 32 }}>
                  <GavelIcon color="primary" fontSize="small" />
                </ListItemIcon>
                <ListItemText primary="Provide accurate account and billing details so we can process your order correctly." />
              </ListItem>
              <ListItem disableGutters>
                <ListItemIcon sx={{ minWidth: 32 }}>
                  <GavelIcon color="primary" fontSize="small" />
                </ListItemIcon>
                <ListItemText primary="Respect intellectual property and only use site content for personal shopping." />
              </ListItem>
              <ListItem disableGutters>
                <ListItemIcon sx={{ minWidth: 32 }}>
                  <GavelIcon color="primary" fontSize="small" />
                </ListItemIcon>
                <ListItemText primary="Notify us quickly if you believe your account has been accessed without permission." />
              </ListItem>
            </List>
          </Box>
        </Stack>
      </Paper>
    </Container>
  );
}

export default Terms;
