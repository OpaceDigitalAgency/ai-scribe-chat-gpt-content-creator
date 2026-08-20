/**
 * P7 §13.11 — screenshot evidence matrix.
 *
 * Every AI-Scribe admin page at 1280 AND 1920, light AND dark, plus a full
 * mock wizard run and an Express run. Output: tests/e2e/screenshots/p7/.
 * Runs once, on the desktop project (viewports are set explicitly here).
 */

import { test, expect, type Page } from '@playwright/test';
import { wpLogin } from './helpers';
import { wizard } from './selectors';

const OUT = 'tests/e2e/screenshots/p7';

const PAGES: Array<[ string, string, string ]> = [
	[ 'wizard', '/wp-admin/admin.php?page=ai-scribe', '#ai-scribe-root' ],
	[ 'settings', '/wp-admin/admin.php?page=ai_scribe_settings', '#ai-scribe-settings-root' ],
	[ 'help', '/wp-admin/admin.php?page=ai_scribe_help', '#ai-scribe-help-root' ],
	[ 'shortcodes', '/wp-admin/admin.php?page=ai_scribe_saved_shortcodes', '#ai-scribe-shortcodes-root' ],
];

async function setTheme( page: Page, theme: 'light' | 'dark' ): Promise<void> {
	await page.addInitScript( ( t ) => {
		window.localStorage.setItem( 'ai-scribe-theme', t as string );
	}, theme );
}

function panel( page: Page, n: number ) {
	return page.locator( wizard.panel( n ) );
}

async function waitReady( page: Page, n: number ): Promise<void> {
	await expect( panel( page, n ) ).toHaveAttribute( 'data-state', 'ready', { timeout: 30_000 } );
}

test.describe( 'P7 §13.11 — screenshot matrix', () => {
	for ( const width of [ 1280, 1920 ] ) {
		for ( const theme of [ 'light', 'dark' ] as const ) {
			test( `admin pages at ${ width } (${ theme })`, async ( { page }, testInfo ) => {
				test.skip( testInfo.project.name !== 'desktop-1280', 'screenshots captured once from the desktop project' );
				await page.setViewportSize( { width, height: width === 1280 ? 900 : 1080 } );
				await setTheme( page, theme );
				await wpLogin( page );
				for ( const [ name, url, root ] of PAGES ) {
					await page.goto( url );
					await expect( page.locator( root ) ).toBeVisible();
					await page.waitForTimeout( 1200 ); // icons + async hydration settle
					await page.screenshot( {
						path: `${ OUT }/${ name }-${ width }-${ theme }.png`,
						fullPage: true,
					} );
				}
			} );
		}
	}

	test( 'full wizard run (mock) with per-step captures', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'desktop-1280', 'captured once on desktop' );
		await page.setViewportSize( { width: 1280, height: 900 } );
		await setTheme( page, 'light' );
		await wpLogin( page );
		await page.goto( wizard.pageUrl );

		await panel( page, 1 ).locator( wizard.ideaInput ).fill( 'how to brew better coffee at home' );
		await panel( page, 1 ).locator( wizard.generateButton ).first().click();
		await waitReady( page, 1 );

		const order: Array<[ number, 'choice' | 'long' ]> = [
			[ 1, 'choice' ], [ 2, 'choice' ], [ 3, 'choice' ], [ 4, 'long' ],
			[ 5, 'choice' ], [ 6, 'long' ], [ 7, 'long' ], [ 8, 'choice' ],
			[ 9, 'long' ],
		];
		for ( const [ n, kind ] of order ) {
			if ( n > 1 ) {
				await waitReady( page, n );
			}
			await page.screenshot( {
				path: `${ OUT }/wizard-run-step-${ String( n ).padStart( 2, '0' ) }.png`,
				fullPage: true,
			} );
			const p = panel( page, n );
			if ( kind === 'choice' && await p.locator( wizard.resultCardSelected ).count() === 0 ) {
				await p.locator( wizard.resultCard ).first().click();
			}
			const cont = p.locator( wizard.continueButton ).first();
			await expect( cont ).toBeEnabled();
			await cont.click();
		}

		// Step 10 review compiles client-side.
		await expect( page.locator( '#review-quill-editor .ql-editor' ) ).not.toBeEmpty();
		await page.screenshot( { path: `${ OUT }/wizard-run-step-10.png`, fullPage: true } );

		// Step 11 evaluate.
		await panel( page, 10 ).locator( wizard.continueButton ).first().click();
		await expect( page.locator( '[data-testid="evaluation-output"] table' ) ).toBeVisible( { timeout: 30_000 } );
		await page.screenshot( { path: `${ OUT }/wizard-run-step-11.png`, fullPage: true } );
	} );

	test( 'express run capture (light + dark)', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'desktop-1280', 'captured once on desktop' );
		await page.setViewportSize( { width: 1280, height: 900 } );
		for ( const theme of [ 'light', 'dark' ] as const ) {
			await setTheme( page, theme );
			await wpLogin( page );
			await page.goto( wizard.pageUrl );
			await page.locator( '[data-testid="mode-express"]' ).click();
			await page.locator( '[data-testid="express-topic"]' ).fill( 'indoor herb gardening' );
			await page.locator( '[data-action="express-generate"]' ).click();
			await expect( page.locator( '#express-stream-output h1' ) ).toBeVisible( { timeout: 30_000 } );
			await page.screenshot( { path: `${ OUT }/express-run-${ theme }.png`, fullPage: true } );
		}
	} );
} );
