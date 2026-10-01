from pathlib import Path
root=Path(__file__).resolve().parents[1]
admin=(root/'inc/admin.php').read_text(encoding='utf-8')
def must(cond,msg):
    if not cond: raise AssertionError(msg)
must("add_action( 'admin_bar_menu', 'ncu_admin_bar_links'" not in admin, 'Theme must not add a redundant 9Code admin-bar menu')
must("'id' => 'ncu-menu'" not in admin, 'Theme top admin bar must remain native/minimal')
must('9code-admin-menu-icon.png' not in admin, 'Theme fallback admin menu must use a native dashicon, not an image logo')
print('Theme minimal admin bar contract: PASS')
