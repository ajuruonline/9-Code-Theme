from pathlib import Path
import json
ROOT=Path(__file__).resolve().parents[1]
style=(ROOT/'style.css').read_text()
func=(ROOT/'functions.php').read_text()
boot=(ROOT/'inc/block-edition/bootstrap.php').read_text()
quick=(ROOT/'inc/block-edition/class-quick-actions.php').read_text()
assert 'Theme Name: 9Code 15 Theme' in style
assert 'Version: 15.0.2' in style
assert "NCU_THEME_VERSION', '15.0.2" in func
assert "NCU_THEME_API_VERSION', 14" in func
assert 'block-edition/bootstrap.php' in func
assert 'frontend-safe.php' in func
assert 'class-mason.php' not in boot
for api in ['ninecode_mason_get_blocks','ninecode_mason_register_engine','ninecode_mason_render_block']:
    assert ('function '+api) not in boot
assert 'N9BE_Quick_Actions::boot' in boot
assert 'ninecode_theme_mason_ready' not in boot
assert 'ninecode_theme_mason_notice' not in boot
assert 'ncu_admin_quick_controls_markup' in quick and 'remove_action' in quick
assert "function_exists( 'ninecode_mason_get_blocks' )" not in quick
for action in ['data_post_editor','data_category_manager','data_post_creator','data_form_manager','data_backup']:
    assert "'%s'"%action in quick
for action in ['save','menu','view_site','new_post','new_page','media_upload','add_plugin','plugins','settings']:
    assert "'%s'"%action in quick
assert "get_user_meta( $user_id, self::OPTION" in quick and "update_user_meta( get_current_user_id(), self::OPTION" in quick
json.loads((ROOT/'blocks/9block.schema.json').read_text())
for f in (ROOT/'blocks').glob('*.block'):
    data=json.loads(f.read_text())
    assert data['format']=='9block' and data['slug'] and data['name']
print('Theme Block Suite static contract: PASS')

integ=(ROOT/'inc/integrations.php').read_text()
assert 'ninecode_theme_assistance_contract' in integ
assert "'may_rewrite_host_nav'  => false" in integ

surface=(ROOT/'inc/surface-contract.php').read_text()
assert 'ninecodepress_surface_context' in surface
assert "ninecode_theme_surface_suppresses" in surface
assert "ninecode-quick-actions" in quick
assert "ninecode-mason-quick-actions" not in quick
assert "register_mason_settings_bridge" not in quick

assert 'NINE_PAGE_EDITION' not in surface
assert 'MASON_PAGE_EDITOR_VERSION' not in surface
