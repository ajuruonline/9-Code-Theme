from pathlib import Path
root=Path(__file__).resolve().parents[1]
main=(root/'functions.php').read_text(errors='ignore')
qa=(root/'inc/block-edition/class-quick-actions.php').read_text(errors='ignore')
errors=[]
if "define( 'NCU_THEME_VERSION', '15.0.2' );" not in main: errors.append('Theme version is not 15.0.2')
if "in_array( $id, array( 'data_post_editor', 'data_category_manager' )" in qa: errors.append('Post/Category still intercepted as resident overlay events')
if "admin.php?page=nine-post-manager" not in qa: errors.append('Post Editor direct launcher URL missing')
if "admin.php?page=nine-category-manager" not in qa: errors.append('Category Manager direct launcher URL missing')
if "catch ( \\Throwable $e )" not in qa and "catch ( Throwable $e )" not in qa: errors.append('front-end launcher Throwable fail-safe missing')
if 'record_frontend_error' not in qa: errors.append('front-end launcher error recorder missing')
if errors: raise SystemExit('FAIL: '+'; '.join(errors))
print('PASS Theme 15.0.2 front-end fatal-isolation + display-default contract')
