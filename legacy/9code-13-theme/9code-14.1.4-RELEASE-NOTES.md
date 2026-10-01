# 9Code 14 Theme — 14.1.4

## 9Page 9.10 Front-End Theme Attachment Repair

This is a compatibility repair for the Edition 9.10 Page Builder/Mason Builder line.

### Fixed
- 9.10 `mason_page` and `mason_custom_page` public templates no longer suppress the Theme-owned Header and Footer.
- The Theme mounts through the Page Builder's existing `wp_body_open()` and `wp_footer()` seams; Page Builder still owns the inner application shell and navigation.
- 9Page/Mason visual variables now inherit Theme design tokens for text, muted text, surface, border and accent.
- When the Theme header is mounted, the fixed 9Page topbar and numbered rail are offset below it using the live header offset, preventing overlap for visitors and logged-in administrators.
- Historical pre-9.10 Mason surfaces retain their prior full-screen suppression behavior.
- 9Page 9.77 authority guard remains intact and is not revived as the Edition 9.10 baseline.

### Ownership preserved
- Theme: public chrome + design tokens.
- 9Page: public application/page renderer.
- Mason: management/control plane.
- 9Core: shared platform services.
- 9 Data: data/AI/post/category/tag/form management.

### Live acceptance
Verify Theme header/footer, active skin/accent, Page Builder scripts, mobile rail, Custom Page return strip, Quick Actions and browser console on a live WordPress site.
