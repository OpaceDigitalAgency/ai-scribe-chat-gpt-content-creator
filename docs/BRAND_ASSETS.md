# AI-Scribe upload assets

Approved WordPress.org artwork prepared on 14 August 2026. Installed-plugin logo and favicon
variants were reconciled with that artwork on 20 August 2026.

## Installed plugin

The release package includes these files under `assets/images/`:

- `ai-scribe-logo.png` — complete approved 256px square logo;
- `ai-scribe-logo-320.png` — complete 320px logo used on the Help screen;
- `ai-scribe-logo-simplified.png` — centred, text-free 168px mark;
- `ai-scribe-logo-icon.png` — centred 128px symbol retained for compact contexts;
- `ai-scribe-logo.png` and `ai-scribe-logo-320.png` — complete approved logo and wordmark used in page headers;
- `ai-scribe-menu-icon-20x20.png` — centred white WordPress admin-menu mark on a transparent canvas;
- `ai-scribe-favicon-16x16.png`, `ai-scribe-favicon-32x32.png` and
  `ai-scribe-favicon-48x48.png` — small symbol-only favicon variants;
- `ai-scribe-favicon.ico` — 32px ICO variant.

Small assets use only the central document/S mark. Do not shrink the wordmark into menu or favicon
sizes because it becomes illegible. The plugin package includes these assets; it does not change the
site-wide WordPress favicon selected by the site owner.

## WordPress.org

Copy the contents of `.wordpress-org/` into the plugin SVN checkout's top-level `assets/` directory.

- `banner-772x250.png` — standard header
- `banner-1544x500.png` — high-DPI header
- `icon-128x128.png` — standard plugin icon
- `icon-256x256.png` — high-DPI plugin icon
- `screenshot-1.png` — Title Generation and the guided Wizard
- `screenshot-2.png` — qualitative keyword demand and Google Trends
- `screenshot-3.png` — selectable Article Outline
- `screenshot-4.png` — Article Body, word target and Image Studio
- `screenshot-5.png` — editable Questions & Answers
- `screenshot-6.png` — editable SEO metadata and guidance
- `screenshot-7.png` — Express mode, word count and save actions
- `screenshot-8.png` — evidence-led Evaluate report

The screenshot set is also used by the GitHub README so both public descriptions show the same
current workflow. The files were curated from the latest owner-approved captures in
`~/Desktop/ai-scribe/`, resized proportionally to 1600 pixels wide and given stable numbered names.
The source filenames and public descriptions are:

| Public file | Source capture | Description |
|---|---|---|
| `screenshot-1.png` | `Screenshot 2026-08-14 at 17.00.22.png` | Title Generation |
| `screenshot-2.png` | `Screenshot 2026-08-14 at 17.00.36.png` | Keyword Research |
| `screenshot-3.png` | `Screenshot 2026-08-14 at 17.01.02.png` | Article Outline |
| `screenshot-4.png` | `Screenshot 2026-08-14 at 17.02.31.png` | Article Body and Image Studio |
| `screenshot-5.png` | `Screenshot 2026-08-14 at 17.03.25.png` | Questions & Answers |
| `screenshot-6.png` | `Screenshot 2026-08-14 at 17.03.42.png` | SEO Meta |
| `screenshot-7.png` | `Screenshot 2026-08-14 at 16.59.06 (2).png` | Express mode |
| `screenshot-8.png` | `Screenshot 2026-08-14 at 17.04.29.png` | Evaluate |

WordPress.org limits headers to 4 MB and icons to 1 MB. Source: <https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/>.

## GitHub

Upload `.github/social-preview.png` in repository **Settings → Social preview**. It is 1280×640 PNG and must remain below 1 MB. GitHub does not provide a separate per-repository icon upload. Source: <https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/customizing-your-repositorys-social-media-preview>.

The WordPress.org assets are maintained in GitHub for review and future SVN publication. Updating
these files in GitHub does not update the live WordPress.org listing; that remains a separate,
explicit release action.
