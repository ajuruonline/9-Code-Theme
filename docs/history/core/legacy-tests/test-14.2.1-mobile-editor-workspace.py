from pathlib import Path
root=Path(__file__).resolve().parents[1]
main=(root/'nine-code-ultra-core.php').read_text(errors='ignore')
workspace=(root/'inc/admin-workspace.php').read_text(errors='ignore')
defaults=(root/'inc/defaults.php').read_text(errors='ignore')
settings=(root/'inc/settings.php').read_text(errors='ignore')
editor=(root/'inc/editor-workspace.php')
assert "Version: 15.0.2" in main and "NCU_CORE_VERSION', '15.0.2'" in main
assert editor.exists(), 'mobile editor workspace module missing'
php=editor.read_text(errors='ignore')
assert "ncu_editor_workspace_is_post_editor" in php
assert "admin_enqueue_scripts" in php and "ncu-editor-workspace" in php
assert "wp_localize_script" in php and "breakpoint" in php
assert "admin_editor_tools_drawer" in defaults
assert "admin_editor_high_contrast" in defaults
assert "admin_editor_tools_drawer" in settings and "admin_editor_high_contrast" in settings
js=(root/'assets/js/editor-workspace.js').read_text(errors='ignore')
css=(root/'assets/css/editor-workspace.css').read_text(errors='ignore')
for token in ['MutationObserver','Edit with Elementor','rank-math','editor-post-publish','ncu-editor-tools-captured','source.click']:
    assert token in js, f'missing JS contract: {token}'
for token in ['.ncu-editor-tools-popover','max-width:1180px','.ncu-editor-tools-captured','.editor-post-publish-button','.components-panel__body-title']:
    assert token in css, f'missing CSS contract: {token}'
assert "ncu_admin_screen_is_post_editor" in workspace and "ncu_admin_workspace_command_node" in workspace
# Core must not add its second plugin-sidebar icon on compact screens.
et=(root/'assets/js/editor-tools.js').read_text(errors='ignore')
assert "matchMedia('(min-width:1181px)')" in et
print('PASS: Core 15.0.2 mobile editor workspace contract')
