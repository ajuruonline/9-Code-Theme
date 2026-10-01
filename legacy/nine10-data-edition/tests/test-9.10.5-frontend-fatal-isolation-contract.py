from pathlib import Path
root=Path(__file__).resolve().parents[1]
main=(root/'nine55-ultron-data.php').read_text(errors='ignore')
cat=(root/'modules/category/includes/class-ninecm-core.php').read_text(errors='ignore')
post=(root/'modules/post/includes/class-nine-post-manager.php').read_text(errors='ignore')
errors=[]
if 'Version: 9.10.14' not in main: errors.append('Data version is not 9.10.14')
if "if ( $theme_launcher || ! empty( $settings['frontend_button'] ) )" in cat: errors.append('Category Manager still eagerly loads with Theme launcher')
if "if ( ! $theme_launcher && empty( $settings['frontend_button'] ) )" in cat: errors.append('Category hidden shell still renders with Theme launcher')
if 'if ( $theme_launcher )' not in cat: errors.append('Category Manager Theme-launcher short circuit missing')
if post.count('if ( $theme_launcher )') < 2: errors.append('Post Editor does not short-circuit both front-end assets and toolbox')
if errors: raise SystemExit('FAIL: '+'; '.join(errors))
print('PASS Data 9.10.14 front-end fatal-isolation contract')
