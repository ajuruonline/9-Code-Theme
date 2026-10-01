
from pathlib import Path
root=Path(__file__).resolve().parents[1]
f=(root/"functions.php").read_text()
h=(root/"header.php").read_text()
ft=(root/"footer.php").read_text()
b=(root/"inc/header-footer/bootstrap.php").read_text()
d=(root/"inc/header-footer/includes/class-elhh-design.php").read_text()
defs=(root/"inc/defaults.php").read_text()

assert "14.2.8" in f, "theme version must be current Edition 9.10 responsive-brand hardening release"
assert "inc/header-footer/bootstrap.php" in f, "admin runtime must retain integrated header/footer settings"
assert "inc/frontend-safe.php" in f, "public runtime must use safe frontend layer"
assert "ncu-site-header" not in h, "legacy built-in theme header renderer must be removed"
assert "wp_body_open()" in h, "document shell must keep wp_body_open seam"
assert "ncu-site-footer" not in ft, "legacy built-in theme footer renderer must be removed"
assert "wp_footer()" in ft, "document shell must keep wp_footer seam"
assert "NCU_HF_VERSION" in b and "'9.2.0'" in b, "integrated module must be v9.2.0"
assert "n9lh8_settings" in b and "n9f_settings" in b, "compatibility option stores must be declared"
assert "ncu_header_footer_skin_mode" in d, "skin authority must be filterable"
assert "'header_owner_mode'          => 'theme'" in defs
assert "'header_enabled'             => 0" in defs
assert "'footer_enabled'             => 0" in defs
print("PASS")
