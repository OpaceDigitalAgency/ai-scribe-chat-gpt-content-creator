/**
 * P9 §15.2 — post-update onboarding notice.
 *
 * The notice must: appear on admin screens after install/update, dismiss via
 * the WP X or the inline Dismiss button, persist the dismissal per-site
 * (never re-shows), and keep the AI-Core install CTA hidden while the
 * feature flag defaults off. State is reset via WP-CLI and always restored
 * to "dismissed" so later specs see a quiet admin.
 */

import { test, expect } from '@playwright/test';
import { assertNoConsoleErrors, watchConsole, wpCli, wpCliTry } from './helpers';

const DISMISS_OPTION = 'ai_scribe_onboarding_dismissed';
const NOTICE = '[data-testid="ai-scribe-onboarding-notice"]';

async function login( page: any ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
}

test.describe( 'P9 §15.2 — onboarding notice', () => {
	test.beforeAll( () => {
		wpCliTry( `wp option delete ${ DISMISS_OPTION }` );
	} );

	test.afterAll( () => {
		// Leave the env quiet for every other spec regardless of outcome.
		wpCliTry( `wp option update ${ DISMISS_OPTION } 3.0.3` );
	} );

	test( 'shows once, dismisses, stays dismissed', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'desktop-1280', 'notice flow exercised once on desktop' );

		const errors = watchConsole( page );
		await login( page );

		// 1. First load after update: notice renders (any admin screen).
		await page.goto( '/wp-admin/index.php' );
		const notice = page.locator( NOTICE );
		await expect( notice ).toBeVisible();
		await expect( notice ).toContainText( 'Welcome to AI-Scribe 3.0' );
		await expect( notice ).toContainText( 'Express mode' );
		await expect( notice ).toContainText( 'per-step costs' );

		// Help-page link present and correct.
		await expect(
			notice.locator( 'a[href*="page=ai_scribe_help"]' )
		).toBeVisible();

		// 2. Feature flag defaults OFF: no hub install CTA (hub not on wp.org).
		await expect(
			notice.locator( '[data-testid="ai-scribe-hub-install-cta"]' )
		).toHaveCount( 0 );

		// 3. Dismiss via the inline button; persistence is server-side.
		await Promise.all( [
			page.waitForResponse(
				( r ) =>
					r.url().includes( 'admin-ajax.php' ) && r.request().postData()?.includes( 'ai_scribe_dismiss_notice' ) === true
			),
			notice.locator( '[data-ai-scribe-dismiss="onboarding"]' ).click(),
		] );
		await expect( notice ).toHaveCount( 0 );

		// 4. Reload: stays dismissed, on plugin pages too.
		await page.goto( '/wp-admin/index.php' );
		await expect( page.locator( NOTICE ) ).toHaveCount( 0 );
		await page.goto( '/wp-admin/admin.php?page=ai-scribe' );
		await expect( page.locator( NOTICE ) ).toHaveCount( 0 );

		// 5. Option persisted per-site.
		const stored = wpCli( `wp option get ${ DISMISS_OPTION }` );
		expect( stored.length ).toBeGreaterThan( 0 );

		assertNoConsoleErrors( errors );
	} );

	test( 'WP core X button also persists the dismissal', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'desktop-1280', 'notice flow exercised once on desktop' );

		wpCliTry( `wp option delete ${ DISMISS_OPTION }` );
		await login( page );
		await page.goto( '/wp-admin/index.php' );
		const notice = page.locator( NOTICE );
		await expect( notice ).toBeVisible();

		await Promise.all( [
			page.waitForResponse(
				( r ) =>
					r.url().includes( 'admin-ajax.php' ) && r.request().postData()?.includes( 'ai_scribe_dismiss_notice' ) === true
			),
			notice.locator( '.notice-dismiss' ).click(),
		] );

		await page.goto( '/wp-admin/index.php' );
		await expect( page.locator( NOTICE ) ).toHaveCount( 0 );
	} );
} );
