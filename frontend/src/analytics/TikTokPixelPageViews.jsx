import * as React from 'react';
import { useLocation } from 'react-router-dom';
import { hasTikTokConsent, initializeTikTokPixel, trackTikTokPageView } from './tiktokPixel';

export default function TikTokPixelPageViews() {
  const location = useLocation();
  const [consentVersion, setConsentVersion] = React.useState(0);

  React.useEffect(() => {
    const refreshConsent = () => setConsentVersion(version => version + 1);
    window.addEventListener('fastxhorizon:cookie-consent-changed', refreshConsent);
    return () => window.removeEventListener('fastxhorizon:cookie-consent-changed', refreshConsent);
  }, []);

  React.useEffect(() => {
    if (!hasTikTokConsent()) return;
    const routeKey = `${location.pathname}${location.search}${location.hash}`;
    const initialized = initializeTikTokPixel(routeKey);
    if (!initialized) trackTikTokPageView(routeKey);
  }, [location.pathname, location.search, location.hash, consentVersion]);

  return null;
}
