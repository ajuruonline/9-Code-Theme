# Instructions for the Next AI / Codex Session

You are continuing the 9CODE Theme Suite. Treat this handoff package as the source of truth for this repair line.

## Immediate mission
Maintain direct editability of WordPress Post Content and post metadata. Do not reintroduce any full-screen editor overlay.

## Before changing code
1. Read `00-CODEX-HANDOFF.md`.
2. Read `01-GOALS-AND-OBJECTIVES.md`.
3. Read `02-ARCHITECTURE-AND-OWNERSHIP.md`.
4. Read `03-WHITE-SCREEN-INCIDENT-AND-ROOT-CAUSE.md`.
5. Run the existing tests before making changes.

## Development rules
- Use test-driven changes for regressions.
- Preserve internal slugs, options, metadata keys and ownership contracts.
- Do not clone provider fields.
- Do not hide metadata to make the UI look cleaner.
- Keep Save/Update directly visible.
- If space is tight, move auxiliary controls into the hamburger popover.
- On post edit screens, never create `position: fixed; inset: 0` 9CODE UI.
- Do not claim live-site success from static tests alone.

## Required final report
State files changed, tests run, exact pass/fail counts, remaining live-site risks and rollback path.
