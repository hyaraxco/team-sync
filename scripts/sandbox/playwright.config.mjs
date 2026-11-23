import { createRequire } from 'node:module';

const require = createRequire('/opt/fe/package.json');
const { defineConfig, chromium } = require('@playwright/test');
const executablePath = chromium.executablePath();
if (!executablePath.startsWith('/opt/browsers/')) {
  throw new Error('Sandbox browser must come from /opt/browsers.');
}

export default defineConfig({
  testDir: '/source/team-sync-fe/e2e',
  globalSetup: '/source/team-sync-fe/e2e/global-setup.ts',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  outputDir: '/scratch/results/roles/test-results',
  reporter: [
    ['list'],
    ['junit', { outputFile: '/scratch/reports/roles-junit.xml' }],
  ],
  use: {
    baseURL: 'http://127.0.0.1:4173',
    browserName: 'chromium',
    headless: true,
    launchOptions: { executablePath },
    trace: 'off',
    screenshot: 'off',
    video: 'off',
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
  },
  webServer: [
    {
      command: 'php artisan serve --host=127.0.0.1 --port=8000 --no-reload',
      cwd: '/source/team-sync-be',
      url: 'http://127.0.0.1:8000/up',
      reuseExistingServer: false,
      timeout: 60_000,
    },
    {
      command: 'node /opt/fe/node_modules/vite/bin/vite.js --host 127.0.0.1 --port 4173 --strictPort',
      cwd: '/source/team-sync-fe',
      url: 'http://127.0.0.1:4173',
      reuseExistingServer: false,
      timeout: 60_000,
    },
  ],
});
