import { test, expect, type Page } from '@playwright/test';
import {
	assertNoConsoleErrors,
	routeMockMode,
	snap,
	unrouteMockMode,
	watchConsole,
	wpCli,
	wpLogin,
	type MockMode,
} from './helpers';
import { wizard } from './selectors';

/**
 * Suite 2 — error paths (REFACTOR.md §9.3): every provider failure must show
 * a VISIBLE error with retry, never advance the wizard, and never fire a
 * silent second billed request.
 *
 * Failure modes come from the mock provider mu-plugin, injected per-request
 * by rewriting admin-ajax URLs with ?ai_scribe_mock_mode=<mode>.
 */

const FAILURE_MODES: MockMode[] = [ 'empty_choices', 'http_500', 'rate_limit_429', 'malformed_json' ];
const HUB_SLUG = 'ai-core-standalone';
const DEV_SLUG = 'ai-scribe-v3';

async function startGeneration( page: Page ): Promise<void> {
	await page.locator( wizard.panel( 1 ) ).locator( wizard.ideaInput ).fill( 'coffee brewing errors' );
	await page.locator( wizard.panel( 1 ) ).locator( wizard.generateButton ).first().click();
}

test.describe( 'Wizard — provider failure modes', () => {
	test.beforeAll( () => {
		const mockGuards = wpCli(
			`wp eval 'echo (defined("AI_SCRIBE_MOCK") && AI_SCRIBE_MOCK && defined("AI_SCRIBE_AUTOMATED_TEST") && AI_SCRIBE_AUTOMATED_TEST) ? "safe" : "unsafe";'`
		);
		if ( mockGuards !== 'safe' ) {
			throw new Error( 'Provider-failure tests require the guarded mock wp-env.' );
		}

		const configured = wpCli(
			`wp eval '$s=get_option("ai_core_settings",array());echo (!empty($s["openai_api_key"])||!empty($s["anthropic_api_key"])||!empty($s["gemini_api_key"])) ? "yes" : "no";'`
		);
		if ( configured === 'yes' ) {
			return;
		}

		// This is a persistent fixture for the isolated mock environment, not a
		// live credential. Leaving it configured prevents concurrent viewport or
		// suite workers from restoring an empty global option underneath another
		// in-flight request.
		wpCli(
			`wp eval '$s=get_option("ai_core_settings",array());$s["openai_api_key"]="sk-test-mock-key";update_option("ai_core_settings",$s);'`
		);
	} );

	test.beforeEach( async ( { page } ) => {
		// The upgrade suite deliberately swaps/deactivates both plugins. A
		// previous focused upgrade run may therefore leave its restored database
		// visible a moment before the web container has reloaded the dev plugin.
		// Restore and verify this suite's own prerequisites before opening the UI.
		wpCli(
			`wp eval '$required=array("ai-core-standalone/ai-core.php","ai-scribe-v3/article_builder.php");$active=get_option("active_plugins",array());$active=array_values(array_diff($active,$required));update_option("active_plugins",array_merge($required,$active));'`
		);
		expect( wpCli( `wp plugin get ${ HUB_SLUG } --field=status` ) ).toBe( 'active' );
		expect( wpCli( `wp plugin get ${ DEV_SLUG } --field=status` ) ).toBe( 'active' );

		await wpLogin( page );
		await page.goto( wizard.pageUrl );
		await expect( page.locator( wizard.root ) ).toBeVisible( { timeout: 30_000 } );
	} );

	for ( const mode of FAILURE_MODES ) {
		test( `failure mode: ${ mode } — visible error, no advance, no double-bill`, async ( { page }, testInfo ) => {
			const errors = watchConsole( page );

			// Count provider-bound generation requests (run_step only).
			let runStepRequests = 0;
			page.on( 'request', ( request ) => {
				if ( request.url().includes( 'admin-ajax.php' )
					&& ( request.postData() || '' ).includes( 'ai_scribe_run_step' ) ) {
					runStepRequests++;
				}
			} );

			await routeMockMode( page, mode );
			await startGeneration( page );

			// 1. A visible, human-readable error…
			const banner = page.locator( wizard.panel( 1 ) ).locator( wizard.errorBanner );
			await expect( banner ).toBeVisible( { timeout: 30_000 } );
			expect( ( await banner.textContent() )!.trim().length ).toBeGreaterThan( 10 );
			// 2. …the step does NOT advance…
			await expect( page.locator( wizard.panel( 1 ) ) ).toBeVisible();
			await expect( page.locator( wizard.panel( 1 ) ) ).toHaveAttribute( 'data-state', 'error' );
			await expect( page.locator( wizard.panel( 2 ) ) ).toBeHidden();
			// 3. …exactly ONE billed attempt (no silent auto-retry)…
			expect( runStepRequests ).toBe( 1 );
			// 4. …and a retry control is offered.
			const retry = page.locator( wizard.panel( 1 ) ).locator( wizard.errorRetry );
			await expect( retry ).toBeVisible();

			await snap( page, testInfo, `error-${ mode }` );

			// Retry after the provider recovers → renders normally. Keep the
			// provider mode explicit: removing the route would make this assertion
			// depend on whichever provider keys happen to be stored in the shared
			// wp-env database rather than on the retry contract under test.
			await unrouteMockMode( page );
			await routeMockMode( page, 'ok' );
			await retry.click();
			await expect( page.locator( wizard.panel( 1 ) ) ).toHaveAttribute( 'data-state', 'ready', { timeout: 30_000 } );
			await expect( page.locator( wizard.panel( 1 ) ).locator( wizard.resultCard ).first() ).toBeVisible();
			expect( runStepRequests ).toBe( 2 );

			assertNoConsoleErrors( errors );
		} );
	}

	test( 'slow response keeps the loading state and eventually renders', async ( { page }, testInfo ) => {
		const errors = watchConsole( page );

		await routeMockMode( page, 'slow' );
		await startGeneration( page );

		// Loading state appears and HOLDS during the 5s mock delay — no premature error.
		await expect( page.locator( wizard.panel( 1 ) ) ).toHaveAttribute( 'data-state', 'loading' );
		await page.waitForTimeout( 3_000 );
		await expect( page.locator( wizard.panel( 1 ) ) ).toHaveAttribute( 'data-state', 'loading' );
		await expect( page.locator( wizard.panel( 1 ) ).locator( wizard.loadingIndicator ) ).toBeVisible();

		// Then the cards render.
		await expect( page.locator( wizard.panel( 1 ) ) ).toHaveAttribute( 'data-state', 'ready', { timeout: 30_000 } );
		await expect( page.locator( wizard.panel( 1 ) ).locator( wizard.resultCard ).first() ).toBeVisible();

		await snap( page, testInfo, 'slow-mode' );
		assertNoConsoleErrors( errors );
	} );
} );
