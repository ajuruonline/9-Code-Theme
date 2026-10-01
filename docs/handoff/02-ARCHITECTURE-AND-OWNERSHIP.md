# Architecture and Ownership

## 9Code Theme 15.0.2
Owns public presentation, Header/Footer, public display conventions, Theme settings and public Quick Actions. **It yields on wp-admin post editor screens** and must not inject a competing editor overlay.

## 9Core 15.0.2
Owns shared infrastructure, contracts, diagnostics and compact editor chrome. On post editor screens it may provide only the non-modal hamburger/popover. It must never move, clone or hide Post Content or provider meta boxes.

## 9 Data Manager 9.10.14
Owns Post Editor, Category Manager, Post Creator, Form Manager and Data Backup. Its Post Editor exposes real WordPress Post Content and Post Metadata. It also provides the native WordPress recovery meta box.

## WordPress
Owns post routing, `post_content`, native post save/update/publish, revisions and native editor layout.

## Provider plugins
Own their CPTs, taxonomies, metadata, meta boxes, JS widgets and save callbacks. 9CODE may surface links/shortcuts but must not duplicate their data model.

## Critical invariant
The UI may reorganize **controls**, but it must not reorganize **data ownership**.
