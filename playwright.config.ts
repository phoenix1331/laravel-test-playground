import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for e2e tests.
 *
 * E2E tests run against a real, booted Laravel server. They drive an actual
 * browser, so they exercise the full stack: routing → controller → view →
 * rendered HTML → JavaScript. That's what makes them slow but high-confidence.
 *
 * The baseURL points at the artisan dev server we start below. Change this if
 * you run the app on a different port.
 */
export default defineConfig({
    testDir: './e2e',
    fullyParallel: false, // keep sequential so the dev server stays stable
    retries: 0,
    reporter: 'list',

    use: {
        baseURL: 'http://127.0.0.1:8000',
        // Capture a screenshot on failure so you can see exactly what broke.
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],

    /**
     * Start the Laravel dev server before tests run and shut it down after.
     * Playwright waits until the URL responds before running any test.
     */
    webServer: {
        command: 'php artisan serve --port=8000',
        url: 'http://127.0.0.1:8000',
        reuseExistingServer: true,
        timeout: 15_000,
    },
});
