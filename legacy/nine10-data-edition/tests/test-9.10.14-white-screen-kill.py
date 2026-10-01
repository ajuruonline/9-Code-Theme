from pathlib import Path
root=Path(__file__).resolve().parents[1]
main=(root/'nine55-ultron-data.php').read_text(errors='ignore')
js=(root/'modules/post/assets/native-editor-recovery.js').read_text(errors='ignore')
css=(root/'modules/post/assets/admin.css').read_text(errors='ignore')
def need(c,m):
    if not c: raise SystemExit('FAIL: '+m)
need('Version: 9.10.14' in main,'Data Manager version must be 9.10.14')
need('.ncu-editor-tools-shell' in js,'Data Manager cleanup must remove legacy Core tools shell')
need("'ncu-editor-tools-open'" in js,'Data Manager cleanup must remove stale body lock class')
need('.ncu-editor-tools-shell' in css and 'display:none!important' in css,'Data Manager CSS must kill legacy shell on post screens')
need('.ncu-editor-focus-backdrop' in css and 'display:none!important' in css,'focus backdrop must remain killed')
need('npm9-native-recovery-content' in css,'native recovery content editor must remain available')
print('PASS 9.10.14 white-screen kill contract')
