import * as React from 'react';
import { Box, Button, Typography } from '@mui/material';

const KEY = 'marketingConsent';

export default function MarketingConsentBanner() {
  const [visible, setVisible] = React.useState(false);
  React.useEffect(() => {
    try { setVisible(localStorage.getItem(KEY) === null); } catch (_) { setVisible(false); }
  }, []);
  const choose = value => {
    try { localStorage.setItem(KEY, String(value)); } catch (_) { /* continue without persistence */ }
    setVisible(false);
    window.dispatchEvent(new Event('fastxhorizon:cookie-consent-changed'));
  };
  if (!visible) return <Button size="small" onClick={() => setVisible(true)} sx={{ position: 'fixed', zIndex: 1400, right: 16, bottom: 16, bgcolor: 'background.paper', border: '1px solid', borderColor: 'divider', boxShadow: 3 }}>Privacy choices</Button>;
  return <Box role="dialog" aria-label="Marketing cookies" sx={{ position: 'fixed', zIndex: 1400, left: 16, right: 16, bottom: 16, maxWidth: 720, mx: 'auto', p: 2, bgcolor: 'background.paper', border: '1px solid', borderColor: 'divider', borderRadius: 2, boxShadow: 8 }}><Typography variant="body2">We use optional marketing cookies to understand visits and improve FASTXHORIZON. You can decline them.</Typography><Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1, mt: 1.5 }}><Button size="small" onClick={() => choose(false)}>Decline</Button><Button size="small" variant="contained" onClick={() => choose(true)}>Accept</Button></Box></Box>;
}
