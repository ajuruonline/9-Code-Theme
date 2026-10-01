from pathlib import Path
root=Path(__file__).resolve().parents[1]
main=(root/'nine-code-ultra-core.php').read_text()
diag=(root/'inc/diagnostics.php').read_text()
contract=(root/'inc/integration-contracts.php').read_text()
assert 'Version: 15.0.2' in main
assert "'schema'       => 5" in diag
assert 'component_contracts' in diag and 'semantic_host' in diag and 'surface_context' in diag and 'interop_policy' in diag
assert 'Core API 14' in diag and 'Core API 13' not in diag
assert "'9code-13-theme'" in diag
assert 'ninecodepress_component_contracts' in contract
assert 'ninecodepress_semantic_host_context' in contract
assert "'allow_nav_rewrite'     => false" in contract
print('Core plugin assistance contract: PASS')

assert 'ninecodepress_surface_context' in contract
assert "'suppress_theme_header'        => false" in contract
assert "'suppress_theme_footer'        => false" in contract
assert "'suppress_theme_quick_actions' => false" in contract

assert 'ninecodepress_interop_policy' in contract
assert 'ninecodepress_interop_capabilities' in contract
assert 'interop_capabilities' in diag
