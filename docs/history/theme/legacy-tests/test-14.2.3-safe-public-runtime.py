from pathlib import Path
p=Path(__file__).resolve().parents[1]
text=(p/'functions.php').read_text()
assert "ncu_theme_is_admin_runtime" in text
assert "inc/frontend-safe.php" in text
split=text.index("if ( ncu_theme_is_admin_runtime() )")
prefix=text[:split]
for bad in [
    "inc/helpers.php","inc/host-compatibility.php","inc/post-display-settings.php","inc/style-takeover.php",
    "inc/header-footer/bootstrap.php","inc/block-edition/bootstrap.php","inc/performance.php",
    "inc/enqueue.php","inc/integrations.php","inc/starter-content.php","inc/admin.php",
]:
    assert bad not in prefix, f"heavy public module loaded before runtime split: {bad}"
for name in ['page.php','single.php','front-page.php'] + [str(x.relative_to(p)) for x in (p/'templates').glob('*.php')]:
    t=(p/name).read_text()
    for bad in ['ncu_theme_settings(', 'ncu_builder_', 'ncu_should_show_', 'ncu_output_post_content(', 'ncu_frontend_edit_']:
        assert bad not in t, f"{name} still depends on {bad}"
# Safe public launcher must retain the three-control rail and plugin replacement endpoint support.
f=(p/'inc/frontend-safe.php').read_text()
for token in ['data-n9be-action="theme_menu"','data-n9be-action="save"','data-n9be-quick-toggle','n9be_quick_plugin_install']:
    # ajax action string is localized through the shared JS and registered by admin runtime.
    if token == 'n9be_quick_plugin_install':
        assert 'n9beQuickActions' in f and 'admin-ajax.php' in f
    else:
        assert token in f
print('PASS safe public runtime contract')
