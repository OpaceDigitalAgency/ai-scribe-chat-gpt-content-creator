/**
 * P7 quality-pass regressions (REFACTOR.md §13).
 *
 * Covers: zombie-purge (no legacy console warnings, no pricing endpoint),
 * model display hydration (§13.1), Express header + distinct Q&A + topic echo
 * (§13.4), validated provider status (§13.5), and the §13.12 settings
 * dedupe with the hub active (Managed by AI-Core panel, key writes refused),
 * plus the fail-closed dependency notice when AI-Core is deactivated.
 */

import { test, expect } from '@playwright/test';
import {
	assertNoConsoleErrors,
	watchConsole,
	wpCli,
	wpCliTry,
} from './helpers';
import { wizard } from './selectors';

const HUB_SLUG = 'ai-core-standalone';
const DEV_SLUG = 'ai-scribe-v3';
const SETTINGS_URL = '/wp-admin/admin.php?page=ai_scribe_settings';

async function login( page: any ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
}

test.describe( 'P7 §13.1 — zombie purge + model display', () => {
	test( 'wizard loads with zero legacy-module warnings and a hydrated model display', async ( { page } ) => {
		const errors = watchConsole( page );
		const warnings: string[] = [];
		page.on( 'console', ( msg ) => {
			if ( msg.type() === 'warning' ) {
				warnings.push( msg.text() );
			}
		} );
		await login( page );
		await page.goto( wizard.pageUrl );
		await expect( page.locator( '#ai-scribe-root' ) ).toBeVisible();

		// The zombie v4 modules logged these on every load (§13.1).
		const zombieSignals = warnings.filter( ( w ) =>
			/Prompt textarea element not found|using fallback: o3|WordPress model setting not available/.test( w )
		);
		expect( zombieSignals, `zombie module warnings:\n${ zombieSignals.join( '\n' ) }` ).toHaveLength( 0 );

		// Model display: hydrates from the live endpoint (or shows the
		// configured id) — never a stuck "Loading model…" and never o3.
		const display = page.locator( '#active-model-details' );
		await expect( display ).not.toHaveText( /Loading model/i, { timeout: 15_000 } );
		await expect( display ).not.toHaveText( /^o3\b/ );
		const text = ( await display.textContent() ) || '';
		expect( text.trim().length ).toBeGreaterThan( 0 );

		assertNoConsoleErrors( errors );
	} );

	test( 'the hardcoded pricing endpoint is gone (§13.2)', async ( { page } ) => {
		await login( page );
		const response = await page.request.post( '/wp-admin/admin-ajax.php', {
			form: { action: 'ai_scribe_get_pricing', security: 'x' },
		} );
		// Unregistered AJAX actions return 400/0 from WP — anything but a
		// successful pricing payload proves the endpoint is removed.
		expect( response.status() ).toBe( 400 );
	} );
} );

test.describe( 'P7 §13.4 — Express mode', () => {
	test( 'express hides the wizard progress readout and renders distinct, topic-echoed Q&A', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'desktop-1280', 'flow exercised once on desktop' );
		const errors = watchConsole( page );
		await login( page );
		await page.goto( wizard.pageUrl );

		await page.locator( '[data-testid="mode-express"]' ).click();
		await expect( page.locator( '.progress-container' ) ).toBeHidden();
		await expect( page.locator( '[data-testid="express-screen"]' ) ).toBeVisible();

		await page.locator( '[data-testid="express-topic"]' ).fill( 'urban beekeeping for beginners' );
		await page.locator( '[data-action="express-generate"]' ).click();

		const output = page.locator( '#express-stream-output' );
		await expect( output.locator( 'h1' ) ).toBeVisible( { timeout: 30_000 } );

		// §13.4: mock echoes the requested topic into the fixtures.
		await expect( output.locator( 'h1' ) ).toContainText( /urban beekeeping/i );

		// §13.4: Q&A entries must be DISTINCT (was the same question ×3).
		const questions = await output.locator( 'h3' ).allTextContents();
		expect( questions.length ).toBeGreaterThanOrEqual( 3 );
		expect( new Set( questions ).size ).toBe( questions.length );

		// Switching back to the wizard restores the progress cluster.
		await page.locator( '[data-testid="mode-wizard"]' ).click();
		await expect( page.locator( '.progress-container' ) ).toBeVisible();

		assertNoConsoleErrors( errors );
	} );
} );

test.describe( 'P7 §13.5 + §13.12 — provider status + settings dedupe', () => {
	test.afterEach( () => {
		// The hub must ALWAYS be left active for the rest of the suite.
		wpCliTry( `wp plugin activate ${ HUB_SLUG }` );
		wpCliTry( `wp plugin activate ${ DEV_SLUG }` );
	} );

	test( 'hub active: Managed by AI-Core panel, validated chips, no key fields, key writes refused', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'desktop-1280', 'settings modes exercised once on desktop' );
		const errors = watchConsole( page );
		await login( page );
		await page.goto( SETTINGS_URL );

		// Managed panel replaces the key fields entirely.
		await expect( page.locator( '[data-testid="managed-by-hub"]' ) ).toBeVisible();
		expect( await page.locator( '[data-testid^="api-key-"]' ).count() ).toBe( 0 );
		await expect( page.locator( '[data-testid="open-ai-core-settings"]' ) ).toHaveAttribute(
			'href',
			/page=ai-core-settings/
		);

		// §13.5: chips reflect VALIDATED status (live check via the mock),
		// not mere key presence. OpenAI/Anthropic keys are configured in the
		// hub for this env; the mock answers their /models endpoints.
		const openaiChip = page.locator( '[data-testid="provider-chip-openai"]' );
		await expect( openaiChip ).toContainText( /Validated/i, { timeout: 20_000 } );

		// Key writes are refused while the hub owns provider config.
		const nonce = await page.evaluate( () => ( window as any ).ai_scribe?.nonce || '' );
		const response = await page.request.post( '/wp-admin/admin-ajax.php', {
			form: {
				action: 'ai_scribe_save_api_keys',
				security: nonce,
				keys: JSON.stringify( { openai: 'sk-should-be-refused' } ),
			},
		} );
		const payload = await response.json();
		expect( payload.success ).toBe( false );
		expect( payload.data?.code ).toBe( 'managed_by_hub' );
		// The refused key must not have been stored anywhere.
		expect( wpCliTry( 'wp option get ab_api_key' ) ).not.toContain( 'sk-should-be-refused' );

		assertNoConsoleErrors( errors );
	} );

	test( 'model picker: grouped by provider, gated on validation, image models excluded, dead selection falls back (§13 addendum)', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'desktop-1280', 'picker exercised once on desktop' );
		await login( page );

		// Persist a DEAD selection (image model from an unconfigured provider —
		// exactly what David's live screenshot showed).
		const saved = wpCliTry( 'wp option get ab_gpt_ai_engine_settings --format=json' );
		wpCli(
			`wp eval '$s=get_option("ab_gpt_ai_engine_settings",array());$s["model"]="gemini-3-pro-image-preview";update_option("ab_gpt_ai_engine_settings",$s);'`
		);
		try {
			await page.goto( SETTINGS_URL );
			const select = page.locator( '[data-testid="model-select"]' );
			await expect
				.poll( async () => select.locator( 'optgroup' ).count(), { timeout: 20_000 } )
				.toBeGreaterThanOrEqual( 3 );

			// Grouped: validated providers plainly labelled; unconfigured ones
			// carry the configure affordance and only disabled options.
			const labels = await select.locator( 'optgroup' ).evaluateAll(
				( groups ) => groups.map( ( g ) => ( g as HTMLOptGroupElement ).label )
			);
			expect( labels.some( ( l ) => /^OpenAI$/.test( l ) ) ).toBe( true );
			expect( labels.some( ( l ) => /Gemini.*not configured.*AI-Core/i.test( l ) ) ).toBe( true );
			const geminiDisabled = await select
				.locator( 'optgroup[label*="Gemini"] option' )
				.evaluateAll( ( opts ) => opts.every( ( o ) => ( o as HTMLOptionElement ).disabled ) );
			expect( geminiDisabled ).toBe( true );

			const values = await select.locator( 'option' ).evaluateAll(
				( opts ) => opts.map( ( o ) => ( o as HTMLOptionElement ).value )
			);
			// The live list replaces the seed for validated providers: real
			// families present, speculative names absent.
			expect( values ).toContain( 'gpt-5-nano' );
			expect( values.join( ',' ) ).not.toContain( 'gpt-5.6' );
			expect( values.join( ',' ) ).not.toMatch( /claude-(opus|sonnet)-5-2026/ );
			// Image-category models are excluded from the TEXT picker...
			expect( values.join( ',' ) ).not.toMatch( /image|dall-e|imagen/ );
			// ...and present in the image picker.
			const imageValues = await page.locator( '[data-testid="image-model-select"] option' ).evaluateAll(
				( opts ) => opts.map( ( o ) => ( o as HTMLOptionElement ).value )
			);
			expect( imageValues.join( ',' ) ).toMatch( /image|imagen/ );

			// Dead selection auto-falls back to a validated-provider model
			// with a visible notice — never a dead selection left in place.
			const selectedValue = await select.inputValue();
			expect( selectedValue ).not.toBe( 'gemini-3-pro-image-preview' );
			const selectedDisabled = await select.evaluate(
				( el ) => ( el as HTMLSelectElement ).selectedOptions[ 0 ]?.disabled ?? true
			);
			expect( selectedDisabled ).toBe( false );
			await expect( page.locator( '[data-testid="model-list-status"]' ) ).toContainText( /unavailable|switched/i );

			// §13 addendum 4: a validated-provider reasoning model shows its
			// REAL controls, not the empty-schema copy.
			await select.selectOption( 'gpt-5-nano' );
			await expect( page.locator( '[data-testid="model-param-reasoning_effort"]' ) ).toBeVisible();
		} finally {
			if ( saved ) {
				const safe = saved.replace( /'/g, `'\\''` );
				wpCliTry( `wp option update ab_gpt_ai_engine_settings '${ safe }' --format=json` );
				// Leave a VALID model selected either way (state coherence).
				wpCliTry(
					`wp eval '$s=get_option("ab_gpt_ai_engine_settings",array());if(!isset($s["model"])||strpos($s["model"],"gpt-5.6")===0||strpos($s["model"],"gemini-3-pro-image")===0){$s["model"]="gpt-5-nano";update_option("ab_gpt_ai_engine_settings",$s);}'`
				);
			}
		}
	} );

	test( 'hub deactivated: dependency notice appears and AI-Scribe screens fail closed', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'desktop-1280', 'settings modes exercised once on desktop' );
		await login( page );
		wpCli( `wp plugin deactivate ${ HUB_SLUG }` );
		try {
			await page.goto( '/wp-admin/index.php' );
			await expect( page.locator( '[data-testid="ai-scribe-hub-required-notice"]' ) ).toBeVisible();
			await expect( page.locator( '[data-testid="ai-scribe-hub-required-notice"]' ) ).toContainText( 'needs the AI-Core plugin' );
			await expect( page.locator( '#adminmenu' ) ).not.toContainText( 'AI Scribe' );
		} finally {
			wpCli( `wp plugin activate ${ HUB_SLUG }` );
			wpCliTry( `wp plugin activate ${ DEV_SLUG }` );
		}
	} );
} );
