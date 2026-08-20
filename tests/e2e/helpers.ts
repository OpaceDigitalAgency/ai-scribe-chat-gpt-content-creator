import { expect, type Page, type TestInfo } from '@playwright/test';
import { wp } from './selectors';

/**
 * Console-error assertion helper (REFACTOR.md §9.3: any console error = fail).
 *
 * Usage:
 *   const consoleErrors = watchConsole( page );
 *   ...actions...
 *   assertNoConsoleErrors( consoleErrors );
 */
export function watchConsole( page: Page ): string[] {
	const errors: string[] = [];
	page.on( 'console', ( msg ) => {
		if ( msg.type() === 'error' ) {
			errors.push( `${ page.url() } :: ${ msg.text() }` );
		}
	} );
	page.on( 'pageerror', ( err ) => {
		errors.push( `${ page.url() } :: pageerror :: ${ err.message }` );
	} );
	return errors;
}

const IGNORED_CONSOLE_PATTERNS: RegExp[] = [
	// WP core occasionally 404s optional admin assets in dev; keep this list SHORT
	// and justified — the default is that every console error fails the test.
	/favicon\.ico.*404/i,
];

export function assertNoConsoleErrors( errors: string[] ): void {
	const relevant = errors.filter(
		( e ) => ! IGNORED_CONSOLE_PATTERNS.some( ( re ) => re.test( e ) )
	);
	expect( relevant, `Console errors detected:\n${ relevant.join( '\n' ) }` ).toHaveLength( 0 );
}

/** Log in to wp-admin with the wp-env default credentials. */
export async function wpLogin(
	page: Page,
	user = 'admin',
	pass = 'password'
): Promise<void> {
	await page.goto( '/wp-login.php' );
	await page.fill( wp.loginUser, user );
	await page.fill( wp.loginPass, pass );
	await page.click( wp.loginSubmit );
	await expect( page.locator( wp.adminBar ) ).toBeVisible();
}

/**
 * Set the mock provider failure mode via WP option (survives across requests).
 * Requires the ai-scribe-mock-provider mu-plugin. Uses admin-ajax-free approach:
 * mode can also be passed per-request via ?ai_scribe_mock_mode=<mode> query param.
 */
export type MockMode =
	| 'ok'
	| 'empty_choices'
	| 'http_500'
	| 'rate_limit_429'
	| 'slow'
	| 'malformed_json';

/** Append the per-request mock-mode parameter to a URL. */
export function withMockMode( url: string, mode: MockMode ): string {
	const sep = url.includes( '?' ) ? '&' : '?';
	return `${ url }${ sep }ai_scribe_mock_mode=${ mode }`;
}

/**
 * Force a mock failure mode for every admin-ajax request from this page.
 * The app's fetch() calls carry no query string, so the page-URL variant of
 * withMockMode never reaches them — this route rewrite does.
 */
export async function routeMockMode( page: Page, mode: MockMode ): Promise<void> {
	await page.route( '**/admin-ajax.php*', ( route ) => {
		const url = new URL( route.request().url() );
		url.searchParams.set( 'ai_scribe_mock_mode', mode );
		route.continue( { url: url.toString() } );
	} );
}

export async function unrouteMockMode( page: Page ): Promise<void> {
	await page.unroute( '**/admin-ajax.php*' );
}

/**
 * Run a WP-CLI command inside the wp-env development container.
 * Returns trimmed stdout. Throws on non-zero exit.
 */
export function wpCli( command: string ): string {
	const { execSync } = require( 'node:child_process' );
	const configured = process.env.WP_CLI_CONTAINER;
	if ( configured ) {
		return execSync( `docker exec ${ configured } ${ command }`, { encoding: 'utf8' } ).trim();
	}
	const names: string = execSync( "docker ps --format '{{.Names}}'" ).toString();
	const cli = names
		.split( '\n' )
		.find( ( n ) => n.endsWith( '-cli-1' ) && ! n.includes( 'tests-cli' ) );
	if ( ! cli ) {
		throw new Error( 'wp-env cli container not found — is wp-env running?' );
	}
	return execSync( `docker exec ${ cli } ${ command }`, { encoding: 'utf8' } ).trim();
}

/** wpCli that tolerates failure (e.g. deleting an option that doesn't exist). */
export function wpCliTry( command: string ): string {
	try {
		return wpCli( command );
	} catch ( e ) {
		return '';
	}
}

/**
 * Watch every admin-ajax response for the ai_scribe_* endpoint surface and
 * assert it parses (regression: no raw `echo json_encode` + PHP-notice soup —
 * REFACTOR.md §3.4 / §9.6 test_no_raw_echo_json).
 */
export function watchAjaxResponses( page: Page ): { failures: string[] } {
	const failures: string[] = [];
	page.on( 'response', async ( response ) => {
		const request = response.request();
		if ( ! response.url().includes( 'admin-ajax.php' ) ) {
			return;
		}
		const postData = request.postData() || '';
		if ( ! postData.includes( 'ai_scribe_' ) ) {
			return;
		}
		const type = ( response.headers()[ 'content-type' ] || '' ).toLowerCase();
		if ( type.includes( 'text/event-stream' ) ) {
			return; // Contract §8: the one non-JSON endpoint.
		}
		try {
			const body = await response.text();
			JSON.parse( body );
		} catch ( e ) {
			failures.push( `${ response.status() } ${ response.url() } :: unparseable body` );
		}
	} );
	return { failures };
}

/** Attach a screenshot to the report with a stable name. */
export async function snap( page: Page, testInfo: TestInfo, name: string ): Promise<void> {
	const shot = await page.screenshot( { fullPage: true } );
	await testInfo.attach( name, { body: shot, contentType: 'image/png' } );
}
