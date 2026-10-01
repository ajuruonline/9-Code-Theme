from pathlib import Path
s=(Path(__file__).resolve().parents[1]/'inc/builders.php').read_text()
assert 'function ncu_builder_allowed_post_types' in s, 'Builder ownership helper missing'
assert "apply_filters( 'ncu_builder_post_types', array( 'post', 'page' ) )" in s, 'Builders must default to post/page only'
assert "in_array( get_post_type( $post_id ), ncu_builder_allowed_post_types(), true )" in s, 'Content filter must refuse foreign CPTs'
assert "$types = ncu_builder_allowed_post_types();" in s, 'Classic builder boxes must use allowed types'
assert "! in_array( $screen->post_type, ncu_builder_allowed_post_types(), true )" in s, 'Block editor tools must refuse foreign CPTs'
print('PASS: Core Builders cannot silently attach to provider-owned CPTs')
assert "in_array( get_post_type( $post_id ), ncu_builder_allowed_post_types(), true )" in s[s.index('function ncu_rest_can_edit_post'):s.index('function ncu_rest_ai_export')], 'Builder REST must refuse provider-owned CPTs'
