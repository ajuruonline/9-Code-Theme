from pathlib import Path
p=Path(__file__).resolve().parents[1]/'nine-code-ultra-core.php'
s=p.read_text()
boot=s[s.index('function ncu_core_boot()'):s.index('/**\n * 13.3:', s.index('function ncu_core_boot()'))]
assert 'ncu_core_maybe_upgrade();' not in boot, 'Core must not run upgrade maintenance during plugins_loaded/public traffic'
assert "add_action( 'admin_init', 'ncu_core_maybe_upgrade'" in s or 'add_action(\'admin_init\',\'ncu_core_maybe_upgrade\'' in s.replace(' ',''), 'Core upgrades must be admin_init only'
print('PASS: Core public traffic is read-only for upgrades')
fallback=(Path(__file__).resolve().parents[1]/'inc/data-engine/class-ninecode-acf-data-engine.php').read_text()
assert "add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' )" not in fallback, 'Core legacy Data fallback must not upgrade on public plugins_loaded'
assert "add_action( 'admin_init', array( $this, 'maybe_upgrade' )" in fallback, 'Core legacy Data fallback upgrades must be admin_init only'
