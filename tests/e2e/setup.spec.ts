import { test, expect } from '@playwright/test';
import {
	assertNoConsoleErrors,
	snap,
	watchConsole,
	wpLogin,
} from './helpers';
import { PLUGIN_SLUG, settings, wp } from './selectors';

/**
 * Suite 0 — environment setup: login, plugin activation, settings save.
 * Runs against the wp-env dev site (http://localhost:8888, admin/password).
 * The mock provider mu-plugin means the "API key" saved here is never sent anywhere.
 */

test.describe( 'AI-Scribe setup', () => {
	test( 'logs in to wp-admin', async ( { page }, testInfo ) => {
		const errors = watchConsole( page );
		await wpLogin( page );
		await snap( page, testInfo, 'dashboard' );
		assertNoConsoleErrors( errors );
	} );

	test( 'activates the AI-Scribe plugin', async ( { page }, testInfo ) => {
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( '/wp-admin/plugins.php' );

		const row = page.locator( wp.pluginsRow( PLUGIN_SLUG ) );
		await expect( row ).toBeVisible();

		const activate = page.locator( wp.activateLink( PLUGIN_SLUG ) );
		if ( await activate.isVisible() ) {
			await activate.click();
			await expect( page.locator( wp.noticeSuccess ).first() ).toBeVisible();
		}
		// Already-active is a pass: deactivate link present.
		await expect( page.locator( wp.deactivateLink( PLUGIN_SLUG ) ) ).toBeVisible();
		await snap( page, testInfo, 'plugins-page' );
		assertNoConsoleErrors( errors );
	} );

	test( 'saves settings (provider key write-only, masked status)', async ( { page }, testInfo ) => {
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( settings.pageUrl );

		// §13.12: with the AI-Core hub active the settings screen shows NO
		// key fields — provider config lives in the hub (p7.spec covers both
		// modes in depth). Key handling below only applies standalone.
		const hubPanel = page.locator( '[data-testid="managed-by-hub"]' );
		if ( await hubPanel.count() ) {
			await expect( hubPanel ).toBeVisible();
			expect( await page.locator( '[data-testid^="api-key-"]' ).count() ).toBe( 0 );
		} else {
			const openai = page.locator( settings.openaiKey ).first();
			await expect( openai, 'Settings page must expose an OpenAI key field' ).toBeVisible();
			await openai.fill( 'sk-test-mock-key' );

			const anthropic = page.locator( settings.anthropicKey ).first();
			await anthropic.fill( 'sk-ant-mock-e2e-key' );

			await page.locator( settings.saveButton ).first().click();
			await expect( page.locator( settings.status ) ).toHaveText( /saved/i, { timeout: 15_000 } );

			// Keys are write-only: fields blank after save, status Configured.
			await expect( openai ).toHaveValue( '' );
			await expect( page.locator( settings.keyStatus( 'openai' ) ) ).toHaveText( /configured/i );
		}

		// Generation settings still save through this screen in both modes.
		await page.locator( settings.saveButton ).first().click();
		await expect( page.locator( settings.status ) ).toHaveText( /saved/i, { timeout: 15_000 } );

		// SECURITY: no key material anywhere in the page source.
		const html = await page.content();
		expect( html ).not.toContain( 'sk-test-mock-key' );
		expect( html ).not.toContain( 'sk-ant-mock-e2e-key' );

		await snap( page, testInfo, 'settings-saved' );
		assertNoConsoleErrors( errors );
	} );

	test( 'models list populates live from the mock provider', async ( { page }, testInfo ) => {
		// P1 gate: "models list live" — served by AI-Core's ModelRegistry via
		// the ai_scribe_get_available_models endpoint (contract v1.1 §9).
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( settings.pageUrl );

		const select = page.locator( settings.modelSelect ).first();
		await expect( select ).toBeVisible();
		await expect
			.poll( async () => select.locator( 'option' ).count(), { timeout: 15_000 } )
			.toBeGreaterThan( 1 );

		// WP 7.0 core AI client appears as a provider choice (P4 adapter).
		const values = await select
			.locator( 'option' )
			.evaluateAll( ( opts ) => opts.map( ( o ) => ( o as HTMLOptionElement ).value ) );
		expect( values ).toContain( 'wordpress-ai' );

		await snap( page, testInfo, 'models-list' );
		assertNoConsoleErrors( errors );
	} );
} );
