from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
main = (ROOT / 'nine55-ultron-data.php').read_text()
post_main = (ROOT / 'modules/post/9-post-manager.php').read_text()
app = (ROOT / 'modules/post/assets/app.js').read_text()
post_class = (ROOT / 'modules/post/includes/class-nine-post-manager.php').read_text()
admin_css = (ROOT / 'modules/post/assets/admin.css').read_text()

checks = {
    'data manager bumped to 9.10.14': "Version: 9.10.14" in main and "NINE55_ULTRON_DATA_VERSION', '9.10.14'" in main,
    'embedded post editor bumped to 4.0.3': "Version: 4.0.3" in post_main and "NPM9_VERSION', '4.0.3'" in post_main,
    'post content is a first-class tab': "label:'Post Content'" in app and 'data-workspace-pane="content"' in app,
    'post content textarea is in content pane': 'id="npm9-content"' in app and 'Post Content' in app,
    'post metadata is a first-class tab': "label:'Post Metadata'" in app and 'data-workspace-pane="metadata"' in app,
    'ordinary metadata rows render': 'ordinaryMeta.map(m=>metaRow(m,managedMetaKeys.has(String(m.key))||!!m.readonly))' in app,
    'protected metadata remains visible read only': 'protectedMeta.map(m=>metaRow(m,true))' in app,
    'save collection includes post content': "content:$('#npm9-content').val()" in app,
    'save collection includes ordinary metadata': ".npm9-meta-row[data-editable=\"1\"]" in app,
    'metadata discovery still uses WordPress post meta': 'get_post_meta( $post_id )' in post_class,
    'normal editor recovery hook exists': "add_action( 'admin_enqueue_scripts', [ $this, 'native_editor_recovery_assets' ]" in post_class,
    'recovery is scoped to post editor screens': "in_array( $hook, [ 'post.php', 'post-new.php' ], true )" in post_class,
    'recovery stylesheet restores stale focus classes': 'ncu-editor-panel-hidden' in admin_css and 'ncu-editor-focus-backdrop' in admin_css,
}

failed = [name for name, ok in checks.items() if not ok]
for name, ok in checks.items():
    print(('PASS' if ok else 'FAIL') + ': ' + name)
if failed:
    raise SystemExit(f'{len(failed)} checks failed: ' + '; '.join(failed))
print(f'{len(checks)}/{len(checks)} checks passed')
