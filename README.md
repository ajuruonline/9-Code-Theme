# Open Scholar Theme

**Open Scholar Theme** is the lean WordPress host for the Open Scholar plugin family. Its install folder remains `nine-code` so existing sites can upgrade in place without losing settings or changing the active theme slug.

The theme owns only shared platform concerns:

- Open Scholar branding, colour tokens, login and administration styling
- a dedicated app home and curated 9/Open Scholar navigation, with maintenance shortcuts in the launcher
- mobile-first settings UI with closed, alternating red/green sections
- public templates that deliberately provide no site header or footer
- a documented registration seam for Open Scholar plugins
- restrained Jetpack UI while preserving Jetpack's app and background services
- lightweight SEO, Open Graph, Twitter/X cards and schema fallback that yields to a dedicated SEO plugin
- Ultra Backup for selective content/plugin transfer, full disaster snapshots, restore preview, schedules and Bluehost/shared-host diagnostics
- standard WordPress theme supports, accessibility and safe upgrade compatibility

Application features belong in plugins. Data Manager, AI import/export, forms, builders, blocks, conference, teacher and other app workflows are not bundled into the theme. The theme does not deactivate those plugins and does not copy their data ownership or editing logic. Ultra Backup is the deliberate host-level exception because it protects the installation and coordinates plugin portability rather than implementing an application's business workflow.

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) and [docs/OPEN-SCHOLAR-THEME-STANDARD.md](docs/OPEN-SCHOLAR-THEME-STANDARD.md).

## Updating

Upload the current ZIP through **Appearance → Themes → Add New → Upload** and choose **Replace current with uploaded**. The unchanged internal folder replaces the existing installation in place. See [docs/RELEASING.md](docs/RELEASING.md).

## Development

```bash
npm install
bin/lint.sh
bin/build.sh
```

The release ZIP is intentionally checked to exclude retired application modules. Historical code remains in the repository for migration reference but is neither loaded nor shipped.

## Rules for changes

- Keep the theme a host. Put application data, fields, AI workflows, CPTs, REST workflows and business screens in plugins.
- Do not rename persistent option names or public hooks without a migration.
- Provider plugins own their fields and save callbacks; never clone them in the theme.
- New host settings must be mobile-first, descriptive and collapsed by default.
- Do not use plugin-only lifecycle APIs in theme runtime code.
- Add verification with every behaviour change.
