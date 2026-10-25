# Ultron Rabbit Verification — Open Scholar Theme 20.2.6

Scope: local source-level, permission/UI regression and headless Chromium fixture checks.

## Results

- PHP parser: all theme PHP files (run via `find ... php -l`) — PASS.
- Workspace capability/sanitisation tests: 17 assertions — PASS.
- Pytest suite covering integration contracts, recovery routes, display geometry and browser interactions — 11 passing.
- Dashboard filtering, menu app sorting, child visibility and display-name/icon overrides — covered by focused PHP and Chromium fixture checks.
- Border-radius rule: every tested border-bearing panel/button has square corners; borderless elements can remain rounded.
- Server-side save fix: `admin-post.php` uses transient server-side registry. Missing registry fails without overwriting saved settings.
- Theme branding: #14532d primary retained; generated ZIP contains the original theme directory.

## Release boundaries

- There is no integrated production WordPress database, Google Drive OAuth account for **this theme** or live institutional site in this execution environment. Full installation testing and end-to-end data export/restore into a real WordPress instance are **not** claimed.
- Do not treat a records-export package as an Updraft-style disaster-recovery archive. The theme retains distinct workflows.
- Always take an independent full backup before replacing the active theme.
