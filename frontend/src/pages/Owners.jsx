import * as React from 'react';
import { Box, Chip, Container, Grid, Paper, Stack, Typography } from '@mui/material';
import SeoMeta, { SITE_URL } from '../components/SeoMeta';

const leaders = [
  {
    name: 'Muhammad Younas',
    role: 'CEO & Director',
    image: '/mdyounas.png',
    bio: 'Leads FASTXHORIZON’s vision, marketplace direction and long-term customer experience as the company grows from its 2026 launch.',
  },
  {
    name: 'Muhammad Hamza',
    role: 'Head of Technology & Operations',
    image: '/mdhamza.png',
    bio: 'Leads the technology, platform operations and day-to-day systems that support a reliable and straightforward shopping experience.',
  },
];

export default function Owners() {
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: 'FASTXHORIZON',
    url: `${SITE_URL}/`,
    employee: leaders.map(person => ({ '@type': 'Person', name: person.name, jobTitle: person.role, image: `${SITE_URL}${person.image}`, worksFor: { '@type': 'Organization', name: 'FASTXHORIZON' } })),
  };

  return (
    <>
      <SeoMeta title="Owners & Leadership | FASTXHORIZON" description="Meet the FASTXHORIZON leadership team behind our 2026 multi-category online marketplace." jsonLd={jsonLd} />
      <Container maxWidth="lg" className="luxury-page luxury-corporate" sx={{ pb: 10 }}>
        <Stack spacing={2} alignItems="center" textAlign="center" sx={{ mt: 6, mb: 6 }}>
          <Chip label="FASTXHORIZON leadership" color="primary" variant="outlined" />
          <Typography variant="h3" fontWeight={800}>The people behind the marketplace.</Typography>
          <Typography variant="body1" color="text.secondary" sx={{ maxWidth: 680, lineHeight: 1.9 }}>
            FASTXHORIZON began in 2026 with a simple goal: make online product discovery clear, dependable and ready for more categories over time.
          </Typography>
        </Stack>

        <Grid container spacing={4} justifyContent="center">
          {leaders.map(person => (
            <Grid item xs={12} md={6} key={person.name}>
              <Paper elevation={0} sx={{ height: '100%', overflow: 'hidden', borderRadius: 4, border: '1px solid', borderColor: 'divider' }}>
                <Box component="img" src={person.image} alt={`${person.name}, ${person.role}`} sx={{ display: 'block', width: '100%', aspectRatio: '1 / 1', objectFit: 'cover', objectPosition: 'center' }} />
                <Stack spacing={1.5} sx={{ p: { xs: 3, md: 4 } }}>
                  <Typography variant="overline" color="primary" sx={{ letterSpacing: '.14em', fontWeight: 800 }}>{person.role}</Typography>
                  <Typography variant="h4" fontWeight={800}>{person.name}</Typography>
                  <Typography variant="body1" color="text.secondary" sx={{ lineHeight: 1.8 }}>{person.bio}</Typography>
                </Stack>
              </Paper>
            </Grid>
          ))}
        </Grid>

        <Paper elevation={0} sx={{ mt: 6, p: { xs: 3, md: 5 }, borderRadius: 4, bgcolor: 'background.paper' }}>
          <Typography variant="h5" fontWeight={800} gutterBottom>Built for the next chapter</Typography>
          <Typography variant="body1" color="text.secondary" sx={{ lineHeight: 1.9 }}>
            Our leadership team is focused on honest product information, responsible operations and a storefront that can expand from jewellery into clothing, perfumes, watches and more.
          </Typography>
        </Paper>
      </Container>
    </>
  );
}
