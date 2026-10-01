from pathlib import Path
root=Path(__file__).resolve().parents[1]
def text(p): return (root/p).read_text(errors='ignore')
defaults=text('inc/defaults.php'); js=text('assets/js/editor-workspace.js'); css=text('assets/css/editor-workspace.css')
assert "'admin_editor_focus_panels'  => 0" in defaults
assert "'admin_editor_hide_plugin_panels_mobile' => 0" in defaults
assert 'clearBlockingEditorLayers' in js
assert 'ncu-edit-panels-fab' in js  # removal selector only
assert 'display:none!important' in css
print('PASS: legacy focus panel system is retired and recoverable')
