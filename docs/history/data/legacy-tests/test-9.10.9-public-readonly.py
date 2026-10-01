from pathlib import Path
root=Path(__file__).resolve().parents[1]
cat=(root/'modules/category/includes/class-ninecm-core.php').read_text()
data=(root/'modules/data/includes/class-ninecode-acf-data-engine.php').read_text()
assert "add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' )" not in cat, 'Category Manager must not upgrade on public plugins_loaded'
assert "add_action( 'admin_init', array( $this, 'maybe_upgrade' )" in cat, 'Category Manager upgrades must run on admin_init'
assert "add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' )" not in data, 'Data Engine must not upgrade on public plugins_loaded'
assert "add_action( 'admin_init', array( $this, 'maybe_upgrade' )" in data, 'Data Engine upgrades must run on admin_init'
print('PASS: Data Manager public traffic is read-only for upgrades')
