# 9CodePress White-Screen Removal QA

Release date: 2026-10-01

## Versions
- 9Code Theme: 15.0.2
- 9Core: 15.0.2
- 9 Data Manager: 9.10.14
- Embedded 9 Post Editor: 4.0.3

## Regression evidence
- 56/56 component regression/runtime test programs: PASS.
- Critical Chromium mobile harness at 390x844: PASS for Classic Editor and Gutenberg.
- Browser harness verified editable post content, editable metadata, visible Save/Update, hamburger settings access, and absence of the retired focus overlay.

## Static gates
- PHP syntax: 135 files PASS.
- JavaScript syntax: 30 files PASS.
- JSON parsing: 6 files PASS.
- CSS structural brace validation: 32 files PASS.

## White-screen guardrails
- 9Core no longer creates a viewport-sized Editor Tools shell/backdrop.
- Editor Tools is a compact non-modal popover.
- No Editor Tools body scroll lock.
- Legacy focus/full-screen classes/nodes are cleaned on post editor load.
- Legacy Editor Tools shell/backdrop is force-hidden on post screens.
- 9CODE Command Palette is disabled/removed on post edit screens.
- Theme yields its post-editor Quick Actions layer.
- Data Manager independently removes stale 9CODE editor overlays.
- WordPress Media Library/provider dialogs are not suppressed.
- Post Content and Post Metadata remain first-class Data Manager Post Editor tabs.
- Normal WordPress post screen retains the 9 Data Post Content & Metadata recovery meta box.

## Runtime boundary
These tests cannot reproduce every live hosting cache/minifier, browser extension, WordPress plugin combination, or site-specific admin CSS. The next live acceptance step is to replace all three synchronized components, clear caches, and open an existing post on the affected site.
