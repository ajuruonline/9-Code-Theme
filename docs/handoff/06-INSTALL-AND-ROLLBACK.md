# Install and Rollback

## Recommended install
Install/replace together:
1. 9Code Theme 15.0.2
2. 9Core 15.0.2
3. 9 Data Manager 9.10.14

Clear WordPress/page/CDN/browser caches after replacement. Then hard-reload the post editor.

## First live test
Open one ordinary post first. Confirm content and metadata are editable before testing specialist CPTs. Then test one provider-owned CPT with its plugin active.

## If a white layer is still visible
Inspect the covering element in browser developer tools and record its class/id. If it is not one of the retired 9CODE classes documented here, do not guess: identify the owning plugin/theme and add a narrowly scoped fix.

## Rollback
Keep the previous 15.0.1 / 9.10.13 packages. Roll back only if the new package causes a distinct regression. Do not restore the old full-screen focus feature.
