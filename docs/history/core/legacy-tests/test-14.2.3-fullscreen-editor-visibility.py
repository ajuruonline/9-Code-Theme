from pathlib import Path
root=Path(__file__).resolve().parents[1]
main=(root/'nine-code-ultra-core.php').read_text(); js=(root/'assets/js/editor-workspace.js').read_text(); css=(root/'assets/css/editor-workspace.css').read_text()
assert 'Version: 15.0.2' in main
assert "$saved['admin_editor_focus_panels'] = 0;" in main
assert "$saved['admin_editor_hide_plugin_panels_mobile'] = 0;" in main
assert 'clearBlockingEditorLayers' in js
assert 'ncu-editor-panel-hidden{display:block!important' in css.replace(' ','')
print('PASS 15.0.2 native editor visibility recovery')
