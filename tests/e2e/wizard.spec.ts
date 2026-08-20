import { test, expect, type Page } from '@playwright/test';
import {
	assertNoConsoleErrors,
	snap,
	watchAjaxResponses,
	watchConsole,
	wpCli,
	wpCliTry,
	wpLogin,
} from './helpers';
import { express, wizard } from './selectors';

/**
 * Suite 1 — 11-step wizard happy path + alternate paths (REFACTOR.md §9.3).
 *
 * The 11 steps (templates/create_template.php order, kept in v3):
 *   1 Titles · 2 Keywords · 3 Outline · 4 Intro · 5 Tagline · 6 Body
 *   7 Conclusion · 8 Q&A (skippable) · 9 SEO Meta · 10 Review · 11 Evaluate
 *
 * Mock provider answers every provider call; the request log
 * (wp option ai_scribe_mock_request_log) backs the context regressions.
 */

const IDEA = 'how to brew better coffee at home';

function panel( page: Page, n: number ) {
	return page.locator( wizard.panel( n ) );
}

async function waitReady( page: Page, n: number ): Promise<void> {
	await expect( panel( page, n ) ).toHaveAttribute( 'data-state', 'ready', { timeout: 30_000 } );
}

/** Pick the first result card on a choice step, then continue. */
async function pickAndContinue( page: Page, n: number ): Promise<void> {
	const p = panel( page, n );
	if ( await p.locator( wizard.resultCardSelected ).count() === 0 ) {
		await p.locator( wizard.resultCard ).first().click();
	}
	const cont = p.locator( wizard.continueButton ).first();
	await expect( cont ).toBeEnabled();
	await cont.click();
}

/** Continue from a long-form step once its content is rendered. */
async function continueLongform( page: Page, n: number ): Promise<void> {
	const cont = panel( page, n ).locator( wizard.continueButton ).first();
	await expect( cont ).toBeEnabled();
	await cont.click();
}

/** Drive the wizard from a fresh page up to (and including) step `upTo`. */
async function driveWizard( page: Page, upTo: number ): Promise<void> {
	await panel( page, 1 ).locator( wizard.ideaInput ).fill( IDEA );
	await panel( page, 1 ).locator( wizard.generateButton ).first().click();
	await waitReady( page, 1 );
	if ( upTo === 1 ) {
		return;
	}
	const order: Array<[ number, 'choice' | 'long' ]> = [
		[ 1, 'choice' ], [ 2, 'choice' ], [ 3, 'choice' ], [ 4, 'long' ],
		[ 5, 'choice' ], [ 6, 'long' ], [ 7, 'long' ], [ 8, 'choice' ],
		[ 9, 'long' ],
	];
	for ( const [ n, kind ] of order ) {
		if ( n >= upTo ) {
			return;
		}
		if ( n > 1 ) {
			await waitReady( page, n );
		}
		if ( kind === 'choice' ) {
			await pickAndContinue( page, n );
		} else {
			await continueLongform( page, n );
		}
	}
}

test.describe( 'Wizard — 11-step happy path (mock provider)', () => {
	test( 'full run: settings → all 11 steps → draft post with SEO meta in DB', async ( { page }, testInfo ) => {
		const errors = watchConsole( page );
		const ajax = watchAjaxResponses( page );
		wpCliTry( 'wp option delete ai_scribe_mock_request_log' );

		await wpLogin( page );
		await page.goto( wizard.pageUrl );
		await expect( page.locator( wizard.root ) ).toBeVisible();

		// Step 1 — Titles.
		await panel( page, 1 ).locator( wizard.ideaInput ).fill( IDEA );
		await panel( page, 1 ).locator( wizard.generateButton ).first().click();
		await waitReady( page, 1 );
		// §5.1: response must render before the workflow can advance.
		await expect( panel( page, 1 ).locator( wizard.resultCard ) ).toHaveCount( 5 );
		await snap( page, testInfo, 'step-01-titles' );
		await pickAndContinue( page, 1 );

		// Step 2 — Keywords (auto-generates on arrival).
		await waitReady( page, 2 );
		await expect( panel( page, 2 ).locator( wizard.resultCard ).first() ).toBeVisible();
		await snap( page, testInfo, 'step-02-keywords' );
		await pickAndContinue( page, 2 );

		// Step 3 — Outline.
		await waitReady( page, 3 );
		await expect( panel( page, 3 ).locator( wizard.resultCard ).first() ).toBeVisible();
		await snap( page, testInfo, 'step-03-outline' );
		await pickAndContinue( page, 3 );

		// Step 4 — Introduction (long-form, full-context thread).
		await waitReady( page, 4 );
		await expect( panel( page, 4 ).locator( wizard.streamOutput ) ).not.toBeEmpty();
		await snap( page, testInfo, 'step-04-intro' );
		await continueLongform( page, 4 );

		// Step 5 — Tagline.
		await waitReady( page, 5 );
		await snap( page, testInfo, 'step-05-tagline' );
		await pickAndContinue( page, 5 );

		// Step 6 — Body: one call, renders INTO QUILL with headings intact.
		await waitReady( page, 6 );
		const editor6 = panel( page, 6 ).locator( wizard.editor ).first();
		await expect( editor6 ).not.toBeEmpty();
		await expect( editor6.locator( 'h2' ).first() ).toBeVisible();
		expect( await editor6.locator( 'h2' ).count() ).toBeGreaterThanOrEqual( 3 );
		await snap( page, testInfo, 'step-06-body' );
		await continueLongform( page, 6 );

		// Step 7 — Conclusion (regression: WITH body context, asserted below).
		await waitReady( page, 7 );
		await expect( panel( page, 7 ).locator( wizard.streamOutput ) ).not.toBeEmpty();
		await snap( page, testInfo, 'step-07-conclusion' );
		await continueLongform( page, 7 );

		// Step 8 — Q&A.
		await waitReady( page, 8 );
		await expect( panel( page, 8 ).locator( wizard.resultCard ).first() ).toBeVisible();
		await snap( page, testInfo, 'step-08-qna' );
		await pickAndContinue( page, 8 );

		// Step 9 — SEO meta: VISIBLE fields, populated (v4 freeze regression:
		// "#meta-results keeps hidden").
		await waitReady( page, 9 );
		await expect( page.locator( wizard.metaTitle ) ).toBeVisible();
		await expect( page.locator( wizard.metaTitle ) ).not.toHaveValue( '' );
		await expect( page.locator( wizard.metaDescription ) ).not.toHaveValue( '' );
		await snap( page, testInfo, 'step-09-seo-meta' );
		await continueLongform( page, 9 );

		// Step 10 — Review: client-side assembly (no provider call), save buttons present.
		const logBeforeReview = wpCli( 'wp option get ai_scribe_mock_request_log --format=json' );
		const reviewEditor = page.locator( '#review-quill-editor .ql-editor' );
		await expect( reviewEditor ).not.toBeEmpty();
		// The exact live Review editor, not the generated body snapshot, must be
		// the source for both save and Evaluate. Add deterministic late edits
		// after Review has compiled, including an image and a link.
		await reviewEditor.evaluate( ( editor ) => {
			editor.insertAdjacentHTML(
				'beforeend',
				'<p>FINAL-REVIEW-EDIT-SENTINEL <a href="https://example.test/evidence">Evidence</a></p><p><img src="/wp-includes/images/blank.gif" alt="Review diagram"></p>'
			);
			editor.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
		await expect( page.locator( wizard.publishDraftButton ) ).toBeVisible();
		await expect( page.locator( wizard.publishPostButton ) ).toBeVisible();
		await snap( page, testInfo, 'step-10-review' );

		// Save as draft → post exists with correct title + SEO meta in DB.
		await page.locator( wizard.publishDraftButton ).click();
		const savedLink = page.locator( wizard.savedPostLink );
		await expect( savedLink ).toBeVisible( { timeout: 20_000 } );
		await expect( savedLink ).toHaveAttribute( 'href', /post\.php\?post=\d+/, { timeout: 10_000 } );
		const href = await savedLink.getAttribute( 'href' );
		const postId = new URL( href!, 'http://localhost' ).searchParams.get( 'post' );
		expect( postId ).toBeTruthy();

		const postTitle = wpCli( `wp post get ${ postId } --field=post_title` );
		expect( postTitle ).toContain( 'How to Brew Better Coffee at Home' );
		expect( wpCli( `wp post get ${ postId } --field=post_status` ) ).toBe( 'draft' );
		const metaTitle = wpCli( `wp post meta get ${ postId } _ai_scribe_meta_title` );
		expect( metaTitle.length ).toBeGreaterThan( 10 );
		const metaDesc = wpCli( `wp post meta get ${ postId } _ai_scribe_meta_description` );
		expect( metaDesc.length ).toBeGreaterThan( 20 );
		const content = wpCli( `wp post get ${ postId } --field=post_content` );
		expect( content ).toContain( '<h2>' );
		expect( content ).toContain( 'FINAL-REVIEW-EDIT-SENTINEL' );

		// Step 10 made NO provider-bound request (review is client-side assembly).
		const logAfterSave = wpCli( 'wp option get ai_scribe_mock_request_log --format=json' );
		expect( JSON.parse( logAfterSave ).length ).toBe( JSON.parse( logBeforeReview ).length );

		// Step 11 — Evaluate.
		await panel( page, 10 ).locator( wizard.continueButton ).click();
		await waitReady( page, 11 );
		await expect( page.locator( wizard.evaluationOutput ).locator( 'table' ) ).toBeVisible();
		await expect( page.locator( '.evaluation-summary' ) ).toBeVisible();
		const imageFact = page.locator( '.evaluation-facts > div' ).filter( { hasText: 'Images' } ).locator( 'dd' );
		expect( Number( await imageFact.textContent() ) ).toBeGreaterThan( 0 );
		const imageCheck = page.locator( '.evaluation-report-table .eval-row' ).filter( { hasText: 'Image accessibility markup' } );
		await expect( imageCheck ).toContainText( /image element is present/i );
		await expect( imageCheck ).not.toContainText( 'No images are present' );
		await snap( page, testInfo, 'step-11-evaluate' );

		// Regression ledger (mock request log):
		const log = JSON.parse( wpCli( 'wp option get ai_scribe_mock_request_log --format=json' ) );
		const conclusionReq = log.find( ( e: any ) => /create a conclusion/i.test( e.last_user ) );
		expect( conclusionReq, 'conclusion request recorded' ).toBeTruthy();
		// test_step7_receives_body_context — the 2.6.2 blind-writing defect.
		expect( conclusionReq.all_text ).toContain( 'Choosing Beans: Freshness Beats Origin' );
		// SEO meta + evaluate also carry the body in the thread.
		const evalReq = log.find( ( e: any ) => /evaluat(?:e|ing|ion).*article/i.test( e.last_user ) );
		expect( evalReq ).toBeTruthy();
		expect( evalReq.all_text ).toContain( 'Choosing Beans' );
		expect( evalReq.last_user ).toContain( 'FINAL-REVIEW-EDIT-SENTINEL' );
		expect( evalReq.last_user ).toContain( '"image_count":' );
		// test_evaluate_never_sends_object_object (§3.2 step 11).
		for ( const entry of log ) {
			expect( entry.all_text ).not.toContain( '[object Object]' );
			expect( entry.last_user ).not.toContain( '[object Object]' );
		}

		// test_no_raw_echo_json — every ai_scribe_* response parsed.
		expect( ajax.failures, `Unparseable AJAX responses:\n${ ajax.failures.join( '\n' ) }` ).toHaveLength( 0 );
		assertNoConsoleErrors( errors );
	} );

	test( 'reload offers explicit resume and restores without re-billing', async ( { page }, testInfo ) => {
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( wizard.pageUrl );
		await driveWizard( page, 4 );
		await waitReady( page, 4 );
		await expect( panel( page, 4 ).locator( wizard.streamOutput ) ).not.toBeEmpty();

		const logBefore = JSON.parse( wpCli( 'wp option get ai_scribe_mock_request_log --format=json' ) ).length;

		await page.reload();
		await expect( page.locator( wizard.root ) ).toBeVisible();
		// Reload starts clean. Recovery is deliberate rather than silently
		// dropping the user back into a half-finished wizard.
		await expect( panel( page, 1 ) ).toBeVisible();
		await expect( page.locator( '[data-testid="resume-draft-notice"]' ) ).toBeVisible();
		await page.locator( '[data-testid="resume-draft"]' ).click();
		// Stored responses re-render only after the explicit Resume action …
		await expect( panel( page, 4 ).locator( wizard.streamOutput ) ).not.toBeEmpty( { timeout: 15_000 } );
		await expect( panel( page, 1 ).locator( wizard.resultCard ).first() ).toBeAttached();
		// … and NOTHING was re-billed.
		const logAfter = JSON.parse( wpCli( 'wp option get ai_scribe_mock_request_log --format=json' ) ).length;
		expect( logAfter ).toBe( logBefore );

		await snap( page, testInfo, 'reload-recovery' );
		assertNoConsoleErrors( errors );
	} );
} );

test.describe( 'Wizard — alternate paths', () => {
	test( 'Q&A step can be skipped', async ( { page }, testInfo ) => {
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( wizard.pageUrl );
		await driveWizard( page, 8 );
		await waitReady( page, 8 );

		await panel( page, 8 ).locator( wizard.skipButton ).click();
		// Step 9 becomes the active step and generates.
		await expect( panel( page, 9 ) ).toBeVisible();
		await waitReady( page, 9 );
		await expect( page.locator( wizard.metaTitle ) ).not.toHaveValue( '' );

		await snap( page, testInfo, 'qna-skip' );
		assertNoConsoleErrors( errors );
	} );

	test( 'regenerate (Generate More) appends options and keeps the selection', async ( { page }, testInfo ) => {
		// Regression: recurring "Generate More" breakage (REFACTOR.md §7).
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( wizard.pageUrl );
		await driveWizard( page, 1 );

		const cards = panel( page, 1 ).locator( wizard.resultCard );
		const initialCount = await cards.count();
		expect( initialCount ).toBeGreaterThan( 0 );
		await cards.first().click();

		await panel( page, 1 ).locator( wizard.regenerateButton ).click();
		await expect
			.poll( async () => cards.count(), { timeout: 30_000 } )
			.toBeGreaterThan( initialCount );
		// Previously selected card stays selected.
		await expect( panel( page, 1 ).locator( wizard.resultCardSelected ) ).toHaveCount( 1 );

		await snap( page, testInfo, 'generate-more' );
		assertNoConsoleErrors( errors );
	} );

	test( 'edited prompt override reaches the server-assembled prompt', async ( { page }, testInfo ) => {
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( wizard.pageUrl );
		await driveWizard( page, 1 );

		const marker = 'OVERRIDE-MARKER-7481 provide article titles about [Idea]';
		await page.locator( wizard.promptEditor ).fill( marker );
		await panel( page, 1 ).locator( wizard.regenerateButton ).click();
		await expect
			.poll( async () => panel( page, 1 ).locator( wizard.resultCard ).count(), { timeout: 30_000 } )
			.toBeGreaterThan( 5 );

		const log = JSON.parse( wpCli( 'wp option get ai_scribe_mock_request_log --format=json' ) );
		const overrideReq = log.find( ( e: any ) => e.last_user.includes( 'OVERRIDE-MARKER-7481' ) );
		expect( overrideReq, 'override text must reach the provider request' ).toBeTruthy();
		// Placeholders are still resolved server-side.
		expect( overrideReq.last_user ).not.toContain( '[Idea]' );
		expect( overrideReq.last_user ).toContain( IDEA );

		assertNoConsoleErrors( errors );
	} );
} );

test.describe( 'Express mode', () => {
	test( 'topic → full structured article → refine-in-wizard handoff', async ( { page }, testInfo ) => {
		const errors = watchConsole( page );
		await wpLogin( page );
		await page.goto( wizard.pageUrl );

		await page.locator( wizard.modeExpress ).click();
		await expect( page.locator( express.screen ) ).toBeVisible();

		await page.locator( express.topicInput ).fill( 'heat pumps for uk homes' );
		await page.locator( express.generateButton ).click();

		const article = page.locator( express.articleOutput );
		await expect( article ).not.toBeEmpty( { timeout: 45_000 } );
		await expect( article.locator( 'h2' ).first() ).toBeVisible();
		await snap( page, testInfo, 'express-article' );

		// Refine in Wizard: seeds the wizard and lands on Review.
		const refine = page.locator( express.refineButton );
		await expect( refine ).toBeVisible();
		await refine.click();
		await expect( page.locator( wizard.screen ) ).toBeVisible();
		await expect( panel( page, 10 ) ).toBeVisible();
		await expect( page.locator( '#review-quill-editor .ql-editor' ) ).not.toBeEmpty();

		await snap( page, testInfo, 'express-refined-in-wizard' );
		assertNoConsoleErrors( errors );
	} );
} );
