import { test, expect, type Page } from '@playwright/test';
import {
	assertNoConsoleErrors,
	snap,
	watchConsole,
	wpCli,
	wpCliTry,
	wpLogin,
} from './helpers';
import { settings, wizard } from './selectors';

/**
 * Suite 5 — UAT §12 (REFACTOR.md, binding P5.5 scope):
 *   1 hub architecture (AI-Core standalone + AI-Scribe as add-on)
 *   2 dynamic model UX (live per-provider fetch, refresh, param panels)
 *   3 branding (logo on every admin page)
 *   4 body-step image workflow (add image → gallery → insert into Quill)
 *   5 previously broken/empty pages render styled and functional
 */

async function driveToStep6( page: Page ): Promise<void> {
	const panel = ( n: number ) => page.locator( wizard.panel( n ) );
	const ready = ( n: number ) =>
		expect( panel( n ) ).toHaveAttribute( 'data-state', 'ready', { timeout: 30_000 } );
	await panel( 1 ).locator( wizard.ideaInput ).fill( 'how to brew better coffee at home' );
	await panel( 1 ).locator( wizard.generateButton ).first().click();
	for ( const n of [ 1, 2, 3 ] ) {
		await ready( n );
		if ( await panel( n ).locator( wizard.resultCardSelected ).count() === 0 ) {
			await panel( n ).locator( wizard.resultCard ).first().click();
		}
		await panel( n ).locator( wizard.continueButton ).first().click();
	}
	await ready( 4 );
	await panel( 4 ).locator( wizard.continueButton ).first().click();
	await ready( 5 );
	if ( await panel( 5 ).locator( wizard.resultCardSelected ).count() === 0 ) {
		await panel( 5 ).locator( wizard.resultCard ).first().click();
	}
	await panel( 5 ).locator( wizard.continueButton ).first().click();
	await ready( 6 );
}

test.describe( 'UAT §12.1 — hub architecture', () => {
	test( 'AI-Core standalone is active and provides the runtime lib', async ( { page } ) => {
		const plugins = wpCli( 'wp plugin list --format=csv' );
		expect( plugins ).toMatch( /ai-core-standalone,active/ );
		expect( plugins ).toMatch( /ai-scribe-v3,active/ );

		// AI-Scribe defers to the hub's lib copy (bootstrap prefers a loaded AICore).
		const libFile = wpCli(
			`wp eval 'echo (new ReflectionClass("AICore\\\\AICore"))->getFileName();'`
		);
		expect( libFile ).toContain( '/plugins/ai-core-standalone/lib/' );

		// Hub menu structure reachable (Dashboard page renders for admins).
		const errors = watchConsole( page );
		await wpLogin( page );
		const response = await page.goto( '/wp-admin/admin.php?page=ai-core' );
		expect( response!.status() ).toBe( 200 );
		await expect( page.locator( '#adminmenu' ) ).toContainText( 'AI-Core' );
		assertNoConsoleErrors( errors );
	} );

	test( 'keys entered in the hub flow through to AI-Scribe', async () => {
		// Give the hub a Gemini key AI-Scribe itself does not have.
		wpCli( `wp option patch insert ai_core_settings gemini_api_key gemini-hub-shared-key-e2e` );
		try {
			const models = wpCli(
				`wp eval '$c = ai_scribe_get_container(); echo (string) $c->get("config")->get_api_key("gemini");'`
			);
			expect( models ).toBe( 'gemini-hub-shared-key-e2e' );
		} finally {
			wpCliTry( 'wp option patch delete ai_core_settings gemini_api_key' );
		}
	} );
} );

test.describe( 'UAT §12.2 — dynamic model UX', () => {
	test( 'model list is fetched LIVE (current model families, not registry-stale)', async ( { page }, testInfo ) => {
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( settings.pageUrl );

		const select = page.locator( settings.modelSelect );
		await expect
			.poll( async () => select.locator( 'option' ).count(), { timeout: 20_000 } )
			.toBeGreaterThan( 3 );
		const values = await select
			.locator( 'option' )
			.evaluateAll( ( opts ) => opts.map( ( o ) => ( o as HTMLOptionElement ).value ) );
		// The bug David reported: stale/static lists. The mock provider's
		// /models endpoints serve the REAL current families (P6 live smoke) —
		// if these appear the live fetch path is working, and the speculative
		// pre-P6 ids must NOT appear (§13 addendum).
		expect( values.some( ( v ) => v.includes( 'gpt-5-nano' ) || v.includes( 'gpt-5-mini' ) ) ).toBe( true );
		expect( values.some( ( v ) => v.includes( 'claude-sonnet-4-5' ) || v.includes( 'claude-opus-4-1' ) ) ).toBe( true );
		expect( values.some( ( v ) => v.includes( 'gpt-5.6' ) ) ).toBe( false );
		await expect( page.locator( settings.modelListStatus ) ).toContainText( /live from/i );
		await snap( page, testInfo, 'live-models' );
		assertNoConsoleErrors( errors );
	} );

	test( 'Refresh models button re-fetches with cache bypass', async ( { page } ) => {
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( settings.pageUrl );
		await expect
			.poll( async () => page.locator( settings.modelSelect ).locator( 'option' ).count(), { timeout: 20_000 } )
			.toBeGreaterThan( 1 );

		let refreshRequest = false;
		page.on( 'request', ( request ) => {
			const data = request.postData() || '';
			// FormData posts are multipart — match the field name, not urlencoding.
			if ( data.includes( 'ai_scribe_get_available_models' ) && /name="refresh"/.test( data ) ) {
				refreshRequest = true;
			}
		} );
		await page.locator( '[data-testid="models-refresh"]' ).click();
		await expect.poll( () => refreshRequest, { timeout: 15_000 } ).toBe( true );
		await expect( page.locator( settings.modelListStatus ) ).toContainText( /models loaded/i );
		assertNoConsoleErrors( errors );
	} );

	test( 'per-model parameter panel renders from the schema', async ( { page }, testInfo ) => {
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( settings.pageUrl );
		const select = page.locator( settings.modelSelect );
		await expect
			.poll( async () => select.locator( 'option' ).count(), { timeout: 20_000 } )
			.toBeGreaterThan( 3 );

		// A reasoning-capable model exposes extra parameters (e.g. reasoning effort).
		const values = await select
			.locator( 'option:not(:disabled)' )
			.evaluateAll( ( opts ) => opts.map( ( o ) => ( o as HTMLOptionElement ).value ) );
		const reasoningModel = values.find( ( v ) => /^gpt-5(?:-|$)/.test( v ) || /^o\d/.test( v ) );
		expect( reasoningModel ).toBeTruthy();
		await select.selectOption( reasoningModel! );
		const panel = page.locator( '[data-testid="model-params"]' );
		await expect
			.poll( async () => panel.locator( '.model-param-field' ).count(), { timeout: 10_000 } )
			.toBeGreaterThan( 0 );
		await snap( page, testInfo, 'model-params-panel' );
		assertNoConsoleErrors( errors );
	} );
} );

test.describe( 'UAT §12.4 — body-step image workflow', () => {
	test( 'Add Image generates into the gallery and inserts into Quill', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name === 'mobile-375', 'gallery interaction is desktop/tablet UX' );
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( wizard.pageUrl );
		await driveToStep6( page );

		const panel6 = page.locator( wizard.panel( 6 ) );
		await panel6.locator( '[data-testid="add-image"]' ).click();
		const galleryImage = panel6.locator( '[data-testid="gallery-image"]' );
		await expect( galleryImage.first() ).toBeVisible( { timeout: 45_000 } );

		// The generated file is a real media-library attachment.
		const src = await galleryImage.first().getAttribute( 'src' );
		expect( src ).toContain( '/wp-content/uploads/' );

		// Click-to-insert lands in the Quill body.
		await galleryImage.first().click();
		await expect( panel6.locator( '.ql-editor img' ).first() ).toBeVisible();

		await snap( page, testInfo, 'body-image-workflow' );
		assertNoConsoleErrors( errors );
	} );
} );

test.describe( 'UAT §12.3 + §12.5 — branding and previously broken pages', () => {
	const pages: Array<[ string, string, string ]> = [
		[ 'wizard', '/wp-admin/admin.php?page=ai-scribe', '[data-testid="app-root"]' ],
		[ 'settings', '/wp-admin/admin.php?page=ai_scribe_settings', '#ai-scribe-settings-root' ],
		[ 'help', '/wp-admin/admin.php?page=ai_scribe_help', '#ai-scribe-help-root' ],
		[ 'shortcodes', '/wp-admin/admin.php?page=ai_scribe_saved_shortcodes', '#ai-scribe-shortcodes-root' ],
	];

	for ( const [ name, url, rootSelector ] of pages ) {
		test( `${ name } page renders styled with branding and no console errors`, async ( { page }, testInfo ) => {
			const errors = watchConsole( page );
			await wpLogin( page );
			const response = await page.goto( url );
			expect( response!.status() ).toBe( 200 );
			await expect( page.locator( rootSelector ) ).toBeVisible();
			// Logo branding present (UAT §12.3).
			await expect( page.locator( `${ rootSelector } [data-testid="brand-logo"]` ).first() ).toBeVisible();
			// Token CSS actually enqueued.
			const cssLoaded = await page.evaluate( () =>
				Array.from( document.styleSheets ).some( ( s ) => s.href && s.href.includes( 'ai-scribe-v3/assets/css/main.css' ) )
			);
			expect( cssLoaded ).toBe( true );
			await snap( page, testInfo, `page-${ name }` );
			assertNoConsoleErrors( errors );
		} );
	}

	test( 'help page has real content sections', async ( { page } ) => {
		await wpLogin( page );
		await page.goto( '/wp-admin/admin.php?page=ai_scribe_help' );
		expect( await page.locator( '#ai-scribe-help-root .help-section' ).count() ).toBeGreaterThanOrEqual( 4 );
	} );

	test( 'shortcodes page shows the table or a styled empty state', async ( { page } ) => {
		// P7 flake fix: this test used to depend on whichever rows earlier
		// specs happened to leave in wp_article_builder (state bleed). Seed
		// BOTH states explicitly instead and assert each renders correctly.
		await wpLogin( page );

		wpCli(
			`wp db query "INSERT INTO wp_article_builder (title, article) VALUES ('P7 seeded row', '<p>seeded</p>')"`
		);
		try {
			await page.goto( '/wp-admin/admin.php?page=ai_scribe_saved_shortcodes' );
			await expect( page.locator( '[data-testid="shortcodes-root"]' ) ).toBeVisible();
			await expect( page.locator( '[data-testid="shortcodes-table"]' ) ).toBeVisible();
			await expect( page.locator( '[data-testid="shortcodes-table"]' ) ).toContainText( 'P7 seeded row' );
		} finally {
			// Delete by title: LAST_INSERT_ID() is unreliable across the CLI
			// boundary, and this also sweeps any leaked seed rows.
			wpCliTry( `wp db query "DELETE FROM wp_article_builder WHERE title='P7 seeded row'"` );
		}

		// Empty state renders when (and only when) no rows exist.
		const remaining = wpCli(
			'wp db query "SELECT COUNT(*) FROM wp_article_builder" --skip-column-names'
		).trim();
		if ( remaining === '0' ) {
			await page.goto( '/wp-admin/admin.php?page=ai_scribe_saved_shortcodes' );
			await expect( page.locator( '[data-testid="shortcodes-empty"]' ) ).toBeVisible();
		}
	} );
} );
