from pathlib import Path
p=Path(__file__).resolve().parents[1]/'inc/data-engine/class-ninecode-acf-data-engine.php'
s=p.read_text()
assert "add_action( 'init', array( $this, 'register_managed_registry' ), PHP_INT_MAX );" in s, 'Core fallback registry must register last so provider plugins own their CPT/taxonomy first'
assert "ncu_data_registry_can_register" in s, 'Core fallback provider-safe registry filter missing'
print('PASS: Core fallback registry cannot pre-empt provider-owned CPTs/taxonomies')
