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
	'Requires an explicitly authorised isolated real-provider continuation.'
);

const RESUME_CONVERSATION_ID = Number( process.env.AI_SCRIBE_RESUME_CONVERSATION_ID || 0 );
const EXPECTED_PLUGIN_VERSION = '3.2.12';
const EXPECTED_BODY_MIN = 1_101;
const ACCEPTED_PLUGIN_SLUGS = [
	'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard',
	'ai-scribe-v3',
];

type PluginRow = { name: string; status: string; version: string };
type AttachmentRow = { ID: number; post_date: string };
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

function savedPostId( href: string | null, conversationId: number ): string {
	const url = new URL( href || '', 'http://localhost' );
	const linked = url.searchParams.get( 'post' ) || url.searchParams.get( 'p' )
		|| url.pathname.match( /\/(?:post|p)\/(\d+)\/?$/ )?.[ 1 ];
	if ( linked && /^\d+$/.test( linked ) ) return linked;
	return wpCli(
		`wp db query "SELECT JSON_UNQUOTE(JSON_EXTRACT(settings, '$.post_id')) FROM wp_ai_scribe_conversations WHERE id=${ conversationId }" --skip-column-names`
	);
}

async function waitReady( page: Page, step: number, timeout = 240_000 ): Promise<void> {
	await expect( panel( page, step ) ).toHaveAttribute( 'data-state', 'ready', { timeout } );
}

test.describe( 'Real Gemini quality acceptance continuation', () => {
	test.describe.configure( { timeout: 1_200_000 } );

	test( 'resume Q&A → metadata optimiser → clean draft → Evaluate', async ( { page }, testInfo ) => {
		expect( RESUME_CONVERSATION_ID, 'Set AI_SCRIBE_RESUME_CONVERSATION_ID to the persisted real-provider conversation.' ).toBeGreaterThan( 0 );
		const errors = watchConsole( page );
		const ajax = watchAjaxResponses( page );

		const mockState = wpCli(
			`wp eval 'echo (defined("AI_SCRIBE_MOCK") && AI_SCRIBE_MOCK) ? "on" : "off"; echo (defined("AI_SCRIBE_AUTOMATED_TEST") && AI_SCRIBE_AUTOMATED_TEST) ? "on" : "off";'`
		);
		expect( mockState ).toBe( 'offoff' );
		const plugins = JSON.parse( wpCli(
			'wp plugin list --fields=name,status,version --format=json'
		) ) as PluginRow[];
		const activeScribe = plugins.filter( ( plugin ) =>
			ACCEPTED_PLUGIN_SLUGS.includes( plugin.name ) && plugin.status === 'active'
		);
		expect( activeScribe ).toHaveLength( 1 );
		expect( activeScribe[ 0 ].version ).toBe( EXPECTED_PLUGIN_VERSION );

		const conversationRow = wpCli(
			`wp db query "SELECT CONCAT(status, '|', mode, '|', user_id, '|', created_at, '|', JSON_LENGTH(JSON_EXTRACT(steps, '$.8'))) FROM wp_ai_scribe_conversations WHERE id=${ RESUME_CONVERSATION_ID }" --skip-column-names`
		).split( '|' );
		expect( conversationRow[ 0 ] ).toBe( 'active' );
		expect( conversationRow[ 1 ] ).toBe( 'wizard' );
		expect( Number( conversationRow[ 2 ] ) ).toBeGreaterThan( 0 );
		expect( Number( conversationRow[ 4 ] ) ).toBeGreaterThan( 0 );
		const conversationCreated = conversationRow[ 3 ];

		// Restore the already-generated featured attachment without generating
		// another image. It must post-date the conversation and remain separate
		// from article HTML.
		const attachments = JSON.parse( wpCli(
			'wp post list --post_type=attachment --post_mime_type=image --orderby=ID --order=DESC --posts_per_page=20 --fields=ID,post_date --format=json'
		) ) as AttachmentRow[];
		const attachment = attachments.find( ( item ) => item.post_date >= conversationCreated );
		expect( attachment, 'No image attachment belongs to the resumed live conversation.' ).toBeTruthy();
		const attachmentId = Number( attachment!.ID );
		const featuredUrl = wpCli( `wp eval 'echo wp_get_attachment_url(${ attachmentId });'` );
		const alt = wpCli( `wp post meta get ${ attachmentId } _wp_attachment_image_alt` );
		const metadata = JSON.parse( wpCli( `wp post meta get ${ attachmentId } _wp_attachment_metadata --format=json` ) );
		expect( featuredUrl ).toMatch( /^http:\/\/localhost:8890\/wp-content\/uploads\// );
		expect( Number( metadata.width ) ).toBeGreaterThan( 0 );
		expect( Number( metadata.height ) ).toBeGreaterThan( 0 );

		await wpLogin( page );
		await page.addInitScript( ( fixture ) => {
			sessionStorage.setItem( 'aiScribeTabId', 'live-quality-resume' );
			localStorage.setItem( 'aiScribeState:live-quality-resume', JSON.stringify( {
				savedAt: Date.now(),
				state: {
					conversationId: fixture.conversationId,
					currentStep: 8,
					workflowMode: 'wizard',
					stepData: [],
					galleryImages: [ fixture.image ],
					featuredImageRemoved: false,
					featuredImageAutoStarted: true,
					persistence: { post: null, shortcode: null },
					settings: {
						apiKeys: {},
						contentSettings: { tone: 'professional', length: 'medium' },
						preferences: { autoSave: true, costAlerts: true }
					},
					ui: { isLoading: false, activeModal: null, errors: [], notifications: [] },
					cost: { currentStepCost: 0, totalCost: 0, tokenEstimate: 0, selectedModel: 'gemini-3.6-flash' }
				}
			} ) );
		}, {
			conversationId: RESUME_CONVERSATION_ID,
			image: {
				url: featuredUrl,
				attachment_id: attachmentId,
				alt_text: alt,
				caption: '',
				width: Number( metadata.width ),
				height: Number( metadata.height ),
				prompt_used: '',
				prompt_draft: '',
				source_section: 'Article introduction',
				image_options: {},
				status: 'ready',
			}
		} );

		await page.goto( wizard.pageUrl );
		await expect( page.locator( '[data-testid="resume-draft-notice"]' ) ).toBeVisible();
		await page.locator( '[data-testid="resume-draft"]' ).click();
		await waitReady( page, 8, 30_000 );

		// The previous full run timed out while a generic card helper searched
		// for .option-text. Q&A uses editable inputs, so select it through its
		// actual checkbox contract and continue without regenerating Step 8.
		const qnaCards = panel( page, 8 ).locator( '[data-testid="result-card"]' );
		await expect( qnaCards.first() ).toBeVisible();
		const selectAll = panel( page, 8 ).locator( '[data-testid="qa-select-all"]' );
		if ( await panel( page, 8 ).locator( '[data-testid="qa-item-include"]:checked' ).count() === 0 ) {
			await selectAll.check();
		}
		expect( await panel( page, 8 ).locator( '[data-testid="qa-item-include"]:checked' ).count() ).toBeGreaterThan( 0 );
		await panel( page, 8 ).locator( wizard.continueButton ).first().click();

		await waitReady( page, 9 );
		const selectedKeywords = await panel( page, 2 ).locator( wizard.resultCardSelected ).evaluateAll( ( cards ) =>
			cards.map( ( card ) => ( card as HTMLElement ).dataset.keyword || '' ).filter( Boolean )
		);
		expect( selectedKeywords ).toHaveLength( 4 );
		const primaryKeyword = selectedKeywords[ 0 ];
		const longTitle = `${ primaryKeyword } | Practical audit steps, accessibility, conversion and website quality for small businesses in 2026`;
		const longDescription = `${ primaryKeyword } explained through a practical accessibility and conversion audit, with prioritised checks, implementation guidance, common pitfalls and next actions for small-business websites in 2026.`;
		await page.locator( wizard.metaTitle ).fill( longTitle );
		await page.locator( wizard.metaDescription ).fill( longDescription );
		const optimisePanel = panel( page, 9 ).locator( '[data-testid="meta-optimise-panel"]' );
		await expect( optimisePanel ).toBeVisible();
		const optimisationResponsePromise = page.waitForResponse( ( response ) =>
			response.url().includes( 'admin-ajax.php' )
				&& ( response.request().postData() || '' ).includes( 'ai_scribe_optimise_meta' )
		);
		await optimisePanel.locator( '[data-testid="optimise-meta-length"]' ).click();
		const optimisationResponse = await optimisationResponsePromise;
		expect( optimisationResponse.ok() ).toBe( true );
		const optimisationPayload = await optimisationResponse.json();
		expect( optimisationPayload.success ).toBe( true );
		const secondaryCoverage = optimisationPayload.data.secondary_coverage as MetaCoverage[];
		expect( secondaryCoverage ).toHaveLength( selectedKeywords.length - 1 );
		secondaryCoverage.forEach( ( coverage ) => {
			expect( coverage.title !== 'absent' || coverage.description !== 'absent' ).toBe( true );
		} );
		const comparison = optimisePanel.locator( '[data-testid="meta-optimise-comparison"]' );
		await expect( comparison ).toBeVisible( { timeout: 240_000 } );
		const suggestedTitle = normalise( await comparison.locator( '[data-testid="meta-suggested-title"]' ).textContent() );
		const suggestedDescription = normalise( await comparison.locator( '[data-testid="meta-suggested-description"]' ).textContent() );
		expect( suggestedTitle.length ).toBeGreaterThanOrEqual( 50 );
		expect( suggestedTitle.length ).toBeLessThanOrEqual( 60 );
		expect( suggestedDescription.length ).toBeGreaterThanOrEqual( 120 );
		expect( suggestedDescription.length ).toBeLessThanOrEqual( 160 );
		expect( suggestedTitle.match( /\s\|\s/g ) || [] ).toHaveLength( 1 );
		expect( suggestedTitle.toLocaleLowerCase() ).toContain( primaryKeyword.toLocaleLowerCase() );
		expect( suggestedDescription.toLocaleLowerCase() ).toContain( primaryKeyword.toLocaleLowerCase() );
		await comparison.locator( '[data-action="apply-meta-optimisation"]' ).click();
		await expect( page.locator( wizard.metaTitle ) ).toHaveValue( suggestedTitle );
		const undoOptimisation = optimisePanel.locator( '[data-action="undo-meta-optimisation"]' );
		await expect( optimisePanel ).toBeVisible( { timeout: 15_000 } );
		await expect( undoOptimisation ).toBeVisible( { timeout: 15_000 } );
		await undoOptimisation.click();
		await expect( page.locator( wizard.metaTitle ) ).toHaveValue( longTitle );
		await page.locator( wizard.metaTitle ).fill( suggestedTitle );
		await page.locator( wizard.metaDescription ).fill( suggestedDescription );
		await snap( page, testInfo, 'real-quality-resume-metadata' );
		await panel( page, 9 ).locator( wizard.continueButton ).first().click();

		const review = page.locator( '#review-quill-editor .ql-editor' );
		await expect( review ).not.toBeEmpty();
		expect( wordCount( await panel( page, 6 ).locator( wizard.editor ).first().textContent() ) ).toBeGreaterThanOrEqual( EXPECTED_BODY_MIN );
		const featuredPreview = panel( page, 10 ).locator( '[data-testid="featured-image-preview"]' );
		await expect( featuredPreview ).toBeVisible();
		await expect( featuredPreview.locator( 'img' ) ).toHaveAttribute( 'src', featuredUrl );
		await expect( featuredPreview.locator( 'img' ) ).toHaveAttribute( 'width', String( metadata.width ) );
		await expect( featuredPreview.locator( 'img' ) ).toHaveAttribute( 'height', String( metadata.height ) );
		await expect( review.locator( `img[src="${ featuredUrl }"]` ) ).toHaveCount( 0 );
		await expect( panel( page, 10 ).locator( '[data-testid="uncategorized-warning"]' ) ).toBeVisible();
		await expect( panel( page, 10 ).locator( '[data-testid="generic-author-warning"]' ) ).toBeVisible();
		await snap( page, testInfo, 'real-quality-resume-review' );

		await page.locator( wizard.publishDraftButton ).click();
		const savedLink = page.locator( wizard.savedPostLink );
		await expect( savedLink ).toBeVisible( { timeout: 30_000 } );
		const href = await savedLink.getAttribute( 'href' );
		const postId = savedPostId( href, RESUME_CONVERSATION_ID );
		expect( postId ).toMatch( /^\d+$/ );
		expect( wpCli( `wp post get ${ postId } --field=post_status` ) ).toBe( 'draft' );
		const savedContent = wpCli( `wp post get ${ postId } --field=post_content` );
		expect( savedContent ).not.toContain( featuredUrl );
		expect( savedContent ).not.toMatch( /<h1\b/i );
		expect( wpCli( `wp post meta get ${ postId } _thumbnail_id` ) ).toBe( String( attachmentId ) );
		expect( wpCli( `wp post meta get ${ postId } _ai_scribe_meta_title` ) ).toBe( suggestedTitle );
		expect( wpCli( `wp post meta get ${ postId } _ai_scribe_meta_description` ) ).toBe( suggestedDescription );

		await panel( page, 10 ).locator( wizard.continueButton ).click();
		await waitReady( page, 11, 300_000 );
		const report = page.locator( wizard.evaluationOutput );
		await expect( report.locator( 'table' ) ).toBeVisible();
		const wordsFact = report.locator( '.evaluation-facts > div' ).filter( { hasText: 'Words' } ).locator( 'dd' );
		expect( Number( await wordsFact.textContent() ) ).toBeGreaterThanOrEqual( 1_500 );
		for ( const label of [ 'Internal contextual links', 'External contextual links' ] ) {
			const row = report.locator( '.eval-row' ).filter( { hasText: label } );
			await expect( row ).toContainText( 'Check' );
			await expect( row.locator( '.eval-action-cell' ) ).not.toBeEmpty();
		}
		await snap( page, testInfo, 'real-quality-resume-evaluate' );
		expect( ajax.failures, ajax.failures.join( '\n' ) ).toHaveLength( 0 );
		assertNoConsoleErrors( errors );
	} );
} );
