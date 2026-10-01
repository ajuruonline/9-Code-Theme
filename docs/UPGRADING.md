# Upgrading from 9Code 15 Theme + 9Core + 9 Data Manager

1. Upload **Nine Code Data** (`nine-code-data-plugin-*.zip`) and activate it. If the old 9 Data Manager is still
   active the new plugin waits quietly and retires the old one on the next wp-admin load. No fatal in either order.
2. Upload and activate the **Nine Code** theme. This deactivates the old 9Core plugin (its code is now in the
   theme) and copies menus, logo and widgets from the old theme folder. Sites that relied on plugins to print
   post titles keep titles **off** (their previous behaviour); new installs show titles.
3. Delete the old plugins and theme when you are happy. Data, settings, forms and metadata are untouched.
4. Clear page/CDN/browser caches.

Rollback: re-activate the old theme/plugins (their data is unchanged). `bin/test-upgrade.sh` rehearses this flow.
