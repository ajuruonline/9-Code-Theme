from pathlib import Path
root=Path(__file__).resolve().parents[1]
main=(root/'nine55-ultron-data.php').read_text(errors='ignore')
cat=(root/'modules/category/includes/class-ninecm-core.php').read_text(errors='ignore')
assert 'Version: 9.10.14' in main and "NINE55_ULTRON_DATA_VERSION', '9.10.14'" in main
needle='public function admin_bar_link( $bar )'
pos=cat.index(needle)
chunk=cat[pos:pos+900]
assert 'get_current_screen' in chunk and "'post' ===" in chunk and 'return;' in chunk
print('PASS: Data Manager 9.10.14 editor admin-bar cleanliness')
