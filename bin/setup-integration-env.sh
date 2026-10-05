#!/usr/bin/env bash
# Adds the Data Manager integration environment to the disposable test site (run bin/setup-test-site.sh first):
#   - Secure Custom Fields (free ACF fork, includes repeater/flexible/gallery) from wordpress.org
#   - the test fixtures (ACF field group, contract provider with a custom table) as must-use plugins
#   - Open Scholar, Conference.lat and the Nine Code apps from their repositories/branches, when available.
# Apps that cannot be fetched are skipped; their integration checks then report SKIP.
set -uo pipefail
export GIT_TERMINAL_PROMPT=0   # never block on a credentials prompt (CI); unreachable apps are skipped
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; DIR="${WP_TEST_DIR:-$ROOT/.wp-test}"
WP="php -d memory_limit=1G $DIR/wp-cli.phar --allow-root --path=$DIR/wp"
PLUG="$DIR/wp/wp-content/plugins"; MU="$DIR/wp/wp-content/mu-plugins"; SRC="${NCD_APPS_DIR:-$DIR/apps}"
OWNER="${NCD_GITHUB_OWNER:-ajuruonline}"
mkdir -p "$MU" "$SRC"

if [ ! -d "$PLUG/secure-custom-fields" ]; then
  curl -fsSLo "$DIR/scf.zip" https://downloads.wordpress.org/plugin/secure-custom-fields.latest-stable.zip && unzip -q -o "$DIR/scf.zip" -d "$PLUG" && rm -f "$DIR/scf.zip"
fi
for f in "$ROOT"/tests/integration/fixtures/*.php; do ln -sfn "$f" "$MU/$(basename "$f")"; done

# repo branch path-in-repo plugin-slug
apps=(
  "Open-Scholar codex/data-manager-bridge open-scholar open-scholar"
  "Conference-LAT codex/data-manager-bridge conference-lat conference-lat"
  "e-lecture-engine claude/excel-import-mvp plugin/9code-teaching-player 9code-teaching-player"
  "e-lecture-engine claude/lectureboard-hardening 9-10-lectureboard 9-10-lectureboard"
  "e-lecture-engine master . google-meeting"
  "e-lecture-engine claude/digital-showglass-v1.1.1 9code-digital-showglass 9code-digital-showglass"
  "e-lecture-engine claude/workshop-v1.1.1 nine-workshop-lecture nine-workshop-lecture"
  "e-lecture-engine claude/post-learning-box-v1.4.1 9code_post_learning_handoff/plugin/9code-post-learning-box 9code-post-learning-box"
  "9stagram claude/9stagram-baseline-0.7.0 9stagram 9stagram"
  "9flix fix-and-enhance-1.2.25 9code-ultra-home-archive-page 9code-ultra-home-archive-page"
)
for a in "${apps[@]}"; do
  read -r repo branch sub slug <<<"$a"
  dest="$SRC/$slug"
  if [ ! -d "$dest" ]; then
    tmp="$SRC/.clone-$repo-${branch//\//-}"
    [ -d "$tmp" ] || git clone -q --depth 1 --branch "$branch" "https://github.com/$OWNER/$repo.git" "$tmp" 2>/dev/null || { echo "skip $slug (cannot fetch $repo@$branch)"; continue; }
    if [ "$sub" = "." ]; then cp -r "$tmp" "$dest"; rm -rf "$dest/.git"; else cp -r "$tmp/$sub" "$dest"; fi
  fi
  ln -sfn "$dest" "$PLUG/$slug"
done
$WP plugin activate secure-custom-fields open-scholar conference-lat 9code-teaching-player 9-10-lectureboard google-meeting \
  9code-digital-showglass nine-workshop-lecture 9code-post-learning-box 9stagram 9code-ultra-home-archive-page 2>&1 | grep -v "wp_update\|WordPress.org" | tail -3
echo "Integration environment ready. Run: tests/integration/run.sh"
