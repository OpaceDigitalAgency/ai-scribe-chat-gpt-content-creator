import { expect, test, type Page } from '@playwright/test';
import path from 'path';

const servicePath = path.resolve( 'assets/js/services/NotificationCenter.js' );
const stylePath = path.resolve( 'assets/css/notification-center.css' );
const tokenStylePath = path.resolve( 'assets/css/main.css' );
const settingsViewPath = path.resolve( 'assets/js/views/SettingsView.js' );

async function notificationFixture( page: Page ) {
	await page.setContent( '<main style="height:1800px;overflow:auto"><button id="action">Save</button></main>' );
	await page.evaluate( () => {
		delete window.aiScribeNotifications;
	} );
	await page.addStyleTag( { path: tokenStylePath } );
	await page.addStyleTag( { path: stylePath } );
	await page.addScriptTag( { path: servicePath } );
}

test.describe( 'shared notification centre', () => {
	test( 'is fixed to the visible viewport and not a nested scroller', async ( { page } ) => {
		await notificationFixture( page );
		await page.evaluate( () => window.aiScribeNotifications.show( {
			title: 'Draft saved',
			message: 'The draft is ready to open in a new tab.',
			type: 'success'
		} ) );
		const centre = page.locator( '#ai-scribe-notification-centre' );
		await expect( centre ).toBeVisible();
		expect( await centre.evaluate( ( el ) => getComputedStyle( el ).position ) ).toBe( 'fixed' );
		expect( await centre.evaluate( ( el ) => el.parentElement === document.body ) ).toBeTruthy();
		const before = await centre.boundingBox();
		await page.evaluate( () => window.scrollTo( 0, 900 ) );
		const after = await centre.boundingBox();
		expect( Math.abs( ( before?.y || 0 ) - ( after?.y || 0 ) ) ).toBeLessThan( 1 );
		expect( after?.width ).toBeLessThanOrEqual( 680 );
		expect( after?.width ).toBeGreaterThan( 300 );
	} );

	test( 'stays readable at 375, 768 and 1440 pixels in dark mode', async ( { page } ) => {
		for ( const width of [ 375, 768, 1440 ] ) {
			await page.setViewportSize( { width, height: 812 } );
			await notificationFixture( page );
			await page.evaluate( () => {
				document.documentElement.setAttribute( 'data-ai-scribe-theme', 'dark' );
				window.aiScribeNotifications.show( {
					title: 'SEO details saved',
					message: 'The meta title and description were saved to the post and are ready for search engines.',
					type: 'info',
					key: 'responsive-dark'
				} );
			} );
			const notification = page.locator( '[data-notification-key="responsive-dark"]' );
			await expect( notification ).toBeVisible();
			const box = await notification.boundingBox();
			expect( box?.x ).toBeGreaterThanOrEqual( 0 );
			expect( ( box?.x || 0 ) + ( box?.width || 0 ) ).toBeLessThanOrEqual( width );
			expect( await notification.evaluate( ( el ) => getComputedStyle( el ).backgroundColor ) ).not.toBe( 'rgb(255, 255, 255)' );
			const contrast = await notification.evaluate( ( el ) => {
				const rgb = ( value: string ) => ( value.match( /[\d.]+/g ) || [] ).slice( 0, 3 ).map( Number );
				const luminance = ( value: string ) => {
					const channels = rgb( value ).map( ( channel ) => {
						const normal = channel / 255;
						return normal <= 0.03928 ? normal / 12.92 : Math.pow( ( normal + 0.055 ) / 1.055, 2.4 );
					} );
					return 0.2126 * channels[ 0 ] + 0.7152 * channels[ 1 ] + 0.0722 * channels[ 2 ];
				};
				const background = luminance( getComputedStyle( el ).backgroundColor );
				const title = luminance( getComputedStyle( el.querySelector( '.ai-scribe-notification-title' )! ).color );
				const detail = luminance( getComputedStyle( el.querySelector( '.ai-scribe-notification-detail' )! ).color );
				const ratio = ( foreground: number ) => ( Math.max( foreground, background ) + 0.05 ) / ( Math.min( foreground, background ) + 0.05 );
				return { title: ratio( title ), detail: ratio( detail ) };
			} );
			expect( contrast.title ).toBeGreaterThanOrEqual( 4.5 );
			expect( contrast.detail ).toBeGreaterThanOrEqual( 4.5 );
		}
	} );

	test( 'stacks outcomes and deduplicates repeated clicks', async ( { page } ) => {
		await notificationFixture( page );
		await page.evaluate( () => {
			window.aiScribeNotifications.show( { title: 'Draft saved', message: 'Ready.', type: 'success', key: 'save' } );
			window.aiScribeNotifications.show( { title: 'Draft saved', message: 'Ready.', type: 'success', key: 'save' } );
			window.aiScribeNotifications.show( { title: 'SEO destination', message: 'Meta saved to the post.', type: 'info' } );
			window.aiScribeNotifications.show( { title: 'Image failed', message: 'Try again.', type: 'error' } );
		} );
		await expect( page.locator( '.ai-scribe-notification' ) ).toHaveCount( 3 );
		await expect( page.locator( '[data-notification-key="save"] .ai-scribe-notification-repeat' ) ).toHaveText( 'Repeated 2 times' );
	} );

	test( 'uses safe durations and pauses routine success on hover', async ( { page } ) => {
		await notificationFixture( page );
		await page.evaluate( () => {
			window.aiScribeNotifications.show( { title: 'Saved', type: 'success', duration: 300, key: 'timed' } );
			window.aiScribeNotifications.show( { title: 'Failed', type: 'error', key: 'persistent' } );
		} );
		const success = page.locator( '[data-notification-key="timed"]' );
		await success.dispatchEvent( 'mouseenter' );
		await page.waitForTimeout( 380 );
		await expect( success ).toBeVisible();
		await success.dispatchEvent( 'mouseleave' );
		await expect( success ).toHaveCount( 0, { timeout: 1000 } );
		await page.waitForTimeout( 220 );
		await expect( page.locator( '[data-notification-key="persistent"]' ) ).toBeVisible();
	} );

	test( 'exposes status semantics and keyboard dismissal without unsafe global Escape', async ( { page } ) => {
		await notificationFixture( page );
		await page.evaluate( () => {
			window.aiScribeNotifications.show( { title: 'Saved', type: 'success', key: 'success' } );
			window.aiScribeNotifications.show( { title: 'Failed', type: 'error', key: 'error' } );
		} );
		await expect( page.locator( '[data-notification-key="success"]' ) ).toHaveAttribute( 'role', 'status' );
		await expect( page.locator( '[data-notification-key="error"]' ) ).toHaveAttribute( 'role', 'alert' );
		await page.keyboard.press( 'Escape' );
		await expect( page.locator( '.ai-scribe-notification' ) ).toHaveCount( 2 );
		const close = page.locator( '[data-notification-key="error"] .ai-scribe-notification-close' );
		await expect( close ).toHaveAttribute( 'aria-label', /Dismiss failed notification/i );
		await close.focus();
		await page.keyboard.press( 'Escape' );
		await expect( page.locator( '[data-notification-key="error"]' ) ).toHaveCount( 0 );
	} );

	test( 'settings success and error use the global centre as well as inline context', async ( { page } ) => {
		await notificationFixture( page );
		await page.setContent( `
			<div id="ai-scribe-settings-root">
				<p data-testid="settings-status"></p>
				<p data-testid="settings-save-feedback" hidden></p>
			</div>
		` );
		await page.addStyleTag( { path: tokenStylePath } );
		await page.addStyleTag( { path: stylePath } );
		await page.addScriptTag( { path: servicePath } );
		await page.addScriptTag( { path: settingsViewPath } );
		await page.evaluate( () => {
			const view = new SettingsView();
			view.announceSaved();
			view.showError( new Error( 'The server rejected the request.' ), true );
		} );
		await expect( page.getByText( 'Settings saved', { exact: true } ) ).toBeVisible();
		await expect( page.getByText( 'Settings were not saved', { exact: true } ) ).toBeVisible();
		await expect( page.locator( '[data-testid="settings-save-feedback"]' ) ).toContainText( 'server rejected' );
		await expect( page.getByText( 'Settings were not saved', { exact: true } ).locator( '..' ).locator( '..' ) ).toHaveAttribute( 'role', 'group' );
	} );
} );

declare global {
	interface Window {
		aiScribeNotifications: any;
	}
	const SettingsView: any;
}
