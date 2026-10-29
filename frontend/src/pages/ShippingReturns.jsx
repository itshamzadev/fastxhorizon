import * as React from 'react';
import { Accordion, AccordionDetails, AccordionSummary, Box, Chip, Container, Divider, Grid, Paper, Stack, Typography } from '@mui/material';
import LocalShippingIcon from '@mui/icons-material/LocalShipping';
import ReplayIcon from '@mui/icons-material/Replay';
import Inventory2Icon from '@mui/icons-material/Inventory2';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import VerifiedIcon from '@mui/icons-material/Verified';
import ChecklistIcon from '@mui/icons-material/Checklist';

const shippingSteps = [
  { title: 'Order confirmed', icon: <VerifiedIcon color="primary" fontSize="small" />, summary: 'Your order is recorded and the next fulfillment status is shared through your account.', details: ['Payment and inventory information are reviewed according to the selected method.', 'Any order notes are passed to the fulfillment team.'] },
  { title: 'Prepared for dispatch', icon: <ChecklistIcon color="primary" fontSize="small" />, summary: 'The fulfillment team prepares your items for dispatch and quality checks.', details: ['Packaging and item handling follow the requirements of the products in your order.', 'Status changes remain available in your authenticated account order history.'] },
  { title: 'Fulfillment update', icon: <LocalShippingIcon color="primary" fontSize="small" />, summary: 'Further fulfillment details will be communicated after your order is confirmed.', details: ['Please keep your contact details current so customer care can reach you when needed.', 'Contact support if you need help with an order status.'] },
];

function ShippingReturns() {
  return (
    <Container maxWidth="md" className="luxury-page luxury-policy" sx={{ py: { xs: 6, md: 10 } }}>
      <Stack spacing={3} alignItems="center" textAlign="center" sx={{ mb: 4 }}>
        <Chip label="Shipping & Returns" color="primary" variant="outlined" />
        <Typography variant="h3" fontWeight={700}>Shipping and returns, clearly explained</Typography>
        <Typography variant="body1" color="text.secondary" sx={{ maxWidth: 680 }}>We confirm order handling details after checkout and keep the relevant status information available in your account.</Typography>
      </Stack>

      <Grid container spacing={4}>
        <Grid item xs={12} md={6}>
          <Paper elevation={0} sx={{ p: { xs: 3, md: 4 }, borderRadius: 4, height: '100%' }}>
            <Stack spacing={3}>
              <Stack direction="row" spacing={2} alignItems="center"><LocalShippingIcon color="primary" fontSize="large" /><Typography variant="h5" fontWeight={700}>Shipping details</Typography></Stack>
              <Typography variant="body2" color="text.secondary">Shipping charges, destination requirements and fulfillment status are shown during checkout or communicated after confirmation.</Typography>
              <Typography variant="body2" color="text.secondary">If additional information is needed for your order, customer care will contact you using the details provided at checkout.</Typography>
            </Stack>
          </Paper>
        </Grid>
        <Grid item xs={12} md={6}>
          <Paper elevation={0} sx={{ p: { xs: 3, md: 4 }, borderRadius: 4, height: '100%' }}>
            <Stack spacing={3}>
              <Stack direction="row" spacing={2} alignItems="center"><Inventory2Icon color="primary" fontSize="large" /><Typography variant="h5" fontWeight={700}>Order preparation</Typography></Stack>
              <Typography variant="body2" color="text.secondary">Products are checked and prepared according to the order and payment method. Delivery details will be communicated after your order is confirmed.</Typography>
              <Typography variant="body2" color="text.secondary">For help with an order, use the support page or your authenticated account order history.</Typography>
            </Stack>
          </Paper>
        </Grid>
      </Grid>

      <Paper elevation={0} sx={{ mt: 5, p: { xs: 3, md: 5 }, borderRadius: 4 }}>
        <Stack spacing={3}>
          <Typography variant="h5" fontWeight={700}>Returns and refunds</Typography>
          <Typography variant="body2" color="text.secondary">Return eligibility, item condition requirements and any applicable charges are communicated for the relevant order. Contact support before sending an item back so the request can be recorded correctly.</Typography>
          <Divider />
          <Stack direction="row" spacing={1} alignItems="center"><ReplayIcon color="primary" fontSize="small" /><Typography variant="caption" color="text.secondary">Keep the original packaging and order information where possible.</Typography></Stack>
        </Stack>
      </Paper>

      <Paper elevation={0} sx={{ mt: 5, p: { xs: 3, md: 5 }, borderRadius: 4 }}>
        <Typography variant="h6" fontWeight={700} gutterBottom>Fulfillment stages</Typography>
        <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>Order status information is updated as fulfillment progresses; no fixed delivery estimate is promised.</Typography>
        <Stack spacing={1.5}>
          {shippingSteps.map((step, index) => <Accordion key={step.title} disableGutters sx={{ borderRadius: 3, '&:before': { display: 'none' } }}><AccordionSummary expandIcon={<ExpandMoreIcon />}><Stack direction="row" spacing={1.5} alignItems="center"><Chip label={index + 1} color="primary" variant="outlined" size="small" /><Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>{step.icon}<Typography variant="subtitle1" fontWeight={700}>{step.title}</Typography></Box></Stack></AccordionSummary><AccordionDetails><Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>{step.summary}</Typography><Stack component="ul" spacing={1} sx={{ pl: 2, m: 0 }}>{step.details.map(detail => <Typography component="li" variant="body2" color="text.secondary" key={detail}>{detail}</Typography>)}</Stack></AccordionDetails></Accordion>)}
        </Stack>
      </Paper>
    </Container>
  );
}

export default ShippingReturns;
