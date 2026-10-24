#!/usr/bin/env python3
"""Static regression guard for the Open Scholar Theme 20 host contract."""
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]


def read(path):
    return (ROOT / path).read_text(encoding="utf-8")


functions = read("theme/nine-code/functions.php")
header = read("theme/nine-code/header.php")
footer = read("theme/nine-code/footer.php")
core = read("theme/nine-code/core/bootstrap.php")
brand = read("theme/nine-code/core/inc/open-scholar.php")
integration = read("theme/nine-code/core/inc/integration-contracts.php")
jetpack = read("theme/nine-code/core/inc/jetpack-hygiene.php")
seo = read("theme/nine-code/core/inc/seo.php")
backup = read("theme/nine-code/core/inc/ultra-backup.php")
backup_manager = read("theme/nine-code/core/ultra-backup/includes/class-nine-backup-manager.php")
backup_exporter = read("theme/nine-code/core/ultra-backup/includes/class-nine-backup-exporter.php")
backup_importer = read("theme/nine-code/core/ultra-backup/includes/class-nine-backup-importer.php")
backup_health = read("theme/nine-code/core/ultra-backup/includes/class-nine-backup-health.php")
setup = read("theme/nine-code/inc/setup.php")
style = read("theme/nine-code/style.css")

checks = {
    "visible theme name": "Theme Name: Open Scholar Theme" in style,
    "theme version 20": "Version: 20.1.1" in style,
    "legacy slug preserved": (ROOT / "theme/nine-code").is_dir(),
    "header footer engine not loaded": "header-footer/bootstrap.php" not in functions,
    "Data Manager is plugin-owned": "data-manager/bootstrap.php" not in functions,
    "theme header renderer not called": "ncu_safe_render_site_header" not in header,
    "theme footer renderer not called": "ncu_safe_render_site_footer" not in footer,
    "plugin header hook preserved": "wp_body_open()" in header,
    "plugin footer hook preserved": "wp_footer()" in footer,
    "legacy application modules not loaded": all(name not in core for name in (
        "builders.php", "shortcodes.php", "admin-ui-controller.php", "frontend-edit.php"
    )),
    "legacy 9 block styles retired": "ncu_register_block_designs" not in setup,
    "remote block patterns left to WordPress": "should_load_remote_block_patterns" not in setup,
    "canonical green exposed": "#14532d" in brand,
    "Open Scholar plugin contract exposed": "open_scholar_theme_contract" in brand,
    "plugin registration seam exposed": "open_scholar_theme_integrations" in integration,
    "Jetpack remains active but unobtrusive": "jetpack_show_promotions" in jetpack and "deactivate_plugins" not in jetpack,
    "SEO fallback yields to SEO plugins": "osch_seo_has_provider" in seo,
    "Bluehost-hardened Ultra Backup loaded": "class-nine-backup-manager.php" in backup and "Nine_Backup_Manager::instance" in backup,
    "standalone backup engine receives priority": "osch_backup_external_engine_active" in backup,
    "shared-host segmented jobs retained": "nine_backup_safe_step" in backup_manager and "shared_host_safe_mode" in backup_health,
    "backup integrity map retained": "integrity" in backup_exporter and "hash_file" in backup_exporter,
    "restore remains preview first": "preview" in backup_importer and "handle_upload_import" in backup_manager,
    "plugin portability registry retained": "nine_backup_plugin_registry" in read("theme/nine-code/core/ultra-backup/includes/class-nine-backup-plugins.php"),
    "backup sections never force themselves open": '<details class="nine-accordion" open' not in backup_manager,
}

failed = [name for name, ok in checks.items() if not ok]
for name, ok in checks.items():
    print(("PASS " if ok else "FAIL ") + name)
if failed:
    print(f"OPEN SCHOLAR CONTRACT FAILED: {len(failed)} check(s)", file=sys.stderr)
    sys.exit(1)
print(f"OPEN SCHOLAR CONTRACT OK: {len(checks)} checks")
