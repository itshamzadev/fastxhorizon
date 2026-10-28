import * as React from 'react';
import { Box, Typography } from '@mui/material';
import { apiClient } from '../services/apiClient';

const DEFAULT_MESSAGE = 'Coming Soon — Discover a New Shopping Experience with FastXHorizon';

function AnnouncementBar() {
  const [announcement, setAnnouncement] = React.useState(null);
  const [messageWidth, setMessageWidth] = React.useState(0);
  const messageRef = React.useRef(null);

  React.useEffect(() => {
    let active = true;
    apiClient.get('storefront/announcement')
      .then(({ data }) => {
        if (active && data?.enabled && data?.message) setAnnouncement(String(data.message));
        else if (active) setAnnouncement(false);
      })
      .catch(error => {
        console.error('Unable to load storefront announcement:', error);
        if (active) setAnnouncement(DEFAULT_MESSAGE);
      });
    return () => { active = false; };
  }, []);

  React.useEffect(() => {
    if (!announcement || !messageRef.current) return undefined;

    const measureMessage = () => {
      const width = messageRef.current?.getBoundingClientRect().width || 0;
      setMessageWidth(previous => (Math.abs(previous - width) > 0.5 ? width : previous));
    };

    measureMessage();
    window.addEventListener('resize', measureMessage);
    const observer = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(measureMessage) : null;
    if (observer) observer.observe(messageRef.current);

    return () => {
      window.removeEventListener('resize', measureMessage);
      observer?.disconnect();
    };
  }, [announcement]);

  if (announcement === null || announcement === false) return null;

  return (
    <Box
      component="aside"
      role="status"
      aria-label="Store announcement"
      sx={{
        width: '100%',
        overflow: 'hidden',
        position: 'relative',
        background: '#256B58',
        color: '#FFFFFF',
        minHeight: 38,
        display: 'flex',
        alignItems: 'center',
        boxShadow: '0 4px 14px rgba(15, 23, 42, 0.12)',
        '@media (prefers-reduced-motion: reduce)': { '& .announcement-track': { animation: 'none', transform: 'translate(-0%, -50%)' } },
      }}
    >
      <Typography
        ref={messageRef}
        component="span"
        className="announcement-track"
        sx={{
          position: 'absolute',
          left: 0,
          top: '50%',
          width: 'max-content',
          whiteSpace: 'nowrap',
          px: { xs: 3, md: 6 },
          fontSize: { xs: '0.78rem', md: '0.9rem' },
          fontWeight: 700,
          letterSpacing: '0.02em',
          visibility: messageWidth > 0 ? 'visible' : 'hidden',
          transform: 'translate(100vw, -50%)',
          animation: messageWidth > 0 ? 'fxhAnnouncement var(--announcement-duration) linear infinite' : 'none',
          '--announcement-duration': messageWidth > 0 ? `${(window.innerWidth + messageWidth) / 90}s` : '1s',
          '@keyframes fxhAnnouncement': {
            from: { transform: 'translate(100vw, -50%)' },
            to: { transform: 'translate(-100%, -50%)' },
          },
        }}
      >
        {announcement}
      </Typography>
    </Box>
  );
}

export default AnnouncementBar;
