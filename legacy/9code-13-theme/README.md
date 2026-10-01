# 9Code Theme - Editor Overlay Removal 15.0.2

## Current Edition 9.10 suite

The synchronized suite is exactly:

1. **9Code Theme** — safe WordPress presentation shell, design tokens and Quick Actions.
2. **9Core** — shared infrastructure, contracts, diagnostics and compatibility services.
3. **9 Data Manager** — Post Editor, Category Manager, Post Creator, Form Manager and Data Backup.

WordPress **Pages remain native WordPress Pages**. No suite plugin replaces the Page post type or inserts a mandatory page-rendering layer. Flyer and other CPT plugins own their own templates/data through the generic host-compatibility contract.

**Mason, legacy 9Page/Page Builder, and the experimental functional Page layer are discontinued and are not part of this suite.**

## Safe public runtime

Public Pages, Posts and CPTs use native WordPress loops and `the_content()`. Optional heavy Theme presentation engines do not load on normal public requests. This keeps the Theme additive rather than a dependency for content rendering.

## Quick Actions

The Theme launcher runs in wp-admin and on the logged-in public front end. It includes 9Code Theme and, when Data Manager is active, Post Editor, Category Manager, Post Creator, Form Manager and Data Backup. Custom post types registered by plugins are discovered generically and receive Add/Manage actions without Theme-specific integration.

## Display defaults

Theme title and date/author meta remain OFF by default so specialist flyer/CPT plugins can own their presentation without duplication.
