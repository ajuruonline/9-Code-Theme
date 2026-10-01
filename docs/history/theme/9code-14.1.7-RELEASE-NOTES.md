# 9Code Theme 14.1.7 — Logged-In Front-End Quick Actions

## Front-end launcher
- The Theme-owned Quick Actions launcher now renders on the public front end for logged-in WordPress users as well as in wp-admin.
- Front-end mode shows the Quick Actions button/drawer without wp-admin-only Save and Menu rail buttons.
- Action visibility remains capability-filtered.

## Data Manager actions
- Schema 4 reinserts **Post Editor** (the current name for the former Post Manager) and **Category Manager** into existing users’ Quick Actions once when 9 Data Manager is active.
- Post Creator, Form Manager and Data Backup remain available in the same Data Manager group.

## Plugin install / replace
- **Install / Replace Plugin** is inserted for users who can install plugins.
- Authorized administrators can upload a ZIP from the public front end. The AJAX endpoint still requires WordPress nonce + install_plugins + upload_plugins + activate_plugins.
- Existing plugin folders use WordPress `overwrite_package` replacement handling.
- The upload form uses delegated JavaScript submission so it works even when the launcher markup is printed after footer scripts.

## Compatibility
- 9Core 14.1.5 and 9 Data Manager 9.10.3 carry forward unchanged.
- Mason and 9Page remain discontinued and excluded from the suite.
