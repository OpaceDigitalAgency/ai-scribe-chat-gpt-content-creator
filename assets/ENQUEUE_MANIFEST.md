# AI-Scribe admin asset manifest

This document records the current asset boundaries implemented by
`includes/services/class-admin-service.php`. The PHP service remains the
authoritative source for handles and dependencies.

## Shared rules

- Every AI-Scribe admin style and script uses `AI_SCRIBE_VERSION` for cache
  busting.
- Scripts load in the footer.
- API keys are never localised to JavaScript. The browser receives only
  configured-provider booleans plus non-secret settings and state.
- `ai-scribe-notification-centre` is available on every AI-Scribe admin screen
  so outcomes stay fixed to the viewport rather than a nested panel.
- The saved light or dark theme is applied before first paint.

## Styles on every AI-Scribe admin page

Loaded in dependency order:

1. `ai-scribe-main` — `assets/css/main.css`
2. `ai-scribe-components` — `assets/css/components.css`
3. `ai-scribe-admin-pages` — `assets/css/admin-pages.css`
4. `ai-scribe-admin-responsive` — `assets/css/admin-responsive.css`
5. `ai-scribe-notification-centre` — `assets/css/notification-center.css`

The Wizard additionally loads `animations.css`, Quill's Snow stylesheet and
the vendored Font Awesome stylesheet.

## Wizard script order

1. Vendored Quill and Lucide assets.
2. `AppState.js`.
3. `ApiClient.js` and `CardRenderer.js`.
4. `BaseStepView.js`, `ChoiceStepView.js` and `StreamingStepView.js`.
5. The 11 step views plus `ExpressView.js`.
6. `StepViewRegistry.js`.
7. `WizardFlowController.js`, modal controller and modal view.
8. `main.js` last, localised as `ai_scribe`.

The localised object contains the AJAX URL and nonce, settings URL, plugin
version, effective model, configured-provider flags, image capabilities,
enhancement settings, prompt data and non-secret content settings.

## Settings page

The Settings screen loads the shared styles and notification centre, followed
by `AppState.js`, `ApiClient.js`, `SettingsController.js`, `SettingsView.js`
and `main.js`.

Its implemented AJAX contract includes `ai_scribe_get_available_models`,
`ai_scribe_save_api_keys` and `ai_scribe_get_settings`; the controller also
owns the other settings save, model refresh and connectivity actions used by
the current interface. The endpoint implementation is in
`includes/ajax/class-settings-ajax-controller.php`.

## Other admin pages

- Saved Shortcodes loads `assets/js/shortcodes-page.js` for nonce-protected
  removal actions.
- Help and diagnostic pages use the shared CSS, responsive layer, theme boot
  and notification centre without loading the Wizard bundle.

When adding an asset, update the PHP enqueue implementation and this manifest
in the same change, preserve the dependency order, and increment the plugin
version for the release package.
