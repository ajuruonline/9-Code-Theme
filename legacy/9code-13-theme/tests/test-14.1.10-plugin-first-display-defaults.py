from pathlib import Path
import sys
root=Path(__file__).resolve().parents[1]
errors=[]
def need(rel, token, label):
    p=root/rel
    s=p.read_text(errors='ignore') if p.exists() else ''
    if token not in s: errors.append(f'MISSING {label}: {token}')
    return s
need('functions.php', "define( 'NCU_THEME_VERSION', '15.0.2' );", 'Theme 15.0.2')
ps=need('inc/post-display-settings.php', "'show_title' => 0", 'title off default')
need('inc/post-display-settings.php', "'show_meta'  => 0", 'meta off default')
need('inc/post-display-settings.php', 'ncu_theme_site_feature_default', 'site feature default resolver')
need('inc/post-display-settings.php', 'ninecode-theme-display', 'Theme display settings page')
need('inc/post-display-settings.php', 'Show the Theme title by default', 'title global toggle')
need('inc/post-display-settings.php', 'Show Theme date / author meta by default', 'meta global toggle')
qa=need('inc/block-edition/class-quick-actions.php', "themes.php?page=ninecode-theme-display", 'Theme quick action route')
need('assets/js/editor-display-settings.js', "Site default", 'editor inheritance label')
if errors:
    print('\n'.join(errors)); sys.exit(1)
print('PASS Theme 15.0.2 plugin-first display defaults contract')
