# White-Screen Incident and Root Cause

## Symptom
Post Content and metadata existed but a white 9CODE surface covered or displaced the actual editable controls, especially on mobile.

## Historical causes
1. The retired Edit Panels/full-screen focus system used viewport presentation classes/backdrops.
2. Cached/stale classes and overlay DOM could survive after the feature was switched off.
3. The replacement Editor Tools hamburger still created a fixed viewport-sized shell with a backdrop. If that shell state became stuck, it could itself resemble the original white-screen failure.
4. The 9CODE Command Palette was another full-viewport admin layer capable of overlapping the post editor.

## 15.0.2 / 9.10.14 correction
- The Editor Tools viewport shell/backdrop was deleted from runtime creation.
- Editor Tools now uses a compact fixed popover with bounded width/height.
- No body scroll lock is used.
- Legacy focus/full-screen classes and nodes are removed at editor startup and again after delayed initialization.
- CSS force-hides legacy overlay classes.
- 9 Data Manager independently removes stale overlay nodes on post screens.
- 9CODE Command Palette is disabled/removed on post edit screens.
- Theme adds no editor Quick Actions overlay on post edit screens.

## What must never return
Do not restore `position: fixed; inset: 0` for 9CODE editor controls on `post.php` or `post-new.php`. Do not force Post Content or `.postbox` nodes into a full-screen presentation state.
