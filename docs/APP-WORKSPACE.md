# Open Scholar app workspace

The dashboard is an Open Scholar application library. It discovers authorized first-party menu entries, displays searchable app tiles and presents site/upload shortcuts. Core widget preferences remain stored for recovery but the stock dashboard container is hidden on the home screen.

The sidebar displays Workspace plus recognized 9/Open Scholar applications. Posts, media, plugin management, upload and settings remain available through the launcher and their standard authorized routes.

First-party applications using another name can declare their menu identity:

```php
add_filter( 'open_scholar_workspace_is_app', function ( $is_app, $slug, $label ) {
    return $is_app || 'my-campus-app' === $slug;
}, 10, 3 );
```

This filter controls navigation visibility, not access control. Capabilities are checked separately before an application is listed. The theme never activates, deactivates or deletes plugins to curate the workspace.

Regression check: `node tests/e2e/open-scholar-mobile.mjs` uses the disposable local WordPress test site and installs `tests/e2e/workspace-fixture.php` there. It checks first-party inclusion, unrelated-plugin exclusion, app search, launcher/upload geometry, dashboard overflow and editor action consolidation.
