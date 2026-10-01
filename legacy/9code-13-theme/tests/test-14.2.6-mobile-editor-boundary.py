from pathlib import Path
root=Path(__file__).resolve().parents[1]
functions=(root/'functions.php').read_text(errors='ignore')
qa=(root/'inc/block-edition/class-quick-actions.php').read_text(errors='ignore')
assert "NCU_THEME_VERSION', '15.0.2'" in functions
assert 'Mobile Editor Focus Update' in functions
pos=qa.index('private static function allowed()')
chunk=qa[pos:pos+1200]
assert "'post' === $screen->base" in chunk
assert 'is_block_editor' in chunk or 'post editor' in chunk.lower()
print('PASS: Theme 15.0.2 yields admin editor chrome to Core drawer')
