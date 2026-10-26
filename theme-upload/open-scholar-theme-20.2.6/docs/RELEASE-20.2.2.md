# Open Scholar Theme 20.2.2 — Campus Dashboard

## Changes

- The Dashboard is the **first permanent right-side launcher** and is pinned as the **first Quick Actions shortcut**, including for users with custom saved shortcut orders.
- The Dashboard is the primary campus app directory: lists every accessible top-level WordPress/plugin navigation app, grouped into **Open Scholar**, **Installed applications** and **WordPress**. Only items and submenu routes for which the signed-in user has the required capabilities appear.
- Each app includes its own native or semantic icon, an accessible description, direct launch link and genuine expandable submenu tools. Existing plugin and WordPress admin routes are not duplicated or renamed.
- Search finds app names, descriptions and submenu names. Category filters work together with search; the interface has a no-results state.
- Live Dashboard section shows recent published posts and WordPress plugin update counts (only to users authorized to view them); information refreshes when the page loads.
- New **future-of-education** branding: restrained green-glass hero, softly rounded surfaces, wider contemporary typography, light accent geometry, subtle transitions, compact mobile layout and reduced-motion support.
- Quick Actions now has dark-green container surfaces with **white buttons and dark-green text**, improving contrast.
- Previous 20.2.1 Top Bar, Search popup, Open Scholar navigation icon, capability checks and shortcode/plugin integration retained.

## Not included

- Theme activation password / licensing gate is **not implemented or active**. Evaluate institutional license issuance, key validation, domain binding, activation revocation, offline grace periods and compatibility with GPL distribution before any integration.

## QA

- WordPress-style PHP fixture tested capability filtering, permission-filtered submenus, direct route creation and dynamic activity rendering.
- Browser tests exercised desktop (1440 px) and mobile (390 px) search, app grouping, expandable submenus, no horizontal overflow, JS runtime errors and measured Quick Actions foreground/background contrast.
- Entire theme PHP source syntax checked; JavaScript syntax checked; ZIP integrity checked.
- Site-specific confirmation still required on a real WordPress install with its active plugins.
