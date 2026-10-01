# 9Core 14.1.4 — Edition 9.10 Data Ownership Handoff

- Detects active **9.10 Data Edition** from WordPress active-plugin state before plugin load order matters.
- When 9 Data is active, Core does not load or boot its embedded legacy Data/AI engine.
- Prevents duplicate data menus, import/export pipelines, version histories and AI workspaces.
- Core dashboard points to 9 Data and remains the shared infrastructure/diagnostics/settings surface.
- If 9 Data is absent, the older Core data engine remains available as a reversible upgrade fallback.
- No 9 Data records, Mason/Page records or Theme presentation data are migrated or rewritten.
- Edition target: **9CodePress 9.10**.
