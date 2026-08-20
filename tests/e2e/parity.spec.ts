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
 * Suite P5.6 — 2.6.2 feature-parity remediation (PARITY_AUDIT.md worklist):
 * Save-as-Shortcode + frontend rendering, early/parallel image generation
 * from the keyword step, skip buttons + placement radios, and the restored
 * settings surface (Humanizer modes, check_Arr enhancements, languages,
 * full image options, delete-data opt-in).
 */

const IDEA = 'how to brew better coffee at home';

/** Update a JSON option through WP-CLI (single-quoted argument, no stdin). */
function wpOptionUpdateJson( name: string, json: string ): void {
	const safe = json.replace( /'/g, `'\\''` );
	wpCli( `wp option update ${ name } '${ safe }' --format=json` );
}


function panel( page: Page, n: number ) {
	return page.locator( wizard.panel( n ) );
}

async function waitReady( page: Page, n: number ): Promise<void> {
	await expect( panel( page, n ) ).toHaveAttribute( 'data-state', 'ready', { timeout: 30_000 } );
}

async function pickAndContinue( page: Page, n: number ): Promise<void> {
	const p = panel( page, n );
	if ( await p.locator( wizard.resultCardSelected ).count() === 0 ) {
		await p.locator( wizard.resultCard ).first().click();
	}
	const cont = p.locator( wizard.continueButton ).first();
	await expect( cont ).toBeEnabled();
	await cont.click();
}

async function continueLongform( page: Page, n: number ): Promise<void> {
	const cont = panel( page, n ).locator( wizard.continueButton ).first();
	await expect( cont ).toBeEnabled();
	await cont.click();
}

/** Drive a fresh wizard through steps 1-9 so step 10 (review) compiles. */
async function driveToReview( page: Page ): Promise<void> {
	await panel( page, 1 ).locator( wizard.ideaInput ).fill( IDEA );
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
		if ( kind === 'choice' ) {
			await pickAndContinue( page, n );
		} else {
			await continueLongform( page, n );
		}
	}
	await expect( page.locator( '#review-quill-editor .ql-editor' ) ).not.toBeEmpty();
}

test.describe( 'P5.6 — Save as Shortcode → frontend rendering (end-to-end)', () => {
	test( 'review step saves a shortcode row and the shortcode renders on a real page', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'desktop-1280', 'full-run flow exercised once on desktop' );
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( wizard.pageUrl );
		await driveToReview( page );

		await page.locator( '[data-testid="save-shortcode"]' ).click();
		const note = page.locator( '[data-testid="review-saved-shortcode-note"]' );
		await expect( note ).toBeVisible( { timeout: 20_000 } );
		const noteText = ( await note.textContent() ) || '';
		const match = noteText.match( /template_id="(\d+)"/ );
		expect( match, `shortcode note shows the template id (got: ${ noteText })` ).toBeTruthy();
		const templateId = match![ 1 ];
		await snap( page, testInfo, 'save-shortcode-note' );

		// The row exists in {prefix}article_builder with the article title.
		const row = wpCli(
			`wp db query "SELECT title FROM wp_article_builder WHERE id=${ templateId }" --skip-column-names`
		);
		expect( row ).toContain( 'How to Brew Better Coffee at Home' );

		// Embed the shortcode in a real published page and view the frontend.
		const pageId = wpCli(
			`wp post create --post_type=page --post_status=publish --post_title='Shortcode parity test' ` +
			`--post_content='[article_builder_generate_data template_id="${ templateId }"]' --porcelain`
		);
		try {
			await page.goto( `/?page_id=${ pageId }` );
			const body = page.locator( 'body' );
			await expect( body ).toContainText( 'How to Brew Better Coffee at Home' );
			// Body content came through the article column.
			await expect( body ).toContainText( 'Choosing Beans' );
			await snap( page, testInfo, 'shortcode-frontend' );

			// Saved Shortcodes screen lists it and Remove deletes the DB ROW
			// (regression: the old handler deleted a phantom option only).
			await page.goto( '/wp-admin/admin.php?page=ai_scribe_saved_shortcodes' );
			const removeButton = page.locator( `#ai-scribe-shortcodes-root .delete[data-id="${ templateId }"]` );
			await expect( removeButton ).toBeVisible();
			await removeButton.click();
			await expect( removeButton ).not.toBeAttached( { timeout: 15_000 } );
			await expect
				.poll( () => wpCli(
					`wp db query "SELECT COUNT(*) FROM wp_article_builder WHERE id=${ templateId }" --skip-column-names`
				), { timeout: 10_000 } )
				.toBe( '0' );
		} finally {
			wpCliTry( `wp post delete ${ pageId } --force` );
			wpCliTry( `wp db query "DELETE FROM wp_article_builder WHERE id=${ templateId }"` );
		}
		assertNoConsoleErrors( errors );
	} );
} );

test.describe( 'Image generation consent boundary', () => {
	let savedImageSettings = '';

	test.beforeAll( () => {
		savedImageSettings = wpCliTry( 'wp option get ab_gpt_image_settings --format=json' );
		wpOptionUpdateJson( 'ab_gpt_image_settings', '{"enabled":true,"model":"gpt-image-1","size":"1024x1024"}' );
	} );

	test.afterAll( () => {
		if ( savedImageSettings ) {
			try { wpOptionUpdateJson( 'ab_gpt_image_settings', savedImageSettings ); } catch ( e ) { /* best-effort restore */ }
		} else {
			wpCliTry( 'wp option delete ab_gpt_image_settings' );
		}
	} );

	test( 'choosing keywords does not silently spend on a background image', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'desktop-1280', 'image flow exercised once on desktop' );
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( wizard.pageUrl );

		await panel( page, 1 ).locator( wizard.ideaInput ).fill( IDEA );
		await panel( page, 1 ).locator( wizard.generateButton ).first().click();
		await waitReady( page, 1 );
		await pickAndContinue( page, 1 );
		await waitReady( page, 2 );
		await pickAndContinue( page, 2 );

		// Images are billable. Generation waits until Body exists and its Image
		// Studio can show the exact prompt and visible progress to the user.
		const galleryImage = panel( page, 6 ).locator( '[data-testid="gallery-image"]' );
		await expect( galleryImage ).toHaveCount( 0 );

		await snap( page, testInfo, 'image-generation-consent-boundary' );
		assertNoConsoleErrors( errors );
	} );
} );

test.describe( 'P5.6 — skip buttons and placement radios', () => {
	test( 'steps 2,3,4,5,7,9 expose Skip; tagline and Q&A placement radios exist', async ( { page } ) => {
		await wpLogin( page );
		await page.goto( wizard.pageUrl );
		await expect( page.locator( wizard.root ) ).toBeVisible();

		for ( const n of [ 2, 3, 4, 5, 7, 8, 9 ] ) {
			await expect(
				panel( page, n ).locator( wizard.skipButton ),
				`step ${ n } has a Skip button`
			).toBeAttached();
		}
		expect( await page.locator( 'input[name="above_below_tagline"]' ).count() ).toBe( 2 );
		expect( await page.locator( 'input[name="above_below_conclusion"]' ).count() ).toBe( 2 );
	} );

	test( 'skipping the keywords step advances to the outline', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name === 'mobile-375', 'flow covered on larger viewports' );
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( wizard.pageUrl );

		await panel( page, 1 ).locator( wizard.ideaInput ).fill( IDEA );
		await panel( page, 1 ).locator( wizard.generateButton ).first().click();
		await waitReady( page, 1 );
		await pickAndContinue( page, 1 );
		await waitReady( page, 2 );

		await panel( page, 2 ).locator( wizard.skipButton ).click();
		// Lands on step 3, which auto-generates the outline.
		await waitReady( page, 3 );
		await expect( panel( page, 3 ).locator( wizard.resultCard ).first() ).toBeVisible();
		assertNoConsoleErrors( errors );
	} );
} );

test.describe( 'P5.6 — restored settings surface', () => {
	let savedContent = '';
	let savedImages = '';

	test.beforeAll( () => {
		savedContent = wpCliTry( 'wp option get ab_gpt_content_settings --format=json' );
		savedImages = wpCliTry( 'wp option get ab_gpt_image_settings --format=json' );
	} );

	test.afterAll( () => {
		if ( savedContent ) {
			try { wpOptionUpdateJson( 'ab_gpt_content_settings', savedContent ); } catch ( e ) { /* best-effort restore */ }
		}
		if ( savedImages ) {
			try { wpOptionUpdateJson( 'ab_gpt_image_settings', savedImages ); } catch ( e ) { /* best-effort restore */ }
		}
		wpCliTry( 'wp option update ai_scribe_delete_data_on_uninstall no' );
	} );

	test( 'Humanizer mode, enhancement toggles, custom language and image options persist', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'desktop-1280', 'settings persistence exercised once on desktop' );
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( settings.pageUrl );

		// Generation tab: languages seeded, mode radios, enhancements.
		await page.locator( settings.tab( 'generation' ) ).click();
		const languageOptions = page.locator( '#ai-scribe-language option' );
		expect( await languageOptions.count() ).toBeGreaterThanOrEqual( 33 );
		await expect( page.locator( '#ai-scribe-language option[value="French"]' ) ).toBeAttached();

		await page.locator( '[data-testid="mode-humanize"]' ).check();
		await page.locator( '[data-testid="check-addinsertToc"]' ).check();
		await page.locator( '[data-testid="check-addQNA"]' ).check();
		await page.locator( '[data-testid="custom-language"]' ).fill( 'Welsh (Cymraeg)' );
		await page.locator( '[data-testid="number-of-headings"]' ).fill( '7' );
		await page.locator( '[data-testid="heading-tag"]' ).selectOption( 'H3' );
		await page.locator( '[data-testid="avoid-keywords"]' ).fill( 'cheap, nasty' );
		await page.locator( '[data-testid="delete-data-on-uninstall"]' ).check();

		// Images tab: the full 2.6.2 option set.
		await page.locator( settings.tab( 'images' ) ).click();
		await page.locator( settings.imageSize ).selectOption( 'auto' );
		await page.locator( '[data-testid="image-quality"]' ).selectOption( 'high' );
		await page.locator( '[data-testid="image-format"]' ).selectOption( 'webp' );
		await page.locator( '[data-testid="image-background"]' ).selectOption( 'transparent' );
		await page.locator( '[data-testid="image-style"]' ).selectOption( 'Cyberpunk' );

		await page.locator( settings.saveButton ).first().click();
		await expect( page.locator( settings.status ) ).toHaveText( /saved/i, { timeout: 15_000 } );
		await snap( page, testInfo, 'parity-settings-saved' );

		const content = JSON.parse( wpCli( 'wp option get ab_gpt_content_settings --format=json' ) );
		expect( content.mode ).toBe( 'humanize' );
		expect( content.check_Arr.addinsertToc ).toBe( 'addinsertToc' );
		expect( content.check_Arr.addQNA ).toBe( 'addQNA' );
		expect( content.language ).toBe( 'Welsh (Cymraeg)' );
		expect( String( content.number_of_headings ) ).toBe( '7' );
		expect( content.heading_tag ).toBe( 'H3' );
		expect( content.avoid_keywords ).toBe( 'cheap, nasty' );

		const languages: string[] = JSON.parse( wpCli( 'wp option get ai_scribe_languages --format=json' ) );
		expect( languages ).toContain( 'Welsh (Cymraeg)' );

		const images = JSON.parse( wpCli( 'wp option get ab_gpt_image_settings --format=json' ) );
		expect( images.size ).toBe( 'auto' );
		expect( images.quality ).toBe( 'high' );
		expect( images.format ).toBe( 'webp' );
		expect( images.background ).toBe( 'transparent' );
		expect( images.style ).toBe( 'Cyberpunk' );
		expect( wpCli( 'wp option get ab_image_format' ) ).toBe( 'webp' );
		expect( wpCli( 'wp option get ai_scribe_delete_data_on_uninstall' ) ).toBe( 'yes' );

		assertNoConsoleErrors( errors );
	} );
} );
