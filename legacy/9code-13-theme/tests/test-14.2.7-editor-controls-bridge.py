from pathlib import Path
root=Path(__file__).resolve().parents[1]
style=(root/'style.css').read_text(errors='ignore')
display=(root/'inc/post-display-settings.php').read_text(errors='ignore')
assert 'Version: 15.0.2' in style
assert "do_action( 'ncu_theme_display_editor_controls' )" in display
print('PASS: Theme 15.0.2 editor controls bridge')
