from pathlib import Path
root=Path(__file__).resolve().parents[1]
qa=(root/'inc/block-edition/class-quick-actions.php').read_text()
js=(root/'assets/js/block-edition-quick-actions.js').read_text()
errors=[]
checks={
 'frontend enqueue hook': "add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend' )" in qa,
 'frontend footer hook': "add_action( 'wp_footer', array( __CLASS__, 'markup_frontend' )" in qa,
 'logged-in frontend guard': "is_user_logged_in()" in qa,
 'frontend launcher context': 'data-n9be-context' in qa and "render_markup( 'frontend' )" in qa,
 'schema 7 migration': 'const CURRENT_SCHEMA = 7;' in qa,
 'post editor action': "'data_post_editor'" in qa and "'label'=>'Post Editor'" in qa,
 'category manager action': "'data_category_manager'" in qa and "'label'=>'Category Manager'" in qa,
 'replace plugin label': "'label'=>'Install / Replace Plugin'" in qa,
 'frontend plugin modal copy': 'without leaving the site' in qa,
 'frontend js mode': "getAttribute('data-n9be-context')" in js,
}
for name,ok in checks.items():
    if not ok: errors.append(name)
if errors:
    raise SystemExit('FAIL: '+', '.join(errors))
print('PASS current front-end launcher contract')
