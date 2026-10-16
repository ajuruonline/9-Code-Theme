# Current handoff: Nine Code theme 17.0.0 and Nine Code Data 17.0.0

**Date:** 2026-10-16
**Repository:** https://github.com/ajuruonline/9-Code-Theme
**Working branch:** `codex/universal-data-manager` (head `226a494` at time of writing)
**Open PR:** https://github.com/ajuruonline/9-Code-Theme/pull/1 (base `claude/nine-code-baseline`, mergeable, CI green on `83a4588`)
**Versions:** theme 17.0.0 (`theme/nine-code/style.css`, `NCU_THEME_VERSION`), Nine Code Data 17.0.0 (`plugin/nine-code-data/nine-code-data.php`, `NINE55_ULTRON_DATA_VERSION`, `readme.txt` stable tag). `bin/lint.sh` checks that they agree.

This file supersedes `00-CODEX-HANDOFF.md` for the current state. The older files in this folder describe the 15.0.2 white-screen work, which is still valid as history and architecture rules.

## Goals

1. **Data Manager 11.0+ is a universal, mobile-first workspace.** An admin on a phone or desktop can discover every data type, edit what they are authorised to edit, export for AI, preview an import, apply selectively, publish only when explicitly allowed, and undo.
2. **Plugins keep ownership of their data.** Data Manager discovers, displays, diffs, exports, imports, records history and undoes. Every value is validated and written by the owning provider's callbacks, or by WordPress APIs for generic data. Data Manager never duplicates another plugin's business logic.
3. **Nothing changes silently.** AI or import can never create, trash, publish, unpublish or structurally alter a record without explicit permission in the request.
4. **Theme is calm on phones.** Long settings pages start closed, the quick-action rail sits at mid-right, and the public site stays usable at 320–430px.
5. **Releases stay in sync.** Theme and Nine Code Data share one version number, with changelog and readme updated in the same commit.

## Objectives and status

| Objective | Status |
|---|---|
| Provider contract v1 (registry, validation, permissions, publish, transactions, export/import transforms) | Done (`docs/DATA-PROVIDER-CONTRACT.md`) |
| Generic discovery of post types, taxonomies, users, ACF/SCF, registered and discovered meta | Done. Post types without admin UI are included now (internal WordPress types stay skipped). |
| Comments as an editable data type (moderation through WordPress functions, status needs explicit permission) | Done (commit `bd5a190`) |
| Forms: question definitions editable with the Form Manager's own rules; responses read-only; visitor IP hash never shown | Done (commit `bd5a190`) |
| Open Scholar provider (in its repo, PR ajuruonline/Open-Scholar#1) | Done in PR, open |
| Conference LAT provider (in its repo, PR ajuruonline/Conference-LAT#2) | Done in PR, open |
| Bundled adapters: Teaching Player, LectureBoard, Live Lecture, Workshop progress (read-only) | Done |
| AI export and import: JSON package, XLSX and CSV, staged imports, field-level preview, conflicts, selective apply, batches | Done |
| History and undo | Done |
| Quick-action rail centred on the right edge (desktop, phone, front end) | Done (theme 16.1.1, folded into 17.0.0) |
| Settings panels start closed (Header & Footer and Style settings; footer no longer reopens the last tab) | Done (commit `226a494`) |
| Versions 17.0.0 for theme and Data Manager | Done (commit `83a4588`) |
| Integration tests | 363 checks across 8 suites, all passing at last full run |
| Workspace end-to-end test | 38 of 38 passing at last run |
| Drive copy of the zips | **Not done.** The Drive connector cannot upload archives this size. The zips were sent to the user directly. |

## How to verify

```bash
bin/lint.sh                                  # lint, version agreement
bin/setup-integration-env.sh                 # once: SCF, fixtures, partner repos (skips unreachable)
tests/integration/run.sh                     # all 8 suites
WP_TEST_PORT=8891 bin/serve-test-site.sh     # test site home URL is localhost:8891
BASE=http://localhost:8891 node tests/e2e/data-workspace.mjs
```

Suite totals last run: discovery 48, records 42, acf 41, exchange 61, security 39, providers 26, compat 18, adapters 88.

Notes:
- The test site's home URL is `localhost:8891`. Serving on 8890 redirects there and fails.
- Use `127.0.0.1` cautiously; `localhost` works.
- `tests/integration/adapters.php` has three PHPCS findings on lines that predate the 17.0 work. Tests are not in the lint gate.

## Where the code lives

- `plugin/nine-code-data/includes/data-api/` — registry, policy, types, store, service, history, exchange, sheet, REST, admin, generic provider (`class-ncd-generic-provider.php`, now including comments), bundled adapters (`adapters/class-ncd-adapter-apps.php`, now including Forms).
- `plugin/nine-code-data/assets/data-workspace.{js,css}` — mobile-first Data Workspace.
- `theme/nine-code/assets/css/block-edition-quick-actions.css` — launcher position.
- `theme/nine-code/inc/header-footer/header/includes/class-settings.php`, `.../footer/includes/class-n9f-settings.php`, `.../footer/assets/js/admin.js`, `theme/nine-code/core/inc/style-takeover.php` — collapsible settings panels.
- `docs/UNIVERSAL-DATA-MANAGER.md` (user guide), `docs/DATA-PROVIDER-CONTRACT.md`, `docs/DATA-MANAGER-APP-INVENTORY.md` (all 29 repos).

## Known gaps

1. **PR #1 description is stale.** It still shows the 349-check totals and does not mention 17.0.0, Forms, comments or the merge from `main`. It needs updating before review.
2. **Not merged to `main`.** `main` is at `7aff97b`, which only adds the 9code preview kit. Merging PR #1 is a separate decision.
3. **Drive sync is not done** (see status table).
4. **Not in Data Manager yet:** navigation menus and menu items, widgets, comment-level ACF, plugin options and settings pages. These are settings rather than records, and each needs its own provider decision.
5. **Front-end header drawer** (public site) still shows its default section open. It uses tabs, not collapsible panels, so this was left as is.
6. **Test coverage gap:** the new comments and forms checks live in `discovery.php` and `adapters.php`. The workspace end-to-end test does not yet cover comment moderation or form responses on a phone.

## Next ideas (in priority order)

1. Update PR #1's description to match the current state, then request review.
2. Add end-to-end coverage on 390px for: approving a comment with the explicit permission, refusing it without, and reading a form response read-only.
3. Decide on navigation menus and widgets: read-only first, or editable through WordPress's own menu functions.
4. Move bundled adapters into their owning apps as providers (Teaching Player, LectureBoard, Live Lecture), following the Open Scholar and Conference LAT pattern. The bundled adapter steps aside automatically once an app registers its own provider.
5. Provide a Drive copy: a text handoff document or a link to the GitHub release, since archives cannot be uploaded through the connector.
6. Review the public-site header drawer on a 390px device and decide whether its sections should collapse.

## Rules for the next AI

- Follow `docs/handoff/02-ARCHITECTURE-AND-OWNERSHIP.md`: the UI may reorganise controls, never data ownership.
- Keep all changes on `codex/universal-data-manager` unless told otherwise. Do not force-push or rewrite history on a shared branch.
- Bump theme and Data Manager versions together, and update `CHANGELOG.md`, `readme.txt` and `docs/DATA-MANAGER-APP-INVENTORY.md`.
- Do not send data to external AI services. Do not add model names to commit messages or PR text.
- Record every new integration check in the right suite and rerun `tests/integration/run.sh` before pushing.
