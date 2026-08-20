import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const here = dirname( fileURLToPath( import.meta.url ) );
const root = resolve( here, '../..' );
const css = [
	'assets/css/main.css',
	'assets/css/components.css',
	'assets/css/admin-pages.css',
	'assets/css/admin-responsive.css',
].map( ( path ) => readFileSync( resolve( root, path ), 'utf8' ) ).join( '\n' );

const markup = `
<main class="ai-scribe-app">
  <section class="ai-scribe-admin-page" data-page="settings">
    <header class="page-brand"><h1>AI-Scribe Settings</h1></header>
    <nav class="settings-tabs">
      <button class="tab-btn active">Providers &amp; Model</button><button class="tab-btn">Generation</button>
      <button class="tab-btn">Images</button><button class="tab-btn">Prompt Library</button>
    </nav>
    <section class="tab-content">
      <div class="form-row article-length-settings">
        <div class="form-group"><label for="length">Default length</label><select id="length" class="form-control"><option>Auto — recommended</option></select></div>
        <div class="form-group"><label for="words">Custom target words</label><input id="words" class="form-control" value="1800"></div>
      </div>
      <div class="form-group"><label for="model">Text model</label><div class="input-with-button"><select id="model" class="form-control"><option>Gemini 3.7 Flash — Google Gemini</option></select><button class="btn">Refresh models</button></div></div>
      <div class="provider-chip-row"><span class="provider-chip">Google Gemini configured and ready</span><span class="provider-chip">OpenAI not configured</span></div>
      <div class="settings-actions"><button class="btn btn-primary">Save Settings</button></div>
    </section>
  </section>
  <section class="ai-scribe-admin-page" data-page="shortcodes">
    <header class="page-brand"><h1>Saved Shortcodes</h1></header>
    <table class="widefat striped ai-scribe-table"><thead><tr><th>Title</th><th>Shortcode</th><th>Actions</th></tr></thead><tbody>
      <tr><td>A long saved article title that must remain readable on a phone</td><td><code>[article_builder_generate_data template_id="123456789"]</code></td><td><button class="btn">Remove</button></td></tr>
    </tbody></table>
  </section>
  <section class="ai-scribe-admin-page" data-page="help">
    <header class="page-brand"><h1>Help</h1></header>
    <section class="help-section"><h2>Getting started</h2><p>Follow these steps to create and publish an article without losing track of the next action.</p><ol><li>Choose a model.</li><li>Review the article.</li></ol></section>
  </section>
</main>`;

const browser = await chromium.launch( { headless: true } );
for ( const theme of [ 'light', 'dark' ] ) {
	for ( const width of [ 375, 768, 1024, 1440 ] ) {
		const page = await browser.newPage( { viewport: { width, height: 1000 }, colorScheme: theme } );
		await page.setContent( `<!doctype html><html data-ai-scribe-theme="${ theme }"><head><style>${ css }</style></head><body>${ markup }</body></html>` );

		const metrics = await page.evaluate( () => {
			const rect = ( selector ) => document.querySelector( selector ).getBoundingClientRect();
			const tabs = [ ...document.querySelectorAll( '.settings-tabs .tab-btn' ) ].map( ( element ) => element.getBoundingClientRect() );
			const labelElement = document.querySelector( 'label[for="length"]' );
			const label = labelElement.getBoundingClientRect();
			const field = rect( '#length' );
			const code = rect( '.ai-scribe-table code' );
			const row = rect( '.ai-scribe-table tbody tr' );
			const help = getComputedStyle( document.querySelector( '.help-section p' ) );
			const codeStyle = getComputedStyle( document.querySelector( '.ai-scribe-table code' ) );
			return {
				overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
				tabs: tabs.map( ( item ) => ( { x: item.x, y: item.y, width: item.width, right: item.right } ) ),
				labelGap: field.top - label.bottom,
				labelMargin: parseFloat( getComputedStyle( labelElement ).marginBottom ),
				codeWidth: code.width,
				rowWidth: row.width,
				helpLineHeight: parseFloat( help.lineHeight ) / parseFloat( help.fontSize ),
				codeBackground: codeStyle.backgroundColor,
				codeColour: codeStyle.color,
				tableDisplay: getComputedStyle( document.querySelector( '.ai-scribe-table' ) ).display,
			};
		} );

		assert.ok( metrics.overflow <= 1, `${ width }px ${ theme }: document overflowed by ${ metrics.overflow }px` );
		assert.ok( metrics.tabs.every( ( tab ) => tab.x >= 0 && tab.right <= width + 1 ), `${ width }px ${ theme }: settings tab clipped` );
		assert.ok( metrics.labelGap + metrics.labelMargin >= 6 && metrics.labelGap + metrics.labelMargin <= 16, `${ width }px ${ theme }: label spacing was ${ metrics.labelGap + metrics.labelMargin }px` );
		assert.ok( metrics.helpLineHeight >= 1.5, `${ width }px ${ theme }: Help line-height was ${ metrics.helpLineHeight }` );
		assert.notEqual( metrics.codeBackground, metrics.codeColour, `${ width }px ${ theme }: shortcode foreground/background collapsed` );

		if ( width <= 600 ) {
			assert.ok( Math.abs( metrics.tabs[ 0 ].width - metrics.tabs[ 1 ].width ) < 1, `${ width }px ${ theme }: settings columns differ` );
			assert.ok( Math.abs( metrics.tabs[ 0 ].y - metrics.tabs[ 1 ].y ) < 1, `${ width }px ${ theme }: first tab row is not aligned` );
			assert.ok( metrics.tabs[ 2 ].y > metrics.tabs[ 0 ].y, `${ width }px ${ theme }: settings tabs did not form two rows` );
			assert.equal( metrics.tableDisplay, 'block', `${ width }px ${ theme }: shortcode table did not become records` );
			assert.ok( metrics.codeWidth > 280, `${ width }px ${ theme }: shortcode remained squeezed (${ metrics.codeWidth }px)` );
			assert.ok( metrics.codeWidth >= metrics.rowWidth - 36, `${ width }px ${ theme }: shortcode ${ metrics.codeWidth }px did not fill ${ metrics.rowWidth }px card` );
		}

		await page.close();
	}
}
await browser.close();
console.log( 'Admin responsive: Settings, Saved Shortcodes and Help pass at 375/768/1024/1440 in light and dark themes' );

// Optional source-candidate visual proof against an installed, authenticated
// WordPress fixture. The stylesheet is injected because the installed plugin
// may deliberately remain on the previous packaged build during development.
if ( process.env.AI_SCRIBE_LIVE_BASE && process.env.AI_SCRIBE_LIVE_STATE ) {
	const liveBrowser = await chromium.launch( { headless: true } );
	for ( const theme of [ 'light', 'dark' ] ) {
		for ( const width of [ 375, 768, 1024, 1440 ] ) {
			for ( const [ name, slug ] of Object.entries( { settings: 'ai_scribe_settings', shortcodes: 'ai_scribe_saved_shortcodes', help: 'ai_scribe_help' } ) ) {
				const context = await liveBrowser.newContext( { storageState: process.env.AI_SCRIBE_LIVE_STATE, viewport: { width, height: 1000 }, colorScheme: theme } );
				const page = await context.newPage();
				await page.goto( `${ process.env.AI_SCRIBE_LIVE_BASE }/wp-admin/admin.php?page=${ slug }`, { waitUntil: 'networkidle', timeout: 45000 } );
				await page.addStyleTag( { content: readFileSync( resolve( root, 'assets/css/admin-responsive.css' ), 'utf8' ) } );
				const result = await page.evaluate( ( pageName ) => {
					const data = { overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth };
					if ( pageName === 'settings' ) data.tabY = [ ...document.querySelectorAll( '.settings-tabs .tab-btn' ) ].map( ( element ) => element.getBoundingClientRect().y );
					if ( pageName === 'shortcodes' ) {
						const table = document.querySelector( '.ai-scribe-table' );
						const code = table?.querySelector( 'code' );
						data.tableDisplay = table ? getComputedStyle( table ).display : '';
						data.codeWidth = code?.getBoundingClientRect().width || 0;
					}
					return data;
				}, name );
				assert.ok( result.overflow <= 1, `live ${ name } ${ width }px ${ theme }: overflow ${ result.overflow }px` );
				if ( name === 'settings' && width <= 600 ) assert.ok( result.tabY[ 2 ] > result.tabY[ 0 ], `live ${ width }px ${ theme }: settings tabs did not form two rows` );
				if ( name === 'shortcodes' && width <= 600 ) {
					assert.equal( result.tableDisplay, 'block', `live ${ width }px ${ theme }: shortcode table did not become records` );
					assert.ok( result.codeWidth > 250, `live ${ width }px ${ theme }: shortcode remained squeezed (${ result.codeWidth }px)` );
				}
				await page.screenshot( { path: `/tmp/admin-post-${ name }-${ width }-${ theme }.png`, fullPage: true } );
				await context.close();
			}
		}
	}
	await liveBrowser.close();
	console.log( 'Admin responsive live fixture: 24 source-candidate page/theme/width combinations pass without overflow' );
}
