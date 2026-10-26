# Open Scholar Theme 20.2.1 — Clean Top Bar and Right-Side Launchers

## Scope
Updated the supplied theme version 20.2.0 in-place. No plugin data, saved options, custom post types, menus, shortcode registrations, authentication policy, upload/backup workflow, or public header/footer ownership was removed or renamed.

## User-facing changes

1. WordPress top admin toolbar is hidden on admin screens that render the authorized Theme launcher. Its top padding is removed. Other users and screens without the replacement controls retain normal WordPress controls to avoid blocking account access.
2. The bottom-left Open Scholar navigation launcher displays only the configured Site Icon (with academic-cap fallback). The extra hamburger graphic and "Menu" label are removed; the existing permission-filtered WordPress menu remains inside the responsive bottom-left popup.
3. Search is removed from the Open Scholar navigation popup and becomes an always-visible search icon in the RIGHT quick-action rail. Search supports WordPress posts and pages as well as finding installed applications/menus in the admin workspace; on public editing surfaces it searches the public site.
4. A permanent Top Bar icon is added to the same right launcher. Its compact popup contains Open Scholar menu access, View Site, Dashboard, Add Post, Add Page, Upload Media, My Profile and Log Out, subject to WordPress capabilities. On public editing surfaces its Theme Menu action preserves the prior frontend menu behavior.
5. Top Bar contains a real **Clear cache** action for the WordPress object cache, restricted to `manage_options` with an AJAX nonce. It reports actual success or failure; it does not pretend to purge unrelated page/CDN caches.
6. Caching plugins' own purge/clear actions (and other additional toolbar links) are collected from the user's live WordPress admin bar and linked through the original toolbar actions. No cache-plugin-specific endpoint is fabricated.
7. The right Quick Actions launcher no longer duplicates the old Open Scholar hamburger/menu and hides permanent actions from the configurable grid. Save remains on its own rail icon.
8. Both popups are mobile first, scroll independently, do not replace the whole screen, support keyboard Escape, accessible dialog names, and focus management.

## Compatibility and rollback
- Theme folder stays `nine-code`; preserves all WordPress identity and settings keys.
- Standard WordPress update path: Appearance > Themes > Add New > Upload Theme (or your existing authorized replacement workflow). Upload this version over 20.2.0, retaining the existing site content and other Open Scholar plugins.
- The visible header and footer on public pages remain owned by the designated Open Scholar plugins, not this theme.
- A full WordPress-site browser test against installed third-party caching plugin configurations is still required; static tests and isolated PHP markup checks do not confirm behavior of every plugin's JavaScript handlers.

## Checks performed
- PHP syntax checked across the theme files.
- Node.js JavaScript syntax checked for changed JS.
- Regression assertions verified left icon-only navigation, absence of its search field, right Search and Top Bar popups, cache action bridging, new theme version, and toolbar geometry.
- Isolated PHP render check verified right rail and accessible popup markup with capability-gated actions.
