const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './e2e/tests',
  timeout: 30_000,
  expect: { timeout: 5_000 },
  fullyParallel: false,
  retries: 0,
  reporter: [['list'], ['html', { outputFolder: 'e2e/report', open: 'never' }]],
  screenshot: 'only-on-failure',
  trace: 'retain-on-failure',
  globalSetup: './e2e/global-setup.js',
  globalTeardown: './e2e/global-teardown.js',
  use: { baseURL: 'http://127.0.0.1:3001', video: 'retain-on-failure', testIdAttribute: 'data-testid' },
  webServer: [
    { command: 'node e2e/start-service.js backend', url: 'http://127.0.0.1:5001/health', timeout: 120_000, reuseExistingServer: false },
    { command: 'node e2e/start-service.js frontend', url: 'http://127.0.0.1:3001', timeout: 120_000, reuseExistingServer: false },
    { command: 'node e2e/start-service.js admin', url: 'http://127.0.0.1:5174', timeout: 120_000, reuseExistingServer: false },
  ],
  projects: [
    { name: 'chromium-desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1366, height: 900 } } },
    { name: 'chromium-mobile', use: { ...devices['Pixel 5'], viewport: { width: 390, height: 844 } } },
  ],
});
