import { defineConfig, devices } from '@playwright/test';
import { resolve } from 'node:path';

process.env.PLAYWRIGHT_BROWSERS_PATH ??= resolve('.runtime/browsers');
const port = Number(process.env.LOLA_E2E_PORT || 8002);

// Tes browser memakai server khusus di port 8002 dan database lola_demo_test.
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  timeout: 60_000,
  retries: process.env.CI ? 1 : 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: `http://127.0.0.1:${port}`,
    screenshot: 'only-on-failure',
    trace: process.env.CI ? 'retain-on-failure' : 'off',
  },
  projects: [
    { name: 'hp', use: { ...devices['Pixel 7'] } },
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
  ],
  webServer: {
    command: 'node scripts/project.mjs e2e:serve',
    url: `http://127.0.0.1:${port}/up`,
    reuseExistingServer: false,
    timeout: 120_000,
  },
});
