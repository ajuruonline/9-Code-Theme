#!/usr/bin/env python3
"""Static guard for the suite-wide closed, mobile, colour-separated settings contract."""
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]


def read(path):
    return (ROOT / path).read_text(encoding="utf-8")


checks = {
    "shared enqueue API exists": "function ninecode_data_enqueue_settings_ui" in read("theme/nine-code/data-manager/includes/bootstrap.php"),
    "future plugins can opt in": "ninecode_settings_ui_should_enqueue" in read("theme/nine-code/data-manager/includes/bootstrap.php"),
    "shared contract closes sections": "section.open = false" in read("theme/nine-code/data-manager/assets/settings-ui.js"),
    "shared contract covers dynamic plugin screens": "MutationObserver" in read("theme/nine-code/data-manager/assets/settings-ui.js"),
    "shared contract alternates red": "ninecode-tone-red" in read("theme/nine-code/data-manager/assets/settings-ui.js"),
    "shared contract alternates green": "ninecode-tone-green" in read("theme/nine-code/data-manager/assets/settings-ui.js"),
    "mobile inputs are full width": "width:100%" in read("theme/nine-code/data-manager/assets/settings-ui.css"),
    "Data Workspace sections are closed": "open: g !== 'protected'" not in read("theme/nine-code/data-manager/assets/data-workspace.js"),
    "Category Manager first panel is closed": 'data-panel="infrastructure" hidden' in read("theme/nine-code/data-manager/modules/category/includes/class-ninecm-admin.php"),
    "Category Manager does not remember an open tab": "ninecm_admin_tab" not in read("theme/nine-code/data-manager/modules/category/assets/admin.js"),
    "Post Manager does not remember an open tab": "lastWorkspaceTab" not in read("theme/nine-code/data-manager/modules/post/assets/app.js"),
    "Post Manager field objects are closed": 'data-ninecf-id="\'+id+\'" open' not in read("theme/nine-code/data-manager/modules/post/assets/app.js"),
    "Theme settings use shared section markup": "data-ninecode-settings-section" in read("theme/nine-code/core/inc/admin-ui-controller.php"),
    "AI settings use shared section markup": "data-ninecode-settings-section" in read("theme/nine-code/data-manager/modules/ai/includes/class-nine-ai-manager-admin.php"),
    "contract documentation exists": (ROOT / "docs/SETTINGS-UI-CONTRACT.md").is_file(),
}

failed = [name for name, ok in checks.items() if not ok]
for name, ok in checks.items():
    print(("PASS " if ok else "FAIL ") + name)
if failed:
    print(f"SETTINGS UI CONTRACT FAILED: {len(failed)} check(s)", file=sys.stderr)
    sys.exit(1)
print(f"SETTINGS UI CONTRACT OK: {len(checks)} checks")
