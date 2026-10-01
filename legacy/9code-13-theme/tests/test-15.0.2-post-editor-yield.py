from pathlib import Path
root=Path(__file__).resolve().parents[1]
style=(root/'style.css').read_text(errors='ignore')
quick=(root/'inc/block-edition/class-quick-actions.php').read_text(errors='ignore')
assert 'Version: 15.0.2' in style, 'Theme must be 15.0.2'
assert "if ( $screen && 'post' === $screen->base ) { return false; }" in quick, 'Theme must yield all post editor quick-action overlays to Core'
print('PASS Theme 15.0.2 post editor yield contract')
