from pathlib import Path
root=Path(__file__).resolve().parents[1]
data=(root/'modules/data/includes/class-ninecode-acf-data-engine.php').read_text()
assert "add_action( 'init', array( $this, 'register_managed_registry' ), PHP_INT_MAX );" in data, 'Data-managed registry must register last so provider plugins own their CPT/taxonomy first'
assert "post_type_exists( $key )" in data and "taxonomy_exists( $key )" in data, 'Registry must refuse already-owned provider slugs'
assert "nine10_data_registry_can_register" in data, 'Provider-safe filter must be available to forbid a Data registry definition'
print('PASS: Data registry cannot pre-empt provider-owned CPTs/taxonomies')
