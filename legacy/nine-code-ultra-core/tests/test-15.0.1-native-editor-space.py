from pathlib import Path
root=Path(__file__).resolve().parents[1]
main=(root/'nine-code-ultra-core.php').read_text(errors='ignore')
defaults=(root/'inc/defaults.php').read_text(errors='ignore')
editor=(root/'inc/editor-workspace.php').read_text(errors='ignore')
js=(root/'assets/js/editor-workspace.js').read_text(errors='ignore')
css=(root/'assets/css/editor-workspace.css').read_text(errors='ignore')
errors=[]
def need(c,m):
    if not c: errors.append(m)
need('Version: 15.0.2' in main,'Core must be 15.0.2')
need("version_compare( $installed, '15.0.2', '<' )" in main,'15.0.2 migration missing')
need("$saved['admin_editor_focus_panels'] = 0;" in main,'upgrade must turn full-screen focus off')
need("$saved['admin_editor_hide_plugin_panels_mobile'] = 0;" in main,'upgrade must restore plugin meta boxes to normal editor flow')
need("'admin_editor_focus_panels'  => 0" in defaults,'focus panels must default off')
need("'admin_editor_hide_plugin_panels_mobile' => 0" in defaults,'plugin panels must default visible')
need('clearBlockingEditorLayers' in js,'JS must clear stale focus/hide classes and old overlay nodes')
need('ncu-editor-tools-toggle--floating' in js,'Classic editor needs one hamburger control')
need("wp_enqueue_script( 'ncu-editor-workspace'" in editor,'recovery script must load on post editors')
need('ncu-editor-panel-fullscreen{position:fixed' not in css.replace(' ',''),'blocking full-screen CSS must be removed')
need('.ncu-editor-focus-backdrop' in css and 'display:none!important' in css,'legacy backdrop must be force-hidden')
need('.ncu-editor-panel-hidden' in css and 'display:block!important' in css,'legacy hidden panels must be restored')
if errors: raise SystemExit('FAIL\n- '+'\n- '.join(errors))
print('PASS 15.0.2 native editor space contract')
