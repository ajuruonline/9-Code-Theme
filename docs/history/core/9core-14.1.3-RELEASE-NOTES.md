# 9Core 14.1.3 — Multi-Mason Diagnostics & Companion Recognition

- Diagnostics schema advances to 5.
- Adds `ninecodepress_mason_control_planes()` and `ninecodepress_mason_control_plane_status()`.
- Detects independently owned control planes without merging them: standalone 9 Mason, 9Page 9.77 internal Mason, and Mason Page Editor.
- Multiple active control planes are reported as an operator-review condition, not silently merged or treated as a fatal conflict.
- 9Page detection is explicitly limited to `NINE_PAGE_EDITION === 9.77`.
- Ownership remains with each companion. Core stores no companion records and rewrites no allocations.

NEEDS LIVE TEST: Site Health/diagnostics JSON schema 5 under each companion combination.
