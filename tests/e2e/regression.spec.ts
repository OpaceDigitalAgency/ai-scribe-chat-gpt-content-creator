import { test, expect } from '@playwright/test';
import * as fs from 'node:fs';
import * as path from 'node:path';
import { assertNoConsoleErrors, watchConsole, wpCli, wpCliTry, wpLogin } from './helpers';

/**
 * Suite 3 — regression ledger (REFACTOR.md §9.6): every §3/§7 shipped defect
 * becomes a named test. The step-context / object-Object / raw-echo ledger
 * items are asserted inside the wizard happy path (wizard.spec.ts) where the
 * live requests exist; this file holds the static and upgrade-path items.
 */

const PLUGIN_ROOT = path.resolve( __dirname, '..', '..' );

function walkJs( dir: string, out: string[] = [] ): string[] {
	for ( const entry of fs.readdirSync( dir, { withFileTypes: true } ) ) {
		const full = path.join( dir, entry.name );
		if ( entry.isDirectory() ) {
			walkJs( full, out );
		} else if ( entry.name.endsWith( '.js' ) ) {
			out.push( full );
		}
	}
	return out;
}

test.describe( 'Regression ledger — static', () => {
	test( 'test_titles_render_without_jquery_migrate: no jQuery(window).load patterns ship', async () => {
		// §3.4 root cause: jQuery(window).load() is a jQuery-3-removed alias —
		// without jquery-migrate it throws and kills every later binding
		// (billed-but-not-displayed). Assert the shipped JS never uses it.
		const files = walkJs( path.join( PLUGIN_ROOT, 'assets', 'js' ) );
		expect( files.length ).toBeGreaterThan( 20 );
		const offenders: string[] = [];
		const pattern = /(?:jQuery|\$)\s*\(\s*window\s*\)\s*\.load\s*\(/;
		for ( const file of files ) {
			if ( pattern.test( fs.readFileSync( file, 'utf8' ) ) ) {
				offenders.push( path.relative( PLUGIN_ROOT, file ) );
			}
		}
		expect( offenders, `jQuery(window).load found in: ${ offenders.join( ', ' ) }` ).toHaveLength( 0 );
	} );

	test( 'no eval() of server-supplied JS ships in assets/js', async () => {
		// §3.4: four eval()s of server JS shipped in 2.6.2. Vendored editors
		// (assets/editor, assets/icons) are third-party and excluded.
		const files = walkJs( path.join( PLUGIN_ROOT, 'assets', 'js' ) );
		const offenders: string[] = [];
		for ( const file of files ) {
			const source = fs.readFileSync( file, 'utf8' );
			if ( /\beval\s*\(/.test( source ) ) {
				offenders.push( path.relative( PLUGIN_ROOT, file ) );
			}
		}
		expect( offenders, `eval() found in: ${ offenders.join( ', ' ) }` ).toHaveLength( 0 );
	} );

	test( 'API keys are never localised into page JavaScript', async () => {
		// SECURITY (ENQUEUE_MANIFEST): the localisation array must not carry
		// key material — enforced live in setup.spec; statically here.
		const admin = fs.readFileSync(
			path.join( PLUGIN_ROOT, 'includes', 'services', 'class-admin-service.php' ),
			'utf8'
		);
		expect( admin ).not.toMatch( /["']apiKey["']\s*=>/ );
		expect( admin ).not.toMatch( /["']anthropicApiKey["']\s*=>/ );
	} );
} );

test.describe( 'Regression ledger — upgrade path', () => {
	test( '2.6.2 → 3.0 in-place upgrade creates the conversations table without reactivation', async ( { page } ) => {
		const errors = watchConsole( page );
		const prefix = wpCli( 'wp db prefix' ).trim();
		const table = `${ prefix }ai_scribe_conversations`;

		// Simulate an in-place update: table missing, schema option absent
		// (exactly the state after overwriting 2.6.2 files with 3.0).
		wpCli( `wp db query "DROP TABLE IF EXISTS ${ table }"` );
		wpCliTry( 'wp option delete ai_scribe_conversations_schema' );

		// Any admin page load fires admin_init → version-compare → dbDelta.
		await wpLogin( page );
		await page.goto( '/wp-admin/index.php' );

		const tables = wpCli( `wp db query "SHOW TABLES LIKE '${ table }'" --skip-column-names` );
		expect( tables ).toContain( table );
		expect( wpCli( 'wp option get ai_scribe_conversations_schema' ) ).toBe( '1' );
		assertNoConsoleErrors( errors );
	} );
} );
