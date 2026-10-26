# Open Scholar Theme 20.2.6 — Dashboard, Menu and Visual Management

## Main changes

- Theme Settings → **Dashboard & Menus** lets institutional administrators choose which registered, permission-filtered applications appear on the dashboard versus in the popup menu, and edit app names, descriptions, icons, order and submenu labels/visibility.
- The Dashboard and theme-settings navigation cannot accidentally be hidden through these controls. The actual WordPress capabilities, URLs and plugin functionality are never modified by display preferences.
- Two unwanted native WordPress triangular indicators are suppressed in the popup; the single deliberate expand/collapse affordance remains.
- **Mandatory visual geometry:** any visibly bordered or outlined box must have sharp/square corners (`border-radius: 0`). Borderless surfaces may be rounded. The theme includes a style layer plus a lightweight dynamic-element checker (also on login), and removes rounded rectangles from the theme's inline SVG icon vocabulary.
- The main Open Scholar institutional green (#14532D) remains the primary identity. Launcher, compact UI and existing data-transfer/recovery facilities are retained.

## Safety and runtime fix

The WordPress `admin-post.php` request does **not** run `admin_menu`. Saving workspace preferences therefore reads the short-lived server-created, capability-filtered menu registry cached for the authenticated administrator when the settings form was presented. The POST is nonce-checked, administrator-only and accepts only the cached registered app identifiers. If the registry is missing or expired, settings are **not** overwritten; the administrator can reload the form.

## Practical deployment checks

1. Update a staging site from v20.2.5. Open `Open Scholar → Dashboard & Menus` and verify app registry.
2. Hide a nonessential app from the Dashboard but keep it in menu; save; revisit. Reverse the operation.
3. Rename an app; choose an icon and position; save and confirm both Dashboard and menu behaviour.
4. Collapse a submenu; hide one noncritical child; confirm only that child is hidden. Confirm the current page remains discoverable where visible.
5. Test as a nonadministrator that restricted admin apps remain absent.
6. Inspect desktop, 390px and 320px mobile widths, and keyboard focus/hover: bordered panels have square corners and there is no horizontal overflow.
7. Independently verify export/import with representative records and backup/Drive upload on a **staging** site before a university production rollout.

**Not introduced:** theme activation key enforcement, uncontrolled automatic raw database restore or a change in institutional branding.
