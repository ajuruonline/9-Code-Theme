from pathlib import Path
root=Path(__file__).resolve().parents[1]
main=(root/'nine55-ultron-data.php').read_text()
form=(root/'includes/class-nine10-form.php').read_text()
assert 'function nine10_data_filter_internal_post_types' in main, 'default provider-aware post-type filter missing'
assert "add_filter( 'nine10_data_editable_post_types', 'nine10_data_filter_internal_post_types'" in main, 'Post Editor/Data Manager internal-type filter not registered'
assert "apply_filters( 'nine10_data_form_post_types'" in form, 'Form selector must expose a provider post-type filter'
assert 'nine10_data_filter_internal_post_types' in form, 'Form selector must default to public/provider content types only'
print('PASS: Data Manager avoids Elementor/ACF/internal configuration CPTs by default')
