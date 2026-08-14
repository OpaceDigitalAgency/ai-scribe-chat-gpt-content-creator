# AI-Scribe upload assets

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
current workflow. Images are resized proportionally to 1600 pixels wide and use stable numbered
names so WordPress.org captions and GitHub references remain predictable.

WordPress.org limits headers to 4 MB and icons to 1 MB. Source: <https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/>.

## GitHub

Upload `.github/social-preview.png` in repository **Settings → Social preview**. It is 1280×640 PNG and must remain below 1 MB. GitHub does not provide a separate per-repository icon upload. Source: <https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/customizing-your-repositorys-social-media-preview>.

The WordPress.org assets are maintained in GitHub for review and future SVN publication. Updating
these files in GitHub does not update the live WordPress.org listing; that remains a separate,
explicit release action.
