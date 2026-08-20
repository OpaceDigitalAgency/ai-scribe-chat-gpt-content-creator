/**
 * P9 §15.1 — 2.6.2 → 3.0.x upgrade simulation (pre-release gate).
 *
 * Named zz- so it runs LAST: it rewrites the whole site state and restores
 * it from a SQL snapshot afterwards.
 *
 * Path taken (honest record):
 *   1. `wp db export` snapshot of the entire dev site (restored in afterAll).
 *   2. Deactivate the v3 dev copy; install the REAL 2.6.2 zip from wp.org
 *      under the real slug; activate it (PHP 8.3 — the §3.4 implode fatal is
 *      in the generation path, not activation; if activation still fails the
 *      spec falls back to seeding 2.6.2's exact default options via WP-CLI
 *      and records which path ran in the assertion output).
 *   3. Configure like a real 2.6.2 user: dummy OpenAI key (plaintext, as
 *      2.6.2 stored it), edited title_prompts, custom language, retired
 *      model id, saved-shortcode DB row embedded in a published page.
 *   4. In-place replacement with dist/ai-scribe-<v>.zip via
 *      `wp plugin install --force` — byte-for-byte what the wp.org one-click
 *      update does (replace files, NO activation hook; the admin_init
 *      upgrade path must do all the work).
 *   5. First post-update admin load in a real browser: no fatals, migration
 *      ran exactly once, everything survived, key encrypted+decrypts, remap
 *      + onboarding notices visible, conversations table present, frontend
 *      shortcode still renders, wizard generates (mock).
 *
 * Both hub modes are exercised: hub-active and hub-absent.
 */

import { test, expect, type Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import {
	assertNoConsoleErrors,
	snap,
	watchConsole,
	wpCli,
	wpCliTry,
	wpLogin,
} from './helpers';
import { wizard } from './selectors';

const REAL_SLUG = 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard';
const DEV_SLUG = 'ai-scribe-v3';
const HUB_SLUG = 'ai-core-standalone';
const V262_ZIP =
	'https://downloads.wordpress.org/plugin/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard.2.6.2.zip';
const CURRENT_PLUGIN_VERSION = '3.2.10';
const CURRENT_MIGRATION_VERSION = '3.0.10';
const V3_ZIP_CONTAINER = `/var/www/html/wp-content/plugins/${ DEV_SLUG }/dist/ai-scribe-${ CURRENT_PLUGIN_VERSION }.zip`;
const SNAPSHOT = '/tmp/p9-upgrade-baseline.sql';
const DUMMY_KEY = 'sk-p9-dummy-openai-key-0123456789abcdef0123456789abcdef';
const CUSTOM_PROMPT = 'P9 CUSTOM TITLE PROMPT — give me 5 titles about [Idea]';
const CUSTOM_LANGUAGE = 'Welsh (P9 custom)';
const RETIRED_MODEL = 'gpt-4.5-preview';
const DEBUG_LOG = '/var/www/html/wp-content/debug.log';

/** wp option get that tolerates absence. */
function optGet( name: string ): string {
	return wpCliTry( `wp option get ${ name }` );
}

/** The wp-env WEB (apache) container name. */
function webContainer(): string {
	const configuredCli = process.env.WP_CLI_CONTAINER;
	if ( configuredCli ) {
		return configuredCli.replace( /-cli-1$/, '-wordpress-1' );
	}
	const names: string = execSync( "docker ps --format '{{.Names}}'" ).toString();
	const web = names
		.split( '\n' )
		.find( ( n ) => n.endsWith( '-wordpress-1' ) && ! n.includes( 'tests-wordpress' ) );
	if ( ! web ) {
		throw new Error( 'wp-env wordpress container not found' );
	}
	return web;
}

/**
 * wp-env test-harness compensation (NOT part of the upgrade under test):
 * the cli and wordpress containers share a macOS host bind mount, so files
 * the cli container writes propagate to the web container with VirtioFS
 * lag, and apache's opcache revalidates on (laggy) mtimes. A real wp.org
 * update runs in the SAME process space as the site, so none of this
 * exists in production. Poll the web container's own filesystem until it
 * sees the expected plugin version, then graceful-reload apache so the
 * next request compiles fresh code.
 */
function syncWebContainer( expectedVersion: string ): void {
	const web = webContainer();
	const file = `/var/www/html/wp-content/plugins/${ REAL_SLUG }/article_builder.php`;
	const deadline = Date.now() + 30_000;
	for ( ;; ) {
		const header = execSync(
			`docker exec ${ web } bash -c "grep -m1 'Version:' '${ file }' 2>/dev/null || true"`,
			{ encoding: 'utf8' }
		);
		if ( header.includes( expectedVersion ) ) {
			break;
		}
		if ( Date.now() > deadline ) {
			throw new Error(
				`web container never saw ${ REAL_SLUG } v${ expectedVersion } (last: ${ header.trim() })`
			);
		}
		execSync( 'sleep 1' );
	}
	execSync(
		`docker exec ${ web } bash -c "apachectl -k graceful 2>/dev/null || true"`
	);
	execSync( 'sleep 2' );
}

/** Reload Apache after restoring the dev plugin/database fixture. */
function reloadWebContainer(): void {
	execSync(
		`docker exec ${ webContainer() } bash -c "apachectl -k graceful 2>/dev/null || true"`
	);
	execSync( 'sleep 2' );
}

function wpEval( php: string ): string {
	// Single-quoted for the container shell; PHP body must avoid single quotes.
	return wpCli( `wp eval "${ php.replace( /"/g, '\\"' ) }"` );
}

function truncateDebugLog(): void {
	wpCliTry( `bash -c "truncate -s 0 ${ DEBUG_LOG } 2>/dev/null || true"` );
}

function pluginErrorsInDebugLog(): string {
	const log = wpCliTry( `bash -c "cat ${ DEBUG_LOG } 2>/dev/null || true"` );
	// PHP Deprecated is excluded deliberately: 2.6.2 itself emits
	// dynamic-property deprecations on PHP 8.3 every time WP-CLI loads it
	// (its own known behaviour, not a v3 defect). Fatals/warnings/notices
	// from either version still fail the gate.
	return log
		.split( '\n' )
		.filter(
			( l ) =>
				/PHP (Fatal|Parse|Warning|Notice)/.test( l ) &&
				/ai-scribe|article_builder|ai-core/i.test( l )
		)
		.join( '\n' );
}

/**
 * Reset to a clean "2.6.2 site" and configure it. Returns which install
 * path ran: 'real-262-activated' or 'options-seeded-fallback'.
 */
function installAndConfigure262( hubActive: boolean ): string {
	// Fresh slate: deactivate v3 dev copy, set hub mode, remove any earlier copy.
	wpCliTry( `wp plugin deactivate ${ DEV_SLUG }` );
	if ( hubActive ) {
		wpCliTry( `wp plugin activate ${ HUB_SLUG }` );
	} else {
		wpCliTry( `wp plugin deactivate ${ HUB_SLUG }` );
	}
	// Deactivate BEFORE delete: deleting an active plugin leaves a stale
	// active_plugins entry, and the fresh 2.6.2 would then be "already
	// active" without its activation hook ever firing.
	wpCliTry( `wp plugin deactivate ${ REAL_SLUG }` );
	wpCliTry( `wp plugin delete ${ REAL_SLUG }` );

	// Wipe every option/table the two versions share so this is a genuine
	// first-install of 2.6.2, not v3 leftovers.
	for ( const opt of [
		'ab_prompts_content',
		'ab_gpt_content_settings',
		'ab_gpt_ai_engine_settings',
		'ab_gpt_image_settings',
		'ai_scribe_languages',
		'ai_scribe_v3_migrated',
		'ai_scribe_conversations_schema',
		'ai_scribe_onboarding_dismissed',
		'ai_scribe_model_remap_notice',
		'ab_api_key',
		'ab_anthropic_api_key',
		'ai_scribe_delete_data_on_uninstall',
	] ) {
		wpCliTry( `wp option delete ${ opt }` );
	}
	wpCliTry( `wp db query "DROP TABLE IF EXISTS wp_article_builder"` );
	wpCliTry( `wp db query "DROP TABLE IF EXISTS wp_ai_scribe_conversations"` );

	// Install the REAL 2.6.2 from wp.org.
	wpCli( `wp plugin install ${ V262_ZIP }` );

	let path = 'real-262-activated';
	try {
		wpCli( `wp plugin activate ${ REAL_SLUG }` );
		// Activation must have created the table + default options.
		const model = wpCli(
			`wp option get ab_gpt_ai_engine_settings --format=json`
		);
		if ( ! model.includes( 'gpt-4o-mini' ) ) {
			throw new Error( '2.6.2 defaults not seeded' );
		}
	} catch ( e ) {
		// Documented fallback (§15 task): seed 2.6.2's exact activation state.
		path = 'options-seeded-fallback';
		wpCliTry( `wp plugin deactivate ${ REAL_SLUG }` );
		wpCli(
			`wp db query "CREATE TABLE IF NOT EXISTS wp_article_builder (id int(20) NOT NULL AUTO_INCREMENT, title text, heading text, keyword text, intro text, tagline text, article text, conclusion longtext, qna longtext, metadata longtext, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"`
		);
		wpEval(
			`update_option('ab_gpt_ai_engine_settings', array('model' => 'gpt-4o-mini', 'temp' => 0.5, 'top_p' => 0.5, 'freq_pent' => 0.2, 'Presence_penalty' => 0.2, 'n' => 1));`
		);
	}

	// --- Configure it like a real 2.6.2 user (WP-CLI writes = the same DB
	// state the 2.6.2 settings form produces). ---

	// Dummy API key + retired model, stored PLAINTEXT as 2.6.2 did.
	wpEval(
		`\\$s = get_option('ab_gpt_ai_engine_settings', array()); \\$s['api_key'] = '${ DUMMY_KEY }'; \\$s['model'] = '${ RETIRED_MODEL }'; update_option('ab_gpt_ai_engine_settings', \\$s);`
	);

	// Edited prompt (title_prompts).
	wpEval(
		`\\$p = get_option('ab_prompts_content', array()); \\$p['title_prompts'] = '${ CUSTOM_PROMPT }'; update_option('ab_prompts_content', \\$p);`
	);

	// Custom language appended to the 2.6.2 flat list.
	wpEval(
		`\\$l = get_option('ai_scribe_languages', array()); if (!is_array(\\$l) || !count(\\$l)) { \\$l = array('English'); } \\$l[] = '${ CUSTOM_LANGUAGE }'; update_option('ai_scribe_languages', \\$l);`
	);

	// Saved shortcode row + a published page embedding it (2.6.2 syntax).
	wpCli(
		`wp db query "INSERT INTO wp_article_builder (title, heading, keyword, intro, tagline, article, conclusion, qna, metadata) VALUES ('P9 Upgrade Article', '', 'upgrade, survival', '', '', '<h1>P9 Upgrade Article</h1><p>Body written under 2.6.2 survives the update.</p>', '<span><p>Conclusion paragraph.</p></span>', '', '')"`
	);
	return path;
}

function savedShortcodeId(): string {
	return wpCli(
		`wp db query "SELECT id FROM wp_article_builder WHERE title='P9 Upgrade Article' ORDER BY id DESC LIMIT 1" --skip-column-names`
	).trim();
}

test.describe( 'P9 §15.1 — 2.6.2 → 3.0.3 upgrade simulation', () => {
	test.beforeAll( () => {
		// Full-site snapshot; everything below is destructive.
		wpCli( `wp db export ${ SNAPSHOT }` );
		// The packaged zip must exist inside the container.
		wpCli( `bash -c "test -f ${ V3_ZIP_CONTAINER }"` );
	} );

	test.afterEach( () => {
		// Per-pass isolation even when a pass fails mid-flight: never leave
		// the real-slug copy active (or a stale active_plugins entry) for
		// the next pass.
		wpCliTry( `wp plugin deactivate ${ REAL_SLUG }` );
		wpCliTry( `wp plugin delete ${ REAL_SLUG }` );
	} );

	test.afterAll( () => {
		// Restore the dev site exactly as it was, whatever happened above.
		wpCliTry( `wp plugin deactivate ${ REAL_SLUG }` );
		wpCliTry( `wp plugin delete ${ REAL_SLUG }` );
		wpCliTry( `wp db import ${ SNAPSHOT }` );
		wpCliTry( `wp cache flush` );
		// Confirm both restored plugins are active, then make Apache reload the
		// dev plugin's admin-menu hooks before a following suite can start.
		// Do not deactivate first: this wp-env maps AI-Core under a development
		// directory name, while WordPress validates its public dependency slug.
		wpCliTry( `wp plugin activate ${ HUB_SLUG }` );
		wpCliTry( `wp plugin activate ${ DEV_SLUG }` );
		reloadWebContainer();
	} );

	for ( const hubActive of [ true, false ] ) {
		const label = hubActive ? 'hub-active' : 'hub-absent';

		test( `full one-click update path survives (${ label })`, async ( { page }, testInfo ) => {
			test.skip(
				testInfo.project.name !== 'desktop-1280',
				'upgrade simulation exercised once on desktop'
			);
			test.setTimeout( 420_000 );

			// ---- 2.6.2 install + user configuration -----------------------
			const installPath = installAndConfigure262( hubActive );
			testInfo.annotations.push( { type: 'install-path', description: `${ label }: ${ installPath }` } );

			const rowId = savedShortcodeId();
			expect( Number( rowId ) ).toBeGreaterThan( 0 );
			const pageId = wpCli(
				`wp post create --post_type=page --post_status=publish --post_title='P9 upgrade ${ label }' --post_content='[article_builder_generate_data template_id="${ rowId }"]' --porcelain`
			).trim();

			// Pre-upgrade baseline: the published shortcode renders under 2.6.2
			// (only assertable when 2.6.2 actually activated).
			const pageUrl = wpCli( `wp post url ${ pageId }` ).trim();
			if ( installPath === 'real-262-activated' ) {
				syncWebContainer( '2.6.2' );
				await page.goto( pageUrl );
				await expect(
					page.locator( 'text=Body written under 2.6.2 survives the update.' )
				).toBeVisible();
			}

			// ---- The one-click update: replace files in place -------------
			truncateDebugLog();
			wpCli( `wp plugin install ${ V3_ZIP_CONTAINER } --force` );
			const version = wpCli(
				`wp plugin get ${ REAL_SLUG } --field=version`
			).trim();
			expect( version ).toBe( CURRENT_PLUGIN_VERSION );
			// The plugin must still be active if 2.6.2 was (update never
			// deactivates); in the fallback path activate the new code now.
			const status = wpCli( `wp plugin get ${ REAL_SLUG } --field=status` ).trim();
			if ( status !== 'active' ) {
				wpCli( `wp plugin activate ${ REAL_SLUG }` );
			}
			// Harness-only: make sure the WEB container is serving the
			// replaced files before the "first post-update load" (see
			// syncWebContainer docblock).
			syncWebContainer( CURRENT_PLUGIN_VERSION );

			// ---- First post-update admin load -----------------------------
			await wpLogin( page );
			await page.goto( '/wp-admin/index.php' );

			if ( ! hubActive ) {
				// Requires Plugins plus the runtime guard intentionally make the
				// dependency notice AI-Scribe's entire admin surface until AI-Core
				// is activated. Migration and onboarding must wait too.
				const dependency = page.locator( '[data-testid="ai-scribe-hub-required-notice"]' );
				await expect( dependency ).toBeVisible();
				await expect( dependency ).toContainText( 'needs the AI-Core plugin' );
				await expect( page.locator( '#adminmenu' ) ).not.toContainText( 'AI Scribe' );

				const blocked = await page.goto( wizard.pageUrl );
				expect( blocked?.status() ).toBe( 403 );
				await expect( page.locator( wizard.root ) ).toHaveCount( 0 );

				// Continue the upgrade acceptance only after satisfying the hard
				// dependency; the first subsequent admin load performs migration.
				wpCli( `wp plugin activate ${ HUB_SLUG }` );
				await page.goto( '/wp-admin/index.php' );
			}
			const errors = watchConsole( page );

			// Diagnostics for any failure below: server-side state + raw DOM.
			console.log(
				`P9 DIAG ${ label }`,
				JSON.stringify( {
					dismissed: wpCliTry( 'wp option get ai_scribe_onboarding_dismissed' ),
					migrated: wpCliTry( 'wp option get ai_scribe_v3_migrated' ),
					remapOpt: wpCliTry( 'wp option get ai_scribe_model_remap_notice --format=json' ),
					active: wpCliTry( 'wp plugin list --status=active --field=name' ).replace( /\n/g, ',' ),
					url: page.url(),
					htmlHasNotice: ( await page.content() ).includes( 'ai-scribe-onboarding-notice' ),
				} )
			);

			// Onboarding notice appears (§15.2).
			const onboarding = page.locator( '[data-testid="ai-scribe-onboarding-notice"]' );
			await expect( onboarding ).toBeVisible();

			// Model remap notice appears with the old and new ids (§15.1).
			const remap = page.locator( '[data-testid="ai-scribe-remap-notice"]' );
			await expect( remap ).toBeVisible();
			await expect( remap ).toContainText( RETIRED_MODEL );

			await snap( page, testInfo, `first-post-update-admin-load-${ label }` );
			await page.screenshot( {
				path: `tests/e2e/screenshots/p9/first-post-update-admin-load-${ label }.png`,
				fullPage: true,
			} );

			// No PHP fatals/warnings from the plugin during the update + load.
			expect( pluginErrorsInDebugLog() ).toBe( '' );

			// ---- Migration ran exactly once, options survived -------------
			expect( optGet( 'ai_scribe_v3_migrated' ) ).toBe( CURRENT_MIGRATION_VERSION );

			const engine = JSON.parse(
				wpCli( `wp option get ab_gpt_ai_engine_settings --format=json` )
			);
			// Retired model remapped, original kept for reference.
			expect( engine.model ).toBe( 'gpt-4o-mini' );
			expect( engine.model_pre_v3 ).toBe( RETIRED_MODEL );
			// Legacy plaintext key now ENCRYPTED at rest…
			expect( String( engine.api_key ).startsWith( 'aisenc1:' ) ).toBe( true );
			expect( JSON.stringify( engine ) ).not.toContain( DUMMY_KEY );
			// …and decrypts through the new encryption path.
			const decrypted = wpEval(
				`echo ai_scribe_get_container()->get('config')->get('ai_engine.api_key');`
			);
			expect( decrypted ).toBe( DUMMY_KEY );

			// Prompt edit survived.
			const prompts = JSON.parse(
				wpCli( `wp option get ab_prompts_content --format=json` )
			);
			expect( prompts.title_prompts ).toBe( CUSTOM_PROMPT );

			// Custom language survived.
			const langs = wpCli( `wp option get ai_scribe_languages --format=json` );
			expect( langs ).toContain( CUSTOM_LANGUAGE );

			// Conversations table created by the admin_init upgrade path.
			const tables = wpCli(
				`wp db query "SHOW TABLES LIKE 'wp_ai_scribe_conversations'" --skip-column-names`
			);
			expect( tables ).toContain( 'wp_ai_scribe_conversations' );

			// Idempotence: a second admin load must not re-run the migration.
			const promptsBefore = wpCli( `wp option get ab_prompts_content --format=json` );
			const engineBefore = wpCli( `wp option get ab_gpt_ai_engine_settings --format=json` );
			await page.goto( '/wp-admin/index.php' );
			expect( wpCli( `wp option get ab_prompts_content --format=json` ) ).toBe( promptsBefore );
			expect( wpCli( `wp option get ab_gpt_ai_engine_settings --format=json` ) ).toBe( engineBefore );

			// ---- Old shortcode on a published page still renders ----------
			await page.goto( pageUrl );
			await expect(
				page.locator( 'text=Body written under 2.6.2 survives the update.' )
			).toBeVisible();

			// ---- Wizard works post-upgrade (mock provider) ----------------
			truncateDebugLog();
			await page.goto( wizard.pageUrl );
			await expect( page.locator( wizard.root ) ).toBeVisible();
			const p1 = page.locator( wizard.panel( 1 ) );
			await p1.locator( wizard.ideaInput ).fill( 'p9 upgrade smoke' );
			await p1.locator( wizard.generateButton ).first().click();
			await expect( p1 ).toHaveAttribute( 'data-state', 'ready', { timeout: 30_000 } );
			await expect( p1.locator( wizard.resultCard ) ).toHaveCount( 5 );
			await snap( page, testInfo, `wizard-post-upgrade-${ label }` );
			await page.screenshot( {
				path: `tests/e2e/screenshots/p9/wizard-post-upgrade-${ label }.png`,
				fullPage: true,
			} );
			expect( pluginErrorsInDebugLog() ).toBe( '' );

			// ---- Onboarding dismissal persists ----------------------------
			await page.goto( '/wp-admin/index.php' );
			await Promise.all( [
				page.waitForResponse( ( r ) =>
					r.url().includes( 'admin-ajax.php' ) &&
					r.request().postData()?.includes( 'ai_scribe_dismiss_notice' ) === true
				),
				onboarding.locator( '[data-ai-scribe-dismiss="onboarding"]' ).click(),
			] );
			await page.goto( '/wp-admin/index.php' );
			await expect(
				page.locator( '[data-testid="ai-scribe-onboarding-notice"]' )
			).toHaveCount( 0 );

			assertNoConsoleErrors( errors );

			// Clean up this pass's page (plugin teardown lives in afterEach).
			wpCliTry( `wp post delete ${ pageId } --force` );
		} );
	}
} );
