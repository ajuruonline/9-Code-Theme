# Open Scholar Theme v20.2.4 — Compact workspace everywhere

## Requested improvements

- **Dashboard:** reduce unnecessary whitespace in the green welcome hero (desktop hero actions occupy previously empty right space), latest-information cards, app directory, filters, descriptions, submenus and footer. Remove fixed card minimum heights. Maintain distinct icon colours and app descriptions.
- **Right launcher:** reduce button dimensions and vertical space, keep Dashboard as the first direct launcher action.
- **Quick Actions:** replace oversized 112px stacked tiles with compact two-column, icon-and-title rows. Keep white tile backgrounds, dark-green text and the institutional green panel.
- **Open Scholar left popup:** compact menu row spacing, header and submenu padding; replace fixed tall popup with content-sized popup capped to viewport height.
- **Top Bar and Search popups:** reduce widths, insets, header height, button padding and internal gaps.
- **Other theme admin surfaces:** compact the theme's settings panels, dashboard widgets, cards, form rows, controls and admin gutters without compressing the Gutenberg editing canvas or user-generated public content.
- **Mobile:** tight page gutters, two-column app directory for normal phones, single column on very narrow phones, wrapped filters with no horizontal-scrolling filter strip. Retain readable text and approximately 40px launcher / control targets.

## Identity and scope

- Canonical green `#14532D` retained, with `#123D2A` deep green. Additional colours remain limited to distinct application-icon recognition cues.
- Existing menus, apps, descriptions, actions, capability checks, saved theme options, shortcodes, plugin compatibility, and inactive licensing proposal are unchanged.
- Scoped stylesheet: `assets/css/open-scholar-compact.css`, loaded after admin shell/popup CSS and on logged-in front-end launcher when that launcher is present.

## Verification

- PHP lint across 41 theme files; JavaScript syntax checks; CSS parse.
- Density regression tests for dashboard, Quick Actions, menus, admin surfaces, mobile and branding.
- Chromium rendering at 1440px, 390px and 320px; application search, filter, and expanding submenu tested; no page horizontal overflow observed in fixtures.
- Actual university-specific WordPress plugin pages still require site integration testing.
