import * as React from 'react';
import { useLocation } from 'react-router-dom';
import { hasMetaConsent, trackMetaPageView } from './metaPixel';

export default function MetaPixelPageViews() {
  const location = useLocation();
  const [consentVersion, setConsentVersion] = React.useState(0);
  React.useEffect(() => {
    const refresh = () => setConsentVersion(value => value + 1);
    window.addEventListener('fastxhorizon:cookie-consent-changed', refresh);
    return () => window.removeEventListener('fastxhorizon:cookie-consent-changed', refresh);
  }, []);
  React.useEffect(() => {
    if (hasMetaConsent()) trackMetaPageView(`${location.pathname}${location.search}${location.hash}`);
  }, [location.pathname, location.search, location.hash, consentVersion]);
  return null;
}
