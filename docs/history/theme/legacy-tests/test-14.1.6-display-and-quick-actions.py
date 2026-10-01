from pathlib import Path
import re, sys
root=Path(__file__).resolve().parents[1]
errors=[]

def need(path, text, label):
    p=root/path
    if not p.exists(): errors.append(f'MISSING {label}: {path}'); return ''
    s=p.read_text(errors='ignore')
    if text not in s: errors.append(f'MISSING {label}: {text}')
    return s

f=need(Path('functions.php'), "define( 'NCU_THEME_VERSION', '15.0.2' );", 'version 15.0.2')
need(Path('functions.php'), "require_once NCU_THEME_DIR . '/inc/post-display-settings.php';", 'display settings include')
ps=need(Path('inc/post-display-settings.php'), "register_post_meta", 'registered post meta')
for key in ['_ncu_display_title','_ncu_display_meta','_ncu_display_featured','_ncu_display_taxonomy','_ncu_display_tags','_ncu_display_navigation','_ncu_display_comments','_ncu_display_breadcrumbs','_ncu_content_width','_ncu_presentation_owner']:
    if key not in ps: errors.append('MISSING field '+key)
for fn in ['ncu_post_display_setting','ncu_should_show_post_feature','ncu_register_display_settings_meta','ncu_enqueue_display_settings_editor']:
    if ('function '+fn) not in ps: errors.append('MISSING function '+fn)
need(Path('assets/js/editor-display-settings.js'), 'PluginDocumentSettingPanel', 'Gutenberg document settings panel')
qa=need(Path('inc/block-edition/class-quick-actions.php'), "'ninecode_theme'", 'Theme quick action')
if "'overwrite_package'=>true" not in qa.replace(' ', ''):
    errors.append('MISSING replace-existing plugin behavior')
if 'CURRENT_SCHEMA = 7' not in qa: errors.append('MISSING Quick Actions schema 7')
sp=need(Path('single.php'), 'the_content();', 'native singular content path')
for bad in ['ncu_should_show_post_feature','ncu_builder_full_width','ncu_output_post_content','ncu_theme_settings']:
    if bad in sp: errors.append('Single template must not own Theme display/builder decisions: '+bad)
page=need(Path('page.php'), 'the_content();', 'native page content path')
if 'ncu_should_show_post_feature' in page or 'ncu_builder_full_width' in page or 'ncu_output_post_content' in page:
    errors.append('Page template must not own Theme display/builder decisions')
if errors:
    print('\n'.join(errors)); sys.exit(1)
print('PASS 15.0.2 display + Quick Actions contract')
