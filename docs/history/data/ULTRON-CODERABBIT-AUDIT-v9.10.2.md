# Ultron + Code Rabbit Audit — 9.10 Data Edition v9.10.2

## Release-worthy findings corrected

1. **Publication-state regression:** Private/Future records were not represented in the 9Form status selector, which could cause an unrelated edit to submit Draft. The current state is now explicitly preserved.
2. **Split sanitization authority:** 9Form pre-filtered post content before routing it to 9 Post Manager. The form now passes unslashed text and leaves capability-aware post-content sanitization to 9 Post Manager, preserving one destination authority and Gutenberg/block semantics.
3. **Author-form visibility:** public Contact Author forms could target any valid post ID. They now require a publicly viewable record unless the authenticated user can read the record.
4. **Mail retry UX:** a failed mail send used to leave the anti-spam transient active. Failed delivery now clears that transient.
5. **Editor discoverability:** users with `edit_posts` but without `manage_options` could technically use 9Form but could not see it beneath the administrator-only parent menu. They now get a focused 9Form menu; administrators retain the integrated submenu.

## Preserved boundaries

- Post create/edit writes route through 9 Post Manager.
- Data/ACF/safe-meta writes route through Data Engine atomic import/version capture.
- Taxonomy structure creation remains in 9 Category Manager.
- AI routing remains through the established integration handler/destination engines.
- 9Form creates no duplicate content/message data store.

## Verification scope

Static verification covers PHP syntax, JavaScript parse, ZIP integrity, version/header checks, engine-version checks, 9Form write firewall, destination-route assertions, status-preservation assertions and contact visibility/rate-limit assertions. Live WordPress, SMTP, Elementor and browser/mobile execution remain staging tests.
