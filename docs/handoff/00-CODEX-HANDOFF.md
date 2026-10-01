# 9CODE Theme Suite - Codex / Other-AI Handoff

**Handoff date:** 2026-10-01  
**Theme:** 15.0.2  
**Core:** 15.0.2  
**9 Data Manager:** 9.10.14  
**Embedded 9 Post Editor:** 4.0.3

## Primary problem being solved
On the WordPress post editor, a 9CODE white screen/full-viewport layer repeatedly covered Post Content and post metadata. The user could see the editing screen but could not reliably reach or edit the underlying post content or metadata. Earlier attempts to preserve a full-screen focus mode were too risky.

## Final architectural decision
**There must be no 9CODE full-screen layer on WordPress post edit screens.**

The post editor is a protected native workspace:
- WordPress Post Content stays in native flow.
- WordPress/provider meta boxes stay in native flow.
- Save/Update/Publish stays directly reachable.
- Auxiliary 9CODE/plugin toolbar controls may move into one hamburger.
- The hamburger opens a compact non-modal popover only.
- No hamburger backdrop.
- No viewport-sized shell.
- No body scroll lock.
- No automatic full-screen focus mode.
- No automatic metadata/meta-box hiding.

## What this package contains
1. Installable Theme 15.0.2 ZIP.
2. Installable 9Core 15.0.2 ZIP.
3. Installable 9 Data Manager 9.10.14 ZIP.
4. Combined synchronized suite ZIP.
5. Full source trees for all three components.
6. QA report and test evidence.
7. Goals, architecture, incident history, acceptance criteria and next-AI instructions.

## Non-negotiable preservation rules
Do not rename internal plugin/theme folders, CPT slugs, taxonomies, stored option names, metadata keys, text domains, REST routes or hooks without an explicit migration. Preserve Theme/Core/Data ownership boundaries. Do not reintroduce a second editor or cloned meta fields.
