# Nine Code

One product, one installable package: the **Nine Code** theme (`theme/nine-code`). It contains:

| Part | Folder | What it is |
|---|---|---|
| Nine Code (theme) | `theme/nine-code` | Public site (templates, header/footer, display settings, patterns) **plus the former 9Core**: Style Authority, admin workspace/branding, editor tools, builders, Doctor, diagnostics. |
| Data Manager | `theme/nine-code/data-manager` | Post Editor, Category Manager, Post Creator, Form Manager, Data Backup, data engine, AI manager. Until 17.1.0 this was the separate "Nine Code Data" plugin. |

Installing or updating the theme installs Data Manager too. The separate plugin is deactivated automatically
once the theme is active; your data is not changed.

Trade-off: Data Manager's custom post types (`nine10_form_def`, `nine10_form_response`, data-engine types),
shortcodes and blocks are registered only while Nine Code is the active theme. Switching to another theme keeps
the data in the database, but the screens that edit it and the shortcodes on pages stop working until Nine Code
is active again.
See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Updating
Upload a newer zip (**Appearance → Themes → Add New → Upload**, **Plugins → Add New → Upload**) and choose
*Replace current with uploaded*. Each piece updates independently. See [docs/RELEASING.md](docs/RELEASING.md).

Upgrading from the old three-package install (9Code 15 Theme + 9Core + 9 Data Manager): see
[docs/UPGRADING.md](docs/UPGRADING.md). It is automatic and keeps all data.

## Development
```bash
npm install
bin/setup-test-site.sh && bin/serve-test-site.sh   # throw-away WordPress (SQLite) on :8890
npm test                                           # lint + public + crawl + editors + integration + workspace + plugin check
bin/test-upgrade.sh                                # old 3-package install -> Nine Code
bin/setup-integration-env.sh                       # + SCF, fixtures, Open Scholar, Conference.lat, Nine Code apps
tests/integration/run.sh                           # Data Manager integration suites (exact totals)
node tests/e2e/data-workspace.mjs                  # Data Workspace end-to-end, phone + desktop
bin/build.sh                                       # dist/*.zip
```
CI runs all of the above (`.github/workflows/ci.yml`).

### The invariant
On standard **Posts and Pages** in `post.php` / `post-new.php`, **Post Content and post metadata must always be directly visible and editable**.
**Save/Save Draft and Publish/Update stay visible; every other editor/plugin top-bar action (including Edit with Elementor) belongs in the single hamburger menu at desktop and mobile widths.**
No Nine Code full-screen layer, backdrop or body scroll lock may exist.
`tests/e2e/editor.mjs` and `bin/lint.sh` enforce the editor safety contract.

### Rules for changes
- Do not rename option names, post meta keys, CPT slugs, taxonomies, REST routes or hooks without a migration.
- Provider plugins (ACF, Rank Math, Elementor...) own their fields and save callbacks; never clone them.
- No plugin-only APIs in the theme (`register_activation_hook`, `plugin_dir_url`...) — lint enforces this.
- Add a test with every bug fix. Report exact pass/fail counts, not "should work".

History from the old packages (release notes, audits) is kept in `docs/history/`; the original handoff in
`docs/handoff/`.
