import { defineConfig, devices } from '@playwright/test';

/**
 * AI-Scribe v3 E2E configuration.
 *
 * Targets the wp-env development site (port 8888). Start it first:
 *   npm run env:start
 *
 * Three viewport projects per REFACTOR.md §9.3: mobile 375, tablet 768, desktop 1280.
 * Screenshots are always captured; traces retained on failure.
 */
export default defineConfig( {
	testDir: './tests/e2e',
	outputDir: './tests/e2e/results',
	fullyParallel: false,
	workers: 1,
	retries: 0,
	timeout: 120_000,
	expect: { timeout: 15_000 },
	reporter: [
		[ 'list' ],
		[ 'html', { outputFolder: './tests/e2e/report', open: 'never' } ],
	],
	use: {
		baseURL: process.env.WP_BASE_URL ?? 'http://localhost:8888',
		screenshot: 'on',
		trace: 'retain-on-failure',
		video: 'retain-on-failure',
	},
	projects: [
		{
			name: 'mobile-375',
			use: { ...devices[ 'Desktop Chrome' ], viewport: { width: 375, height: 812 } },
		},
		{
			name: 'tablet-768',
			use: { ...devices[ 'Desktop Chrome' ], viewport: { width: 768, height: 1024 } },
		},
		{
			name: 'desktop-1280',
			use: { ...devices[ 'Desktop Chrome' ], viewport: { width: 1280, height: 800 } },
		},
	],
} );
