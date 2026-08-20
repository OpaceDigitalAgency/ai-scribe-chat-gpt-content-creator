import { test, expect, type Locator, type Page } from '@playwright/test';
import {
	assertNoConsoleErrors,
	snap,
	watchAjaxResponses,
	watchConsole,
	wpCli,
	wpLogin,
} from './helpers';
import { wizard } from './selectors';

test.skip(
	process.env.AI_SCRIBE_LIVE_E2E !== '1',
	'Requires an explicitly authorised isolated real-provider run.'
);

const TOPIC = 'Practical website accessibility and conversion audit for small businesses in 2026';
const EXPECTED_LENGTH_MODE = 'standard';
// Standard targets 1,800 complete-article words. With Q&A enabled, the
// published plan reserves 28% for the other Wizard fragments and applies its
// 15% tolerance: floor((1800 - round(1800 * 0.28)) * 0.85) = 1,101.
const EXPECTED_BODY_MIN = 1_101;
const EXPECTED_KEYWORD_COUNT = 4;
const EXPECTED_OUTLINE_COUNT = 5;
const EXPECTED_PLUGIN_VERSION = '3.2.12';
const ACCEPTED_PLUGIN_SLUGS = [
	'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard',
	'ai-scribe-v3',
];

type PluginRow = {
	name: string;
	status: string;
	version: string;
};

type MetaCoverage = {
	keyword: string;
	title: 'exact' | 'combined' | 'partial' | 'absent';
	description: 'exact' | 'combined' | 'partial' | 'absent';
};

function panel( page: Page, step: number ): Locator {
	return page.locator( wizard.panel( step ) );
}

function normalise( value: string | null ): string {
	return String( value || '' ).replace( /\s+/g, ' ' ).trim();
}

function wordCount( value: string | null ): number {
	return normalise( value ).match( /[\p{L}\p{N}]+(?:[\u2019'-][\p{L}\p{N}]+)*/gu )?.length || 0;
}

async function waitReady( page: Page, step: number, timeout = 180_000 ): Promise<void> {
	await expect( panel( page, step ) ).toHaveAttribute( 'data-state', 'ready', { timeout } );
}

async function continueReadyStep( page: Page, step: number, timeout = 180_000 ): Promise<void> {
	await waitReady( page, step, timeout );
	const button = panel( page, step ).locator( wizard.continueButton ).first();
	await expect( button ).toBeEnabled();
	await button.click();
}

async function selectSingleAndContinue( page: Page, step: number ): Promise<string> {
	await waitReady( page, step );
	const current = panel( page, step );
	const cards = current.locator( wizard.resultCard );
	await expect( cards.first() ).toBeVisible();
	if ( await current.locator( wizard.resultCardSelected ).count() === 0 ) {
		await cards.first().click();
	}
	const selected = current.locator( wizard.resultCardSelected ).first();
	const text = normalise( await selected.locator( '.option-text, .keyword-title' ).first().textContent() );
	await current.locator( wizard.continueButton ).first().click();
	return text;
}

async function selectFirstN( container: Locator, count: number ): Promise<void> {
	const cards = container.locator( wizard.resultCard );
	await expect( cards.first() ).toBeVisible( { timeout: 180_000 } );
	expect( await cards.count() ).toBeGreaterThanOrEqual( count );
	for ( const card of await cards.all() ) {
		if ( await card.getAttribute( 'aria-checked' ) === 'true' ) {
			await card.click();
		}
	}
	for ( let index = 0; index < count; index += 1 ) {
		await cards.nth( index ).click();
	}
}

test.describe( 'Real Gemini article-quality acceptance', () => {
	test.describe.configure( { timeout: 900_000 } );

	test( 'multiple keywords → planned article → optional meta rewrite → clean draft → Evaluate', async ( { page }, testInfo ) => {
		const errors = watchConsole( page );
		const ajax = watchAjaxResponses( page );

		// Fail before opening the authoring UI if this is not the isolated real
		// environment. The combined value must be "offoff"; undefined is also off.
		const mockState = wpCli(
			`wp eval 'echo (defined("AI_SCRIBE_MOCK") && AI_SCRIBE_MOCK) ? "on" : "off"; echo (defined("AI_SCRIBE_AUTOMATED_TEST") && AI_SCRIBE_AUTOMATED_TEST) ? "on" : "off";'`
		);
		expect( mockState ).toBe( 'offoff' );
		const plugins = JSON.parse(
			wpCli( 'wp plugin list --fields=name,status,version --format=json' )
		) as PluginRow[];
		const activeScribePlugins = plugins.filter( ( plugin ) =>
			ACCEPTED_PLUGIN_SLUGS.includes( plugin.name ) && plugin.status === 'active'
		);
		const core = JSON.parse( wpCli( 'wp plugin get ai-core --fields=status,version --format=json' ) );
		expect( activeScribePlugins ).toHaveLength( 1 );
		expect( activeScribePlugins[ 0 ].version ).toBe( EXPECTED_PLUGIN_VERSION );
		expect( core.status ).toBe( 'active' );

		await wpLogin( page );
		await page.goto( wizard.pageUrl );
		await expect( page.locator( wizard.root ) ).toBeVisible();
		await expect( page.locator( '#active-model-details' ) ).toContainText( /Gemini/i );

		// Per-article Standard overrides the global preference for this run.
		await panel( page, 1 ).locator( '[data-article-length-mode]' ).selectOption( EXPECTED_LENGTH_MODE );
		await expect( panel( page, 1 ).locator( '[data-article-length-mode]' ) ).toHaveValue( EXPECTED_LENGTH_MODE );
		await panel( page, 1 ).locator( wizard.ideaInput ).fill( TOPIC );
		await panel( page, 1 ).locator( wizard.generateButton ).first().click();
		await selectSingleAndContinue( page, 1 );

		// Select four real provider suggestions. The user remains free to
		// continue, while the quality warning recommends a tighter 1–3 set.
		await waitReady( page, 2 );
		const keywordPanel = panel( page, 2 );
		await selectFirstN( keywordPanel, EXPECTED_KEYWORD_COUNT );
		const selectedKeywordCards = keywordPanel.locator( wizard.resultCardSelected );
		await expect( selectedKeywordCards ).toHaveCount( EXPECTED_KEYWORD_COUNT );
		const selectedKeywords = await selectedKeywordCards.evaluateAll( ( cards ) =>
			cards.map( ( card ) => ( card as HTMLElement ).dataset.keyword || '' ).filter( Boolean )
		);
		expect( new Set( selectedKeywords.map( ( keyword ) => keyword.toLocaleLowerCase() ) ).size )
			.toBe( EXPECTED_KEYWORD_COUNT );
		const keywordWarning = keywordPanel.locator( '[data-testid="keyword-load-warning"]' );
		await expect( keywordWarning ).toBeVisible();
		await expect( keywordWarning ).toContainText( '1 primary keyword and 1–2 secondary keywords' );
		await expect( keywordPanel.locator( wizard.continueButton ).first() ).toBeEnabled();
		await keywordPanel.locator( wizard.continueButton ).first().click();

		// Use five unique selected sections, then require the body to preserve
		// each heading exactly and in order rather than silently omitting one.
		await waitReady( page, 3 );
		const outlinePanel = panel( page, 3 );
		await selectFirstN( outlinePanel, EXPECTED_OUTLINE_COUNT );
		const selectedOutlineCards = outlinePanel.locator( wizard.resultCardSelected );
		await expect( selectedOutlineCards ).toHaveCount( EXPECTED_OUTLINE_COUNT );
		const selectedOutline = await selectedOutlineCards.locator( '.option-text' ).allTextContents();
		expect( new Set( selectedOutline.map( ( heading ) => normalise( heading ).toLocaleLowerCase() ) ).size )
			.toBe( EXPECTED_OUTLINE_COUNT );
		await outlinePanel.locator( wizard.continueButton ).first().click();

		await continueReadyStep( page, 4 );
		await selectSingleAndContinue( page, 5 );

		// The body quality gate may make up to two bounded corrective calls.
		// It must not expose a short draft as ready after that gate.
		await waitReady( page, 6, 360_000 );
		const body = panel( page, 6 ).locator( wizard.editor ).first();
		const bodyText = await body.textContent();
		expect( wordCount( bodyText ) ).toBeGreaterThanOrEqual( EXPECTED_BODY_MIN );
		const bodyHeadings = ( await body.locator( 'h2' ).allTextContents() ).map( normalise );
		expect( bodyHeadings ).toEqual( selectedOutline.map( normalise ) );
		await expect( body.locator( 'p:empty, p:has(br:only-child)' ) ).toHaveCount( 0 );

		// Entering Body starts one real Gemini image request. A usable stored
		// attachment and featured designation are required before continuing.
		const imageStatus = panel( page, 6 ).locator( '[data-testid="image-generation-status"]' );
		await expect( imageStatus ).toBeVisible( { timeout: 30_000 } );
		const galleryImage = panel( page, 6 ).locator( '[data-testid="gallery-image"]' ).first();
		await expect( galleryImage ).toBeVisible( { timeout: 300_000 } );
		const featuredUrl = await galleryImage.getAttribute( 'src' );
		expect( featuredUrl ).toBeTruthy();
		await expect( panel( page, 6 ).locator( '[data-testid="featured-image-badge"]' ) ).toBeVisible();
		await expect( imageStatus ).toHaveAttribute( 'aria-busy', 'false', { timeout: 30_000 } );
		await continueReadyStep( page, 6 );
		await continueReadyStep( page, 7 );
		await selectSingleAndContinue( page, 8 );

		// Deliberately make both fields too long, then exercise the optional
		// real-model shortening action, Apply, Undo and final accepted values.
		await waitReady( page, 9 );
		const primaryKeyword = selectedKeywords[ 0 ];
		const longTitle = `${ primaryKeyword } | Practical audit steps, accessibility, conversion and website quality for small businesses in 2026`;
		const longDescription = `${ primaryKeyword } explained through a practical accessibility and conversion audit, with prioritised checks, implementation guidance, common pitfalls and next actions for small-business websites in 2026.`;
		await page.locator( wizard.metaTitle ).fill( longTitle );
		await page.locator( wizard.metaDescription ).fill( longDescription );
		expect( longTitle.length ).toBeGreaterThan( 60 );
		expect( longDescription.length ).toBeGreaterThan( 160 );
		const optimisePanel = panel( page, 9 ).locator( '[data-testid="meta-optimise-panel"]' );
		await expect( optimisePanel ).toBeVisible();
		const optimisationResponsePromise = page.waitForResponse( ( response ) => {
			const request = response.request();
			return response.url().includes( 'admin-ajax.php' )
				&& ( request.postData() || '' ).includes( 'ai_scribe_optimise_meta' );
		} );
		await optimisePanel.locator( '[data-testid="optimise-meta-length"]' ).click();
		const optimisationResponse = await optimisationResponsePromise;
		expect( optimisationResponse.ok() ).toBe( true );
		const optimisationPayload = await optimisationResponse.json();
		expect( optimisationPayload.success ).toBe( true );
		const secondaryCoverage = optimisationPayload.data.secondary_coverage as MetaCoverage[];
		expect( secondaryCoverage ).toHaveLength( selectedKeywords.length - 1 );
		secondaryCoverage.forEach( ( coverage, index ) => {
			expect( normalise( coverage.keyword ).toLocaleLowerCase() )
				.toBe( normalise( selectedKeywords[ index + 1 ] ).toLocaleLowerCase() );
			expect( [ 'exact', 'combined', 'partial', 'absent' ] ).toContain( coverage.title );
			expect( [ 'exact', 'combined', 'partial', 'absent' ] ).toContain( coverage.description );
			expect(
				coverage.title !== 'absent' || coverage.description !== 'absent',
				`Secondary keyword "${ coverage.keyword }" was absent from both optimised fields.`
			).toBe( true );
		} );
		const comparison = optimisePanel.locator( '[data-testid="meta-optimise-comparison"]' );
		await expect( comparison ).toBeVisible( { timeout: 180_000 } );
		const suggestedTitle = normalise( await comparison.locator( '[data-testid="meta-suggested-title"]' ).textContent() );
		const suggestedDescription = normalise( await comparison.locator( '[data-testid="meta-suggested-description"]' ).textContent() );
		expect( suggestedTitle.length ).toBeGreaterThanOrEqual( 50 );
		expect( suggestedTitle.length ).toBeLessThanOrEqual( 60 );
		expect( suggestedDescription.length ).toBeGreaterThanOrEqual( 120 );
		expect( suggestedDescription.length ).toBeLessThanOrEqual( 160 );
		expect( suggestedTitle.match( /\s\|\s/g ) || [] ).toHaveLength( 1 );
		expect( suggestedTitle.match( /\|/g ) || [] ).toHaveLength( 1 );
		expect( suggestedTitle ).not.toMatch( /(?:\s[-\u2013\u2014]\s|[:/])/u );
		expect( suggestedTitle.toLocaleLowerCase() ).toContain( primaryKeyword.toLocaleLowerCase() );
		expect( suggestedDescription.toLocaleLowerCase() ).toContain( primaryKeyword.toLocaleLowerCase() );

		await comparison.locator( '[data-action="apply-meta-optimisation"]' ).click();
		await expect( page.locator( wizard.metaTitle ) ).toHaveValue( suggestedTitle );
		await expect( optimisePanel.locator( '[data-action="undo-meta-optimisation"]' ) ).toBeVisible();
		await optimisePanel.locator( '[data-action="undo-meta-optimisation"]' ).click();
		await expect( page.locator( wizard.metaTitle ) ).toHaveValue( longTitle );
		// Restore the already-reviewed suggestion without paying for a second call.
		await page.locator( wizard.metaTitle ).fill( suggestedTitle );
		await page.locator( wizard.metaDescription ).fill( suggestedDescription );
		await expect( panel( page, 9 ).locator( '[data-testid="meta-optimise-panel"]' ) ).toBeHidden();
		const keywordCoverageRows = panel( page, 9 ).locator( '[data-testid="meta-keyword-guidance"] li' );
		await expect( keywordCoverageRows ).toHaveCount( selectedKeywords.length );
		await expect( keywordCoverageRows.first() ).toContainText( 'title exact; description exact' );
		for ( let index = 0; index < secondaryCoverage.length; index += 1 ) {
			const coverage = secondaryCoverage[ index ];
			await expect( keywordCoverageRows.nth( index + 1 ) ).toContainText( coverage.keyword );
			await expect( keywordCoverageRows.nth( index + 1 ) )
				.toContainText( `title ${ coverage.title }; description ${ coverage.description }` );
		}
		await snap( page, testInfo, 'real-quality-metadata' );
		await panel( page, 9 ).locator( wizard.continueButton ).first().click();

		// Featured media is visible for review but absent from article HTML, so
		// the theme can render the WordPress thumbnail exactly once.
		const review = page.locator( '#review-quill-editor .ql-editor' );
		await expect( review ).not.toBeEmpty();
		const featuredPreview = panel( page, 10 ).locator( '[data-testid="featured-image-preview"]' );
		await expect( featuredPreview ).toBeVisible();
		const previewImage = featuredPreview.locator( 'img' );
		await expect( previewImage ).toHaveAttribute( 'src', featuredUrl! );
		await expect( previewImage ).toHaveAttribute( 'width', /\d+/ );
		await expect( previewImage ).toHaveAttribute( 'height', /\d+/ );
		await expect( review.locator( `img[src="${ featuredUrl }"]` ) ).toHaveCount( 0 );
		await expect( panel( page, 10 ).locator( '[data-testid="uncategorized-warning"]' ) ).toBeVisible();
		await expect( panel( page, 10 ).locator( '[data-testid="generic-author-warning"]' ) ).toBeVisible();
		await snap( page, testInfo, 'real-quality-review' );

		await page.locator( wizard.publishDraftButton ).click();
		const savedLink = page.locator( wizard.savedPostLink );
		await expect( savedLink ).toBeVisible( { timeout: 30_000 } );
		const href = await savedLink.getAttribute( 'href' );
		const postId = new URL( href!, 'http://localhost' ).searchParams.get( 'post' );
		expect( postId ).toMatch( /^\d+$/ );
		expect( wpCli( `wp post get ${ postId } --field=post_status` ) ).toBe( 'draft' );

		const savedContent = wpCli( `wp post get ${ postId } --field=post_content` );
		const savedSlug = wpCli( `wp post get ${ postId } --field=post_name` );
		const savedExcerpt = wpCli( `wp post get ${ postId } --field=post_excerpt` );
		expect( savedContent ).not.toContain( featuredUrl! );
		expect( savedContent ).not.toMatch( /<h1\b/i );
		expect( savedContent ).not.toMatch( /<p\b[^>]*>(?:\s|&nbsp;|&#160;|<br\s*\/?\s*>)*<\/p>/i );
		expect( savedSlug ).toMatch( /^[a-z0-9]+(?:-[a-z0-9]+)*$/ );
		expect( savedSlug.length ).toBeLessThanOrEqual( 75 );
		expect( savedExcerpt.length ).toBeGreaterThanOrEqual( 80 );
		expect( savedExcerpt ).not.toMatch( /<[^>]+>/ );
		expect( wpCli( `wp post meta get ${ postId } _ai_scribe_meta_title` ) ).toBe( suggestedTitle );
		expect( wpCli( `wp post meta get ${ postId } _ai_scribe_meta_description` ) ).toBe( suggestedDescription );

		const thumbnailId = wpCli( `wp post meta get ${ postId } _thumbnail_id` );
		expect( thumbnailId ).toMatch( /^\d+$/ );
		const alt = wpCli( `wp post meta get ${ thumbnailId } _wp_attachment_image_alt` );
		expect( alt.length ).toBeGreaterThanOrEqual( 3 );
		expect( alt.length ).toBeLessThanOrEqual( 160 );
		expect( alt ).not.toMatch( /\b(prompt|generate|create an image|instruction)\b/i );
		const dimensions = JSON.parse( wpCli(
			`wp eval '$m=wp_get_attachment_metadata(${ thumbnailId }); echo wp_json_encode(array("width"=>(int)($m["width"]??0),"height"=>(int)($m["height"]??0)));'`
		) );
		expect( dimensions.width ).toBeGreaterThan( 0 );
		expect( dimensions.height ).toBeGreaterThan( 0 );

		// Evaluate measures link destinations from final HTML. Absent contextual
		// links receive an actionable Check, never an invented Pass.
		await panel( page, 10 ).locator( wizard.continueButton ).click();
		await waitReady( page, 11, 240_000 );
		const report = page.locator( wizard.evaluationOutput );
		await expect( report.locator( 'table' ) ).toBeVisible();
		const wordsFact = report.locator( '.evaluation-facts > div' ).filter( { hasText: 'Words' } ).locator( 'dd' );
		expect( Number( await wordsFact.textContent() ) ).toBeGreaterThanOrEqual( 1_500 );
		for ( const label of [ 'Internal contextual links', 'External contextual links' ] ) {
			const row = report.locator( '.eval-row' ).filter( { hasText: label } );
			await expect( row ).toContainText( 'Check' );
			await expect( row.locator( '.eval-action-cell' ) ).not.toBeEmpty();
		}
		await expect( report ).not.toContainText( '[object Object]' );
		await snap( page, testInfo, 'real-quality-evaluate' );

		expect( ajax.failures, ajax.failures.join( '\n' ) ).toHaveLength( 0 );
		assertNoConsoleErrors( errors );
	} );
} );
