from pathlib import Path
root = Path(__file__).resolve().parents[1]
def read(rel): return (root/rel).read_text(encoding='utf-8')
def must(cond, msg):
    if not cond: raise AssertionError(msg)

defaults=read('inc/defaults.php')
settings=read('inc/settings.php')
workspace=read('inc/admin-workspace.php')
branding=read('inc/branding.php')
css=read('assets/css/admin-skin.css')

must("'admin_skin_workspace_bar'   => 0" in defaults, 'workspace context bar must default OFF')
must("'admin_bar_brand_enabled'    => 0" in defaults, 'image brand in WP admin bar must default OFF')
must('ncu-admin__brand-logo' not in settings, 'settings hero must not render backend brand image')
segment = workspace[workspace.find('function ncu_admin_workspace_context_bar'):workspace.find("add_action( 'admin_bar_menu'", workspace.find('function ncu_admin_workspace_context_bar'))]
must('<img ' not in segment, 'workspace context must not render backend brand image')
must("'<span class=\"ncu-adminbar-brand\"><img" not in branding, 'admin bar brand must not inject an image')
must('.edit-post-fullscreen-mode-close,.edit-site-layout__hub{background-image:url(' not in branding, 'editor close/hub must keep native WordPress icons')
must('#wp-admin-bar-ncu-command-palette' in css and '@media' in css, 'admin bar responsive contract must be present')
settings_all = read('inc/settings.php')
legacy_ai = read('inc/data-engine/ai-data-workspace.php')
must('9code-dashboard-logo' not in settings_all, 'Core admin menu/settings must not use image product logos')
must('9code-dashboard-logo' not in legacy_ai, 'legacy fallback workspace must remain image-free too')

print('Minimal responsive admin chrome contract: PASS')
