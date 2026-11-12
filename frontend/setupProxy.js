const { createProxyMiddleware } = require('http-proxy-middleware');

module.exports = function (app) {
  const backendProxy = createProxyMiddleware({
    target: 'http://localhost:5000',
    changeOrigin: true,
  });

  app.use('/api', backendProxy);
  app.use('/media', backendProxy);
};
