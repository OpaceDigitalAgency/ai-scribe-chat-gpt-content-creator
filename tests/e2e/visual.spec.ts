import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import { assertNoConsoleErrors, watchConsole, wpLogin } from './helpers';
import { express, settings, wizard } from './selectors';

/**
 * Suite 4 — visual pass: dark-mode toggle behaviour + the final screenshot
 * set (tests/e2e/screenshots/final/) used for release material review.
 * Desktop shots come from the desktop-1280 project; the mobile shot from
 * mobile-375. Other combinations skip.
 */

const FINAL_DIR = path.resolve( __dirname, 'screenshots', 'final' );

function finalShot( name: string ): string {
	return path.join( FINAL_DIR, `${ name }.png` );
}

test.describe( 'Visual — dark mode + final screenshots', () => {
	test( 'wizard light + dark (toggle persists and flips tokens)', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'desktop-1280', 'desktop shots only' );
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( wizard.pageUrl );
		await expect( page.locator( wizard.root ) ).toBeVisible();

		// Force light first for a deterministic pair.
		const html = page.locator( 'html' );
		if ( ( await html.getAttribute( 'data-ai-scribe-theme' ) ) !== 'light' ) {
			await page.locator( wizard.themeToggle ).click();
		}
		await expect( html ).toHaveAttribute( 'data-ai-scribe-theme', 'light' );
		await page.screenshot( { path: finalShot( 'wizard-light' ), fullPage: true } );

		await page.locator( wizard.themeToggle ).click();
		await expect( html ).toHaveAttribute( 'data-ai-scribe-theme', 'dark' );
		await expect( page.locator( wizard.themeToggle ) ).toHaveAttribute( 'aria-pressed', 'true' );
		await page.screenshot( { path: finalShot( 'wizard-dark' ), fullPage: true } );

		// Preference survives reload.
		await page.reload();
		await expect( html ).toHaveAttribute( 'data-ai-scribe-theme', 'dark' );

		// Restore light for later suites/screens.
		await page.locator( wizard.themeToggle ).click();
		await expect( html ).toHaveAttribute( 'data-ai-scribe-theme', 'light' );
		assertNoConsoleErrors( errors );
	} );

	test( 'settings screen light + dark', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'desktop-1280', 'desktop shots only' );
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( settings.pageUrl );
		await expect( page.locator( settings.root ) ).toBeVisible();
		await page.screenshot( { path: finalShot( 'settings-light' ), fullPage: true } );

		// Settings page has no toggle button; theme comes from the stored pref.
		await page.evaluate( () => {
			document.documentElement.setAttribute( 'data-ai-scribe-theme', 'dark' );
		} );
		await page.screenshot( { path: finalShot( 'settings-dark' ), fullPage: true } );
		assertNoConsoleErrors( errors );
	} );

	test( 'help + shortcodes pages light + dark', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'desktop-1280', 'desktop shots only' );
		const errors = watchConsole( page );
		await wpLogin( page );
		for ( const [ name, url ] of [
			[ 'help', '/wp-admin/admin.php?page=ai_scribe_help' ],
			[ 'shortcodes', '/wp-admin/admin.php?page=ai_scribe_saved_shortcodes' ],
		] as Array<[ string, string ]> ) {
			await page.goto( url );
			await page.screenshot( { path: finalShot( `${ name }-light` ), fullPage: true } );
			await page.evaluate( () => {
				document.documentElement.setAttribute( 'data-ai-scribe-theme', 'dark' );
			} );
			await page.screenshot( { path: finalShot( `${ name }-dark` ), fullPage: true } );
		}
		assertNoConsoleErrors( errors );
	} );

	test( 'express screen screenshot', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'desktop-1280', 'desktop shots only' );
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( wizard.pageUrl );
		await page.locator( wizard.modeExpress ).click();
		await expect( page.locator( express.screen ) ).toBeVisible();
		await page.locator( express.topicInput ).fill( 'heat pumps for uk homes' );
		await page.locator( express.generateButton ).click();
		await expect( page.locator( express.articleOutput ) ).not.toBeEmpty( { timeout: 45_000 } );
		await page.screenshot( { path: finalShot( 'express' ), fullPage: true } );
		assertNoConsoleErrors( errors );
	} );

	test( 'mobile wizard screenshot (375px stepper collapse)', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'mobile-375', 'mobile shot only' );
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( wizard.pageUrl );
		await expect( page.locator( wizard.root ) ).toBeVisible();
		// No horizontal document overflow on mobile.
		const overflow = await page.evaluate(
			() => document.documentElement.scrollWidth - document.documentElement.clientWidth
		);
		expect( overflow ).toBeLessThanOrEqual( 24 );
		await page.screenshot( { path: finalShot( 'wizard-mobile-375' ), fullPage: true } );
		assertNoConsoleErrors( errors );
	} );
} );
