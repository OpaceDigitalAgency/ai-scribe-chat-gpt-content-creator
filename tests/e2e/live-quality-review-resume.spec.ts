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

const CONVERSATION_ID = Number( process.env.AI_SCRIBE_RESUME_CONVERSATION_ID || 0 );
const EXPECTED_POST_ID = Number( process.env.AI_SCRIBE_EXPECTED_POST_ID || 22 );
const EXPECTED_PLUGIN_VERSION = '3.2.12';
const ACCEPTED_PLUGIN_SLUGS = [
	'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard',
	'ai-scribe-v3',
];

type PluginRow = { name: string; status: string; version: string };

function panel( page: Page, step: number ): Locator {
	return page.locator( wizard.panel( step ) );
}

function postIdFromLink( href: string | null ): string {
	const url = new URL( href || '', 'http://localhost' );
	return url.searchParams.get( 'post' ) || url.searchParams.get( 'p' )
		|| url.pathname.match( /\/(?:post|p)\/(\d+)\/?$/ )?.[ 1 ] || '';
}

test.describe( 'Real Gemini saved Review continuation', () => {
	test.describe.configure( { timeout: 420_000 } );

	test( 'resume confirmed draft at Review → Evaluate', async ( { page }, testInfo ) => {
		expect( CONVERSATION_ID ).toBeGreaterThan( 0 );
		const errors = watchConsole( page );
		const ajax = watchAjaxResponses( page );
		expect( wpCli(
			`wp eval 'echo (defined("AI_SCRIBE_MOCK") && AI_SCRIBE_MOCK) ? "on" : "off"; echo (defined("AI_SCRIBE_AUTOMATED_TEST") && AI_SCRIBE_AUTOMATED_TEST) ? "on" : "off";'`
		) ).toBe( 'offoff' );
		const plugins = JSON.parse( wpCli(
			'wp plugin list --fields=name,status,version --format=json'
		) ) as PluginRow[];
		const activeScribe = plugins.filter( ( plugin ) =>
			ACCEPTED_PLUGIN_SLUGS.includes( plugin.name ) && plugin.status === 'active'
		);
		expect( activeScribe ).toHaveLength( 1 );
		expect( activeScribe[ 0 ].version ).toBe( EXPECTED_PLUGIN_VERSION );

		const row = wpCli(
			`wp db query "SELECT CONCAT(status, '|', JSON_UNQUOTE(JSON_EXTRACT(settings, '$.post_id')), '|', JSON_UNQUOTE(JSON_EXTRACT(settings, '$.post_status')), '|', JSON_LENGTH(JSON_EXTRACT(steps, '$.9'))) FROM wp_ai_scribe_conversations WHERE id=${ CONVERSATION_ID }" --skip-column-names`
		).split( '|' );
		expect( row[ 0 ] ).toBe( 'complete' );
		expect( Number( row[ 1 ] ) ).toBe( EXPECTED_POST_ID );
		expect( row[ 2 ] ).toBe( 'draft' );
		expect( Number( row[ 3 ] ) ).toBeGreaterThan( 0 );
		const postId = row[ 1 ];
		expect( wpCli( `wp post get ${ postId } --field=post_status` ) ).toBe( 'draft' );
		const attachmentId = Number( wpCli( `wp post meta get ${ postId } _thumbnail_id` ) );
		expect( attachmentId ).toBeGreaterThan( 0 );
		const featuredUrl = wpCli( `wp eval 'echo wp_get_attachment_url(${ attachmentId });'` );
		const alt = wpCli( `wp post meta get ${ attachmentId } _wp_attachment_image_alt` );
		const metadata = JSON.parse( wpCli( `wp post meta get ${ attachmentId } _wp_attachment_metadata --format=json` ) );

		await wpLogin( page );
		await page.addInitScript( ( fixture ) => {
			sessionStorage.setItem( 'aiScribeTabId', 'live-quality-review-resume' );
			localStorage.setItem( 'aiScribeState:live-quality-review-resume', JSON.stringify( {
				savedAt: Date.now(),
				state: {
					conversationId: fixture.conversationId,
					currentStep: 10,
					workflowMode: 'wizard',
					stepData: [],
					galleryImages: [ fixture.image ],
					featuredImageRemoved: false,
					featuredImageAutoStarted: true,
					persistence: { post: null, shortcode: null },
					settings: { apiKeys: {}, contentSettings: {}, preferences: { autoSave: true } },
					ui: { isLoading: false, activeModal: null, errors: [], notifications: [] },
					cost: { currentStepCost: 0, totalCost: 0, tokenEstimate: 0, selectedModel: 'gemini-3.6-flash' }
				}
			} ) );
		}, {
			conversationId: CONVERSATION_ID,
			image: {
				url: featuredUrl,
				attachment_id: attachmentId,
				alt_text: alt,
				caption: '',
				width: Number( metadata.width ),
				height: Number( metadata.height ),
				status: 'ready',
			}
		} );

		await page.goto( wizard.pageUrl );
		await expect( page.locator( '[data-testid="resume-draft-notice"]' ) ).toBeVisible();
		await page.locator( '[data-testid="resume-draft"]' ).click();
		await expect( panel( page, 10 ) ).toBeVisible( { timeout: 30_000 } );
		const review = page.locator( '#review-quill-editor .ql-editor' );
		await expect( review ).not.toBeEmpty();
		const preview = panel( page, 10 ).locator( '[data-testid="featured-image-preview"]' );
		await expect( preview ).toBeVisible();
		await expect( preview.locator( 'img' ) ).toHaveAttribute( 'src', featuredUrl );
		await expect( review.locator( `img[src="${ featuredUrl }"]` ) ).toHaveCount( 0 );
		const savedLink = page.locator( wizard.savedPostLink );
		await expect( savedLink ).toBeVisible();
		const href = await savedLink.getAttribute( 'href' );
		const linkedPostId = postIdFromLink( href );
		if ( linkedPostId ) expect( Number( linkedPostId ) ).toBe( EXPECTED_POST_ID );
		expect( wpCli(
			`wp db query "SELECT JSON_UNQUOTE(JSON_EXTRACT(settings, '$.post_id')) FROM wp_ai_scribe_conversations WHERE id=${ CONVERSATION_ID }" --skip-column-names`
		) ).toBe( String( EXPECTED_POST_ID ) );
		await snap( page, testInfo, 'real-quality-saved-review-resume' );

		// This is the only provider call in this continuation. Steps 8, 9 and the
		// metadata optimiser are already persisted and are not regenerated.
		await panel( page, 10 ).locator( wizard.continueButton ).click();
		await expect( panel( page, 11 ) ).toHaveAttribute( 'data-state', 'ready', { timeout: 300_000 } );
		const report = page.locator( wizard.evaluationOutput );
		await expect( report.locator( 'table' ) ).toBeVisible();
		const words = Number( await report.locator( '.evaluation-facts > div' ).filter( { hasText: 'Words' } ).locator( 'dd' ).textContent() );
		expect( words ).toBeGreaterThanOrEqual( 1_500 );
		for ( const label of [ 'Internal contextual links', 'External contextual links' ] ) {
			const check = report.locator( '.eval-row' ).filter( { hasText: label } );
			await expect( check ).toContainText( 'Check' );
			await expect( check.locator( '.eval-action-cell' ) ).not.toBeEmpty();
		}
		await snap( page, testInfo, 'real-quality-saved-review-evaluate' );
		expect( ajax.failures, ajax.failures.join( '\n' ) ).toHaveLength( 0 );
		assertNoConsoleErrors( errors );
	} );
} );
