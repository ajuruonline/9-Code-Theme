from pathlib import Path
root=Path(__file__).resolve().parents[1]
main=(root/'nine55-ultron-data.php').read_text(errors='ignore')
pm=(root/'modules/post/includes/class-nine-post-manager.php').read_text(errors='ignore')
assert "Version: 9.10.14" in main, "Data Manager version not bumped"
assert "nine10_data_editable_post_types" in pm, "editable CPT filter missing"
assert "nine10_data_meta_editable" in pm, "plugin meta ownership filter missing"
assert "readonly' => $readonly" in pm or 'readonly" => $readonly' in pm, "computed readonly state missing"
print('PASS Conference interop Data Manager contract')
