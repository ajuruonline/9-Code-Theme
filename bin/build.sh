#!/usr/bin/env bash
# Builds the lean Open Scholar host theme. Application/data modules are plugins
# and are deliberately excluded. The stable nine-code root replaces upgrades in place.
set -euo pipefail
cd "$(dirname "$0")/.."
bin/lint.sh >/dev/null
tv=$(grep -m1 '^Version:' theme/nine-code/style.css | awk '{print $2}')
rm -rf dist && mkdir -p dist
( cd theme  && zip -qr "../dist/open-scholar-theme-$tv.zip" nine-code \
  -x '*.DS_Store' \
  -x 'nine-code/data-manager/*' 'nine-code/inc/header-footer/*' \
  -x 'nine-code/patterns/*' 'nine-code/blocks/*' 'nine-code/templates/*' \
  -x 'nine-code/inc/helpers.php' 'nine-code/inc/host-compatibility.php' 'nine-code/inc/post-display-settings.php' \
  -x 'nine-code/inc/style-takeover.php' 'nine-code/inc/surface-contract.php' 'nine-code/inc/renderers.php' \
  -x 'nine-code/inc/performance.php' 'nine-code/inc/enqueue.php' 'nine-code/inc/integrations.php' 'nine-code/inc/starter-content.php' \
  -x 'nine-code/core/inc/responses.php' 'nine-code/core/inc/style-takeover.php' 'nine-code/core/inc/dark-mode.php' \
  -x 'nine-code/core/inc/admin-workspace.php' 'nine-code/core/inc/editor-workspace.php' 'nine-code/core/inc/admin-ui-controller.php' \
  -x 'nine-code/core/inc/frontend-edit.php' 'nine-code/core/inc/profile.php' 'nine-code/core/inc/backup.php' \
  -x 'nine-code/core/inc/diagnostics.php' 'nine-code/core/inc/builders.php' 'nine-code/core/inc/doctor.php' 'nine-code/core/inc/shortcodes.php' \
  -x 'nine-code/core/assets/css/admin-ui-13.css' \
  -x 'nine-code/core/assets/css/editor-tools.css' 'nine-code/core/assets/css/front-doctor.css' 'nine-code/core/assets/css/front-edit.css' \
  -x 'nine-code/core/assets/js/admin-skin.js' 'nine-code/core/assets/js/admin-ui-13.js' 'nine-code/core/assets/js/editor-tools.js' 'nine-code/core/assets/js/front-doctor.js' 'nine-code/core/assets/js/front-edit.js' \
  -x 'nine-code/assets/css/data-manager.css' 'nine-code/assets/css/site-chrome.css' 'nine-code/assets/css/style-authority.css' \
  -x 'nine-code/assets/js/data-manager.js' 'nine-code/assets/js/editor-display-settings.js' 'nine-code/assets/js/nav.js' 'nine-code/assets/js/style-authority.js' 'nine-code/assets/js/theme.js' \
  -x 'nine-code/template-parts/site-header.php' 'nine-code/template-parts/site-footer.php' )
( cd dist && sha256sum *.zip > SHA256SUMS.txt && cat SHA256SUMS.txt && ls -la )
