from pathlib import Path
s=(Path(__file__).resolve().parents[1]/'inc/admin-workspace.php').read_text()
assert 'function ncu_admin_skin_foreign_app_screen' in s, 'foreign admin app detector missing'
assert "'elementor' === $action" in s, 'Elementor editor must opt out of Core admin skin'
assert "false !== strpos( $screen_id, 'acf' )" in s, 'ACF configuration screens must opt out of Core admin skin'
assert "apply_filters( 'ncu_admin_skin_foreign_app_screen'" in s, 'provider filter for admin-app ownership missing'
assert 'ncu_admin_skin_foreign_app_screen( $screen )' in s, 'admin skin must consult foreign-app ownership'
print('PASS: Core admin skin yields to foreign admin applications')
