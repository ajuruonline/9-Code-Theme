# 9Code Theme 14.1.8 — Unified Front-End Control Rail

## Public logged-in rail
The logged-in public front end now exposes exactly three permanent Theme controls:
1. **Theme hamburger** — opens the active 9Code Theme header/menu.
2. **Save** — broadcasts the shared front-end save contract; Data Manager Post Editor consumes it when its workspace is active.
3. **Launcher** — opens Theme Quick Actions.

Post Editor and Category Manager are no longer separate permanent front-end buttons when the Theme launcher is available. They live inside the launcher drawer.

## Data Manager launcher integration
- Post Editor opens through `ninecode:data-post-editor`.
- Category Manager opens through `ninecode:data-category-manager`.
- Data Manager retains its own fallback launchers when the Theme Quick Actions class is unavailable.

## Compatibility
- 9Core 14.1.5 is carried forward.
- 9 Data Manager 9.10.4 is the synchronized data/editor companion.
- Mason and 9Page remain discontinued and excluded from the suite.
