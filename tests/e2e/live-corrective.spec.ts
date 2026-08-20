import { test, expect, type Page } from '@playwright/test';
import { assertNoConsoleErrors, snap, watchAjaxResponses, watchConsole, wpLogin } from './helpers';
import { wizard } from './selectors';

test.skip( process.env.AI_SCRIBE_LIVE_E2E !== '1', 'Requires an explicitly authorised real provider run.' );

const TOPIC = 'ecommerce SEO tips for this year';
const REUSE = process.env.LIVE_REUSE_CONVERSATION === '1';

function panel( page: Page, step: number ) {
	return page.locator( wizard.panel( step ) );
}

async function waitReady( page: Page, step: number, timeout = 120_000 ) {
	await expect( panel( page, step ) ).toHaveAttribute( 'data-state', 'ready', { timeout } );
}

async function ensureChoiceAndContinue( page: Page, step: number ) {
	const current = panel( page, step );
	await waitReady( page, step );
	const cards = current.locator( wizard.resultCard );
	await expect( cards.first() ).toBeVisible();
	if ( await current.locator( wizard.resultCardSelected ).count() === 0 ) {
		await cards.first().click();
	}
	const button = current.locator( wizard.continueButton ).first();
	await expect( button ).toBeEnabled();
	await button.click();
}

async function continueReadyStep( page: Page, step: number, timeout = 120_000 ) {
	await waitReady( page, step, timeout );
	const button = panel( page, step ).locator( wizard.continueButton ).first();
	await expect( button ).toBeEnabled();
	await button.click();
}

test.describe( 'Real-provider corrective acceptance', () => {
	test.describe.configure( { timeout: 600_000 } );

	test( 'fresh Wizard → real Gemini → image → save → accurate Evaluate', async ( { page }, testInfo ) => {
		const errors = watchConsole( page );
		const ajax = watchAjaxResponses( page );

		await wpLogin( page );
		await page.goto( wizard.pageUrl );
		await expect( page.locator( wizard.root ) ).toBeVisible();

		// Reloads never silently resume an old article.
		await expect( panel( page, 1 ) ).toBeVisible();
		await expect( page.locator( wizard.activeStep ) ).toContainText( 'TITLE', { ignoreCase: true } );

		if ( REUSE ) {
			await expect( page.locator( '[data-testid="resume-draft-notice"]' ) ).toBeVisible();
			await page.locator( '[data-testid="resume-draft"]' ).click();
			await expect( panel( page, 10 ) ).toBeVisible( { timeout: 30_000 } );
		} else {
			await panel( page, 1 ).locator( wizard.ideaInput ).fill( TOPIC );
			await panel( page, 1 ).locator( wizard.generateButton ).first().click();
			await expect( panel( page, 1 ).locator( '[data-testid="progress-ticker"]' ) ).toBeVisible();
			await ensureChoiceAndContinue( page, 1 );
			await ensureChoiceAndContinue( page, 2 );
			await ensureChoiceAndContinue( page, 3 );

			await continueReadyStep( page, 4 );
			await ensureChoiceAndContinue( page, 5 );

			await waitReady( page, 6, 180_000 );
		}

		const body = panel( page, 6 ).locator( wizard.editor ).first();
		if ( ! REUSE ) {
			await expect( body.locator( 'h2' ).first() ).toBeVisible();
			expect( await body.locator( 'h2' ).count() ).toBeGreaterThanOrEqual( 3 );

			// Body entry starts one visible, billable featured-image operation. It
			// must end in a usable gallery item, not a grey button with no outcome.
			const imageStatus = panel( page, 6 ).locator( '[data-testid="image-generation-status"]' );
			await expect( imageStatus ).toBeVisible( { timeout: 20_000 } );
			await expect( panel( page, 6 ).locator( '[data-testid="gallery-image"]' ).first() )
				.toBeVisible( { timeout: 240_000 } );
			await expect( imageStatus ).toHaveAttribute( 'aria-busy', 'false', { timeout: 30_000 } );
			await expect( panel( page, 6 ).locator( '[data-testid="featured-image-badge"]' ) ).toBeVisible();

			await continueReadyStep( page, 6 );
			await continueReadyStep( page, 7 );
			await ensureChoiceAndContinue( page, 8 );
			await waitReady( page, 9 );
		}
		const metaTitle = await page.locator( wizard.metaTitle ).inputValue();
		expect( metaTitle ).toMatch( /^\p{Lu}/u );
		expect( metaTitle ).toContain( 'SEO' );
		expect( metaTitle ).toMatch( /\s\|\s/ );
		expect( metaTitle ).not.toMatch( /\bSeo\b/ );
		if ( ! REUSE ) {
			await continueReadyStep( page, 9 );
		}

		const review = page.locator( '#review-quill-editor .ql-editor' );
		await expect( review.locator( 'h1' ) ).toHaveCount( 1 );
		await expect( review.locator( 'img' ).first() ).toBeVisible();
		await review.evaluate( ( editor ) => {
			editor.insertAdjacentHTML(
				'beforeend',
				'<p><a href="/about-us/">Related internal guide</a> and <a href="https://developers.google.com/search/docs">Google Search documentation</a>.</p>'
			);
			editor.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
		await snap( page, testInfo, 'real-gemini-review' );

		await page.locator( wizard.publishDraftButton ).click();
		await expect( page.locator( wizard.savedPostLink ) ).toBeVisible( { timeout: 30_000 } );
		await expect( page.locator( '[data-save-context="review"]' ) ).toContainText( /saved|draft/i );

		await panel( page, 10 ).locator( wizard.continueButton ).click();
		await waitReady( page, 11, 180_000 );
		const report = page.locator( wizard.evaluationOutput );
		await expect( report.locator( 'table' ) ).toBeVisible();
		await expect( report ).toContainText( 'Internal contextual links' );
		await expect( report ).toContainText( 'External contextual links' );
		await expect( report ).toContainText( 'Table of contents and anchor links' );
		await expect( report.locator( '.eval-row' ).filter( { hasText: 'Internal contextual links' } ) ).toContainText( 'Pass' );
		await expect( report.locator( '.eval-row' ).filter( { hasText: 'External contextual links' } ) ).toContainText( 'Pass' );
		await expect( page.locator( '[data-save-context="evaluate"]' ) ).toContainText( /saved|current/i );
		await snap( page, testInfo, 'real-gemini-evaluate' );

		expect( ajax.failures, ajax.failures.join( '\n' ) ).toHaveLength( 0 );
		assertNoConsoleErrors( errors );
	} );
} );
