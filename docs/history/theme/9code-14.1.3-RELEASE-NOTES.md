# 9Code Theme 14.1.3 — Companion Shell Recognition & Mason Namespace Guard

- Recognizes only the active 9Page **9.77** edition as an app-shell owner and suppresses duplicate Theme Header/Footer on its `nine_page` public surface.
- Recognizes Mason Page Editor full-screen `mason_page` and `mason_custom_page` surfaces and suppresses duplicate Theme Header/Footer there.
- Both companion adapters remain additive: Theme design tokens can still be inherited, and neither companion becomes a core dependency.
- The 9Page adapter is explicitly gated by `NINE_PAGE_EDITION === 9.77`; discontinued Nine Page/9.55 Page 0.3.x-0.5.x code cannot become a current baseline merely by using the historical `nine_page` post type.
- No companion data, editor logic or business logic is moved into the Theme.

NEEDS LIVE TEST: browser rendering for 9Page 9.77, Mason Page Editor, Header/Footer suppression, Quick Actions and companion deactivation rollback.
