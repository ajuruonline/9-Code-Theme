# Upgrading to Open Scholar Theme 20

1. Back up the site, then upload `open-scholar-theme-20.1.5.zip` through **Appearance → Themes → Add New → Upload**.
2. Choose **Replace current with uploaded**. The internal `nine-code` folder is unchanged, so WordPress upgrades the active theme in place.
3. Keep Data Manager, Conference, Teacher, OpenScholar and other application plugins installed and active. Version 20 does not bundle or deactivate them.
4. Confirm the plugin-provided header/footer, important editing screens, sharing preview and mobile administration.
5. Clear page, CDN and browser caches.

Version 20 retires legacy application code from the theme runtime and package. Existing database data is not removed. If an old site depended on the previously bundled Data Manager, install the current standalone Data Manager plugin before or immediately after upgrading so its screens and post types remain available.

Rollback is an ordinary theme ZIP replacement. Plugin data remains owned by the plugins and is unaffected.
