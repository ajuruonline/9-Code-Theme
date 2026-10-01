from pathlib import Path
root=Path(__file__).resolve().parents[1]; js=(root/'assets/js/editor-workspace.js').read_text(); css=(root/'assets/css/editor-workspace.css').read_text(); main=(root/'nine-code-ultra-core.php').read_text()
assert 'Version: 15.0.2' in main
assert 'clearBlockingEditorLayers' in js
assert 'cloneNode' not in js
assert 'ncu-editor-panel-fullscreen{position:fixed' not in css.replace(' ','')
assert '.ncu-editor-focus-backdrop' in css and 'display:none!important' in css
print('PASS white-screen overlay retired')
