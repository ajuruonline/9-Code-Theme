
from pathlib import Path
root=Path(__file__).resolve().parents[1]
defs=(root/"inc/defaults.php").read_text()
settings=(root/"inc/settings.php").read_text()
style=(root/"inc/style-takeover.php").read_text()
main=(root/"nine-code-ultra-core.php").read_text()

assert "14.1.7" in main, "core version must advance"
assert "'popular_site_template'" in defs, "popular site template must be stored"
assert "'template_selection_mode'" in defs, "selection mode must be stored"
assert "ncu_popular_site_default_skin" in style, "popular site must resolve a default skin"
assert "ncu_style_skin_family" in style, "skin must resolve its popular-site family"
assert "Popular Sites" in style, "admin must expose Popular Sites"
assert "Skins" in style, "admin must expose Skins"
assert "template_selection_mode" in settings, "sanitizer must resolve selection intent"
assert "aggressive_style_takeover" in settings, "popular site/skin choice must activate full style authority"
print("PASS")
