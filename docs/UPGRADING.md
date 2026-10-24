# Upgrading from 9Code 15 Theme + 9Core + 9 Data Manager

1. Upload and activate the **Nine Code** theme (`nine-code-theme-*.zip`). Data Manager is inside it. This deactivates
   the old 9Core plugin (its code is now in the theme), the old "9 Data Manager" plugin and the separate
   "Nine Code Data" plugin, and copies menus, logo and widgets from the old theme folder. Sites that relied on
   plugins to print post titles keep titles **off** (their previous behaviour); new installs show titles.
   Upgrading from the separate "Nine Code Data" plugin: you do not need to install anything else.
2. Delete the old plugins and theme when you are happy. Data, settings, forms and metadata are untouched.
3. Clear page/CDN/browser caches.

Rollback: re-activate the old theme/plugins (their data is unchanged). `bin/test-upgrade.sh` rehearses this flow,
including the step from the separate "Nine Code Data" plugin 17.1.0.
