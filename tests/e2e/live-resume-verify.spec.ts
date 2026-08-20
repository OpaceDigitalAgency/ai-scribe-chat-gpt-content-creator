import { test, expect } from '@playwright/test';
import { assertNoConsoleErrors, snap, watchAjaxResponses, watchConsole, wpLogin } from './helpers';
import { wizard } from './selectors';

test.skip( process.env.AI_SCRIBE_LIVE_E2E !== '1', 'Requires the isolated real-provider conversation fixture.' );

test.describe( 'Real-provider saved conversation verification', () => {
	test( 'explicit resume restores the saved article and Evaluate remains factual', async ( { page }, testInfo ) => {
		const errors = watchConsole( page );
		const ajax = watchAjaxResponses( page );
		await wpLogin( page );

		await page.addInitScript( ( conversationId ) => {
			sessionStorage.setItem( 'aiScribeTabId', 'live-corrective-resume' );
			localStorage.setItem( 'aiScribeState:live-corrective-resume', JSON.stringify( {
				savedAt: Date.now(),
				state: {
					conversationId,
					currentStep: 11,
					workflowMode: 'wizard',
					stepData: [],
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
		}, 40 );

		await page.goto( wizard.pageUrl );
		await expect( page.locator( wizard.panel( 1 ) ) ).toBeVisible();
		await expect( page.locator( '[data-testid="resume-draft-notice"]' ) ).toBeVisible();
		await page.evaluate( () => window.aiScribeApp.resumeSavedDraft() );

		await expect( page.locator( wizard.panel( 11 ) ) ).toBeVisible( { timeout: 30_000 } );
		await expect( page.locator( wizard.evaluationOutput ).locator( 'table' ) ).toBeVisible();
		const report = page.locator( wizard.evaluationOutput );
		await expect( report ).toContainText( 'Table of contents and anchor links' );
		await expect( report ).toContainText( 'Internal contextual links' );
		await expect( report ).toContainText( 'External contextual links' );
		await expect( report.locator( '.eval-row' ).filter( { hasText: 'Internal contextual links' } ) ).toContainText( 'Pass' );
		await expect( report.locator( '.eval-row' ).filter( { hasText: 'External contextual links' } ) ).toContainText( 'Pass' );
		await expect( page.locator( '[data-save-context="evaluate"]' ) ).toContainText( /saved|current/i );
		await snap( page, testInfo, 'real-gemini-resumed-evaluate' );

		expect( ajax.failures, ajax.failures.join( '\n' ) ).toHaveLength( 0 );
		assertNoConsoleErrors( errors );
	} );
} );
