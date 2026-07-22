import { defineConfig, devices } from '@playwright/test';

import { e2eBaseURL, e2eEnvironment } from './tests/e2e/environment';

const shouldStartServer = process.env.PLAYWRIGHT_START_SERVER !== 'false';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 60_000,
    expect: {
        timeout: 10_000,
    },
    fullyParallel: false,
    workers: 1,
    reporter: process.env.CI
        ? [['list'], ['html', { open: 'never' }]]
        : [['list'], ['html', { open: 'never' }]],
    globalSetup: './tests/e2e/global-setup.ts',
    globalTeardown: './tests/e2e/global-teardown.ts',
    use: {
        baseURL: e2eBaseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        actionTimeout: 15_000,
        navigationTimeout: 30_000,
    },
    webServer: shouldStartServer
        ? {
              command: 'php artisan serve --host=127.0.0.1 --port=8787',
              url: e2eBaseURL,
              env: e2eEnvironment,
              reuseExistingServer: false,
              timeout: 120_000,
          }
        : undefined,
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'firefox',
            use: { ...devices['Desktop Firefox'] },
        },
        {
            name: 'webkit',
            use: { ...devices['Desktop Safari'] },
        },
        {
            name: 'mobile-chromium',
            use: { ...devices['Pixel 7'] },
        },
    ],
});
